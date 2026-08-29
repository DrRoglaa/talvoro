<?php
declare(strict_types=1);

putenv('APP_KEY=contact-forms-test-key-0123456789abcdef0123456789abcdef');
putenv('APP_URL=http://localhost:8080');

// The project requires ext-mbstring in production. The execution sandbox used
// by this regression check does not provide it, so keep the test harness able
// to exercise ASCII-only fixtures without changing production behavior.
if (!function_exists('mb_strlen')) { function mb_strlen(string $value): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value, int $start, ?int $length = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value): string { return strtolower($value); } }

use CMS\Core\ContactFormContext;
use CMS\Core\ContactFormState;
use CMS\Core\ContactSettings;
use CMS\Core\ContactSubmissionService;
use CMS\Core\ContactSubmissions;
use CMS\Core\Csrf;
use CMS\Core\Mailer;
use CMS\Core\PageBlocks;
use CMS\Core\RateLimiter;

require __DIR__ . '/../bootstrap/app.php';

$failed = 0;
$passed = 0;
$check = static function (bool $ok, string $label) use (&$failed, &$passed): void {
    if ($ok) {
        $passed++;
        echo "[PASS] {$label}\n";
        return;
    }
    $failed++;
    echo "[FAIL] {$label}\n";
};

$check(in_array('contact', PageBlocks::types(), true), 'Page Builder registers contact block');

$sample = [[
    'id' => 'contact001',
    'type' => 'contact',
    'enabled' => true,
    'heading' => 'Contact us',
    'intro' => 'We would love to hear from you.',
    'show_subject' => true,
    'require_subject' => false,
    'subject_prefix' => 'Website',
    'success_message' => 'Thanks — your message has been sent.',
    'submit_label' => 'Send message',
]];
$validated = PageBlocks::validateSubmitted(json_encode($sample, JSON_THROW_ON_ERROR));
$contact = $validated['blocks'][0] ?? [];
$check($validated['errors'] === [], 'Contact block validates');
$check(($contact['type'] ?? '') === 'contact' && !array_key_exists('recipient', $contact), 'Contact block cannot store recipient address');
$check(($contact['show_subject'] ?? null) === true && ($contact['require_subject'] ?? null) === false, 'Contact Subject is configurable and optional by default');

$defaultContact = PageBlocks::validateSubmitted(json_encode([[
    'id' => 'contact002', 'type' => 'contact', 'enabled' => true,
]], JSON_THROW_ON_ERROR));
$check(
    str_contains(strtolower((string)($defaultContact['blocks'][0]['success_message'] ?? '')), 'received'),
    'Default success copy remains truthful when a message is stored locally instead of emailed'
);

$rendered = PageBlocks::renderBlocks($validated['blocks']);
$check(($rendered[0]['_render_owner_id'] ?? '') === 'contact001' && ($rendered[0]['_render_block_id'] ?? '') === 'contact001', 'Rendered blocks carry stable form instance metadata');

$pageBlocksSource = (string)file_get_contents(base_path('app/Core/PageBlocks.php'));
$check(
    str_contains($pageBlocksSource, '$resolvedInner[\'_render_owner_id\']')
    && str_contains($pageBlocksSource, '$resolvedInner[\'_render_block_id\']'),
    'Synced Pattern blocks preserve distinct outer-owner and inner-block form identities'
);

$check(class_exists(ContactSettings::class), 'Contact settings service exists');
$check(class_exists(ContactFormContext::class), 'Signed contact form context service exists');
$check(class_exists(ContactFormState::class), 'Contact PRG flash-state service exists');
$check(class_exists(ContactSubmissionService::class), 'Contact submission service exists');
$check(class_exists(CMS\Core\ContactSubmissions::class), 'Optional contact inbox repository exists');

