<?php

declare(strict_types=1);

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function view(string $relative, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require BASE_PATH . '/app/Views/' . $relative . '.php';
}
