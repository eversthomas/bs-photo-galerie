<?php

declare(strict_types=1);

/** @var string $csrfToken */
/** @var string $baseUrl */

$post = static function (string $key, string $default = ''): string {
    if (! isset($_POST[$key])) {
        return $default;
    }

    return is_scalar($_POST[$key]) ? (string) $_POST[$key] : $default;
};
?>
<form method="post" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/index.php" class="form-grid dev-reset-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="dev_reset">
    <p class="muted small">Aktiv nur bei <code>INSTALL_DEV_RESET=true</code> in <code>config/.env</code>. Nicht in Produktion verwenden.</p>

    <label class="field field-span-2 check">
        <input type="checkbox" name="dev_reset_confirm" value="1" required>
        <span>Ja, Installation zurücksetzen (Lock und optional Daten)</span>
    </label>
    <label class="field field-span-2 check">
        <input type="checkbox" name="dev_reset_wipe_db" value="1" checked>
        <span>Alle Tabellen löschen (empfohlen vor Neuinstallation)</span>
    </label>
    <label class="field field-span-2 check">
        <input type="checkbox" name="dev_reset_remove_config" value="1">
        <span><code>config/config.php</code> löschen (Zugangsdaten entfernen)</span>
    </label>

    <p class="muted small field-span-2">Ohne ausgefülltes Datenbankformular werden beim Leeren der DB vorhandene Werte aus <code>config/config.php</code> verwendet (falls vorhanden).</p>

    <label class="field">
        <span>Host</span>
        <input name="db_host" type="text" autocomplete="off" value="<?= htmlspecialchars($post('db_host', '127.0.0.1'), ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="field">
        <span>Port</span>
        <input name="db_port" type="number" min="1" max="65535" value="<?= htmlspecialchars($post('db_port', '3306'), ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="field field-span-2">
        <span>Datenbankname</span>
        <input name="db_name" type="text" autocomplete="off" value="<?= htmlspecialchars($post('db_name'), ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="field">
        <span>DB-Benutzer</span>
        <input name="db_user" type="text" autocomplete="off" value="<?= htmlspecialchars($post('db_user'), ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="field">
        <span>DB-Passwort</span>
        <input name="db_password" type="password" autocomplete="off" value="">
    </label>

    <div class="actions field-span-2">
        <button type="submit" class="button button-danger">Zurücksetzen</button>
    </div>
</form>
