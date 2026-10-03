<?php
declare(strict_types=1);

function require_zip_support(): void
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('امتداد ZipArchive غير متوفر على الخادم.');
}

function create_files_backup(): string
{
    require_zip_support();
    $name = 'al-hayat-files-' . gmdate('Ymd-His') . '.zip';
    $destination = BACKUP_PATH . DIRECTORY_SEPARATOR . $name;
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('تعذر إنشاء ملف النسخة الاحتياطية.');
    $root = realpath(APP_ROOT);
    if (!$root) throw new RuntimeException('مسار الموقع غير صالح.');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $path = $file->getRealPath();
        if (!$path) continue;
        $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
        if (str_starts_with($relative, 'storage/') || str_starts_with($relative, 'source-materials/')) continue;
        $zip->addFile($path, $relative);
    }
    $zip->close();
    @chmod($destination, 0640);
    return $destination;
}

function create_database_backup(): string
{
    $destination = BACKUP_PATH . DIRECTORY_SEPARATOR . 'al-hayat-db-' . gmdate('Ymd-His') . '.sql';
    $stream = fopen($destination, 'xb');
    if (!$stream) throw new RuntimeException('تعذر إنشاء نسخة قاعدة البيانات.');
    $pdo = db();
    fwrite($stream, "-- BAGHDAD AL HAYAT MYSQL BACKUP V2\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach (database_backup_tables() as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        if (!$create || !isset($create[1])) continue;
        fwrite($stream, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n");
        $rows = $pdo->query('SELECT * FROM `' . $table . '`');
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
            $values = array_map(static fn(mixed $value): string => $value === null ? 'NULL' : (string) $pdo->quote((string) $value), array_values($row));
            fwrite($stream, 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
        }
    }
    fwrite($stream, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($stream);
    @chmod($destination, 0640);
    return $destination;
}

function verify_and_stage_database_restore(array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new RuntimeException('لم يكتمل رفع قاعدة البيانات.');
    if ((int) ($file['size'] ?? 0) < 100 || (int) $file['size'] > 250_000_000) throw new RuntimeException('حجم قاعدة البيانات غير مسموح.');
    $tmp = (string) $file['tmp_name'];
    $sql = file_get_contents($tmp);
    if (!is_string($sql) || !str_starts_with($sql, '-- BAGHDAD AL HAYAT MYSQL BACKUP V2')) throw new RuntimeException('الملف ليس نسخة MySQL صادرة من هذا النظام.');
    $statements = split_mysql_backup_statements($sql);
    if (count($statements) < 10 || count($statements) > 2_000_000) throw new RuntimeException('محتوى نسخة قاعدة البيانات غير مكتمل أو كبير جداً.');
    $allowed = array_flip(database_backup_tables());
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($statements as $statement) {
            $normalized = ltrim($statement);
            if ($normalized === '' || str_starts_with($normalized, '--')) continue;
            if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i', $normalized)) {
                $pdo->exec($normalized);
                continue;
            }
            if (!preg_match('/^(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`([a-z_]+)`/i', $normalized, $match) || !isset($allowed[strtolower($match[1])])) {
                throw new RuntimeException('تحتوي النسخة على أمر غير مسموح.');
            }
            $pdo->exec($statement);
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    foreach (['settings', 'admin_users', 'audit_logs'] as $required) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($required))->fetchColumn() !== 1) throw new RuntimeException('النسخة لا تحتوي جداول النظام المطلوبة.');
    }
}

function database_backup_tables(): array
{
    return ['settings','content_overrides','registrations','contact_messages','admin_users','audit_logs','rate_limits','pages','page_translations','content_blocks','content_block_translations','element_styles','media_library','visitors','conversations','bookings','conversation_messages','attachments','visit_events','security_events','database_migrations','update_history'];
}

function split_mysql_backup_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $escaped = false;
    $length = strlen($sql);
    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];
        if ($escaped) {
            $buffer .= $character;
            $escaped = false;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $character;
            if ($character === '\\') $escaped = true;
            elseif ($character === $quote) {
                if ($index + 1 < $length && $sql[$index + 1] === $quote) $buffer .= $sql[++$index];
                else $quote = null;
            }
            continue;
        }
        if ($character === "'" || $character === '"' || $character === '`') {
            $quote = $character;
            $buffer .= $character;
            continue;
        }
        if ($character === ';') {
            $statement = trim(preg_replace('/^--[^\r\n]*(?:\r?\n)?/', '', ltrim($buffer)) ?? '');
            if ($statement !== '') $statements[] = $statement;
            $buffer = '';
            continue;
        }
        $buffer .= $character;
    }
    if (trim($buffer) !== '') throw new RuntimeException('نهاية ملف النسخة الاحتياطية غير صحيحة.');
    return $statements;
}

