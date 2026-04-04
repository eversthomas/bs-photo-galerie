<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $siteTitle */
/** @var string $siteDescription */
/** @var list<\BSPhotoGalerie\Models\Media> $previewItems */
?>
<section class="home-hero">
    <h1><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($siteDescription !== '') : ?>
        <p class="home-lead"><?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>
        <p class="home-lead">Willkommen — öffentliche Fotos findest du in der Galerie.</p>
    <?php endif; ?>
    <p class="home-cta">
        <a class="public-btn public-btn-primary" href="<?= htmlspecialchars($app->url('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Zur Galerie</a>
    </p>
</section>

<?php if ($previewItems !== []) : ?>
<section class="home-preview" aria-labelledby="preview-heading">
    <h2 id="preview-heading" class="home-preview-title">Aktuelle Bilder</h2>
    <div class="home-preview-grid">
        <?php foreach ($previewItems as $m) : ?>
            <a class="home-preview-tile" href="<?= htmlspecialchars($app->url('/galerie'), ENT_QUOTES, 'UTF-8') ?>"
               title="<?= htmlspecialchars($m->title !== '' ? $m->title : $m->filename, ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($app->url('/thumb/' . $m->id), ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($m->title !== '' ? $m->title : 'Bild', ENT_QUOTES, 'UTF-8') ?>"
                     width="200" height="200" loading="lazy" decoding="async">
            </a>
        <?php endforeach; ?>
    </div>
    <p class="home-preview-more"><a href="<?= htmlspecialchars($app->url('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Alle anzeigen →</a></p>
</section>
<?php endif; ?>
