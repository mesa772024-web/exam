<?php
declare(strict_types=1);

function apply_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), geolocation=(), payment=(), usb=(), microphone=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');
    $nonce = csp_nonce();
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self'; frame-src 'self'");
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function csp_nonce(): string
{
    static $nonce;
    if (!is_string($nonce)) $nonce = base64_encode(random_bytes(18));
    return $nonce;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): void
{
    if (!$token || !hash_equals(csrf_token(), $token)) {
        security_event('csrf.failed', 'warning');
        http_response_code(419);
        throw new RuntimeException('انتهت صلاحية الجلسة. حدّث الصفحة وحاول مجدداً.');
    }
}

function clean_text(mixed $value, int $maxLength): string
{
    $value = trim(is_string($value) ? $value : '');
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function clean_multiline(mixed $value, int $maxLength): string
{
    $value = clean_text($value, $maxLength);
    return preg_replace("/\r\n?|\n/u", "\n", $value) ?? '';
}

function clean_email(mixed $value): string
{
    $email = mb_strtolower(clean_text($value, 190), 'UTF-8');
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function clean_slug(mixed $value): string
{
    $slug = mb_strtolower(clean_text($value, 90), 'UTF-8');
    $slug = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}-]+/u', '-', $slug) ?? '';
    return trim($slug, '-');
}

