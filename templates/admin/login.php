<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $csrfToken */
?>
<section class="admin-panel" aria-labelledby="login-h">
    <h1 id="login-h">Anmeldung</h1>
    <p class="muted">Melden Sie sich mit dem bei der Installation angelegten Administrator-Konto an.</p>
    <form method="post" action="<?= htmlspecialchars($app->url('/admin/login'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label class="field">
            <span>Benutzername</span>
            <input name="username" type="text" required autocomplete="username" autofocus>
        </label>
        <label class="field">
            <span>Passwort</span>
            <input name="password" type="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="button-primary">Anmelden</button>
    </form>
    <p class="small muted"><a href="<?= htmlspecialchars($app->publicUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Zurück zur Startseite</a></p>
</section>
