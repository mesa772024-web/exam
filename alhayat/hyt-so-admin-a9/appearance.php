<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin-layout.php';
$admin = require_admin('admin');
$admins = db()->query('SELECT id,username,display_name,role,active,last_login_at FROM admin_users ORDER BY id')->fetchAll();
$audits = db()->query('SELECT actor_name,action,entity_type,entity_id,created_at FROM audit_logs ORDER BY id DESC LIMIT 20')->fetchAll();
render_admin_start($admin, 'الهوية والإعدادات', 'appearance');
?>
<div class="admin-heading"><div><span>BRAND & ACCESS</span><h1>الهوية، الأدوات، والمستخدمون</h1><p>تحكم في الألوان والشعار وشعارات الشركاء وصلاحيات فريق الإدارة.</p></div></div>
<div class="admin-grid">
  <section class="admin-card wide"><div class="card-title"><div><span>VISUAL IDENTITY</span><h2>الهوية المرئية</h2></div><em>محفوظة فوراً</em></div><form class="admin-form" method="post" action="<?= h(admin_url('api.php')) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_appearance"><input type="hidden" name="fallback" value="appearance.php"><label class="admin-field"><span>الاسم العربي</span><input name="siteNameAr" value="<?= h(setting('siteNameAr')) ?>" required></label><label class="admin-field"><span>الاسم الإنكليزي</span><input name="siteNameEn" value="<?= h(setting('siteNameEn')) ?>" dir="ltr" required></label><label class="admin-field"><span>اللون الرئيسي</span><input name="primaryColor" type="color" value="<?= h(setting('primaryColor','#0f6b48')) ?>"></label><label class="admin-field"><span>اللون المميز</span><input name="accentColor" type="color" value="<?= h(setting('accentColor','#b5344d')) ?>"></label><label class="admin-field"><span>لون الخلفية</span><input name="surfaceColor" type="color" value="<?= h(setting('surfaceColor','#f2f7f3')) ?>"></label><label class="admin-field"><span>الخط</span><select name="fontFamily"><option value="Hayat Sans" <?= setting('fontFamily')==='Hayat Sans'?'selected':'' ?>>Hayat Sans</option><option value="IBM Plex Sans" <?= setting('fontFamily')==='IBM Plex Sans'?'selected':'' ?>>IBM Plex Sans</option><option value="System" <?= setting('fontFamily')==='System'?'selected':'' ?>>System</option></select></label><label class="admin-field full"><span>لغات الموقع</span><select name="siteLanguages"><option value="both" <?= setting('siteLanguages','both')==='both'?'selected':'' ?>>العربية والإنكليزية</option><option value="en" <?= setting('siteLanguages','both')==='en'?'selected':'' ?>>الإنكليزية فقط</option></select><small>عند اختيار الإنكليزية فقط تختفي أداة تبديل اللغة ويُفتح الموقع بالإنكليزية دائماً.</small></label><div class="admin-form-actions"><button class="admin-primary" type="submit">حفظ الإعدادات</button></div></form></section>
  <section class="admin-card narrow"><div class="card-title"><div><span>LOGO</span><h2>تبديل الشعار</h2></div><em>PNG/JPG/WebP</em></div><div style="background:#f3f7f4;border-radius:14px;padding:26px;display:grid;place-items:center;margin-bottom:18px"><img style="max-width:180px;max-height:90px" src="<?= h(site_url(setting('logoPath'))) ?>" alt="الشعار"></div><form class="admin-form" method="post" enctype="multipart/form-data" action="<?= h(admin_url('api.php')) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="upload_logo"><input type="hidden" name="fallback" value="appearance.php"><label class="admin-field full"><span>صورة حقيقية أقل من 4MB</span><input name="logo" type="file" accept="image/png,image/jpeg,image/webp" required></label><div class="admin-form-actions"><button class="admin-primary" type="submit">رفع واعتماد</button></div></form></section>
  <?php
  $partnerList = partner_records();
  $partnerVisible = count(array_filter($partnerList, static fn(array $p): bool => $p['status'] === 'visible'));
  $partnerHidden = static function (string $action, string $id = ''): void {
      ?><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="<?= h($action) ?>"><input type="hidden" name="fallback" value="appearance.php#partners"><input type="hidden" name="partner_id" value="<?= h($id) ?>"><?php
  };
  ?>
  <section class="admin-card full" id="partners"><div class="card-title"><div><span>PARTNER LOGOS</span><h2>شعارات الشركاء</h2></div><em><?= $partnerVisible ?> ظاهر · <?= count($partnerList) - $partnerVisible ?> مؤرشف</em></div>
    <p class="partner-admin-note">تظهر الشعارات في الصفحة الرئيسية وصفحة الشركاء بنفس الترتيب هنا. <b>الأرشفة</b> تُخفي الشريك من الموقع مع الاحتفاظ به هنا لإعادته لاحقاً، و<b>الحذف</b> يزيله نهائياً.</p>
    <div class="partner-admin-grid">
      <?php foreach ($partnerList as $position => $partner): $archived = $partner['status'] === 'archived'; ?>
      <article class="partner-admin-card<?= $archived ? ' is-archived' : '' ?>">
        <div class="partner-admin-logo"><?php if ($partner['logo']): ?><img src="<?= h(site_url($partner['logo'])) ?>" alt="<?= h($partner['name']) ?>"><?php else: ?><strong><?= h($partner['name']) ?></strong><?php endif; ?><span class="status-pill<?= $archived ? ' muted' : '' ?>"><?= $archived ? 'مؤرشف' : 'ظاهر' ?></span><b class="partner-admin-order"><?= str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT) ?></b></div>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= h(admin_url('api.php')) ?>"><?php $partnerHidden('save_partner', $partner['id']); ?>
          <label class="admin-field"><span>اسم الشريك</span><input name="name" value="<?= h($partner['name']) ?>" maxlength="80" dir="auto" required></label>
          <label class="admin-field"><span>سنة الشراكة</span><input name="since" value="<?= h($partner['since']) ?>" maxlength="20" dir="ltr"></label>
          <label class="admin-field full"><span><?= $partner['logo'] ? 'تبديل الشعار' : 'رفع شعار' ?> — PNG / JPG / WebP</span><input name="logo" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <?php if ($partner['logo']): ?><label class="admin-field full partner-admin-check"><input type="checkbox" name="remove_logo" value="1"><span>إزالة الشعار وإظهار الاسم نصاً</span></label><?php endif; ?>
          <div class="admin-form-actions"><button class="admin-primary" type="submit">حفظ</button></div>
        </form>
        <div class="partner-admin-actions">
          <form method="post" action="<?= h(admin_url('api.php')) ?>"><?php $partnerHidden('partner_move', $partner['id']); ?><input type="hidden" name="direction" value="up"><button class="admin-secondary" type="submit" title="تقديم" aria-label="تقديم" <?= $position === 0 ? 'disabled' : '' ?>>▲</button></form>
          <form method="post" action="<?= h(admin_url('api.php')) ?>"><?php $partnerHidden('partner_move', $partner['id']); ?><input type="hidden" name="direction" value="down"><button class="admin-secondary" type="submit" title="تأخير" aria-label="تأخير" <?= $position === count($partnerList) - 1 ? 'disabled' : '' ?>>▼</button></form>
          <form method="post" action="<?= h(admin_url('api.php')) ?>"><?php $partnerHidden('partner_status', $partner['id']); ?><button class="admin-secondary" type="submit"><?= $archived ? 'إظهار على الموقع' : 'أرشفة (إخفاء)' ?></button></form>
          <form method="post" action="<?= h(admin_url('api.php')) ?>"><?php $partnerHidden('partner_delete', $partner['id']); ?><button class="admin-danger" type="submit" data-confirm="حذف «<?= h($partner['name']) ?>» نهائياً؟ للإخفاء المؤقت استخدم الأرشفة.">حذف</button></form>
        </div>
      </article>
      <?php endforeach; ?>
      <article class="partner-admin-card is-new">
        <div class="partner-admin-logo"><strong>＋</strong></div>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= h(admin_url('api.php')) ?>"><?php $partnerHidden('save_partner'); ?>
          <label class="admin-field"><span>اسم الشريك</span><input name="name" maxlength="80" dir="auto" required></label>
          <label class="admin-field"><span>سنة الشراكة</span><input name="since" maxlength="20" dir="ltr" placeholder="<?= date('Y') ?>"></label>
          <label class="admin-field full"><span>الشعار — PNG / JPG / WebP (اختياري)</span><input name="logo" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <div class="admin-form-actions"><button class="admin-primary" type="submit">إضافة شريك</button></div>
        </form>
      </article>
    </div>
  </section>
  <section class="admin-card wide" id="users"><div class="card-title"><div><span>ACCESS CONTROL</span><h2>حسابات الإدارة</h2></div><em>5 مستويات</em></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>الاسم</th><th>الدخول</th><th>الصلاحية</th><th>آخر دخول</th></tr></thead><tbody><?php foreach($admins as $user): ?><tr><td><?= h($user['display_name']) ?></td><td dir="ltr"><?= h($user['username']) ?></td><td><span class="status-pill"><?= h(role_label($user['role'])) ?></span></td><td dir="ltr"><?= h($user['last_login_at']??'—') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  <section class="admin-card narrow"><div class="card-title"><div><span>SUPER ADMIN</span><h2>إضافة مستخدم</h2></div><em><?= admin_can($admin,'super_admin')?'متاح':'محمي' ?></em></div><form class="admin-form" method="post" action="<?= h(admin_url('api.php')) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create_admin"><input type="hidden" name="fallback" value="appearance.php#users"><label class="admin-field full"><span>الاسم</span><input name="display_name" required <?= admin_can($admin,'super_admin')?'':'disabled' ?>></label><label class="admin-field full"><span>اسم الدخول</span><input name="username" pattern="[A-Za-z0-9._-]{3,40}" dir="ltr" required <?= admin_can($admin,'super_admin')?'':'disabled' ?>></label><label class="admin-field full"><span>كلمة المرور — 8 خانات على الأقل</span><input name="password" type="password" minlength="8" dir="ltr" required <?= admin_can($admin,'super_admin')?'':'disabled' ?>></label><label class="admin-field full"><span>المستوى</span><select name="role" <?= admin_can($admin,'super_admin')?'':'disabled' ?>><?php foreach(ROLE_LEVELS as $role=>$level): ?><option value="<?= h($role) ?>"><?= h(role_label($role)) ?></option><?php endforeach; ?></select></label><div class="admin-form-actions"><button class="admin-primary" type="submit" <?= admin_can($admin,'super_admin')?'':'disabled' ?>>إنشاء الحساب</button></div></form></section>
  <section class="admin-card full"><div class="card-title"><div><span>AUDIT TRAIL</span><h2>آخر التغييرات</h2></div><em>غير قابل للتحرير</em></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>المستخدم</th><th>الإجراء</th><th>العنصر</th><th>التاريخ</th></tr></thead><tbody><?php foreach($audits as $audit): ?><tr><td><?= h($audit['actor_name']) ?></td><td dir="ltr"><?= h($audit['action']) ?></td><td><?= h($audit['entity_type'].' / '.$audit['entity_id']) ?></td><td dir="ltr"><?= h($audit['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
  <section class="admin-card full"><div class="card-title"><div><span>DEVELOPER GUIDE</span><h2>دليل التطوير · Skill.md</h2></div><em>Markdown</em></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="admin-primary" href="<?= h(admin_url('skill.php')) ?>">⬇ تحميل Skill.md</a></div>
  </section>
<?php render_admin_end(); ?>