function request_expects_json(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedFormat = strtolower((string) ($_POST['response_format'] ?? $_GET['response_format'] ?? ''));
    return $requestedFormat === 'json' || $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > 2_000_000) {
            throw new RuntimeException('حجم الطلب أكبر من الحد المسموح.');
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function client_ip(): string
{
    return clean_text($_SERVER['REMOTE_ADDR'] ?? 'unknown', 64);
}

function ip_hash(): string
{
    return hash_hmac('sha256', client_ip(), app_key());
}

function enforce_rate_limit(string $action, int $maximum, int $windowSeconds, string $subject = ''): void
{
    $key = hash_hmac('sha256', $action . '|' . client_ip() . '|' . $subject, app_key());
    $now = time();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT window_started, hit_count FROM rate_limits WHERE limit_key = :key');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();
        if (!$row || $now - (int) $row['window_started'] >= $windowSeconds) {
            $statement = $pdo->prepare('INSERT INTO rate_limits(limit_key, window_started, hit_count) VALUES(:key, :started, 1) ON DUPLICATE KEY UPDATE window_started = VALUES(window_started), hit_count = 1');
            $statement->execute(['key' => $key, 'started' => $now]);
        } elseif ((int) $row['hit_count'] >= $maximum) {
            $pdo->rollBack();
            security_event('rate_limit.' . $action, 'warning', ['subject' => $subject]);
            throw new RuntimeException('طلبات كثيرة خلال وقت قصير. حاول لاحقاً.');
        } else {
            $statement = $pdo->prepare('UPDATE rate_limits SET hit_count = hit_count + 1 WHERE limit_key = :key');
            $statement->execute(['key' => $key]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode(string $value): string|false
{
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function issue_visitor_cookie(string $visitorId): void
{
    $expires = time() + 86400 * 180;
    $payload = $visitorId . '|' . $expires;
    $signature = hash_hmac('sha256', $payload, app_key());
    setcookie('hyt_visitor', base64url_encode($payload . '|' . $signature), [
        'expires' => $expires,
        'path' => BASE_URL !== '' ? parse_url(BASE_URL, PHP_URL_PATH) . '/' : '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['hyt_visitor'] = base64url_encode($payload . '|' . $signature);
}

function current_visitor_id(): ?string
{
    $encoded = $_COOKIE['hyt_visitor'] ?? '';
    if (!is_string($encoded) || $encoded === '') {
        return null;
    }
    $decoded = base64url_decode($encoded);
    if (!is_string($decoded)) {
        return null;
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 3) {
        return null;
    }
    [$id, $expires, $signature] = $parts;
    if (!preg_match('/^[a-f0-9-]{36}$/', $id) || (int) $expires < time()) {
        return null;
    }
    $expected = hash_hmac('sha256', $id . '|' . $expires, app_key());
    return hash_equals($expected, $signature) ? $id : null;
}

function safe_rich_text(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><h2><h3><h4><a><span><mark>';
    $clean = strip_tags($html, $allowed);
    $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? '';
    $clean = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\')/iu', '', $clean) ?? '';
    $clean = preg_replace('/href\s*=\s*(["\'])\s*(?:javascript|data):.*?\1/iu', 'href="#"', $clean) ?? '';
    return $clean;
}

function safe_style_map(array $styles): array
{
    $allowed = [
        'color' => '/^#[0-9a-f]{3,8}$/i',
        'backgroundColor' => '/^#[0-9a-f]{3,8}$/i',
        'fontSize' => '/^(?:[8-9]|[1-9][0-9]|1[0-5][0-9]|160)px$/',
        'fontWeight' => '/^(?:300|400|500|600|700|800)$/',
        'lineHeight' => '/^(?:0\.[89]|[12](?:\.\d)?|3(?:\.0)?)$/',
        'width' => '/^(?:auto|[1-9][0-9]{0,3}px|(?:[1-9][0-9]?(?:\.[0-9]+)?|100)%|100v[wh])$/',
        'maxWidth' => '/^(?:none|[1-9][0-9]{0,3}px|(?:[1-9][0-9]?(?:\.[0-9]+)?|100)%|100v[wh])$/',
        'height' => '/^(?:auto|[1-9][0-9]{0,3}px|(?:[1-9][0-9]?(?:\.[0-9]+)?|100)%|100v[wh]|100svh)$/',
        'padding' => '/^(?:[0-9]|[1-9][0-9]|1[0-5][0-9]|160)px$/',
        'marginTop' => '/^-?(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-3][0-9]|240)px$/',
        'marginBottom' => '/^-?(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-3][0-9]|240)px$/',
        'textAlign' => '/^(?:start|end|left|right|center)$/',
        'borderRadius' => '/^(?:[0-9]|[1-9][0-9]|1[01][0-9]|120)px$/',
        'borderWidth' => '/^(?:[0-9]|1[0-2])px$/',
        'borderColor' => '/^#[0-9a-f]{3,8}$/i',
        'borderStyle' => '/^(?:none|solid|dashed|dotted)$/',
        'opacity' => '/^(?:0(?:\.\d{1,2})?|1(?:\.0{1,2})?)$/',
        'objectFit' => '/^(?:cover|contain|fill|scale-down)$/',
        'display' => '/^(?:initial|block|inline-block|flex|grid|none)$/',
        'translateX' => '/^-?(?:[0-9]|[1-9][0-9]|[12][0-9]{2}|300)px$/',
        'translateY' => '/^-?(?:[0-9]|[1-9][0-9]|[12][0-9]{2}|300)px$/',
    ];
    $result = [];
    foreach ($allowed as $key => $pattern) {
        $value = clean_text($styles[$key] ?? '', 30);
        if ($value !== '' && preg_match($pattern, $value)) {
            $result[$key] = $value;
        }
    }
    return $result;
}

function store_private_upload(array $file, string $purpose): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('لم يكتمل رفع الملف.');
    }
    $size = (int) ($file['size'] ?? 0);
    $limits = ['image' => 8_000_000, 'audio' => 20_000_000, 'file' => 15_000_000];
    $limit = $limits[$purpose] ?? 8_000_000;
    if ($size < 1 || $size > $limit) {
        throw new RuntimeException('حجم الملف غير مسموح.');
    }
    $tmp = (string) $file['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $allowed = [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'],
        'audio' => ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a'],
        'file' => ['application/pdf' => 'pdf', 'text/plain' => 'txt', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'],
    ];
    if (!isset($allowed[$purpose][$mime])) {
        throw new RuntimeException('نوع الملف غير مسموح.');
    }
    $width = $height = null;
    if ($purpose === 'image') {
        $dimensions = @getimagesize($tmp);
        if (!is_array($dimensions) || ($dimensions['mime'] ?? '') !== $mime) {
            security_event('upload.disguised_file', 'critical', ['mime' => $mime]);
            throw new RuntimeException('الصورة غير صالحة أو لا تطابق نوعها الحقيقي.');
        }
        [$width, $height] = [(int) $dimensions[0], (int) $dimensions[1]];
        if ($width < 1 || $height < 1 || $width * $height > 50_000_000) {
            throw new RuntimeException('أبعاد الصورة غير مسموحة.');
        }
    }
    $subdir = date('Y/m');
    $directory = UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
    if (!is_dir($directory)) {
        mkdir($directory, 0770, true);
    }
    $extension = $allowed[$purpose][$mime];
    $name = bin2hex(random_bytes(20)) . '.' . $extension;
    $destination = $directory . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('تعذر حفظ الملف بأمان.');
    }
    @chmod($destination, 0640);
    return [
        'path' => $subdir . '/' . $name,
        'original_name' => clean_text($file['name'] ?? 'file', 180),
        'mime' => $mime,
        'size' => $size,
        'sha256' => hash_file('sha256', $destination),
        'width' => $width,
        'height' => $height,
    ];
}

function store_public_image(array $file, string $folder = 'cms'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('لم يكتمل رفع الصورة.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 10_000_000) throw new RuntimeException('حجم الصورة غير مسموح.');
    $tmp = (string) $file['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $dimensions = @getimagesize($tmp);
    if (!isset($allowed[$mime]) || !is_array($dimensions) || ($dimensions['mime'] ?? '') !== $mime) {
        security_event('upload.public_disguised_file', 'critical', ['mime'=>$mime]);
        throw new RuntimeException('الملف ليس صورة صالحة.');
    }
    $width=(int)$dimensions[0];$height=(int)$dimensions[1];
    if($width<1||$height<1||$width*$height>50_000_000)throw new RuntimeException('أبعاد الصورة غير مسموحة.');
    $relative='uploads/'.$folder.'/'.date('Y/m');
    $directory=APP_ROOT.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
    if(!is_dir($directory))mkdir($directory,0755,true);
    $name=bin2hex(random_bytes(20)).'.'.$allowed[$mime];
    $destination=$directory.DIRECTORY_SEPARATOR.$name;
    if(!move_uploaded_file($tmp,$destination))throw new RuntimeException('تعذر حفظ الصورة.');
    @chmod($destination,0644);
    return ['path'=>$relative.'/'.$name,'original_name'=>clean_text($file['name']??'image',180),'mime'=>$mime,'size'=>$size,'sha256'=>hash_file('sha256',$destination),'width'=>$width,'height'=>$height];
}

function security_event(string $type, string $severity = 'notice', array $details = []): void
{
    try {
        $statement = db()->prepare('INSERT INTO security_events(event_type, severity, ip_hash, details_json) VALUES(:type, :severity, :ip, :details)');
        $statement->execute([
            'type' => clean_text($type, 100),
            'severity' => in_array($severity, ['notice', 'warning', 'critical'], true) ? $severity : 'notice',
            'ip' => ip_hash(),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable) {
        // Logging cannot become an availability problem.
    }
}

function record_visit_event(): void
{
    if (empty($_COOKIE['hyt_visit'])) {
        $visitId = bin2hex(random_bytes(18));
        setcookie('hyt_visit', $visitId, [
            'expires' => time() + 86400 * 365,
            'path' => BASE_URL !== '' ? parse_url(BASE_URL, PHP_URL_PATH) . '/' : '/',
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['hyt_visit'] = $visitId;
    }
    $sessionHash = hash_hmac('sha256', (string) $_COOKIE['hyt_visit'], app_key());
    $userAgent = clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 500);
    $device = preg_match('/mobile|android|iphone|ipod/i', $userAgent) ? 'mobile' : (preg_match('/ipad|tablet/i', $userAgent) ? 'tablet' : 'desktop');
    $path = clean_text(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/', 500);
    $statement = db()->prepare('INSERT INTO visit_events(session_hash, path, page_key, locale, referrer, user_agent, device_type, ip_hash) VALUES(:session, :path, :page, :locale, :referrer, :agent, :device, :ip)');
    $statement->execute([
        'session' => $sessionHash,
        'path' => $path,
        'page' => clean_text($_GET['slug'] ?? basename($path), 100),
        'locale' => current_locale(),
        'referrer' => clean_text($_SERVER['HTTP_REFERER'] ?? '', 500),
        'agent' => $userAgent,
        'device' => $device,
        'ip' => ip_hash(),
    ]);
}
