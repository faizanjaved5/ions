<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'App') ?></title>
    <style>
        body {
            font-family: system-ui, Arial, sans-serif;
            margin: 2rem
        }
    </style>
</head>

<body>
    <main>
        <?php require __DIR__ . '/../' . $view . '.php'; ?>
    </main>
</body>

</html>