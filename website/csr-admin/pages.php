<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'create') {
        $slug = strtolower(preg_replace('/[^a-z0-9\-_]/', '', str_replace(' ', '-', (string)($_POST['slug'] ?? ''))));
        $titleAr = clean_text($_POST['title_ar'] ?? '', 200);
        $titleEn = clean_text($_POST['title_en'] ?? '', 200);
        if ($slug === '' || strlen($slug) < 2) json_out(['ok' => false, 'msg' => 'أدخل معرّف الرابط (أحرف لاتينية وأرقام)'], 422);
        if ($titleAr === '' && $titleEn === '') json_out(['ok' => false, 'msg' => 'أدخل العنوان'], 422);
        if (q_one('SELECT id FROM pages WHERE slug = ?', [$slug]) !== null) json_out(['ok' => false, 'msg' => 'المعرّف مستخدم مسبقاً'], 409);
        q('INSERT INTO pages (slug, title_ar, title_en, blocks, published) VALUES (?,?,?,?,0)', [$slug, $titleAr, $titleEn, '[]']);
        admin_audit('page_create', $slug);
        json_out(['ok' => true, 'id' => (int)db()->lastInsertId()]);
    }
    if ($act === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM pages WHERE id = ?', [$id]);
        admin_audit('page_del', 'id=' . $id);
        json_out(['ok' => true]);
    }
    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $f = $_POST['field'] === 'in_nav' ? 'in_nav' : 'published';
        q("UPDATE pages SET $f = 1 - $f WHERE id = ?", [$id]);
        admin_audit('page_toggle', $f . ' id=' . $id);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$rows = q_all('SELECT * FROM pages ORDER BY sort, id');
admin_header('بناء الصفحات', 'pages');
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= count($rows) ?> صفحة</h2>
    <button class="btn-p" id="newPageBtn">+ صفحة جديدة</button>
  </div>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>العنوان</th><th>الرابط</th><th>الحالة</th><th>في القائمة</th><th>آخر تعديل</th><th style="width:220px"></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr data-id="<?= (int)$r['id'] ?>">
        <td><b><?= e($r['title_ar'] ?: $r['title_en']) ?></b></td>
        <td dir="ltr" class="mono">page.php?s=<?= e($r['slug']) ?></td>
        <td><button class="badge-btn <?= $r['published'] ? 'b-approved' : 'b-pending' ?>" data-toggle="published"><?= $r['published'] ? 'منشورة' : 'مسودة' ?></button></td>
        <td><button class="badge-btn <?= $r['in_nav'] ? 'b-approved' : 'b-gray' ?>" data-toggle="in_nav"><?= $r['in_nav'] ? 'ظاهرة' : 'مخفية' ?></button></td>
        <td class="mono"><?= e(date('m/d H:i', strtotime($r['updated_at']))) ?></td>
        <td class="row-actions">
          <a class="act act-ok" href="page-edit.php?id=<?= (int)$r['id'] ?>"><?= icon('edit') ?> المحرر</a>
          <a class="act" href="../page.php?s=<?= e($r['slug']) ?>" target="_blank">↗ معاينة</a>
          <button class="act act-del" data-page-del><?= icon('trash') ?></button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">لا صفحات — أنشئ صفحة جديدة (مثال: التوصيات، القاعة، الأسئلة الشائعة)</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal" id="pageModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>صفحة جديدة</h3>
    <form id="pageForm" class="modal-form">
      <label>العنوان (عربي)<input name="title_ar" maxlength="200"></label>
      <label>Title (EN)<input name="title_en" maxlength="200" dir="ltr"></label>
      <label>معرّف الرابط (لاتيني)<input name="slug" maxlength="80" dir="ltr" placeholder="recommendations"></label>
      <button class="btn-p btn-block">إنشاء وفتح المحرر</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