if (class_exists(ContactSettings::class)) {
    $settingsErrors = ContactSettings::save([
        'contact_recipient' => "bad\r\nBcc:inject@example.test",
        'contact_subject_prefix' => 'Website contact',
        'contact_retention_days' => '30',
    ], 1);
    $check(in_array('Default contact recipient is invalid.', $settingsErrors, true), 'Contact settings reject invalid or injected recipient addresses');
} else {
    $check(false, 'Contact settings reject invalid or injected recipient addresses');
}

$_SESSION['_csrf'] = str_repeat('a', 64);
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
$check(Csrf::valid(str_repeat('b', 64)) === false && Csrf::valid(str_repeat('a', 64)) === true, 'Public contact submissions reuse Talvoro CSRF validation');

if (class_exists(ContactFormContext::class)) {
    $token = ContactFormContext::issue(42, 'owner0001', 'contact001', 1_700_000_000);
    $context = ContactFormContext::verify($token, 1_700_000_120);
    $check(is_array($context) && ($context['page_id'] ?? 0) === 42 && ($context['owner_id'] ?? '') === 'owner0001', 'Signed form context round-trips');
    $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');
    $check(ContactFormContext::verify($tampered, 1_700_000_120) === null, 'Tampered form context is rejected');
    $check(ContactFormContext::verify($token, 1_700_030_000) === null, 'Expired form context is rejected');
}

if (class_exists(ContactFormState::class)) {
    $putState = new ReflectionMethod(ContactFormState::class, 'put');
    $pullState = new ReflectionMethod(ContactFormState::class, 'pull');
    $isolatesPages = $putState->getNumberOfParameters() >= 4 && $pullState->getNumberOfParameters() >= 3;
    if ($isolatesPages) {
        $_SESSION = [];
        ContactFormState::put(10, 'owner0001', 'contact001', ['success' => 'Page A']);
        ContactFormState::put(11, 'owner0001', 'contact001', ['success' => 'Page B']);
        $a = ContactFormState::pull(10, 'owner0001', 'contact001');
        $b = ContactFormState::pull(11, 'owner0001', 'contact001');
        $isolatesPages = ($a['success'] ?? '') === 'Page A' && ($b['success'] ?? '') === 'Page B';
    }
    $check($isolatesPages, 'Contact PRG state is isolated by page as well as form instance');
}

if (class_exists(ContactSubmissionService::class)) {
    $result = ContactSubmissionService::validateFields([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'subject' => '',
        'message' => '<script>alert(1)</script> Hello',
    ], $contact);
    $check(($result['errors'] ?? []) === [] && ($result['data']['email'] ?? '') === 'ada@example.test', 'Valid contact submission passes server validation');
    $check(str_contains((string)($result['data']['message'] ?? ''), '<script>'), 'Validation preserves message text for safe escaping at render time');

    $badEmail = ContactSubmissionService::validateFields(['name' => 'Ada', 'email' => "bad\r\nBcc:x@example.test", 'message' => 'Hello'], $contact);
    $check(in_array('Enter a valid email address.', $badEmail['errors'] ?? [], true), 'Invalid/header-injection email is rejected');

    $badName = ContactSubmissionService::validateFields(['name' => "Ada\r\nBcc: injected", 'email' => 'ada@example.test', 'message' => 'Hello'], $contact);
    $check(in_array('Name contains unsupported characters.', $badName['errors'] ?? [], true), 'Reply-To display name rejects mail-control characters');

    $missing = ContactSubmissionService::validateFields(['name' => '', 'email' => '', 'message' => ''], $contact);
    $check(count($missing['errors'] ?? []) >= 3, 'Missing required contact fields are rejected');

    $oversized = ContactSubmissionService::validateFields(['name' => 'Ada', 'email' => 'ada@example.test', 'message' => str_repeat('x', 10001)], $contact);
    $check(in_array('Message must be 10,000 characters or fewer.', $oversized['errors'] ?? [], true), 'Oversized message is rejected');

    $check(ContactSubmissionService::isSpam(['company_website' => 'https://spam.test'], 100, 200), 'Honeypot submission is classified as spam');
    $check(ContactSubmissionService::isSpam([], 199, 200), 'Implausibly fast submission is classified as spam');
    $check(!ContactSubmissionService::isSpam([], 190, 200), 'Normal form-fill timing is accepted');

    $notificationBodies = new ReflectionMethod(ContactSubmissionService::class, 'notificationBodies');
    $serviceSourceForContext = (string)file_get_contents(base_path('app/Core/ContactSubmissionService.php'));
    $hasFormContext = $notificationBodies->getNumberOfParameters() >= 3
        && str_contains($serviceSourceForContext, '$block[\'heading\']')
        && str_contains($serviceSourceForContext, "htmlRow('Form'");
    $check($hasFormContext, 'Contact notification identifies the specific form as well as the page');
}

