<?php
use CMS\Core\Csrf;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <title><?= e($title ?? 'Install Talvoro') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e(app_version()) ?>">
</head>
<body class="install-body">
<div class="install-shell">
    <header class="install-brand">
        <span class="brand-mark" aria-hidden="true"><span></span></span>
        <div><strong>Talvoro</strong><small>Secure web installer · v<?= e(app_version()) ?></small></div>
    </header>
    <main class="install-main"><?= $content ?></main>
    <footer class="install-footer">Installer routes lock automatically after installation.</footer>
</div>
<script src="/assets/js/install.js?v=<?= e(app_version()) ?>" defer></script>
</body>
</html>
