<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Update specific keys in the project .env without clobbering the rest.
 * Config is re-read from .env every request, so changes apply on next request.
 */
final class Env
{
    private static function path(): string
    {
        return dirname(__DIR__, 2) . '/.env';
    }

    /** @param array<string,string> $kv */
    public static function update(array $kv): void
    {
        $path = self::path();
        $content = is_file($path) ? file_get_contents($path) : '';

        foreach ($kv as $key => $val) {
            // Strip CR/LF so an admin-supplied value can't inject extra .env
            // lines (e.g. a rogue SIGNER_KEY=).
            $clean = str_replace(["\r", "\n"], '', (string) $val);
            $line = $key . '=' . $clean;
            // Replacement is a plain string, but callback avoids $-backreference
            // interpretation if the value ever contains "$1".
            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $content)) {
                $content = preg_replace_callback(
                    '/^' . preg_quote($key, '/') . '=.*$/m',
                    static fn () => $line,
                    $content
                );
            } else {
                $content = rtrim($content) . "\n" . $line . "\n";
            }
        }
        file_put_contents($path, $content);
        @chmod($path, 0600);
    }
}
