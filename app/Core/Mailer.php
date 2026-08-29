<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class Mailer
{
    public static function sendWelcome(array $user, string $temporaryPassword): void
    {
        $loginUrl = AdminPath::absoluteUrl('/login');
        $appName = (string)Env::get('APP_NAME', 'Talvoro');
        $name = (string)$user['display_name'];
        $email = (string)$user['email'];
        $role = (string)($user['role_label'] ?? 'CMS user');

        $subject = $appName . ' — your CMS login';
        $text = "Hi {$name},\n\nYour CMS account is ready.\n\nLogin: {$loginUrl}\nEmail: {$email}\nTemporary password: {$temporaryPassword}\nRole: {$role}\n\nYou will be asked to choose a new password after signing in.\n\n{$appName}";

        $body = '<p>Hi ' . e($name) . ',</p>'
            . '<p>Your CMS account is ready.</p>'
            . '<div style="margin:20px 0;padding:18px;border:1px solid #eadfd7;border-radius:14px;background:#fffaf6">'
            . '<div style="margin-bottom:10px"><small style="color:#8f8177;text-transform:uppercase;letter-spacing:.08em">Login</small><br><a href="' . e($loginUrl) . '" style="color:#ef684f">' . e($loginUrl) . '</a></div>'
            . '<div style="margin-bottom:10px"><small style="color:#8f8177;text-transform:uppercase;letter-spacing:.08em">Email</small><br><strong>' . e($email) . '</strong></div>'
            . '<div style="margin-bottom:10px"><small style="color:#8f8177;text-transform:uppercase;letter-spacing:.08em">Temporary password</small><br><code style="font-size:15px">' . e($temporaryPassword) . '</code></div>'
            . '<div><small style="color:#8f8177;text-transform:uppercase;letter-spacing:.08em">Role</small><br><strong>' . e($role) . '</strong></div>'
            . '</div>'
            . '<p>For security, you will be asked to choose a new password after signing in.</p>';

        self::send($email, $subject, $text, self::shell('Your CMS account is ready', $body), 'user_welcome');
    }

    public static function sendTest(string $recipient): void
    {
        $appName = (string)Env::get('APP_NAME', 'Talvoro');
        self::send(
            $recipient,
            $appName . ' mail test',
            "Email delivery is configured correctly.\n",
            self::shell('Mail test successful', '<p>Email delivery is configured correctly.</p>'),
            'mail_test'
        );
    }

    public static function send(
        string $to,
        string $subject,
        string $text,
        string $html,
        string $type = 'generic',
        array $options = []
    ): void {
        $config = MailSettings::config();

        if (!MailSettings::isReady()) {
            throw new RuntimeException('Email delivery is not configured.');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Recipient email address is invalid.');
        }

        try {
            self::smtpSend($config, $to, $subject, $text, $html, $options);
            $logSubject = self::logSubject($subject, $options);
            self::log($type, $to, $logSubject, 'sent', null);
        } catch (\Throwable $e) {
            self::log($type, $to, self::logSubject($subject, $options), 'failed', self::slice($e->getMessage(), 1000));
            throw $e;
        }
    }

    public static function recentLog(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            return Database::connection()->query(
                'SELECT id,mail_type,recipient,subject,delivery_status,delivery_error,created_at
'
                . 'FROM mail_delivery_log ORDER BY id DESC LIMIT ' . $limit
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function shell(string $title, string $bodyHtml): string
    {
        $appName = (string)Env::get('APP_NAME', 'Talvoro');
        $config = MailSettings::config(false);
        $from = (string)$config['from_email'];

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;background:#f4f0eb;color:#2d2622;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">'
            . '<table role="presentation" width="100%"><tr><td align="center" style="padding:30px 12px">'
            . '<table role="presentation" width="100%" style="max-width:620px;background:#fffdf9;border:1px solid #eadfd7;border-radius:22px;overflow:hidden">'
            . '<tr><td style="height:5px;background:linear-gradient(90deg,#63cfc2,#ff705d,#a681ea)"></td></tr>'
            . '<tr><td style="padding:24px 30px;border-bottom:1px solid #eee5df"><strong style="font-size:19px;letter-spacing:-.03em">' . e($appName) . '</strong><div style="margin-top:5px;color:#96887e;font-size:10px;text-transform:uppercase;letter-spacing:.12em">Private CMS</div></td></tr>'
            . '<tr><td style="padding:30px"><h1 style="margin:0 0 20px;font-size:26px;letter-spacing:-.035em">' . e($title) . '</h1><div style="color:#554a44;font-size:15px;line-height:1.7">' . $bodyHtml . '</div></td></tr>'
            . '<tr><td style="padding:18px 30px;background:#fbf7f3;border-top:1px solid #eee5df;color:#8e8178;font-size:12px">'
            . ($from !== '' ? 'Sent by ' . e($appName) . ' · ' . e($from) : e($appName))
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function smtpSend(array $c, string $to, string $subject, string $text, string $html, array $options = []): void
    {
        $host = (string)$c['host'];
        $port = (int)$c['port'];
        $encryption = (string)$c['encryption'];

        if ($port === 465) {
            $encryption = 'ssl';
        } elseif ($port === 587 && $encryption === 'ssl') {
            $encryption = 'starttls';
        }

        $remote = ($encryption === 'ssl' ? 'tls://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(self::friendlyError("Could not connect to {$host}:{$port}: {$errstr}"));
        }

        stream_set_timeout($socket, 15);

        try {
            self::expect($socket, [220]);
            $ehlo = self::ehloHost();
            self::command($socket, 'EHLO ' . $ehlo, [250]);

            if ($encryption === 'starttls') {
                self::command($socket, 'STARTTLS', [220]);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                self::command($socket, 'EHLO ' . $ehlo, [250]);
            }

            if ((string)$c['username'] !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode((string)$c['username']), [334]);
                self::command($socket, base64_encode((string)$c['password']), [235]);
            }

            $envelope = (string)$c['envelope_from'];
            if ($envelope === '') {
                $envelope = (string)$c['from_email'];
            }

            self::command($socket, 'MAIL FROM:<' . $envelope . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250,251]);
            self::command($socket, 'DATA', [354]);

            $raw = self::message($c, $to, $subject, $text, $html, $options);
            $raw = preg_replace('/\r?\n/', "\r\n", $raw) ?? $raw;
            $raw = preg_replace('/(^|\r\n)\./', '$1..', $raw) ?? $raw;
            fwrite($socket, $raw . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221], false);
        } catch (\Throwable $e) {
            throw new RuntimeException(self::friendlyError($e->getMessage()), 0, $e);
        } finally {
            fclose($socket);
        }
    }

    private static function message(array $c, string $to, string $subject, string $text, string $html, array $options = []): string
    {
        $boundary = 'pcms_' . bin2hex(random_bytes(12));
        $fromName = self::headerText((string)$c['from_name']);
        $fromEmail = self::headerAddress((string)$c['from_email']);
        $to = self::headerAddress($to);
        $subject = self::encodedHeader($subject);
        $domain = parse_url((string)Env::get('APP_URL', ''), PHP_URL_HOST) ?: 'talvoro.local';

        $headers = [
            'From: ' . ($fromName !== '' ? self::encodedHeader($fromName) . ' ' : '') . '<' . $fromEmail . '>',
        ];

        $replyToEmail = trim((string)($options['reply_to_email'] ?? ''));
        if ($replyToEmail !== '') {
            $replyToEmail = self::headerAddress($replyToEmail);
            $replyToName = self::headerText((string)($options['reply_to_name'] ?? ''));
            $headers[] = 'Reply-To: ' . ($replyToName !== '' ? self::encodedHeader($replyToName) . ' ' : '') . '<' . $replyToEmail . '>';
        }

        $headers = array_merge($headers, [
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ]);

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset="UTF-8"',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text), 76, "\r\n"),
            '--' . $boundary,
            'Content-Type: text/html; charset="UTF-8"',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html), 76, "\r\n"),
            '--' . $boundary . '--',
            '',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }

    private static function command($socket, string $command, array $expected, bool $throw = true): array
    {
        fwrite($socket, $command . "\r\n");
        try {
            return self::expect($socket, $expected);
        } catch (\Throwable $e) {
            if (!$throw) {
                return [0, ''];
            }
            throw $e;
        }
    }

    private static function expect($socket, array $expected): array
    {
        $lines = [];
        $code = 0;

        while (($line = fgets($socket, 4096)) !== false) {
            $lines[] = rtrim($line, "\r\n");
            if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
                $code = (int)$m[1];
                if ($m[2] === ' ') {
                    break;
                }
            }
        }

        $meta = stream_get_meta_data($socket);
        if (($meta['timed_out'] ?? false) === true) {
            throw new RuntimeException('SMTP connection timed out.');
        }

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP returned ' . $code . ': ' . implode(' ', $lines));
        }

        return [$code, implode("\n", $lines)];
    }

    private static function friendlyError(string $raw): string
    {
        $c = MailSettings::config(false);
        if (preg_match('/wrong version number|tls|STARTTLS/i', $raw)) {
            return 'SMTP TLS negotiation failed. Port 587 normally uses STARTTLS; port 465 normally uses SSL/TLS.';
        }
        if (preg_match('/authentication|AUTH|535|534/i', $raw)) {
            return 'SMTP authentication failed. Verify the username and mailbox/app password.';
        }
        if (preg_match('/refused|php_network_getaddresses|unable to connect|could not connect/i', $raw)) {
            return 'Could not reach SMTP server ' . $c['host'] . ':' . $c['port'] . '. Verify the host, port and hosting firewall.';
        }
        if (preg_match('/timed out/i', $raw)) {
            return 'SMTP connection timed out.';
        }
        return $raw;
    }

    private static function ehloHost(): string
    {
        return parse_url((string)Env::get('APP_URL', ''), PHP_URL_HOST) ?: 'localhost';
    }

    private static function headerText(string $value): string
    {
        return trim(preg_replace('/[\r\n\0]+/', ' ', $value) ?? '');
    }

    private static function headerAddress(string $value): string
    {
        $value = trim(preg_replace('/[\r\n]+/', '', $value) ?? '');
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address in mail configuration.');
        }
        return $value;
    }

    private static function encodedHeader(string $value): string
    {
        $value = self::headerText($value);
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }


    private static function logSubject(string $subject, array $options): string
    {
        $value = self::headerText((string)($options['log_subject'] ?? $subject));
        if ($value === '') $value = 'Message';
        return self::slice($value, 255);
    }

    private static function slice(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private static function log(string $type, string $recipient, string $subject, string $status, ?string $error): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO mail_delivery_log
                 (mail_type,recipient,subject,delivery_status,delivery_error,created_at)
                 VALUES (?,?,?,?,?,UTC_TIMESTAMP())'
            )->execute([$type, $recipient, $subject, $status, $error]);
        } catch (\Throwable) {
        }
    }

    private function __construct()
    {
    }
}
