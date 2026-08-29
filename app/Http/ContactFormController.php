<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\ContactFormContext;
use CMS\Core\ContactFormState;
use CMS\Core\ContactSubmissionService;
use CMS\Core\Csrf;
use CMS\Core\PageBlocks;
use CMS\Core\Pages;
use CMS\Core\RateLimiter;
use CMS\Core\Response;
use CMS\Core\View;

final class ContactFormController
{
    public static function submit(): Response
    {
        $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > ContactSubmissionService::MAX_REQUEST_BYTES) {
            return self::message('Message too large', 'Your message is too large to send. Please shorten it and try again.', 413);
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return self::message('Request could not be verified', 'Refresh the page and try sending your message again.', 419);
        }

        $context = ContactFormContext::verify((string)($_POST['contact_context'] ?? ''));
        if ($context === null) {
            return self::message('Contact form expired', 'Refresh the page before sending your message again.', 422);
        }

        $page = Pages::find($context['page_id']);
        if (!$page || (string)($page['status'] ?? '') !== 'published') {
            return self::message('Contact form unavailable', 'This contact form is no longer available.', 404);
        }

        $block = self::resolveBlock($page, $context['owner_id'], $context['block_id']);
        if ($block === null) {
            return self::message('Contact form unavailable', 'This contact form has changed. Refresh the page and try again.', 422);
        }

        try {
            if (RateLimiter::tooManyContactAttempts()) {
                self::flash($page, $block, [
                    'errors' => ['Too many messages were submitted recently. Please wait a little before trying again.'],
                    'old' => self::safeOld($_POST),
                    'focus' => 'error',
                ]);
                return self::redirectToPage($page, $block);
            }
            $spam = ContactSubmissionService::isSpam($_POST, $context['issued_at']);
            if (!$spam) {
                $preflight = ContactSubmissionService::validateFields($_POST, $block);
                if (($preflight['errors'] ?? []) !== []) {
                    self::flash($page, $block, [
                        'errors' => $preflight['errors'],
                        'field_errors' => $preflight['field_errors'] ?? [],
                        'old' => $preflight['data'] ?? self::safeOld($_POST),
                        'focus' => 'error',
                    ]);
                    return self::redirectToPage($page, $block);
                }
            }

            // Count obvious spam and valid delivery attempts, but do not lock a
            // visitor out merely for correcting ordinary validation errors.
            RateLimiter::hitContact();

            $result = ContactSubmissionService::submit($page, $block, $_POST, $context['issued_at']);
            if (($result['status'] ?? '') === 'invalid') {
                self::flash($page, $block, [
                    'errors' => $result['errors'] ?? [],
                    'field_errors' => $result['field_errors'] ?? [],
                    'old' => $result['data'] ?? self::safeOld($_POST),
                    'focus' => 'error',
                ]);
                return self::redirectToPage($page, $block);
            }

            if (($result['status'] ?? '') === 'unavailable') {
                self::flash($page, $block, [
                    'errors' => ['This contact form is temporarily unavailable. Please try again later.'],
                    'old' => self::safeOld($_POST),
                    'focus' => 'error',
                ]);
                return self::redirectToPage($page, $block);
            }

            if (($result['status'] ?? '') === 'delivery_failed') {
                self::flash($page, $block, [
                    'errors' => ["We couldn't send your message right now. Please try again."],
                    'old' => self::safeOld($_POST),
                    'focus' => 'error',
                ]);
                return self::redirectToPage($page, $block);
            }
        } catch (\Throwable $e) {
            error_log(sprintf('Contact form processing error (%s): %s', $e::class, $e->getMessage()));
            self::flash($page, $block, [
                'errors' => ['This contact form is temporarily unavailable. Please try again later.'],
                'old' => self::safeOld($_POST),
                'focus' => 'error',
            ]);
            return self::redirectToPage($page, $block);
        }

        self::flash($page, $block, [
            'success' => (string)($block['success_message'] ?? 'Thanks — your message has been received.'),
            'focus' => 'success',
        ]);
        return self::redirectToPage($page, $block);
    }

    private static function resolveBlock(array $page, string $ownerId, string $blockId): ?array
    {
        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : PageBlocks::decode((string)($page['blocks_json'] ?? ''));
        foreach (PageBlocks::renderBlocks($blocks) as $block) {
            if (($block['type'] ?? '') !== 'contact') continue;
            if (($block['_render_owner_id'] ?? '') !== $ownerId || ($block['_render_block_id'] ?? '') !== $blockId) continue;
            return $block;
        }
        return null;
    }

    private static function flash(array $page, array $block, array $state): void
    {
        ContactFormState::put(
            (int)($page['id'] ?? 0),
            (string)($block['_render_owner_id'] ?? $block['id'] ?? ''),
            (string)($block['_render_block_id'] ?? $block['id'] ?? ''),
            $state
        );
    }

    private static function redirectToPage(array $page, array $block): Response
    {
        $path = (string)($page['path'] ?? '/');
        if ($path === '') $path = '/';
        $anchor = self::formId($block);
        return Response::redirect($path . '#' . $anchor, 303);
    }

    private static function formId(array $block): string
    {
        return 'contact-' . (string)($block['_render_owner_id'] ?? $block['id'] ?? '') . '-' . (string)($block['_render_block_id'] ?? $block['id'] ?? '');
    }

    /** @return array{name:string,email:string,subject:string,message:string} */
    private static function safeOld(array $input): array
    {
        $slice = static fn(string $value, int $max): string => function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
        return [
            'name' => $slice(trim((string)($input['name'] ?? '')), ContactSubmissionService::MAX_NAME),
            'email' => $slice(trim((string)($input['email'] ?? '')), ContactSubmissionService::MAX_EMAIL),
            'subject' => $slice(trim((string)($input['subject'] ?? '')), ContactSubmissionService::MAX_SUBJECT),
            'message' => $slice(trim((string)($input['message'] ?? '')), ContactSubmissionService::MAX_MESSAGE),
        ];
    }

    private static function message(string $title, string $message, int $status): Response
    {
        return new Response(View::render('errors/message', ['title' => $title, 'message' => $message]), $status);
    }

    private function __construct() {}
}
