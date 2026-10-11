<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * events tablosundaki özet sayıların (hit_count, particle_count, ...)
 * yeniden hesaplanması.
 */
class EventStats
{
    /** Bir dosyanın tüm olayları için (içe aktarma sonunda) */
    public static function forFile(int $fileId): void
    {
        DB::update(
            'UPDATE events SET hit_count = 0, hit_count_u = 0, hit_count_v = 0, hit_count_y = 0,
                    nu_hit_count = 0, particle_count = 0, slice_count = 0, flash_count = 0
             WHERE h5_file_id = ?',
            [$fileId]
        );

        DB::update(
            'UPDATE events AS e
                SET hit_count = s.n, hit_count_u = s.nu, hit_count_v = s.nv, hit_count_y = s.ny
               FROM (SELECT event_id,
                            COUNT(*) AS n,
                            COUNT(*) FILTER (WHERE local_plane = 0) AS nu,
                            COUNT(*) FILTER (WHERE local_plane = 1) AS nv,
                            COUNT(*) FILTER (WHERE local_plane = 2) AS ny
                       FROM hits WHERE h5_file_id = ? GROUP BY event_id) AS s
              WHERE e.id = s.event_id',
            [$fileId]
        );

        foreach ([
            'nu_hit_count' => ['edeps', 'COUNT(DISTINCT hit_id)'],
            'particle_count' => ['particles', 'COUNT(*)'],
            'slice_count' => ['pandora_primaries', 'COUNT(*)'],
            'flash_count' => ['opflashes', 'COUNT(*)'],
        ] as $column => [$table, $agg]) {
            DB::update(
                "UPDATE events AS e SET {$column} = s.n
                   FROM (SELECT event_id, {$agg} AS n FROM {$table}
                          WHERE h5_file_id = ? GROUP BY event_id) AS s
                  WHERE e.id = s.event_id",
                [$fileId]
            );
        }
    }

    /**
     * Belirli olaylar için (web arayüzünden bir satır eklenip silindiğinde).
     *
     * @param  list<int>  $eventIds
     */
    public static function forEvents(array $eventIds): void
    {
        $eventIds = array_values(array_unique(array_map('intval', array_filter($eventIds))));
        if ($eventIds === []) {
            return;
        }
        $in = implode(',', array_fill(0, count($eventIds), '?'));

        DB::update(
            "UPDATE events SET
                hit_count      = (SELECT COUNT(*) FROM hits h WHERE h.event_id = events.id),
                hit_count_u    = (SELECT COUNT(*) FROM hits h WHERE h.event_id = events.id AND h.local_plane = 0),
                hit_count_v    = (SELECT COUNT(*) FROM hits h WHERE h.event_id = events.id AND h.local_plane = 1),
                hit_count_y    = (SELECT COUNT(*) FROM hits h WHERE h.event_id = events.id AND h.local_plane = 2),
                nu_hit_count   = (SELECT COUNT(DISTINCT hit_id) FROM edeps d WHERE d.event_id = events.id),
                particle_count = (SELECT COUNT(*) FROM particles p WHERE p.event_id = events.id),
                slice_count    = (SELECT COUNT(*) FROM pandora_primaries s WHERE s.event_id = events.id),
                flash_count    = (SELECT COUNT(*) FROM opflashes f WHERE f.event_id = events.id)
             WHERE id IN ({$in})",
            $eventIds
        );
    }
}
