<?php
/**
 * منشورات تغطية المنتدى — قاعدة البيانات (جدول posts).
 * يُملأ الجدول تلقائياً أول مرة من app/edition2-content.json، وبعدها تُدار من لوحة التحكم.
 */
require_once __DIR__ . '/helpers.php';

function scf_posts_json(): array
{
    static $data = null;
    if ($data === null) {
        $data = json_decode((string)@file_get_contents(__DIR__ . '/edition2-content.json'), true);
        if (!is_array($data)) $data = ['posts' => [], 'thanks' => ['ar' => '', 'en' => '']];
    }
    return $data;
}

function scf_posts_ensure(): void
{
    static $done = false;
    if ($done) return;
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(80) NOT NULL,
        category VARCHAR(20) NOT NULL DEFAULT 'event',
        label_ar VARCHAR(120) NOT NULL DEFAULT '',
        label_en VARCHAR(120) NOT NULL DEFAULT '',
        title_ar VARCHAR(300) NOT NULL DEFAULT '',
        title_en VARCHAR(300) NOT NULL DEFAULT '',
        text_ar MEDIUMTEXT NULL,
        text_en MEDIUMTEXT NULL,
        images MEDIUMTEXT NULL,
        sort INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_post_slug (slug),
        KEY idx_post_sort (active, sort)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
    if (setting('posts_seeded', '') === '') {
        $count = (int)q_val('SELECT COUNT(*) FROM posts');
        if ($count === 0 && false) { // CSR forum: no inherited IDEF posts
            $st = $pdo->prepare('INSERT IGNORE INTO posts (slug, category, label_ar, label_en, title_ar, title_en, text_ar, text_en, images, sort) VALUES (?,?,?,?,?,?,?,?,?,?)');
            foreach (scf_posts_json()['posts'] as $i => $p) {
                $st->execute([
                    $p['slug'], $p['category'] ?? 'event', $p['label']['ar'] ?? '', $p['label']['en'] ?? '',
                    $p['title']['ar'] ?? '', $p['title']['en'] ?? '', $p['text']['ar'] ?? '', $p['text']['en'] ?? '',
                    json_encode($p['images'] ?? [], JSON_UNESCAPED_SLASHES), ($i + 1) * 10,
                ]);
            }
        }
        setting_set('posts_seeded', date('c'));
    }
}

/** شكل المنشور كما تستخدمه قوالب الواجهة */
function scf_post_shape(array $r): array
{
    $imgs = json_decode((string)$r['images'], true);
    return [
        'id' => (int)$r['id'], 'slug' => $r['slug'], 'category' => $r['category'], 'active' => (int)$r['active'],
        'label' => ['ar' => $r['label_ar'], 'en' => $r['label_en']],
        'title' => ['ar' => $r['title_ar'], 'en' => $r['title_en']],
        'text' => ['ar' => (string)$r['text_ar'], 'en' => (string)$r['text_en']],
        'images' => is_array($imgs) ? $imgs : [],
    ];
}

function scf_posts_list(bool $activeOnly = true): array
{
    scf_posts_ensure();
    $rows = q_all('SELECT * FROM posts ' . ($activeOnly ? 'WHERE active = 1 ' : '') . 'ORDER BY sort, id');
    return array_map('scf_post_shape', $rows);
}

/** نص رسالة الشكر (قابل للتعديل من لوحة التحكم) */
function scf_thanks_text(): array
{
    $json = scf_posts_json()['thanks'] ?? ['ar' => '', 'en' => ''];
    $ar = setting('recap_thanks_ar', '');
    $en = setting('recap_thanks_en', '');
    return ['ar' => $ar !== '' ? $ar : (string)($json['ar'] ?? ''), 'en' => $en !== '' ? $en : (string)($json['en'] ?? '')];
}

/** رابط وسائط: الملفات المرفوعة (uploads/…) أو أصول الموقع (assets/…) */
function scf_media_url(string $path): string
{
    if (strpos($path, 'uploads/') === 0) return base_url() . '/' . $path;
    return asset($path);
}

function scf_slugify(string $s): string
{
    $s = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? substr($s, 0, 60) : 'post-' . date('Ymd-His');
}

/**
 * يعالج صورة مرفوعة: فحص حقيقي + إعادة ترميز (يزيل أي حمولة) + نسخة كبيرة ومصغّرة.
 * @return array{file:string,thumb:string,width:int,height:int} مسارات نسبية تبدأ بـ uploads/
 */
function scf_image_process(string $tmp, string $subdir, int $maxW = 1800, int $thumbW = 760, bool $keepPng = false): array
{
    $info = @getimagesize($tmp);
    if ($info === false) throw new RuntimeException('الملف ليس صورة صالحة');
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($tmp); break;
        case 'image/png': $src = @imagecreatefrompng($tmp); break;
        case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false; break;
        case 'image/gif': $src = @imagecreatefromgif($tmp); break;
        default: throw new RuntimeException('نوع الصورة غير مدعوم (JPG / PNG / WebP)');
    }
    if (!$src) throw new RuntimeException('تعذر معالجة الصورة');
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3) $src = imagerotate($src, 180, 0);
        elseif ($o === 6) $src = imagerotate($src, -90, 0);
        elseif ($o === 8) $src = imagerotate($src, 90, 0);
    }
    $alpha = $keepPng || $mime === 'image/png' || $mime === 'image/gif';
    $webp = function_exists('imagewebp');
    $ext = $webp ? 'webp' : ($alpha ? 'png' : 'jpg');
    $dir = SCF_UPLOADS . '/' . trim($subdir, '/');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('تعذر إنشاء مجلد الصور');
    $base = date('Ymd') . '-' . bin2hex(random_bytes(6));
    $save = function ($img, int $w, string $name) use ($src, $alpha, $ext, $dir) {
        $sw = imagesx($src); $sh = imagesy($src);
        $w = min($w, $sw);
        $h = (int)round($sh * $w / $sw);
        $dst = imagecreatetruecolor($w, $h);
        if ($alpha) { imagealphablending($dst, false); imagesavealpha($dst, true); $tr = imagecolorallocatealpha($dst, 0, 0, 0, 127); imagefilledrectangle($dst, 0, 0, $w, $h, $tr); }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        $path = $dir . '/' . $name . '.' . $ext;
        $ok = $ext === 'webp' ? imagewebp($dst, $path, 82) : ($ext === 'png' ? imagepng($dst, $path, 8) : imagejpeg($dst, $path, 86));
        imagedestroy($dst);
        if (!$ok) throw new RuntimeException('تعذر حفظ الصورة');
        return [$w, $h];
    };
    [$w, $h] = $save($src, $maxW, $base);
    $rel = 'uploads/' . trim($subdir, '/') . '/' . $base . '.' . $ext;
    $thumb = $rel;
    if ($thumbW > 0 && imagesx($src) > $thumbW) {
        $save($src, $thumbW, $base . '-thumb');
        $thumb = 'uploads/' . trim($subdir, '/') . '/' . $base . '-thumb.' . $ext;
    }
    imagedestroy($src);
    return ['file' => $rel, 'thumb' => $thumb, 'width' => $w, 'height' => $h];
}

/** يحذف ملف صورة مرفوعة (فقط داخل uploads/) */
function scf_media_delete(string $rel): void
{
    if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false) return;
    $path = SCF_ROOT . '/' . $rel;
    if (is_file($path)) @unlink($path);
}
