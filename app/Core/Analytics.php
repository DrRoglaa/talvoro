<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class Analytics
{
    public static function recordPageView(string $path, int $status): void
    {
        if (!Env::bool('ANALYTICS_ENABLED', true) || Auth::check()) {
            return;
        }
        if (Env::bool('ANALYTICS_RESPECT_DNT', true) && (($_SERVER['HTTP_DNT'] ?? '') === '1')) {
            return;
        }

        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (Env::bool('ANALYTICS_IGNORE_BOTS', true) && preg_match('/bot|spider|crawler|slurp|monitor|uptime|headless/i', $ua)) {
            return;
        }

        try {
            $appKey = Env::get('APP_KEY', '') ?: 'local-dev-key';
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $dailySalt = gmdate('Y-m-d');
            $visitor = hash_hmac('sha256', $dailySalt . '|' . $ip . '|' . $ua, $appKey);

            if (!isset($_SESSION['_analytics_session'])) {
                $_SESSION['_analytics_session'] = bin2hex(random_bytes(16));
            }
            $session = hash_hmac('sha256', $_SESSION['_analytics_session'], $appKey);

            $ref = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST) ?: null;
            [$device, $browser, $os] = self::classify($ua);

            $utmSource = self::cleanCampaignValue($_GET['utm_source'] ?? null, 120);
            $utmMedium = self::cleanCampaignValue($_GET['utm_medium'] ?? null, 120);
            $utmCampaign = self::cleanCampaignValue($_GET['utm_campaign'] ?? null, 190);
            $utmContent = self::cleanCampaignValue($_GET['utm_content'] ?? null, 190);
            $utmTerm = self::cleanCampaignValue($_GET['utm_term'] ?? null, 190);

            $stmt = Database::connection()->prepare(
                "INSERT INTO analytics_events
                 (occurred_at,event_type,path,http_status,visitor_hash,session_hash,referrer_host,device_type,browser,os,
                  utm_source,utm_medium,utm_campaign,utm_content,utm_term)
                 VALUES (UTC_TIMESTAMP(),'page_view',?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $path,
                $status,
                $visitor,
                $session,
                $ref,
                $device,
                $browser,
                $os,
                $utmSource,
                $utmMedium,
                $utmCampaign,
                $utmContent,
                $utmTerm,
            ]);
        } catch (\Throwable) {
            // Analytics must never take down the public site.
        }
    }

    public static function overview(int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90, 180, 365], true) ? $days : 30;
        $db = Database::connection();
        $where = "event_type='page_view' AND occurred_at >= (UTC_TIMESTAMP() - INTERVAL {$days} DAY)";
        $previousWhere = "event_type='page_view'
            AND occurred_at < (UTC_TIMESTAMP() - INTERVAL {$days} DAY)
            AND occurred_at >= (UTC_TIMESTAMP() - INTERVAL " . ($days * 2) . " DAY)";

        $metrics = self::metrics($db, $where);
        $previous = self::metrics($db, $previousWhere);

        $sessionStats = $db->query(
            "SELECT COUNT(*) sessions,
                    COALESCE(SUM(CASE WHEN views >= 2 THEN 1 ELSE 0 END),0) engaged_sessions,
                    COALESCE(AVG(views),0) pages_per_session
             FROM (
                 SELECT session_hash,COUNT(*) views
                 FROM analytics_events
                 WHERE {$where}
                 GROUP BY session_hash
             ) x"
        )->fetch(PDO::FETCH_ASSOC);

        $sessions = max(0, (int)($sessionStats['sessions'] ?? 0));
        $engaged = max(0, (int)($sessionStats['engaged_sessions'] ?? 0));
        $metrics['pages_per_session'] = (float)($sessionStats['pages_per_session'] ?? 0);
        $metrics['engagement_rate'] = $sessions > 0 ? ($engaged / $sessions) * 100 : 0.0;

        $series = self::safeRows(
            $db,
            "SELECT DATE(occurred_at) day,
                    COUNT(*) pageviews,
                    COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE {$where}
             GROUP BY DATE(occurred_at)
             ORDER BY day"
        );

        $top = self::safeRows(
            $db,
            "SELECT path,COUNT(*) views,COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE {$where}
             GROUP BY path
             ORDER BY views DESC
             LIMIT 15"
        );

        $channels = self::safeRows(
            $db,
            "SELECT channel,COUNT(*) views,COUNT(DISTINCT session_hash) sessions
             FROM (
                 SELECT session_hash,
                        CASE
                            WHEN referrer_host IS NULL OR referrer_host='' THEN 'Direct'
                            WHEN LOWER(referrer_host) REGEXP 'google|bing|duckduckgo|yahoo' THEN 'Search'
                            WHEN LOWER(referrer_host) REGEXP 'facebook|instagram|linkedin|reddit|twitter|t.co|x.com|youtube|tiktok' THEN 'Social'
                            ELSE 'Referral'
                        END channel
                 FROM analytics_events
                 WHERE {$where}
             ) x
             GROUP BY channel
             ORDER BY sessions DESC"
        );

        $referrers = self::safeRows(
            $db,
            "SELECT referrer_host referrer,COUNT(*) views,COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE {$where} AND referrer_host IS NOT NULL AND referrer_host<>''
             GROUP BY referrer_host
             ORDER BY sessions DESC
             LIMIT 12"
        );

        $devices = self::dimension($db, 'device_type', $where);
        $browsers = self::dimension($db, 'browser', $where);
        $oses = self::dimension($db, 'os', $where);

        $entries = $db->query(
            "SELECT e.path,COUNT(*) sessions
             FROM analytics_events e
             JOIN (
                 SELECT session_hash,MIN(id) id
                 FROM analytics_events
                 WHERE {$where}
                 GROUP BY session_hash
             ) firsts ON firsts.id=e.id
             GROUP BY e.path
             ORDER BY sessions DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $exits = $db->query(
            "SELECT e.path,COUNT(*) sessions
             FROM analytics_events e
             JOIN (
                 SELECT session_hash,MAX(id) id
                 FROM analytics_events
                 WHERE {$where}
                 GROUP BY session_hash
             ) lasts ON lasts.id=e.id
             GROUP BY e.path
             ORDER BY sessions DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $realtime = $db->query(
            "SELECT path,COUNT(*) views,COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE event_type='page_view'
               AND occurred_at >= (UTC_TIMESTAMP() - INTERVAL 30 MINUTE)
             GROUP BY path
             ORDER BY MAX(occurred_at) DESC
             LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);

        $activeNow = (int)$db->query(
            "SELECT COUNT(DISTINCT session_hash)
             FROM analytics_events
             WHERE event_type='page_view'
               AND occurred_at >= (UTC_TIMESTAMP() - INTERVAL 5 MINUTE)"
        )->fetchColumn();

        $actions = $db->query(
            "SELECT event_type,COUNT(*) events,COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE event_type<>'page_view'
               AND occurred_at >= (UTC_TIMESTAMP() - INTERVAL {$days} DAY)
             GROUP BY event_type
             ORDER BY events DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $campaigns = $db->query(
            "SELECT
                COALESCE(NULLIF(utm_source,''),'(not set)') source,
                COALESCE(NULLIF(utm_medium,''),'(not set)') medium,
                COALESCE(NULLIF(utm_campaign,''),'(not set)') campaign,
                COUNT(*) views,
                COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE {$where}
               AND (
                    utm_source IS NOT NULL
                    OR utm_medium IS NOT NULL
                    OR utm_campaign IS NOT NULL
               )
             GROUP BY utm_source,utm_medium,utm_campaign
             ORDER BY sessions DESC,views DESC
             LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'days' => $days,
            'metrics' => $metrics,
            'previous' => $previous,
            'series' => $series,
            'top' => $top,
            'channels' => $channels,
            'referrers' => $referrers,
            'devices' => $devices,
            'browsers' => $browsers,
            'oses' => $oses,
            'entries' => $entries,
            'exits' => $exits,
            'realtime' => $realtime,
            'active_now' => $activeNow,
            'actions' => $actions,
            'campaigns' => $campaigns,
        ];
    }

    private static function metrics(PDO $db, string $where): array
    {
        $row = $db->query(
            "SELECT COUNT(*) pageviews,
                    COUNT(DISTINCT visitor_hash) visitors,
                    COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE {$where}"
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'pageviews' => (int)($row['pageviews'] ?? 0),
            'visitors' => (int)($row['visitors'] ?? 0),
            'sessions' => (int)($row['sessions'] ?? 0),
        ];
    }

    private static function dimension(PDO $db, string $column, string $where): array
    {
        $allowed = ['device_type', 'browser', 'os'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        return self::safeRows(
            $db,
            "SELECT COALESCE({$column},'Other') label,
                    COUNT(*) views,
                    COUNT(DISTINCT session_hash) sessions
             FROM analytics_events
             WHERE {$where}
             GROUP BY {$column}
             ORDER BY sessions DESC"
        );
    }

    private static function safeRows(PDO $db, string $sql): array
    {
        try {
            $result = $db->query($sql);
            return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function cleanCampaignValue(mixed $value, int $maxLength): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private static function classify(string $ua): array
    {
        $device = preg_match('/iPad|Tablet/i', $ua)
            ? 'Tablet'
            : (preg_match('/Mobile|iPhone|Android/i', $ua) ? 'Mobile' : 'Desktop');

        $browser = str_contains($ua, 'Edg/')
            ? 'Edge'
            : (str_contains($ua, 'Chrome/')
                ? 'Chrome'
                : (str_contains($ua, 'Firefox/')
                    ? 'Firefox'
                    : (str_contains($ua, 'Safari/') ? 'Safari' : 'Other')));

        $os = str_contains($ua, 'Windows')
            ? 'Windows'
            : (str_contains($ua, 'Mac OS X')
                ? 'macOS'
                : ((str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))
                    ? 'iOS/iPadOS'
                    : (str_contains($ua, 'Android')
                        ? 'Android'
                        : (str_contains($ua, 'Linux') ? 'Linux' : 'Other'))));

        return [$device, $browser, $os];
    }

    private function __construct()
    {
    }
}
