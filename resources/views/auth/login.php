<?php use CMS\Core\Csrf; ?>
<section class="auth-card">
    <div class="auth-brand">
        <span class="brand-mark" aria-hidden="true"><span></span></span>
        <div class="brand-copy"><strong>Talvoro</strong><small>Private administration</small></div>
    </div>
    <h1>Welcome back.</h1>
    <p>Sign in to manage your website.</p>
    <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(admin_login_url()) ?>" class="stack">
        <?= Csrf::field() ?>
        <label>Email<input type="email" name="email" autocomplete="username" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button" type="submit">Sign in</button>
    </form>
    <a class="auth-back" href="/">← Back to site</a>
</section>
