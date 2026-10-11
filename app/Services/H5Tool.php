<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * scripts/h5_tool.py (h5py) için ince PHP sarmalayıcısı.
 */
class H5Tool
{
    private function base(): array
    {
        return [config('h5.python'), config('h5.script')];
    }

    /**
     * Dosyanın HDF5 şemasını döndürür (gruplar, sütunlar, satır/olay sayıları).
     */
    public function inspect(string $path): array
    {
        $result = Process::timeout(600)->run([...$this->base(), 'inspect', $path]);
        $data = $this->lastJson($result->output());

        if (! $result->successful() || ! is_array($data) || ($data['event'] ?? null) === 'error') {
            throw new RuntimeException($data['message'] ?? $this->stderr($result->errorOutput(), 'h5_tool.py inspect başarısız oldu.'));
        }

        return $data;
    }

    /**
     * Olay aralığını TSV dosyalarına aktarır. Her JSON ilerleme mesajı
     * $onMessage'a iletilir; "done" mesajı (manifest) geri döndürülür.
     *
     * @param  callable(array): void  $onMessage
     */
    public function export(string $path, string $outDir, int $offset, ?int $maxEvents, array $groups, callable $onMessage): array
    {
        $cmd = [
            ...$this->base(), 'export', $path,
            '--out', $outDir,
            '--offset', (string) $offset,
            '--chunk', (string) config('h5.read_chunk'),
            '--groups', implode(',', $groups),
        ];
        if ($maxEvents !== null) {
            array_push($cmd, '--max-events', (string) $maxEvents);
        }

        $buffer = '';
        $done = null;
        $error = null;

        $result = Process::forever()->run($cmd, function (string $type, string $output) use (&$buffer, &$done, &$error, $onMessage) {
            if ($type !== 'out') {
                return;
            }
            $buffer .= $output;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $msg = json_decode($line, true);
                if (! is_array($msg)) {
                    continue;
                }
                if (($msg['event'] ?? null) === 'done') {
                    $done = $msg;
                } elseif (($msg['event'] ?? null) === 'error') {
                    $error = $msg['message'] ?? 'Bilinmeyen hata';
                }
                $onMessage($msg);
            }
        });

        if ($error !== null) {
            throw new RuntimeException('h5 okuma hatası: '.$error);
        }
        if (! $result->successful() || $done === null) {
            throw new RuntimeException($this->stderr($result->errorOutput(), 'h5_tool.py export başarısız oldu.'));
        }

        return $done;
    }

    /**
     * Python ortamını sınar: [başarılı mı, açıklama]
     */
    public function check(): array
    {
        try {
            $r = Process::timeout(60)->run([...$this->base(), 'check']);
        } catch (\Throwable $e) {
            return [false, 'Python çalıştırılamadı ('.config('h5.python').'): '.$e->getMessage()];
        }
        $d = $this->lastJson($r->output());

        if (! $r->successful() || ! is_array($d) || ($d['event'] ?? null) === 'error') {
            return [false, $d['message'] ?? $this->stderr($r->errorOutput(), 'Python/h5py bulunamadı.')];
        }

        return [true, sprintf(
            'Python %s · h5py %s · numpy %s · pandas %s',
            $d['python'], $d['h5py'], $d['numpy'], $d['pandas'] ?? 'yok (yavaş yazıcı kullanılır)'
        )];
    }

    private function lastJson(string $out): ?array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $d = json_decode($lines[$i], true);
            if (is_array($d)) {
                return $d;
            }
        }

        return null;
    }

    private function stderr(string $err, string $fallback): string
    {
        $err = trim($err);
        if ($err === '') {
            return $fallback;
        }
        $lines = explode("\n", $err);

        return $fallback.' '.Str::limit(end($lines), 500);
    }
}
