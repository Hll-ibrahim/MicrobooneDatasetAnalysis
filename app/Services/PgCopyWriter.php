<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Satırları tamponlayıp PostgreSQL'e COPY ... FROM STDIN ile toplu yazar.
 *
 * Satırlar COPY metin biçimindedir: alanlar TAB ile ayrılır, NULL = \N.
 * Sürücü PostgreSQL değilse (ör. hızlı deneme için SQLite) normal toplu
 * INSERT'e düşer.
 */
class PgCopyWriter
{
    /** @var list<string> */
    private array $buffer = [];

    private int $written = 0;

    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        private readonly string $table,
        private readonly array $columns,
        private readonly int $batch = 20000,
    ) {}

    public function add(string $line): void
    {
        $this->buffer[] = $line;
        if (count($this->buffer) >= $this->batch) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $conn = DB::connection();

        if ($conn->getDriverName() === 'pgsql') {
            $pdo = $conn->getPdo();
            $fields = implode(',', array_map(fn ($c) => '"'.$c.'"', $this->columns));
            $table = '"'.$this->table.'"';

            // PHP 8.4+: Pdo\Pgsql::copyFromArray, öncesi: PDO::pgsqlCopyFromArray
            if (method_exists($pdo, 'copyFromArray')) {
                $pdo->copyFromArray($table, $this->buffer, "\t", '\\\\N', $fields);
            } else {
                $pdo->pgsqlCopyFromArray($table, $this->buffer, "\t", '\\\\N', $fields);
            }
        } else {
            $rows = [];
            foreach ($this->buffer as $line) {
                $values = array_map(fn ($v) => $v === '\N' ? null : $v, explode("\t", $line));
                $rows[] = array_combine($this->columns, $values);
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($this->table)->insert($chunk);
            }
        }

        $this->written += count($this->buffer);
        $this->buffer = [];
    }

    public function written(): int
    {
        return $this->written;
    }
}
