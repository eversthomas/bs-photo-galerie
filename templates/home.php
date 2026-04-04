<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $siteTitle */
/** @var string $siteDescription */
/** @var list<\BSPhotoGalerie\Models\Media> $previewItems */
/** @var list<array{id:int,name:string,slug:string,sort_order:int}> $categories */
?>
<section class="home-hero">
    <h1><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($siteDescription !== '') : ?>
        <p class="home-lead"><?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>
        <p class="home-lead">Wählen Sie eine Kategorie oder öffnen Sie die Gesamtgalerie.</p>
    <?php endif; ?>
    <p class="home-cta">
        <a class="public-btn public-btn-primary" href="<?= htmlspecialchars($app->publicUrl('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Alle Bilder</a>
    </p>
</section>

<?php if ($categories !== []) : ?>
<section class="home-categories" aria-labelledby="cat-heading">
    <h2 id="cat-heading" class="home-categories-title">Kategorie wählen</h2>
    <p class="muted small home-categories-lead">Öffnet die Galerie mit Lightbox, Diashow und allen bisherigen Funktionen.</p>
    <ul class="home-category-list">
        <?php foreach ($categories as $c) : ?>
            <li>
                <a class="home-category-card" href="<?= htmlspecialchars($app->publicUrl('/galerie/kategorie/' . rawurlencode($c['slug'])), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="home-category-name"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="home-category-hint">Galerie anzeigen →</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if ($previewItems !== []) : ?>
<section class="home-preview" aria-labelledby="preview-heading">
    <h2 id="preview-heading" class="home-preview-title">Aktuelle Bilder</h2>
    <div class="home-preview-grid">
        <?php foreach ($previewItems as $m) : ?>
            <a class="home-preview-tile" href="<?= htmlspecialchars($app->publicUrl('/galerie'), ENT_QUOTES, 'UTF-8') ?>"
               title="<?= htmlspecialchars($m->title !== '' ? $m->title : $m->filename, ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($app->publicUrl('/thumb/' . $m->id), ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($m->title !== '' ? $m->title : 'Bild', ENT_QUOTES, 'UTF-8') ?>"
                     width="200" height="200" loading="lazy" decoding="async">
            </a>
        <?php endforeach; ?>
    </div>
    <p class="home-preview-more"><a href="<?= htmlspecialchars($app->publicUrl('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Alle anzeigen →</a></p>
</section>
<?php endif; ?>
