<?php use CMS\Core\Csrf; ?>
<section class="auth-card">
    <div class="auth-brand">
        <span class="brand-mark" aria-hidden="true"><span></span></span>
        <div class="brand-copy"><strong>Talvoro</strong><small>Two-factor authentication</small></div>
    </div>
    <h1>Verify it is you.</h1>
    <p>Enter a current authenticator code for <?= e($email ?? '') ?>.</p>
    <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(admin_url('/verify')) ?>" class="stack">
        <?= Csrf::field() ?>
        <label>Authenticator code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" required autofocus></label>
        <button class="button" type="submit">Verify</button>
    </form>
    <a class="auth-back" href="<?= e(admin_login_url()) ?>">← Start over</a>
</section>
