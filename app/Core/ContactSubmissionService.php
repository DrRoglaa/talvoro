<?php
declare(strict_types=1);

namespace CMS\Core;

final class ContactSubmissionService
{
    public const MAX_REQUEST_BYTES = 65536;
    public const MAX_NAME = 120;
    public const MAX_EMAIL = 254;
    public const MAX_SUBJECT = 200;
    public const MAX_MESSAGE = 10000;
    public const MIN_FILL_SECONDS = 2;

    /** @return array{data:array{name:string,email:string,subject:string,message:string},errors:list<string>,field_errors:array<string,string>} */
    public static function validateFields(array $input, array $block): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $subject = trim((string)($input['subject'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        $showSubject = !array_key_exists('show_subject', $block) || !empty($block['show_subject']);
        $requireSubject = $showSubject && !empty($block['require_subject']);
        if (!$showSubject) $subject = '';

        $errors = [];
        $fieldErrors = [];
        $add = static function (string $field, string $message) use (&$errors, &$fieldErrors): void {
            $errors[] = $message;
            if (!isset($fieldErrors[$field])) $fieldErrors[$field] = $message;
        };

        if ($name === '') $add('name', 'Enter your name.');
        elseif (self::length($name) > self::MAX_NAME) $add('name', 'Name must be 120 characters or fewer.');
        elseif (preg_match('/[\r\n\0]/', $name)) $add('name', 'Name contains unsupported characters.');

        if ($email === '') $add('email', 'Enter your email address.');
        elseif (self::length($email) > self::MAX_EMAIL || preg_match('/[\r\n\0]/', $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $add('email', 'Enter a valid email address.');

        if ($requireSubject && $subject === '') $add('subject', 'Enter a subject.');
        if (self::length($subject) > self::MAX_SUBJECT) $add('subject', 'Subject must be 200 characters or fewer.');
        if (preg_match('/[\r\n\0]/', $subject)) $add('subject', 'Subject contains unsupported characters.');

        if ($message === '') $add('message', 'Enter a message.');
        elseif (self::length($message) > self::MAX_MESSAGE) $add('message', 'Message must be 10,000 characters or fewer.');

        return [
            'data' => ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message],
            'errors' => array_values(array_unique($errors)),
            'field_errors' => $fieldErrors,
        ];
    }

    public static function isSpam(array $input, int $issuedAt, ?int $now = null): bool
    {
        $honeypot = trim((string)($input['company_website'] ?? ''));
        if ($honeypot !== '') return true;
        $now ??= time();
        return $issuedAt < 1 || ($now - $issuedAt) < self::MIN_FILL_SECONDS;
    }

    /** @return array{status:string,errors?:list<string>,data?:array<string,string>,stored?:bool,delivery?:string,submission_id?:int} */
    public static function submit(array $page, array $block, array $input, int $issuedAt): array
    {
        if (self::isSpam($input, $issuedAt)) {
            return ['status' => 'accepted', 'stored' => false, 'delivery' => 'suppressed'];
        }

        $validated = self::validateFields($input, $block);
        if ($validated['errors'] !== []) {
            return ['status' => 'invalid', 'errors' => $validated['errors'], 'field_errors' => $validated['field_errors'], 'data' => $validated['data']];
        }

        $settings = ContactSettings::config();
        $canStore = $settings['store_submissions'];
        $canDeliver = ContactSettings::deliveryReady();
        if (!$canStore && !$canDeliver) {
            return ['status' => 'unavailable'];
        }

        $data = $validated['data'];
        $submissionId = 0;
        if ($canStore) {
            $pageLabel = trim((string)($page['title'] ?? 'Contact form'));
            $formLabel = trim((string)($block['heading'] ?? ''));
            $sourceLabel = $formLabel !== '' ? $pageLabel . ' — ' . $formLabel : $pageLabel;
            $submissionId = ContactSubmissions::create([
                'page_id' => (int)($page['id'] ?? 0),
                'form_owner_id' => (string)($block['_render_owner_id'] ?? $block['id'] ?? ''),
                'block_id' => (string)($block['_render_block_id'] ?? $block['id'] ?? ''),
                'source_label' => self::slice($sourceLabel, 255),
                'source_path' => self::slice(trim((string)($page['path'] ?? '/')), 191),
                'sender_name' => $data['name'],
                'sender_email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message'],
            ]);
        }

        if (!$canDeliver) {
            if ($submissionId > 0) ContactSubmissions::markDelivery($submissionId, 'failed');
            self::purgeStored($settings['retention_days']);
            return ['status' => 'accepted', 'stored' => $submissionId > 0, 'delivery' => 'failed', 'submission_id' => $submissionId];
        }

        [$text, $html] = self::notificationBodies($page, $block, $data);
        try {
            Mailer::send(
                $settings['recipient'],
                self::notificationSubject($block, $data),
                $text,
                $html,
                'contact_notification',
                [
                    'reply_to_email' => $data['email'],
                    'reply_to_name' => $data['name'],
                    'log_subject' => 'Contact form notification',
                ]
            );
            if ($submissionId > 0) ContactSubmissions::markDelivery($submissionId, 'sent');
        } catch (\Throwable) {
            if ($submissionId > 0) {
                ContactSubmissions::markDelivery($submissionId, 'failed');
                self::purgeStored($settings['retention_days']);
                return ['status' => 'accepted', 'stored' => true, 'delivery' => 'failed', 'submission_id' => $submissionId];
            }
            return ['status' => 'delivery_failed', 'stored' => false, 'delivery' => 'failed'];
        }

        self::purgeStored($settings['retention_days']);
        return ['status' => 'accepted', 'stored' => $submissionId > 0, 'delivery' => 'sent', 'submission_id' => $submissionId];
    }

    public static function notificationSubject(array $block, array $data): string
    {
        $settings = ContactSettings::config();
        $prefix = trim((string)($block['subject_prefix'] ?? ''));
        if ($prefix === '') $prefix = $settings['subject_prefix'];
        $prefix = self::headerText($prefix);
        $visitorSubject = self::headerText((string)($data['subject'] ?? ''));
        $suffix = $visitorSubject !== '' ? $visitorSubject : 'Message from ' . self::headerText((string)($data['name'] ?? 'visitor'));
        return self::slice('[' . $prefix . '] ' . $suffix, 240);
    }

    /** @return array{0:string,1:string} */
    private static function notificationBodies(array $page, array $block, array $data): array
    {
        $siteName = (string)Env::get('APP_NAME', 'Talvoro');
        $pageTitle = trim((string)($page['title'] ?? 'Contact form'));
        $pagePath = trim((string)($page['path'] ?? '/'));
        $formLabel = trim((string)($block['heading'] ?? ''));
        if ($formLabel === '') $formLabel = 'Contact form';
        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        $subject = $data['subject'] !== '' ? $data['subject'] : '(No subject)';

        $text = "New contact form message\n\n"
            . "Name: {$data['name']}\nEmail: {$data['email']}\nSubject: {$subject}\n"
            . "Form: {$formLabel}\nPage: {$pageTitle} ({$pagePath})\nReceived: {$timestamp}\n\nMessage:\n{$data['message']}\n";

        $body = '<p>A new contact form message was received on <strong>' . e($siteName) . '</strong>.</p>'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;margin:18px 0">'
            . self::htmlRow('Name', $data['name'])
            . self::htmlRow('Email', $data['email'])
            . self::htmlRow('Subject', $subject)
            . self::htmlRow('Form', $formLabel)
            . self::htmlRow('Page', $pageTitle . ' (' . $pagePath . ')')
            . self::htmlRow('Received', $timestamp)
            . '</table>'
            . '<h2 style="font-size:17px;margin:24px 0 10px">Message</h2>'
            . '<div style="white-space:pre-wrap;overflow-wrap:anywhere">' . nl2br(e($data['message'])) . '</div>';

        return [$text, Mailer::shell('New contact message', $body)];
    }

    private static function htmlRow(string $label, string $value): string
    {
        return '<tr><td style="padding:7px 12px 7px 0;color:#8f8177;vertical-align:top">' . e($label) . '</td><td style="padding:7px 0;vertical-align:top"><strong>' . e($value) . '</strong></td></tr>';
    }

    private static function purgeStored(int $days): void
    {
        try { ContactSubmissions::purgeExpired($days); } catch (\Throwable) {}
    }

    private static function headerText(string $value): string
    {
        return trim((string)(preg_replace('/[\r\n\0]+/', ' ', $value) ?? ''));
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private static function slice(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function __construct() {}
}