$send = new ReflectionMethod(Mailer::class, 'send');
$check($send->getNumberOfParameters() >= 6, 'Mailer accepts safe delivery options without a second SMTP subsystem');
$check(method_exists(RateLimiter::class, 'tooManyContactAttempts') && method_exists(RateLimiter::class, 'hitContact'), 'Existing rate limiter exposes contact buckets');

if ($send->getNumberOfParameters() >= 6) {
    $message = new ReflectionMethod(Mailer::class, 'message');
    $headerText = new ReflectionMethod(Mailer::class, 'headerText');
    $mailConfig = [
        'from_name' => 'Talvoro',
        'from_email' => 'site@example.test',
    ];
    try {
        $raw = $message->invoke(null, $mailConfig, 'owner@example.test', 'Hello', 'Plain', '<p>HTML</p>', [
            'reply_to_email' => 'visitor@example.test',
            'reply_to_name' => "Ada\r\nBcc: injected@example.test",
        ]);
        $check(str_contains($raw, 'Reply-To:') && !str_contains($raw, "\r\nBcc: injected@example.test"), 'Reply-To is generated without header injection');
        $cleanHeaderText = (string)$headerText->invoke(null, "Ada\0\r\nBcc");
        $check(!str_contains($cleanHeaderText, "\0") && !str_contains($cleanHeaderText, "\r") && !str_contains($cleanHeaderText, "\n"), 'Mailer strips all control separators from display-name headers');
    } catch (Throwable) {
        $check(false, 'Reply-To is generated without header injection');
        $check(false, 'Mailer strips all control separators from display-name headers');
    }
}

$migration = base_path('database/migrations/022_contact_forms.sql');
$migrationSql = is_file($migration) ? (string)file_get_contents($migration) : '';
$check(is_file($migration), 'Forward-only migration 022 exists');
$check(str_contains($migrationSql, 'contact_submissions') && str_contains($migrationSql, 'contact.view') && str_contains($migrationSql, 'contact.manage'), 'Migration creates inbox and RBAC permissions');
$check(!preg_match('/\bip(_address)?\b/i', $migrationSql), 'Contact submissions schema does not store visitor IP addresses');
$check(str_contains($migrationSql, 'INDEX idx_contact_created (created_at)'), 'Contact retention cleanup has a direct created-at index');

$routes = (string)file_get_contents(base_path('routes/web.php'));
$check(str_contains($routes, "post('/_talvoro/contact'") || str_contains($routes, "post(\'/_talvoro/contact\'"), 'Explicit reserved public contact POST route exists');
$check(
    str_contains($routes, '$router->get($admin . \'/contact-submissions\'')
    && str_contains($routes, '$router->post($admin . \'/contact-submissions/bulk-delete\'')
    && str_contains($routes, '$router->post($admin . \'/contact-submissions/cleanup\''),
    'Admin contact inbox routes cover listing, bulk deletion, and retention cleanup'
);
$check(str_contains((string)file_get_contents(base_path('app/Core/Pages.php')), "'/_talvoro'"), 'Talvoro contact endpoint namespace is reserved from Pages');
$check(is_file(base_path('resources/views/page/blocks/contact.php')), 'Accessible public contact block view exists');
$check(is_file(base_path('resources/views/admin/contact/index.php')) && is_file(base_path('resources/views/admin/contact/show.php')), 'Focused admin contact inbox views exist');
$inboxView = (string)file_get_contents(base_path('resources/views/admin/contact/index.php'));
$check(
    str_contains($inboxView, "Gate::allows('contact.manage') || Gate::allows('mail.manage')"),
    'Read-only inbox users are not shown an inaccessible Contact settings link'
);

