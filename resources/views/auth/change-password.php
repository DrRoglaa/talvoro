<?php use CMS\Core\Csrf; ?>
<section class="auth-card">
    <div class="auth-brand">
        <span class="brand-mark" aria-hidden="true"><span></span></span>
        <div class="brand-copy"><strong>Talvoro</strong><small>Account protection</small></div>
    </div>
    <h1>Choose a new password.</h1>
    <p>Your temporary password has done its job. Replace it before continuing.</p>
    <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(admin_url('/account/password')) ?>" class="stack">
        <?= Csrf::field() ?>
        <input class="password-manager-username" type="email" name="username" value="<?= e($email ?? '') ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
        <label>New password<input type="password" name="password" minlength="16" maxlength="128" required autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="password_confirm" minlength="16" maxlength="128" required autocomplete="new-password"></label>
        <button class="button" type="submit">Save password</button>
    </form>
</section>
