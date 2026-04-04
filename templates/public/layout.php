<?php

declare(strict_types=1);

use BSPhotoGalerie\Config\PublicAppearance;

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $content */
/** @var string $pageTitle */
$includeGallery = ! empty($includeGalleryAssets);
$settings = $app->settingsRepository();
$publicTheme = PublicAppearance::normalizeTheme($settings->get('public_theme', 'default'));
$layoutWidth = PublicAppearance::normalizeLayoutWidth($settings->get('public_layout_width', 'standard'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($app->url('/css/public.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($app->url('/css/public-themes.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($includeGallery) : ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($app->url('/css/gallery.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body class="public-body<?= $includeGallery ? ' public-body--gallery' : '' ?>"
      data-public-theme="<?= htmlspecialchars($publicTheme, ENT_QUOTES, 'UTF-8') ?>"
      data-layout-width="<?= htmlspecialchars($layoutWidth, ENT_QUOTES, 'UTF-8') ?>">
<header class="public-header">
    <a class="public-logo" href="<?= htmlspecialchars($app->publicUrl('/'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($app->settingsRepository()->get('site_title', 'BS Photo Galerie'), ENT_QUOTES, 'UTF-8') ?></a>
    <nav class="public-nav" aria-label="Hauptnavigation">
        <a href="<?= htmlspecialchars($app->publicUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Start</a>
        <a href="<?= htmlspecialchars($app->publicUrl('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Galerie</a>
        <a href="<?= htmlspecialchars($app->publicUrl('/admin/login'), ENT_QUOTES, 'UTF-8') ?>" class="public-nav-admin">Verwaltung</a>
    </nav>
</header>

<main class="public-main public-main--grow">
    <?= $content ?>
</main>

<footer class="public-footer">
    <small>BS Photo Galerie · <a href="<?= htmlspecialchars($app->publicUrl('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Galerie</a>
        · <a href="<?= htmlspecialchars($app->publicUrl('/admin/login'), ENT_QUOTES, 'UTF-8') ?>">Verwaltung</a></small>
</footer>

<?php if ($includeGallery) : ?>
<script src="<?= htmlspecialchars($app->url('/js/gallery-lightbox.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
</body>
</html>
