<?php
/**
 * حارس النظام — طبقة المراقبة والصدّ الخلفية
 * Backend security guard: request inspection, rate limiting,
 * IP banning, hardened sessions, security headers.
 *
 * يُحمَّل هذا الملف في بداية كل نقطة دخول قبل أي معالجة.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* =========================================================
 * Client identity
 * ======================================================= */
function client_ip(): string
{
    // على Hostinger خلف بروكسي يمكن الاعتماد على REMOTE_ADDR مباشرة.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr(preg_replace('/[^0-9a-fA-F:.]/', '', $ip), 0, 45);
}

function client_ua(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

/* =========================================================
 * Security log
 * ======================================================= */
function sec_log(string $action, string $detail = '', string $severity = 'info'): void
{
    try {
        q('INSERT INTO security_log (ip, ua, action, path, detail, severity) VALUES (?,?,?,?,?,?)', [
            client_ip(),
            client_ua(),
            substr($action, 0, 60),
            substr($_SERVER['REQUEST_URI'] ?? '', 0, 255),
            substr($detail, 0, 1000),
            $severity,
        ]);
    } catch (Throwable $e) {
        // fallback ملف إذا تعذّرت القاعدة
        @file_put_contents(
            SCF_STORAGE . '/logs/security-fallback.log',
            date('c') . "\t" . client_ip() . "\t" . $action . "\t" . $detail . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

/* =========================================================
 * IP ban management
 * ======================================================= */
function ip_is_banned(string $ip): bool
{
    $r = q_one('SELECT id FROM banned_ips WHERE ip = ? AND until_at > NOW() LIMIT 1', [$ip]);
    return $r !== null;
}

function ip_ban(string $ip, int $hours, string $reason): void
{
    q('INSERT INTO banned_ips (ip, reason, until_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL ? HOUR))
       ON DUPLICATE KEY UPDATE reason = VALUES(reason), until_at = VALUES(until_at)', [$ip, substr($reason, 0, 255), $hours]);
    sec_log('ip_ban', $reason . ' (' . $hours . 'h)', 'high');
}

/* =========================================================
 * Rate limiting  (sliding window per ip+action)
 * ======================================================= */
function rate_limit(string $action, int $max, int $windowSec, int $banMinutes = 0): bool
{
    $ip = client_ip();
    try {
        $row = q_one('SELECT id, hits, UNIX_TIMESTAMP(window_start) ws FROM rate_limits WHERE ip = ? AND action = ? LIMIT 1', [$ip, $action]);
        $now = time();
        if ($row === null) {
            q('INSERT INTO rate_limits (ip, action, hits, window_start) VALUES (?,?,1,NOW())', [$ip, $action]);
            return true;
        }
        if ($now - (int)$row['ws'] > $windowSec) {
            q('UPDATE rate_limits SET hits = 1, window_start = NOW() WHERE id = ?', [$row['id']]);
            return true;
        }
        if ((int)$row['hits'] >= $max) {
            sec_log('rate_limit', $action . ' exceeded (' . $row['hits'] . '/' . $max . ')', 'medium');
            if ($banMinutes > 0) {
                q('INSERT INTO banned_ips (ip, reason, until_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
                   ON DUPLICATE KEY UPDATE until_at = VALUES(until_at)', [$ip, 'rate:' . $action, $banMinutes]);
            }
            return false;
        }
        q('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row['id']]);
        return true;
    } catch (Throwable $e) {
        return true; // لا نوقف الموقع إذا تعذّر العدّاد
    }
}

/* =========================================================
 * WAF — request inspection
 * ======================================================= */
function waf_patterns(): array
{
    return [
        // SQL injection
        '/\bunion\b[\s\/\*]+\bselect\b/i',
        '/\b(select|insert|update|delete|drop|truncate)\b[\s\/\*]+(from|into|table|database)\b/i',
        '/\b(sleep|benchmark|extractvalue|updatexml|load_file)\s*\(/i',
        '/\binformation_schema\b/i',
        '/(\'|")\s*(or|and)\s*(\'|")?\s*\d+\s*=\s*\d+/i',
        // XSS
        '/<\s*script[\s>]/i',
        '/<\s*(iframe|object|embed|svg)[^>]*on\w+\s*=/i',
        '/\bon(error|load|click|mouseover|focus)\s*=/i',
        '/javascript\s*:/i',
        '/document\s*\.\s*(cookie|location)/i',
        // Path traversal / LFI / RFI
        '/\.\.[\/\\\\]/',
        '/\b(etc\/passwd|proc\/self)\b/i',
        '/(php|data|expect|zip|phar):\/\//i',
        // Code execution
        '/\b(eval|assert|system|exec|shell_exec|passthru|popen|proc_open)\s*\(/i',
        '/base64_decode\s*\(/i',
        '/\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[/i',
        '/<\?php/i',
        // Null bytes / CRLF
        '/%00|\x00/',
    ];
}

function waf_scan_value(string $value): ?string
{
    if ($value === '') return null;
    // فحص القيمة كما هي وبعد فك الترميز
    $candidates = [$value];
    $decoded = rawurldecode($value);
    if ($decoded !== $value) $candidates[] = $decoded;
    foreach ($candidates as $v) {
        foreach (waf_patterns() as $p) {
            if (preg_match($p, $v)) return $p;
        }
    }
    return null;
}

/**
 * فحص المصفوفات المتداخلة (GET/POST/COOKIE keys+values + URI).
 * تُستثنى حقول محددة (مثل محتوى محرر الصفحات في لوحة التحكم الموثقة).
 */
function waf_inspect(array $exemptKeys = []): void
{
    $sources = [
        'uri'    => [$_SERVER['REQUEST_URI'] ?? ''],
        'get'    => $_GET,
        'post'   => $_POST,
        'cookie' => $_COOKIE,
    ];
    $walk = function ($data, $src) use (&$walk, $exemptKeys) {
        foreach ((array)$data as $k => $v) {
            if (in_array((string)$k, $exemptKeys, true)) continue;
            $hitK = waf_scan_value((string)$k);
            if ($hitK !== null) return [$src, (string)$k, $hitK];
            if (is_array($v)) {
                $r = $walk($v, $src);
                if ($r !== null) return $r;
            } else {
                $hit = waf_scan_value((string)$v);
                if ($hit !== null) return [$src, (string)$k, $hit];
            }
        }
        return null;
    };
    foreach ($sources as $src => $data) {
        $r = $walk($data, $src);
        if ($r !== null) {
            waf_block($src . ':' . $r[1], $r[2]);
        }
    }
}

function waf_block(string $where, string $pattern): void
{
    $ip = client_ip();
    sec_log('waf_block', 'field=' . $where . ' pattern=' . $pattern, 'high');
    // نظام الإنذارات: تكرار المحاولات يؤدي لحظر العنوان
    try {
        $strikes = (int)q_val(
            "SELECT COUNT(*) FROM security_log WHERE ip = ? AND action = 'waf_block' AND ts > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        if ($strikes >= WAF_STRIKES_BAN) {
            ip_ban($ip, WAF_BAN_HOURS, 'waf strikes: ' . $strikes);
        }
    } catch (Throwable $e) {
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('403 Forbidden');
}

/* =========================================================
 * Security headers
 * ======================================================= */
function send_security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    /* الكاميرا مسموحة من نفس النطاق داخل لوحة الأدمن فقط لماسح الباركود. */
    $cameraPolicy = $admin ? 'camera=(self)' : 'camera=()';
    header('Permissions-Policy: ' . $cameraPolicy . ', microphone=(), geolocation=(), payment=()');
    header('X-XSS-Protection: 1; mode=block');
    $csp = "default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; "
         . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
         . "font-src 'self' data:; connect-src 'self'; frame-src 'self' https://www.google.com; "
         . "object-src 'none'; base-uri 'self'; form-action 'self'";
    header('Content-Security-Policy: ' . $csp);
    if ($admin) {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
}

/* =========================================================
 * Hardened session
 * ======================================================= */
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // ربط الجلسة ببصمة المتصفح للتصدي لسرقة الجلسات
    $fp = hash('sha256', client_ua() . '|' . SCF_SECRET);
    if (!isset($_SESSION['_fp'])) {
        $_SESSION['_fp'] = $fp;
    } elseif (!hash_equals($_SESSION['_fp'], $fp)) {
        sec_log('session_fp_mismatch', 'possible hijack', 'high');
        session_unset();
        session_destroy();
        secure_session_start();
        return;
    }
    // تدوير المعرف دورياً
    if (!isset($_SESSION['_rot'])) {
        $_SESSION['_rot'] = time();
    } elseif (time() - (int)$_SESSION['_rot'] > 900) {
        session_regenerate_id(true);
        $_SESSION['_rot'] = time();
    }
}

/* =========================================================
 * Entry point — يُستدعى من كل الصفحات
 * ======================================================= */
function guard_boot(array $opts = []): void
{
    $admin  = !empty($opts['admin']);
    $exempt = $opts['waf_exempt'] ?? [];

    // 0) طرق مسموحة فقط
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        http_response_code(405);
        exit('405');
    }

    // 1) العناوين المحظورة
    if (ip_is_banned(client_ip())) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('403 Forbidden');
    }

    // 2) فيضان الطلبات على نقاط POST
    if ($method === 'POST' && !rate_limit('post_flood', 40, 60, 30)) {
        http_response_code(429);
        exit('429 Too Many Requests');
    }

    // 3) فحص الحمولة
    waf_inspect($exempt);

    // 4) الرؤوس الأمنية
    send_security_headers($admin);

    // 5) جلسة مقواة
    secure_session_start();
}
