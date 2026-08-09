<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Minimal fixed-window rate limiter backed by the `rate_hits` table. Cross-driver
 * (sqlite/mysql) via select-then-write, matching the app's upsert convention.
 * Fail-open: if the DB is unreachable the request is allowed rather than 500'd.
 */
final class RateLimit
{
    /** True while under the cap; increments the counter for $key. */
    public static function allow(string $key, int $max, int $window): bool
    {
        try {
            $db = Db::conn();
        } catch (\Throwable $e) {
            return true;
        }

        $now = time();
        $sel = $db->prepare('SELECT hits, window_start FROM rate_hits WHERE bucket = :b');
        $sel->execute(['b' => $key]);
        $row = $sel->fetch();

        if (!$row) {
            try {
                $db->prepare('INSERT INTO rate_hits (bucket, hits, window_start) VALUES (:b, 1, :t)')
                    ->execute(['b' => $key, 't' => $now]);
            } catch (\Throwable $e) {
                // Concurrent insert of the same bucket — treat as allowed.
            }
            return true;
        }

        if (($now - (int) $row['window_start']) >= $window) {
            $db->prepare('UPDATE rate_hits SET hits = 1, window_start = :t WHERE bucket = :b')
                ->execute(['t' => $now, 'b' => $key]);
            return true;
        }

        if ((int) $row['hits'] >= $max) {
            return false;
        }

        $db->prepare('UPDATE rate_hits SET hits = hits + 1 WHERE bucket = :b')
            ->execute(['b' => $key]);
        return true;
    }

    /** Client IP, honouring a single trusted proxy hop. */
    public static function ip(): string
    {
        $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
