<?php

declare(strict_types=1);

namespace App\Lib;

use Base;

/**
 * Tiny PHP-template renderer: renders ui/<view>.php inside ui/layout.php.
 */
final class View
{
    public static function render(string $view, array $data = []): void
    {
        $f3 = Base::instance();
        $data['f3'] = $f3;
        $data['user'] = Auth::user();
        $data['isAdmin'] = Auth::isAdmin();

        $content = self::capture($view, $data);
        $data['content'] = $content;
        echo self::capture('layout', $data);
    }

    /** Render an admin page inside the sidebar shell. */
    public static function admin(string $view, array $data = [], string $active = ''): void
    {
        $f3 = Base::instance();
        $data['f3'] = $f3;
        $data['user'] = Auth::user();
        $data['active'] = $active;
        $data['content'] = self::capture('admin/' . $view, $data);
        echo self::capture('admin/shell', $data);
    }

    /** Render a view WITHOUT the app layout (full standalone page, e.g. installer). */
    public static function renderRaw(string $view, array $data = []): void
    {
        $f3 = Base::instance();
        $data['f3'] = $f3;
        echo self::capture($view, $data);
    }

    private static function capture(string $view, array $data): string
    {
        $path = Base::instance()->get('VIEW_DIR') . '/' . $view . '.php';
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
