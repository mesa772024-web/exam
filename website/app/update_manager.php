<?php
/**
 * مدير التحديثات المباشرة (بدون توقيع).
 *
 * الحزمة = ملف ZIP يحوي ملفات الموقع بمساراتها النسبية من جذر الموقع (مثل app/helpers.php).
 * ملف update.json اختياري: {"version","title","notes","delete":[…],"migrate":true}.
 * الدليل الكامل لكتابة التحديثات: app/docs/Skill.md (يُنزَّل من صفحة التحديثات).
 *
 * الأمان: للمدير الكامل فقط + CSRF. فحص المسارات (لا storage/ ولا uploads/)، والامتدادات،
 * وفحص صياغة كل ملف PHP قبل التثبيت، ونسخة رجوع تلقائية لكل ملف يُستبدل أو يُحذف.
 */
require_once __DIR__ . '/helpers.php';

const SCF_UPDATE_MAX_FILES = 3000;
const SCF_UPDATE_MAX_FILE = 62914560;   // 60MB لكل ملف
const SCF_UPDATE_MAX_TOTAL = 209715200; // 200MB بعد فك الضغط

function scf_update_current_version(): string
{
    return setting('code_release', setting('app_release', '2026.08.09-r6'));
}

function scf_update_normalize_path(string $path): string
{
    if (strpos($path, "\0") !== false) throw new RuntimeException('مسار غير صالح داخل الحزمة');
    $path = str_replace('\\', '/', trim($path));
    $path = (string)preg_replace('#/+#', '/', $path);
    $path = trim($path, '/');
    if ($path === '' || strlen($path) > 240 || preg_match('#(^|/)\.\.(/|$)#', $path) || preg_match('/^[A-Za-z]:/', $path)) {
        throw new RuntimeException('مسار غير صالح داخل الحزمة: ' . $path);
    }
    if (!preg_match('#^[A-Za-z0-9._@-]+(?:/[A-Za-z0-9._@-]+)*$#', $path)) throw new RuntimeException('اسم ملف غير صالح (حروف لاتينية وأرقام فقط): ' . $path);
    return $path;
}

/** ملفات مساعدة داخل الحزمة لا تُنسخ إلى الموقع */
function scf_update_is_meta(string $path): bool
{
    $base = strtolower(basename($path));
    if (strpos($path, '__MACOSX/') === 0 || in_array($base, ['.ds_store', 'thumbs.db'], true)) return true;
    return strpos($path, '/') === false && in_array($base, ['update.json', 'skill.md', 'readme.md', 'readme.txt', 'changelog.md'], true);
}

function scf_update_path_allowed(string $path): bool
{
    $lower = strtolower($path);
    foreach (['storage', 'uploads', '.git', '.claude', '.agents', '.codex', 'node_modules'] as $blocked) {
        if ($lower === $blocked || strpos($lower, $blocked . '/') === 0) return false;
    }
    if ($lower === '.env') return false;
    $ext = strtolower(pathinfo($lower, PATHINFO_EXTENSION));
    $okExt = ['php', 'js', 'css', 'json', 'html', 'htm', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'mp4', 'webm', 'txt', 'md', 'xml', 'map', 'pdf', 'sql'];
    return basename($lower) === '.htaccess' || in_array($ext, $okExt, true);
}

/** مسار الحزمة ← مسار الموقع (يدعم إعادة تسمية مجلد لوحة التحكم) */
function scf_update_map_path(string $path): string
{
    if (ADMIN_DIR !== 'sc-admin-x9' && strpos($path, 'sc-admin-x9/') === 0) return ADMIN_DIR . '/' . substr($path, 12);
    return $path;
}

/**
 * يقرأ الحزمة ويحللها دون لمس الموقع.
 * @return array{manifest:array,files:array,deletes:array,warnings:array,prefix:string}
 */
