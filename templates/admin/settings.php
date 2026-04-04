<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $csrfToken */
/** @var bool $slideshowEnabled */
/** @var int $slideshowInterval */
/** @var list<int> $slideshowIntervalChoices */
/** @var bool $musicEnabled */
/** @var string $musicPlaylist */
/** @var string $publicTheme */
/** @var string $publicLayoutWidth */
?>
<section class="admin-panel">
    <h1>Galerie-Einstellungen</h1>
    <p class="muted">Öffentliche Website, Lightbox und Darstellung (<a href="<?= htmlspecialchars($app->url('/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Startseite</a>, <a href="<?= htmlspecialchars($app->url('/galerie'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Galerie</a>).</p>

    <form method="post" action="<?= htmlspecialchars($app->url('/admin/settings/update'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form wide">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <fieldset class="admin-fieldset">
            <legend>Öffentliche Darstellung</legend>
            <label class="field">
                <span>Erscheinungsbild (Farben)</span>
                <select name="public_theme">
                    <option value="default"<?= $publicTheme === 'default' ? ' selected' : '' ?>>Hell (Standard)</option>
                    <option value="dark"<?= $publicTheme === 'dark' ? ' selected' : '' ?>>Dunkel</option>
                    <option value="warm"<?= $publicTheme === 'warm' ? ' selected' : '' ?>>Warm (Creme)</option>
                </select>
            </label>
            <label class="field">
                <span>Inhaltsbreite</span>
                <select name="public_layout_width">
                    <option value="standard"<?= $publicLayoutWidth === 'standard' ? ' selected' : '' ?>>Standard (schmaler Text, Galerie bis 1200px)</option>
                    <option value="wide"<?= $publicLayoutWidth === 'wide' ? ' selected' : '' ?>>Breiter (Text bis 900px, Galerie bis 1400px)</option>
                </select>
            </label>
        </fieldset>

        <fieldset class="admin-fieldset">
            <legend>Diashow</legend>
            <label class="field field-inline">
                <input type="checkbox" name="slideshow_enabled" value="1"<?= $slideshowEnabled ? ' checked' : '' ?>>
                <span>Diashow in der Lightbox erlauben (Autoplay mit Wechselintervall)</span>
            </label>
            <label class="field">
                <span>Standard-Intervall (Sekunden)</span>
                <select name="slideshow_interval_seconds">
                    <?php foreach ($slideshowIntervalChoices as $sec) : ?>
                        <option value="<?= (int) $sec ?>"<?= $slideshowInterval === $sec ? ' selected' : '' ?>><?= (int) $sec ?> Sek.</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <fieldset class="admin-fieldset">
            <legend>Musik</legend>
            <label class="field field-inline">
                <input type="checkbox" name="background_music_enabled" value="1"<?= $musicEnabled ? ' checked' : '' ?>>
                <span>Hintergrundmusik in der Lightbox anbieten (Besucher startet die Wiedergabe)</span>
            </label>
            <label class="field">
                <span>Wiedergabeliste (eine URL pro Zeile)</span>
                <textarea name="music_playlist" rows="6" class="mono" placeholder="https://example.com/music.mp3&#10;/musik/track1.mp3"><?= htmlspecialchars($musicPlaylist, ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <p class="small muted">Erlaubt: absolute Pfade ab „/“ (Dateien unter <code>public/</code>) oder <code>http(s)://</code>. Bei mehreren Einträgen wird nacheinander abgespielt.</p>
        </fieldset>

        <button type="submit" class="button-primary">Speichern</button>
    </form>
</section>