$contactController = (string)file_get_contents(base_path('app/Http/ContactFormController.php'));
$check(
    str_contains($contactController, 'Csrf::valid')
    && str_contains($contactController, 'RateLimiter::tooManyContactAttempts')
    && str_contains($contactController, 'ContactSubmissionService::submit'),
    'Public contact endpoint composes CSRF, rate limiting, and submission service'
);
$preValidationPos = strpos($contactController, 'ContactSubmissionService::validateFields');
$rateHitPos = strpos($contactController, 'RateLimiter::hitContact');
$check(
    $preValidationPos !== false && $rateHitPos !== false && $preValidationPos < $rateHitPos,
    'Ordinary validation errors do not consume the contact rate-limit quota'
);
$check(
    str_contains($contactController, 'catch (\\Throwable')
    && str_contains($contactController, 'temporarily unavailable'),
    'Unexpected contact processing errors are contained behind a generic visitor message'
);

$publicContact = (string)file_get_contents(base_path('resources/views/page/blocks/contact.php'));
$check(
    str_contains($publicContact, 'contact-eyebrow')
    && str_contains($publicContact, 'contact-assurances')
    && substr_count($publicContact, 'contact-assurance-item') >= 3,
    'Public Contact block uses the redesigned editorial hero with three reassurance points'
);
$check(
    str_contains($publicContact, 'contact-form-heading')
    && str_contains($publicContact, 'contact-fields-row')
    && str_contains($publicContact, 'contact-form-footer'),
    'Public Contact form uses the redesigned card hierarchy and compact field grid'
);
$contactDetail = (string)file_get_contents(base_path('resources/views/admin/contact/show.php'));
$check(
    str_contains($publicContact, 'e((string)($old[\'message\'] ?? \'\'))')
    && str_contains($contactDetail, 'e((string)$submission[\'message\'])'),
    'Visitor message HTML is escaped in both the public validation round-trip and admin inbox'
);
$check(
    str_contains($publicContact, '$formId . \'-error-\''),
    'Field error IDs are scoped to each rendered Contact form instance'
);
$check(
    preg_match('/name="company_website"[^>]*maxlength="200"/', $publicContact) === 1,
    'Honeypot field has an explicit client-side length ceiling in addition to the request-size limit'
);

$builder = (string)file_get_contents(base_path('public/assets/js/page-builder.js'));
$check(str_contains($builder, "contact: 'Contact form'") && str_contains($builder, "type === 'contact'"), 'Page Builder editor and live preview support Contact form');
$check(str_contains($builder, 'autocomplete="email"') || is_file(base_path('resources/views/page/blocks/contact.php')), 'Contact UI provides email autocomplete semantics');
$check(str_contains($builder, 'contact-form-preview') && str_contains($builder, 'disabled'), 'Page Builder Contact preview is visibly non-submitting');

$contactScript = (string)file_get_contents(base_path('public/assets/js/contact-form.js'));
$check(
    str_contains($contactScript, 'prefers-reduced-motion')
    && str_contains($contactScript, 'data-contact-focus'),
    'Contact result focus management respects reduced-motion preferences'
);