function scf_update_analyze(string $zipPath): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('إضافة ZIP غير مفعّلة على الخادم');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('ملف ZIP غير صالح');
    try {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($n === '' || substr($n, -1) === '/') continue;
            $names[$i] = $n;
        }
        if (!$names) throw new RuntimeException('الحزمة فارغة');
        if (count($names) > SCF_UPDATE_MAX_FILES) throw new RuntimeException('عدد الملفات كبير جداً');

        // مجلد جذري واحد يغلّف الملفات؟ (مثل update-2026/app/…) ← نزيله
        $prefix = '';
        $firsts = array_unique(array_map(function ($n) { return strpos($n, '/') !== false ? explode('/', $n)[0] : ''; }, array_filter($names, function ($n) { return strpos($n, '__MACOSX/') !== 0; })));
        $roots = ['app', 'api', 'assets', 'install', 'uploads', 'storage', 'sc-admin-x9', ADMIN_DIR];
        if (count($firsts) === 1 && reset($firsts) !== '' && !in_array(reset($firsts), $roots, true)) $prefix = reset($firsts) . '/';

        $manifest = [];
        $rawManifest = $zip->getFromName($prefix . 'update.json');
        if ($rawManifest !== false) {
            $manifest = json_decode($rawManifest, true);
            if (!is_array($manifest)) throw new RuntimeException('ملف update.json غير صالح (JSON)');
        }

        $files = []; $warnings = []; $total = 0;
        foreach ($names as $i => $n) {
            if ($prefix !== '' && strpos($n, $prefix) === 0) $n = substr($n, strlen($prefix));
            if (strpos($n, '__MACOSX/') === 0) continue;
            $path = scf_update_normalize_path($n);
            if (scf_update_is_meta($path)) continue;
            if (!scf_update_path_allowed($path)) throw new RuntimeException('غير مسموح بتحديث هذا الملف: ' . $path);
            $stat = $zip->statIndex($i);
            $size = (int)($stat['size'] ?? 0);
            if ($size > SCF_UPDATE_MAX_FILE) throw new RuntimeException('ملف كبير جداً: ' . $path);
            $total += $size;
            if ($total > SCF_UPDATE_MAX_TOTAL) throw new RuntimeException('الحزمة كبيرة جداً بعد فك الضغط');
            $target = scf_update_map_path($path);
            $content = $zip->getFromIndex($i);
            if ($content === false) throw new RuntimeException('تعذر قراءة: ' . $path);
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                try {
                    token_get_all($content, TOKEN_PARSE);
                } catch (Throwable $e) {
                    throw new RuntimeException('خطأ صياغة في ' . $path . ' (سطر ' . $e->getLine() . '): ' . $e->getMessage());
                }
                if (preg_match('/\b(match\s*\(|str_contains|str_starts_with|str_ends_with)\b|\?->/', $content)) $warnings[] = 'قد يحتاج PHP 8: ' . $path;
            }
            $abs = SCF_ROOT . '/' . $target;
            $status = !is_file($abs) ? 'new' : (sha1_file($abs) === sha1($content) ? 'same' : 'changed');
            $files[$target] = ['status' => $status, 'size' => $size, 'index' => $i];
            if ($target === 'app/config.php') $warnings[] = 'الحزمة تستبدل app/config.php — تأكد أن ADMIN_DIR صحيح';
            if ($target === '.htaccess') $warnings[] = 'الحزمة تستبدل .htaccess الرئيسي';
        }

        $deletes = [];
        foreach ((array)($manifest['delete'] ?? []) as $d) {
            $path = scf_update_map_path(scf_update_normalize_path((string)$d));
            if (!scf_update_path_allowed($path)) throw new RuntimeException('غير مسموح بحذف: ' . $path);
            if (is_file(SCF_ROOT . '/' . $path)) $deletes[] = $path;
        }
        if (!$files && !$deletes) throw new RuntimeException('لا توجد ملفات للتحديث داخل الحزمة');
        ksort($files);
        return ['manifest' => $manifest, 'files' => $files, 'deletes' => $deletes, 'warnings' => array_values(array_unique($warnings)), 'prefix' => $prefix];
    } finally {
        $zip->close();
    }
}

function scf_update_write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('تعذر إنشاء المجلد: ' . $dir);
    $tmp = $path . '.scf-new-' . bin2hex(random_bytes(5));
    if (file_put_contents($tmp, $content, LOCK_EX) !== strlen($content)) {
        @unlink($tmp);
        throw new RuntimeException('تعذر كتابة الملف: ' . basename($path));
    }
    @chmod($tmp, is_file($path) ? (fileperms($path) & 0777) : 0644);
    if (!@rename($tmp, $path)) {
        if (!@copy($tmp, $path)) { @unlink($tmp); throw new RuntimeException('تعذر استبدال الملف: ' . basename($path)); }
        @unlink($tmp);
    }
}

