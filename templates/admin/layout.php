<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $content */
/** @var string $title */
/** @var string $csrfToken */
/** @var \BSPhotoGalerie\Models\User|null $user */
/** @var list<array{type:string,message:string}> $flash */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Backend', ENT_QUOTES, 'UTF-8') ?> – BS Photo Galerie</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($app->url('/css/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="admin-body">
<header class="admin-header">
    <div class="admin-brand">
        <a href="<?= htmlspecialchars($app->url('/admin'), ENT_QUOTES, 'UTF-8') ?>">BS Photo Galerie</a>
        <span class="admin-brand-sub">Verwaltung</span>
    </div>
    <?php if (isset($user) && $user !== null) : ?>
        <nav class="admin-nav-links" aria-label="Hauptnavigation">
            <a href="<?= htmlspecialchars($app->url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($app->url('/admin/media'), ENT_QUOTES, 'UTF-8') ?>">Medien</a>
            <a href="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>">Hochladen</a>
            <a href="<?= htmlspecialchars($app->url('/admin/import'), ENT_QUOTES, 'UTF-8') ?>">Import</a>
            <a href="<?= htmlspecialchars($app->url('/admin/categories'), ENT_QUOTES, 'UTF-8') ?>">Kategorien</a>
            <a href="<?= htmlspecialchars($app->url('/admin/settings'), ENT_QUOTES, 'UTF-8') ?>">Einstellungen</a>
            <a href="<?= htmlspecialchars($app->url('/admin/update'), ENT_QUOTES, 'UTF-8') ?>">Update</a>
        </nav>
        <nav class="admin-nav">
            <span class="admin-user" title="Angemeldet"><?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?></span>
            <form method="post" action="<?= htmlspecialchars($app->url('/admin/logout'), ENT_QUOTES, 'UTF-8') ?>" class="admin-logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="button-link">Abmelden</button>
            </form>
        </nav>
    <?php endif; ?>
</header>
<div class="admin-wrap">
    <?php foreach ($flash as $flashItem) : ?>
        <?php
        if (! is_array($flashItem) || ! isset($flashItem['message']) || ! is_string($flashItem['message']) || $flashItem['message'] === '') {
            continue;
        }
        $flashType = isset($flashItem['type']) && is_string($flashItem['type']) ? $flashItem['type'] : 'info';
        ?>
        <div class="flash flash-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?>" role="status">
            <?= htmlspecialchars($flashItem['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</div>
</body>
</html>
