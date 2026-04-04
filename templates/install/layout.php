<?php

declare(strict_types=1);

/** @var string $contentTemplate */
/** @var string $baseUrl */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation – BS Photo Galerie</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/install.css?v=1">
</head>
<body class="install-body">
<div class="install-shell">
    <header class="install-header">
        <h1>BS Photo Galerie</h1>
        <p class="install-tagline">Installer – Datenbank und Administrator einrichten</p>
    </header>
    <main class="install-main">
        <?php require $contentTemplate; ?>
    </main>
    <footer class="install-footer">
        <small>Lizenz: GPL-3.0-or-later · <span aria-hidden="true">PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></span></small>
    </footer>
</div>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/install.js?v=1" defer></script>
</body>
</html>
