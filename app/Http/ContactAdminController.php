<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\ContactSettings;
use CMS\Core\ContactSubmissions;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\Response;
use CMS\Core\View;

final class ContactAdminController
{
    public static function updateSettings(): Response
    {
        if ($r = self::requirePermission('contact.manage')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $actor = Auth::user();
        $errors = ContactSettings::save($_POST, (int)$actor['id']);
        if ($errors !== []) {
            // Reuse the Email screen while keeping SMTP credentials and contact
            // configuration in their respective services.
            return new Response(View::render('admin/mail-settings', [
                'title' => 'Email delivery',
                'config' => Gate::allows('mail.manage') ? \CMS\Core\MailSettings::config(false) : [],
                'contactConfig' => array_merge(ContactSettings::config(), [
                    'recipient' => (string)($_POST['contact_recipient'] ?? ''),
                    'subject_prefix' => (string)($_POST['contact_subject_prefix'] ?? ''),
                    'store_submissions' => isset($_POST['contact_store_submissions']),
                    'retention_days' => (int)($_POST['contact_retention_days'] ?? 30),
                ]),
                'contactRetentionOptions' => ContactSettings::retentionOptions(),
                'canManageMail' => Gate::allows('mail.manage'),
                'canManageContact' => true,
                'saved' => false,
                'contactSaved' => false,
                'tested' => '',
                'errors' => [],
                'contactErrors' => $errors,
                'deliveries' => Gate::allows('mail.manage') ? \CMS\Core\Mailer::recentLog(20) : [],
            ]), 422);
        }
        Audit::log('contact.settings.update', 'contact_settings');
        return Response::redirect(AdminPath::baseUrl() . '/mail?contact_saved=1', 303);
    }

    public static function index(): Response
    {
        if ($r = self::requirePermission('contact.view')) return $r;
        $purged = 0;
        try { $purged = ContactSubmissions::purgeExpired(ContactSettings::config()['retention_days']); } catch (\Throwable) {}
        if ($purged > 0) Audit::log('contact.retention.cleanup', 'contact_submission', null, ['deleted' => $purged, 'automatic' => true]);
        return new Response(View::render('admin/contact/index', [
            'title' => 'Contact submissions',
            'pageData' => ContactSubmissions::adminPage((int)($_GET['page'] ?? 1), (string)($_GET['status'] ?? '')),
            'canManage' => Gate::allows('contact.manage'),
            'deleted' => isset($_GET['deleted']),
            'bulkDeleted' => (int)($_GET['bulk_deleted'] ?? 0),
            'cleaned' => (int)($_GET['cleaned'] ?? 0),
        ]));
    }

    public static function show(string $id): Response
    {
        if ($r = self::requirePermission('contact.view')) return $r;
        $submissionId = self::positiveId($id);
        $submission = $submissionId ? ContactSubmissions::find($submissionId) : null;
        if (!$submission) return new Response(View::render('errors/404', ['title' => 'Submission not found']), 404);
        if (($submission['status'] ?? '') === 'new') {
            ContactSubmissions::setRead($submissionId, true);
            $submission['status'] = 'read';
            $submission['read_at'] = gmdate('Y-m-d H:i:s');
        }
        return new Response(View::render('admin/contact/show', [
            'title' => 'Contact submission',
            'submission' => $submission,
            'canManage' => Gate::allows('contact.manage'),
        ]));
    }

    public static function setStatus(string $id): Response
    {
        if ($r = self::requirePermission('contact.view')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $submissionId = self::positiveId($id);
        if (!$submissionId || !ContactSubmissions::find($submissionId)) return new Response(View::render('errors/404', ['title' => 'Submission not found']), 404);
        $read = (string)($_POST['status'] ?? '') === 'read';
        ContactSubmissions::setRead($submissionId, $read);
        return Response::redirect(AdminPath::baseUrl() . '/contact-submissions/' . $submissionId, 303);
    }

    public static function delete(string $id): Response
    {
        if ($r = self::requirePermission('contact.manage')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        if (($_POST['confirm_delete'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Delete not confirmed', 'message' => 'Confirm permanent deletion first.']), 422);
        $submissionId = self::positiveId($id);
        if (!$submissionId || !ContactSubmissions::delete($submissionId)) return new Response(View::render('errors/404', ['title' => 'Submission not found']), 404);
        Audit::log('contact.submission.delete', 'contact_submission', $submissionId);
        return Response::redirect(AdminPath::baseUrl() . '/contact-submissions?deleted=1', 303);
    }

    public static function bulkDelete(): Response
    {
        if ($r = self::requirePermission('contact.manage')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        if (($_POST['confirm_delete'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Bulk delete not confirmed', 'message' => 'Confirm permanent deletion first.']), 422);
        $ids = is_array($_POST['submission_ids'] ?? null) ? $_POST['submission_ids'] : [];
        $deleted = ContactSubmissions::deleteMany($ids);
        Audit::log('contact.submission.bulk_delete', 'contact_submission', null, ['deleted' => $deleted]);
        return Response::redirect(AdminPath::baseUrl() . '/contact-submissions?bulk_deleted=' . $deleted, 303);
    }

    public static function cleanup(): Response
    {
        if ($r = self::requirePermission('contact.manage')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $days = ContactSettings::config()['retention_days'];
        $deleted = ContactSubmissions::purgeExpired($days);
        Audit::log('contact.retention.cleanup', 'contact_submission', null, ['deleted' => $deleted, 'retention_days' => $days, 'automatic' => false]);
        return Response::redirect(AdminPath::baseUrl() . '/contact-submissions?cleaned=' . $deleted, 303);
    }

    private static function requirePermission(string $permission): ?Response
    {
        if (!Auth::check()) return Response::redirect(AdminPath::loginUrl());
        if (Auth::requiresPasswordChange()) return Response::redirect(AdminPath::passwordUrl());
        if (!Gate::allows($permission)) return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        return null;
    }

    private static function csrfFailure(): Response
    {
        return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private static function positiveId(string $value): int
    {
        return ctype_digit($value) && (int)$value > 0 ? (int)$value : 0;
    }

    private function __construct() {}
}
