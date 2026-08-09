<?php

declare(strict_types=1);

namespace App\Lib;

use Base;
use PDO;

/**
 * Dual-driver DB (MySQL or SQLite), config-driven. One connection cached on the
 * F3 hive. Schema is written per-driver so both stay portable.
 */
final class Db
{
    public static function init(Base $f3): void
    {
        $pdo = self::connect($f3);
        self::migrate($pdo, $f3->get('DB_DRIVER'));
        $f3->set('DB', $pdo);
    }

    /** Build a PDO from hive config (used by installer to test, and by init). */
    public static function connect(Base $f3): PDO
    {
        $driver = $f3->get('DB_DRIVER');
        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $f3->get('DB_HOST'),
                (int) $f3->get('DB_PORT'),
                $f3->get('DB_NAME')
            );
            $pdo = new PDO($dsn, $f3->get('DB_USER'), $f3->get('DB_PASS'));
        } else {
            $pdo = new PDO('sqlite:' . $f3->get('DATA_DIR') . '/panel.sqlite');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA journal_mode=WAL;');
            $pdo->exec('PRAGMA foreign_keys=ON;');
        }
        return $pdo;
    }

    public static function conn(): PDO
    {
        /** @var PDO $pdo */
        $pdo = \Base::instance()->get('DB');
        return $pdo;
    }

    public static function migrate(PDO $pdo, string $driver): void
    {
        $pk = $driver === 'mysql'
            ? 'BIGINT AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tbl = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id            $pk,
            name          VARCHAR(200) NOT NULL,
            description   TEXT,
            image         VARCHAR(255) NOT NULL DEFAULT '',       -- /assets/products/<file>
            price_type    VARCHAR(10) NOT NULL DEFAULT 'free',   -- free | fiat | crypto
            price_wei     VARCHAR(80) NOT NULL DEFAULT '0',       -- crypto price
            price_fiat    VARCHAR(20) NOT NULL DEFAULT '0',       -- fiat major units
            currency      VARCHAR(8)  NOT NULL DEFAULT 'USD',
            license_type  VARCHAR(12) NOT NULL DEFAULT 'perpetual',
            duration_days INTEGER NOT NULL DEFAULT 0,
            active        INTEGER NOT NULL DEFAULT 1,
            created_at    INTEGER NOT NULL
        )$tbl");

        $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
            id          $pk,
            product_id  BIGINT NOT NULL,
            token_id    VARCHAR(80) NOT NULL UNIQUE,
            recipient   VARCHAR(42) NOT NULL DEFAULT '0x0000000000000000000000000000000000000000',
            expiry      INTEGER NOT NULL DEFAULT 0,
            uri         TEXT,
            price_wei   VARCHAR(80) NOT NULL DEFAULT '0',
            signature   TEXT,
            status      VARCHAR(12) NOT NULL DEFAULT 'draft',
            created_at  INTEGER NOT NULL
        )$tbl");

        $pdo->exec("CREATE TABLE IF NOT EXISTS challenges (
            nonce      VARCHAR(64) PRIMARY KEY,
            token_id   VARCHAR(80) NOT NULL,
            created_at INTEGER NOT NULL
        )$tbl");

        // Auto-registered users (wallet = identity; row created on first login).
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            address    VARCHAR(42) PRIMARY KEY,
            created_at INTEGER NOT NULL,
            last_login INTEGER NOT NULL
        )$tbl");

        // Purchase orders (free / fiat / crypto).
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id          $pk,
            user_addr   VARCHAR(42) NOT NULL,
            product_id  BIGINT NOT NULL,
            voucher_id  BIGINT,
            method      VARCHAR(10) NOT NULL,                    -- free | fiat | crypto
            status      VARCHAR(12) NOT NULL DEFAULT 'pending',  -- pending | paid | issued | claimed
            amount      VARCHAR(40) NOT NULL DEFAULT '0',
            ref         VARCHAR(120),                            -- external payment ref (stripe id)
            created_at  INTEGER NOT NULL
        )$tbl");

        // Fixed-window rate-limit counters (per ip+action bucket).
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_hits (
            bucket       VARCHAR(80) PRIMARY KEY,
            hits         INTEGER NOT NULL DEFAULT 0,
            window_start INTEGER NOT NULL
        )$tbl");

        // Additive migrations for DBs created before a column existed.
        self::addColumn($pdo, $driver, 'products', 'image', "VARCHAR(255) NOT NULL DEFAULT ''");
    }

    /** Delete challenges older than $ttl seconds so the table can't grow unbounded. */
    public static function gcChallenges(int $ttl = 600): void
    {
        try {
            self::conn()->prepare('DELETE FROM challenges WHERE created_at < :cut')
                ->execute(['cut' => time() - $ttl]);
        } catch (\Throwable $e) {
            // Best-effort cleanup; never break the request over it.
        }
    }

    /** Add a column only if it's missing (portable across sqlite/mysql). */
    private static function addColumn(PDO $pdo, string $driver, string $table, string $col, string $def): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table) . "
                 AND column_name = " . $pdo->quote($col)
            )->fetchColumn();
        } else {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
            $exists = 0;
            foreach ($cols as $c) {
                if (($c['name'] ?? '') === $col) {
                    $exists = 1;
                    break;
                }
            }
        }
        if (!$exists) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
        }
    }
}
