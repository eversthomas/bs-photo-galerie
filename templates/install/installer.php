<?php

declare(strict_types=1);

/** @var bool $locked */
/** @var bool $devReset */
/** @var string $csrfToken */
/** @var array{type:string,message:string}|null $flash */
/** @var string $baseUrl */

$post = static function (string $key, string $default = ''): string {
    if (! isset($_POST[$key])) {
        return $default;
    }

    return is_scalar($_POST[$key]) ? (string) $_POST[$key] : $default;
};
?>

<?php if ($flash !== null) : ?>
    <div class="flash flash-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($locked && ! $devReset) : ?>
    <section class="panel panel-info" aria-labelledby="h-installed">
        <h2 id="h-installed">Installation abgeschlossen</h2>
        <p>Diese Anwendung ist bereits installiert. Aus Sicherheitsgründen ist der Installer deaktiviert.</p>
        <p><a class="button button-secondary" href="../">Zur Startseite</a></p>
    </section>
<?php elseif ($locked && $devReset) : ?>
    <section class="panel panel-warn" aria-labelledby="h-locked-dev">
        <h2 id="h-locked-dev">Installation aktiv (Entwicklermodus)</h2>
        <p>Der Installer ist gesperrt, aber <strong>INSTALL_DEV_RESET</strong> ist aktiv. Sie können die Installation zurücksetzen und anschließend neu ausführen.</p>
        <p><a class="button button-secondary" href="../">Zur Startseite</a></p>
    </section>
    <?php require __DIR__ . '/partials/dev_reset.php'; ?>
<?php else : ?>

<section class="panel" aria-labelledby="h-db">
    <h2 id="h-db">1. Datenbank</h2>
    <p class="muted">Lege zuvor eine leere MySQL-Datenbank an und trage die Zugangsdaten ein.</p>
    <form method="post" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/index.php" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="test_db">
        <label class="field">
            <span>Host</span>
            <input name="db_host" type="text" required autocomplete="off" value="<?= htmlspecialchars($post('db_host', '127.0.0.1'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Port</span>
            <input name="db_port" type="number" min="1" max="65535" value="<?= htmlspecialchars($post('db_port', '3306'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field field-span-2">
            <span>Datenbankname</span>
            <input name="db_name" type="text" required autocomplete="off" value="<?= htmlspecialchars($post('db_name'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Benutzer</span>
            <input name="db_user" type="text" required autocomplete="username" value="<?= htmlspecialchars($post('db_user'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Passwort</span>
            <input name="db_password" type="password" autocomplete="current-password" value="">
        </label>
        <div class="actions field-span-2">
            <button type="submit" class="button button-secondary">Verbindung testen</button>
        </div>
    </form>
</section>

<section class="panel" aria-labelledby="h-admin">
    <h2 id="h-admin">2. Administrator</h2>
    <form method="post" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/index.php" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="install">
        <label class="field">
            <span>Host</span>
            <input name="db_host" type="text" required value="<?= htmlspecialchars($post('db_host', '127.0.0.1'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Port</span>
            <input name="db_port" type="number" min="1" max="65535" value="<?= htmlspecialchars($post('db_port', '3306'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field field-span-2">
            <span>Datenbankname</span>
            <input name="db_name" type="text" required value="<?= htmlspecialchars($post('db_name'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>DB-Benutzer</span>
            <input name="db_user" type="text" required value="<?= htmlspecialchars($post('db_user'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>DB-Passwort</span>
            <input name="db_password" type="password" value="">
        </label>
        <hr class="field-span-2">
        <label class="field">
            <span>Admin-Benutzername</span>
            <input name="admin_username" type="text" required minlength="3" maxlength="64" autocomplete="off" value="<?= htmlspecialchars($post('admin_username'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Admin-Passwort</span>
            <input name="admin_password" type="password" required minlength="10" autocomplete="new-password" value="">
        </label>
        <label class="field field-span-2">
            <span>E-Mail (optional)</span>
            <input name="admin_email" type="email" autocomplete="email" value="<?= htmlspecialchars($post('admin_email'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <div class="actions field-span-2">
            <button type="submit" class="button button-primary">Installieren</button>
        </div>
    </form>
    <p class="muted small">Es werden Tabellen angelegt, <code>config/config.php</code> geschrieben und <code>storage/locks/install.lock</code> gesetzt. Passwörter werden mit <code>password_hash</code> (bcrypt/argon2) gespeichert.</p>
</section>

<?php if ($devReset) : ?>
    <section class="panel panel-dev" aria-labelledby="h-dev">
        <h2 id="h-dev">Entwicklung: Zurücksetzen</h2>
        <?php require __DIR__ . '/partials/dev_reset.php'; ?>
    </section>
<?php endif; ?>

<?php endif; ?>