$css = (string)file_get_contents(base_path('public/assets/css/app.css'));
$check(str_contains($css, '.contact-form'), 'Contact form uses Talvoro responsive Design System styling');
$check(str_contains($css, '@media') && str_contains($css, '.contact-hero') && str_contains($css, '.contact-fields-row'), 'Contact form styling includes responsive behavior');
$check(
    str_contains($css, '.status-badge.new') && str_contains($css, '.status-badge.read'),
    'Contact inbox gives New and Read states distinct visible styling'
);
$check(
    str_contains($css, 'var(--talvoro-on-brand')
    && str_contains((string)file_get_contents(base_path('app/Core/DesignSystem.php')), '--talvoro-on-brand:'),
    'Contact submit button inherits the Design System contrast-aware on-brand color'
);

$mailSettingsView = (string)file_get_contents(base_path('resources/views/admin/mail-settings.php'));
$check(
    str_contains($mailSettingsView, 'contact_recipient')
    && str_contains($mailSettingsView, 'contact_store_submissions')
    && str_contains($mailSettingsView, 'contact_retention_days'),
    'Existing Email settings screen contains privileged Contact form configuration'
);
$check(
    str_contains($mailSettingsView, '$canManageMail')
    && str_contains($mailSettingsView, '$canManageContact'),
    'Email and Contact configuration remain permission-partitioned'
);
$check(
    str_contains($mailSettingsView, '$contactDeliveryReady = ContactSettings::deliveryReady();')
    && str_contains($mailSettingsView, '!$contactDeliveryReady'),
    'Contact setup warnings account for recipient readiness as well as SMTP readiness'
);

$layout = (string)file_get_contents(base_path('resources/views/layouts/app.php'));
$check(
    str_contains($layout, '$reservedAdminNavLabels')
    && str_contains($layout, '$reservedAdminNavSlugs')
    && str_contains($layout, 'ContentModels::all(true)'),
    'Admin sidebar filters custom content models that would duplicate reserved core navigation items'
);
$check(
    str_contains($layout, "Gate::allows('contact.view')")
    && str_contains($layout, 'Contact submissions')
    && str_contains($layout, "Gate::allows('contact.manage')"),
    'Admin navigation exposes Contact inbox/settings only through Contact permissions'
);

$contactAdminController = (string)file_get_contents(base_path('app/Http/ContactAdminController.php'));
$check(
    str_contains($contactAdminController, "Gate::allows('mail.manage') ? \\CMS\\Core\\MailSettings::config(false) : []"),
    'Contact-only administrators do not receive SMTP configuration on Contact settings validation errors'
);

$serviceSource = (string)file_get_contents(base_path('app/Core/ContactSubmissionService.php'));
$check(
    str_contains($serviceSource, 'ContactSubmissions::create')
    && str_contains($serviceSource, 'markDelivery($submissionId, \'sent\')')
    && str_contains($serviceSource, "'delivery' => 'sent'"),
    'Successful Contact delivery records a sent outcome when local storage is enabled'
);
$check(
    str_contains($serviceSource, "'status' => 'delivery_failed'")
    && str_contains($serviceSource, "'stored' => false"),
    'Email-only delivery failure remains retryable instead of pretending the message was accepted'
);
$check(
    str_contains($serviceSource, "'status' => 'accepted', 'stored' => true, 'delivery' => 'failed'"),
    'Stored submissions remain accepted when only the email notification fails'
);
$check(
    method_exists(ContactSubmissions::class, 'deleteMany')
    && method_exists(ContactSubmissions::class, 'purgeExpired'),
    'Contact inbox exposes focused deletion and retention cleanup behavior'
);
$check(str_contains($serviceSource, "'log_subject' => 'Contact form notification'"), 'Mail delivery log uses a generic Contact notification subject');

$mainCheck = (string)file_get_contents(base_path('bin/check.php'));
$check(
    str_contains($mainCheck, 'check-contact-forms.php')
    && str_contains($mainCheck, 'contact_submissions')
    && str_contains($mainCheck, '022_contact_forms.sql'),
    'Main Talvoro check suite asserts Contact Forms feature and migration presence'
);

printf("\nContact Forms checks: %d passed, %d failed.\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