function scf_update_staging_dir(): string
{
    $dir = SCF_STORAGE . '/update-staging';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    foreach (glob($dir . '/*.zip') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);
    return $dir;
}

/** يثبّت حزمة محفوظة في مجلد الانتظار مع نسخة رجوع كاملة */
function scf_update_install(string $zipPath, string $packageName, int $adminId, bool $dbBackup = true): array
{
    $an = scf_update_analyze($zipPath);
    $manifest = $an['manifest'];
    $previous = scf_update_current_version();
    $version = clean_text($manifest['version'] ?? '', 60);
    if ($version === '') $version = date('Y.m.d-Hi');
    $backupRel = 'update-backups/' . date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9._-]/', '-', $version) . '-' . bin2hex(random_bytes(3));
    $backupRoot = SCF_STORAGE . '/' . $backupRel;
    if (!@mkdir($backupRoot . '/files', 0750, true) && !is_dir($backupRoot . '/files')) throw new RuntimeException('تعذر إنشاء نسخة الرجوع');

    if ($dbBackup || !empty($manifest['migrate'])) {
        require_once __DIR__ . '/backup.php';
        @file_put_contents($backupRoot . '/database.sql', backup_sql_dump(), LOCK_EX);
    }

    $touch = array_merge(array_keys(array_filter($an['files'], function ($f) { return $f['status'] !== 'same'; })), $an['deletes']);
    $state = [];
    foreach ($touch as $rel) {
        $abs = SCF_ROOT . '/' . $rel;
        $state[$rel] = is_file($abs);
        if ($state[$rel]) {
            $b = $backupRoot . '/files/' . $rel;
            if (!is_dir(dirname($b)) && !@mkdir(dirname($b), 0750, true) && !is_dir(dirname($b))) throw new RuntimeException('تعذر نسخ الملفات القديمة');
            if (!@copy($abs, $b)) throw new RuntimeException('تعذر نسخ الملف القديم: ' . $rel);
        }
    }
    file_put_contents($backupRoot . '/state.json', json_encode(['files' => $state, 'manifest' => $manifest, 'previous' => $previous], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('ملف ZIP غير صالح');
    $applied = [];
    try {
        foreach ($an['files'] as $rel => $f) {
            if ($f['status'] === 'same') continue;
            $content = $zip->getFromIndex($f['index']);
            if ($content === false) throw new RuntimeException('تعذر قراءة: ' . $rel);
            scf_update_write_file(SCF_ROOT . '/' . $rel, $content);
            $applied[] = $rel;
        }
        foreach ($an['deletes'] as $rel) { @unlink(SCF_ROOT . '/' . $rel); $applied[] = $rel; }
    } catch (Throwable $e) {
        foreach (array_reverse($applied) as $rel) {
            if (!empty($state[$rel])) @copy($backupRoot . '/files/' . $rel, SCF_ROOT . '/' . $rel);
            else @unlink(SCF_ROOT . '/' . $rel);
        }
        $zip->close();
        scf_update_log($version, $previous, $packageName, count($applied), $backupRel, 'failed', $adminId, $e->getMessage());
        throw $e;
    }
    $zip->close();
    if (function_exists('opcache_reset')) @opcache_reset();

    $migrated = 0;
    if (!empty($manifest['migrate'])) {
        require_once __DIR__ . '/migrate.php';
        $migrated = count(scf_migrate());
    }
    setting_set('code_release', $version);
    $changed = count(array_filter($an['files'], function ($f) { return $f['status'] !== 'same'; }));
    scf_update_log($version, $previous, $packageName, $changed + count($an['deletes']), $backupRel, 'installed', $adminId, clean_text((string)($manifest['title'] ?? $manifest['notes'] ?? ''), 500));
    return ['ok' => true, 'version' => $version, 'previous' => $previous, 'files' => $changed, 'deleted' => count($an['deletes']), 'migrated' => $migrated];
}

function scf_update_log(string $version, string $previous, string $package, int $count, string $backup, string $status, int $adminId, string $detail): void
{
    try {
        q('INSERT INTO system_updates (version, previous_version, package_name, file_count, backup_path, status, admin_id, detail) VALUES (?,?,?,?,?,?,?,?)',
            [$version, $previous, basename($package), $count, $backup, $status, $adminId, clean_text($detail, 500)]);
    } catch (Throwable $e) {}
}

/** يرجع الموقع إلى ما قبل تحديث معيّن (الملفات فقط) */
function scf_update_rollback(int $id, int $adminId): array
{
    $row = q_one('SELECT * FROM system_updates WHERE id = ?', [$id]);
    if (!$row || $row['status'] !== 'installed') throw new RuntimeException('لا يمكن الرجوع عن هذا التحديث');
    $root = SCF_STORAGE . '/' . $row['backup_path'];
    if (strpos((string)$row['backup_path'], 'update-backups/') !== 0 || !is_file($root . '/state.json')) throw new RuntimeException('نسخة الرجوع غير موجودة');
    $state = json_decode((string)file_get_contents($root . '/state.json'), true);
    $files = $state['files'] ?? $state;
    if (!is_array($files)) throw new RuntimeException('نسخة الرجوع تالفة');
    $n = 0;
    foreach ($files as $rel => $existed) {
        $rel = scf_update_normalize_path((string)$rel);
        $abs = SCF_ROOT . '/' . $rel;
        if ($existed) {
            if (is_file($root . '/files/' . $rel)) { scf_update_write_file($abs, (string)file_get_contents($root . '/files/' . $rel)); $n++; }
        } elseif (is_file($abs)) { @unlink($abs); $n++; }
    }
    if (function_exists('opcache_reset')) @opcache_reset();
    q("UPDATE system_updates SET status = 'rolled_back' WHERE id = ?", [$id]);
    if (!empty($row['previous_version'])) setting_set('code_release', (string)$row['previous_version']);
    scf_update_log((string)$row['previous_version'], (string)$row['version'], 'rollback #' . $id, $n, (string)$row['backup_path'], 'rollback', $adminId, 'رجوع عن ' . $row['version']);
    return ['ok' => true, 'files' => $n];
}

/** ملفات الموقع كاملة (بدون بيانات الاتصال والنسخ الاحتياطية) في ZIP مؤقت */
function scf_site_zip(bool $withUploads, string $extraSql = ''): string
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('إضافة ZIP غير مفعّلة على الخادم');
    @set_time_limit(600);
    $tmpDir = SCF_STORAGE . '/tmp';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0750, true);
    foreach (glob($tmpDir . '/*.zip') ?: [] as $old) if (filemtime($old) < time() - 3600) @unlink($old);
    $out = $tmpDir . '/site-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('تعذر إنشاء ملف ZIP');
    $root = SCF_ROOT;
    $skip = function (string $rel) use ($withUploads) {
        if (preg_match('#^(\.git|\.claude|node_modules)(/|$)#', $rel)) return true;
        if (strpos($rel, 'storage/') === 0) return !in_array($rel, ['storage/.htaccess'], true) && !preg_match('#^storage/(backups|logs)/\.keep$#', $rel);
        if (!$withUploads && strpos($rel, 'uploads/') === 0) return !preg_match('#/(\.htaccess|\.keep)$#', $rel);
        return false;
    };
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        if ($skip($rel)) continue;
        if ($f->isDir()) $zip->addEmptyDir($rel);
        elseif ($f->isFile()) $zip->addFile($f->getPathname(), $rel);
    }
    if ($extraSql !== '') $zip->addFromString('_backup/database.sql', $extraSql);
    $zip->close();
    return $out;
}

/** قالب حزمة تحديث فارغة */
function scf_update_template_zip(): string
{
    $tmpDir = SCF_STORAGE . '/tmp';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0750, true);
    $out = $tmpDir . '/update-template-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    $zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', json_encode([
        'version' => date('Y.m.d') . '-r1',
        'title' => 'وصف قصير للتحديث',
        'notes' => 'ما الذي تغيّر ولماذا',
        'delete' => [],
        'migrate' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('Skill.md', (string)@file_get_contents(__DIR__ . '/docs/Skill.md'));
    $zip->addFromString('README.txt', "ضع ملفات الموقع المعدّلة بمساراتها من جذر الموقع (مثل app/helpers.php أو assets/css/recap.css) بجانب update.json.
اقرأ Skill.md قبل كتابة أي تحديث.
");
    $zip->close();
    return $out;
}
