<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$foundation = $read('public/assets/css/talvoro-foundation.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');
$publicCss = $read('public/assets/css/talvoro-public.css');
$contactView = $read('resources/views/page/blocks/contact.php');

$checks = [
    'Foundation keeps the shared Talvoro action token' => str_contains($foundation, '--tv-action-primary: #d85f4a'),
    'Controls use the shared rounded geometry' => str_contains($foundation, '--tv-radius-control: 14px;'),
    'CMS headings use product UI typography' => str_contains($adminCss, 'body.admin-body :where(h1,h2,h3,h4)'),
    'CMS keeps full-width workspace and lighter product surfaces' => str_contains($adminCss, '.cms-workspace') && str_contains($adminCss, 'margin-left: 0;') && str_contains($adminCss, 'linear-gradient(180deg,#faf7f4 0%,#f4efea 100%)'),
    'Public shell adds Trenlume-like warm light treatment' => str_contains($publicCss, 'body.public-body') && str_contains($publicCss, '.talvoro-public-footer'),
    'Contact form labels now show inline requirement chips' => str_contains($contactView, 'contact-required-star') && str_contains($contactView, 'contact-optional'),
    'Contact form styling overrides exist in public redesign sheet' => str_contains($publicCss, '.contact-required-star') && str_contains($publicCss, '.contact-form-panel'),
    'Milestone remains dependency-free PHP/CSS/JS' => !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-01b checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
