<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Models\User|null $user */
?>
<section class="admin-panel" aria-labelledby="dash-h">
    <h1 id="dash-h">Dashboard</h1>
    <?php if (isset($user) && $user !== null) : ?>
        <p>Sie sind angemeldet als <strong><?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?></strong>
            (<?= htmlspecialchars($user->role, ENT_QUOTES, 'UTF-8') ?>).</p>
    <?php endif; ?>
    <p class="muted">Medienverwaltung, Kategorien und weitere Funktionen sind in den Phasen 3–5 vorgesehen.</p>
</section>
