<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    public function index(): void
    {
        view('home/index', [
            'title' => 'Tiny MVC',
            'time'  => date('c'),
        ]);
    }
}
