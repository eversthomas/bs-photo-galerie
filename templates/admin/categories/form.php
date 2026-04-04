<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $csrfToken */
/** @var array{id:int,name:string,slug:string,sort_order:int}|null $category */
$isEdit = $category !== null;
$action = $isEdit
    ? $app->url('/admin/categories/' . $category['id'] . '/update')
    : $app->url('/admin/categories/store');
?>
<section class="admin-panel">
    <h1><?= $isEdit ? 'Kategorie bearbeiten' : 'Kategorie anlegen' ?></h1>
    <?php if ($isEdit) : ?>
        <?php
        $galleryCategoryUrl = $app->publicUrl('/galerie/kategorie/' . $category['slug']);
        ?>
        <p class="small">
            <a href="<?= htmlspecialchars($galleryCategoryUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Frontend-Galerie dieser Kategorie öffnen</a>
            <span class="muted"> — öffentliche Ansicht in neuem Tab</span>
        </p>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label class="field">
            <span>Name</span>
            <input name="name" type="text" required maxlength="255" value="<?= htmlspecialchars($isEdit ? $category['name'] : '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Slug (optional, sonst aus Name)</span>
            <input name="slug" type="text" maxlength="255" placeholder="z. B. urlaub-2026" value="<?= htmlspecialchars($isEdit ? $category['slug'] : '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <button type="submit" class="button-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
    </form>
    <p class="small muted"><a href="<?= htmlspecialchars($app->url('/admin/categories'), ENT_QUOTES, 'UTF-8') ?>">Zur Übersicht</a></p>
</section>
