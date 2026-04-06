<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var \BSPhotoGalerie\Models\User|null $user */
?>
<section class="admin-panel" aria-labelledby="dash-h">
    <h1 id="dash-h">Dashboard</h1>
    <?php if (isset($user) && $user !== null) : ?>
        <p>Sie sind angemeldet als <strong><?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?></strong>
            (<?= htmlspecialchars($user->role, ENT_QUOTES, 'UTF-8') ?>).</p>
    <?php endif; ?>
    <ul class="admin-shortcuts">
        <li><a href="<?= htmlspecialchars($app->url('/admin/media'), ENT_QUOTES, 'UTF-8') ?>">Medien anzeigen</a></li>
        <li><a href="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>">Bilder hochladen</a></li>
        <li><a href="<?= htmlspecialchars($app->url('/admin/import'), ENT_QUOTES, 'UTF-8') ?>">Ordner- / FTP-Import</a></li>
        <li><a href="<?= htmlspecialchars($app->url('/admin/categories'), ENT_QUOTES, 'UTF-8') ?>">Kategorien</a></li>
        <li><a href="<?= htmlspecialchars($app->url('/admin/update'), ENT_QUOTES, 'UTF-8') ?>">Software-Update (GitHub)</a></li>
    </ul>
</section>
