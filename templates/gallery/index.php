<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var string $siteTitle */
/** @var string $siteDescription */
/** @var list<\BSPhotoGalerie\Models\Media> $items */
/** @var list<array{id:int,name:string,slug:string,sort_order:int,is_public:bool}> $categories */
/** @var array{id:int,name:string,slug:string,sort_order:int,is_public:bool}|null $currentCategory */
/** @var array{slideshowEnabled: bool, slideshowInterval: int, musicEnabled: bool, musicUrls: list<string>} $galleryRuntimeConfig */

$isFiltered = $currentCategory !== null;
$galleryRuntimeJson = json_encode(
    $galleryRuntimeConfig,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<script type="application/json" id="gallery-runtime-config"><?= $galleryRuntimeJson ?></script>
<div class="gallery-page">
    <header class="gallery-header">
        <h1 class="gallery-title"><?= $isFiltered ? htmlspecialchars($currentCategory['name'], ENT_QUOTES, 'UTF-8') : 'Galerie' ?></h1>
        <?php if (! $isFiltered && $siteDescription !== '') : ?>
            <p class="gallery-desc"><?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </header>

    <?php if ($categories !== []) : ?>
        <nav class="gallery-filters" aria-label="Kategorien">
            <a class="gallery-filter<?= ! $isFiltered ? ' is-active' : '' ?>" href="<?= htmlspecialchars($app->publicUrl('/galerie'), ENT_QUOTES, 'UTF-8') ?>">Alle</a>
            <?php foreach ($categories as $c) : ?>
                <a class="gallery-filter<?= ($isFiltered && $currentCategory['id'] === $c['id']) ? ' is-active' : '' ?>"
                   href="<?= htmlspecialchars($app->publicUrl('/galerie/kategorie/' . rawurlencode($c['slug'])), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($items === []) : ?>
        <p class="gallery-empty">Noch keine öffentlichen Bilder. Schalte Bilder in der Verwaltung auf „sichtbar“.</p>
    <?php else : ?>
        <div class="gallery-masonry" id="gallery-grid">
            <?php $i = 0; foreach ($items as $m) : ?>
                <button type="button"
                        class="gallery-tile"
                        data-index="<?= $i ?>"
                        data-full="<?= htmlspecialchars($app->publicUrl('/' . $m->storagePath), ENT_QUOTES, 'UTF-8') ?>"
                        data-title="<?= htmlspecialchars($m->title !== '' ? $m->title : $m->filename, ENT_QUOTES, 'UTF-8') ?>"
                        data-desc="<?= htmlspecialchars($m->description, ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars($m->title !== '' ? $m->title : 'Bild vergrößern', ENT_QUOTES, 'UTF-8') ?>">
                    <img class="gallery-tile-img"
                         src="<?= htmlspecialchars($app->publicUrl('/thumb/' . $m->id), ENT_QUOTES, 'UTF-8') ?>"
                         alt=""
                         width="400" height="400"
                         loading="lazy"
                         decoding="async">
                </button>
            <?php ++$i; endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<dialog class="gallery-lightbox" id="gallery-lightbox" aria-labelledby="lb-title">
    <div class="lb-fs-root" id="lb-fs-root">
    <div class="lb-backdrop" data-lb-close></div>
    <div class="lb-inner">
        <div class="lb-toolbar" id="lb-toolbar" role="toolbar" aria-label="Lightbox-Steuerung">
            <div class="lb-toolbar-group lb-toolbar-group--slideshow" id="lb-group-slideshow" hidden>
                <button type="button" class="lb-tool" id="lb-slideshow" aria-pressed="false">Diashow</button>
                <label class="lb-toolbar-label">
                    <span class="sr-only">Intervall</span>
                    <select id="lb-interval" class="lb-select" title="Wechsel alle X Sekunden" aria-label="Diashow-Intervall"></select>
                </label>
            </div>
            <div class="lb-toolbar-group lb-toolbar-group--music" id="lb-group-music" hidden>
                <button type="button" class="lb-tool" id="lb-music" aria-pressed="false">Musik</button>
            </div>
            <div class="lb-toolbar-group">
                <button type="button" class="lb-tool" id="lb-fullscreen" aria-pressed="false" title="Vollbildmodus">Vollbild</button>
            </div>
        </div>
        <button type="button" class="lb-close" data-lb-close aria-label="Schließen">×</button>
        <figure class="lb-figure">
            <img class="lb-img" id="lb-img" src="" alt="">
            <figcaption class="lb-caption" id="lb-caption">
                <strong id="lb-title" class="lb-title"></strong>
                <span id="lb-desc" class="lb-desc"></span>
            </figcaption>
        </figure>
        <audio id="gallery-bg-audio" preload="metadata" playsinline class="lb-audio-hidden"></audio>
    </div>
    <button type="button" class="lb-nav lb-prev" id="lb-prev" aria-label="Vorheriges Bild">‹</button>
    <button type="button" class="lb-nav lb-next" id="lb-next" aria-label="Nächstes Bild">›</button>
    </div>
</dialog>
