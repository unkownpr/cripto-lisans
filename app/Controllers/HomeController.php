<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\View;
use Base;

final class HomeController
{
    public function index(Base $f3): void
    {
        View::render('home');
    }
}
