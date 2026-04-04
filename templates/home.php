<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $title */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($app->url('/css/public.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="public-body">
<main class="public-main">
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Willkommen bei der BS Photo Galerie. Die öffentliche Galerie folgt in Phase&nbsp;6.</p>
    <p><a class="public-link" href="<?= htmlspecialchars($app->url('/admin/login'), ENT_QUOTES, 'UTF-8') ?>">Zur Verwaltung</a></p>
</main>
</body>
</html>
