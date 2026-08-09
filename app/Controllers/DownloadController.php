<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Templates;
use Base;

/**
 * Serves integration files as downloads so a developer integrating a product
 * can grab them without the panel source. All are public, non-secret assets.
 */
final class DownloadController
{
    /** Generated PHP license guard with the current panel URL baked in. */
    public function licensePhp(Base $f3): void
    {
        $this->attach('license.php', Templates::licensePhp((string) $f3->get('APP_URL')));
    }

    /** Generated Python license client with the current panel URL baked in. */
    public function licensePy(Base $f3): void
    {
        $this->attach('license.py', Templates::licensePy((string) $f3->get('APP_URL')));
    }

    /** Generated Go license client with the current panel URL baked in. */
    public function licenseGo(Base $f3): void
    {
        $this->attach('license.go', Templates::licenseGo((string) $f3->get('APP_URL')));
    }

    /** Generated C# / .NET license client with the current panel URL baked in. */
    public function licenseCs(Base $f3): void
    {
        $this->attach('License.cs', Templates::licenseCs((string) $f3->get('APP_URL')));
    }

    /** The smart-contract source (public / on-chain verifiable). */
    public function contract(Base $f3): void
    {
        $path = dirname(__DIR__, 2) . '/contracts/LicenseNFT.sol';
        if (!is_file($path)) {
            $f3->error(404);
            return;
        }
        $this->attach('LicenseNFT.sol', (string) file_get_contents($path));
    }

    private function attach(string $filename, string $body): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo $body;
    }
}