function apply_update_package(array $file, array $admin): string
{
    require_zip_support();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new RuntimeException('لم يكتمل رفع حزمة التحديث.');
    if ((int) ($file['size'] ?? 0) < 100 || (int) $file['size'] > 150_000_000) throw new RuntimeException('حجم حزمة التحديث غير مسموح.');
    $zip = new ZipArchive();
    if ($zip->open((string) $file['tmp_name']) !== true) throw new RuntimeException('ملف ZIP غير صالح.');
    if ($zip->numFiles > 5000) { $zip->close(); throw new RuntimeException('حزمة التحديث تحتوي ملفات كثيرة جداً.'); }
    $total = 0;
    for ($i=0; $i<$zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
        $total += (int) ($stat['size'] ?? 0);
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/^[A-Za-z]:/', $name)) { $zip->close(); throw new RuntimeException('تحتوي الحزمة مساراً غير آمن.'); }
    }
    if ($total > 600_000_000) { $zip->close(); throw new RuntimeException('الحجم بعد فك الضغط أكبر من الحد الآمن.'); }
    $manifestRaw = $zip->getFromName('update.json');
    if (!is_string($manifestRaw)) { $zip->close(); throw new RuntimeException('ملف update.json مطلوب داخل الحزمة.'); }
    $manifest = json_decode($manifestRaw, true);
    if (!is_array($manifest) || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', (string) ($manifest['version'] ?? '')) || !is_array($manifest['files'] ?? null)) { $zip->close(); throw new RuntimeException('بيانات حزمة التحديث غير صحيحة.'); }
    $stage = UPDATE_PATH . DIRECTORY_SEPARATOR . 'stage-' . bin2hex(random_bytes(8));
    mkdir($stage, 0700, true);
    if (!$zip->extractTo($stage)) { $zip->close(); remove_directory_tree($stage); throw new RuntimeException('تعذر فك حزمة التحديث.'); }
    $zip->close();
    $protected = ['storage/','uploads/','source-materials/','.env','update.json'];
    foreach ($manifest['files'] as $relative => $expected) {
        $relative = str_replace('\\','/',clean_text($relative,500));
        if ($relative === '' || str_starts_with($relative,'/') || preg_match('#(^|/)\.\.(/|$)#',$relative)) { remove_directory_tree($stage); throw new RuntimeException('مسار ملف تحديث غير آمن.'); }
        foreach ($protected as $prefix) if ($relative === $prefix || str_starts_with($relative,$prefix)) { remove_directory_tree($stage); throw new RuntimeException('الحزمة تحاول استبدال بيانات محمية.'); }
        $source = realpath($stage . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$relative));
        $stageRoot = realpath($stage);
        if (!$source || !$stageRoot || !str_starts_with($source,$stageRoot.DIRECTORY_SEPARATOR) || !is_file($source) || !hash_equals(strtolower((string)$expected),hash_file('sha256',$source))) { remove_directory_tree($stage); throw new RuntimeException('فشل فحص سلامة ملف التحديث: '.$relative); }
    }
    $backup = create_files_backup();
    foreach (array_keys($manifest['files']) as $relative) {
        $relative = str_replace('\\','/',clean_text($relative,500));
        $source = $stage . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$relative);
        $destination = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if (!is_dir(dirname($destination))) mkdir(dirname($destination),0755,true);
        if (!copy($source,$destination)) { remove_directory_tree($stage); throw new RuntimeException('تعذر استبدال ملف أثناء التحديث: '.$relative); }
    }
    remove_directory_tree($stage);
    save_setting('site_version',(string)$manifest['version'],(int)$admin['id']);
    db()->prepare("INSERT INTO update_history(version,status,details_json,applied_by)VALUES(:version,'applied',:details,:admin)")->execute(['version'=>$manifest['version'],'details'=>json_encode(['files'=>count($manifest['files']),'backup'=>basename($backup)]),'admin'=>$admin['id']]);
    return (string) $manifest['version'];
}

function remove_directory_tree(string $path): void
{
    $root = realpath(UPDATE_PATH);
    $resolved = realpath($path);
    if (!$root || !$resolved || !str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
    rmdir($resolved);
}
