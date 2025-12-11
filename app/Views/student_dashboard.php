<div class="row">
    <div class="col-lg-3">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title">حساب الطالب</h5>
                <p class="mb-1">مرحباً بكم في لوحة الطالب. يمكن تعديل الفئة الدراسية أو تحديث البيانات من هنا.</p>
                <a class="btn btn-sm btn-primary" href="?page=student_settings">إعدادات الطالب</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title">الامتحانات المتاحة</h5>
                    <p class="mb-0 text-muted">اختر الامتحان المناسب ثم أدخل رمز الدخول عند بدء الامتحان.</p>
                </div>
                <a class="btn btn-outline-success" href="?page=exam_list">قائمة الامتحانات</a>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($exams as $exam): ?>
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($exam['name']); ?></h6>
                            <p class="text-muted small mb-2">مقرر: <?php echo htmlspecialchars($exam['course'] ?? ''); ?></p>
                            <p class="mb-2">عدد الأسئلة: <?php echo htmlspecialchars($exam['number_of_questions']); ?> | الزمن: <?php echo htmlspecialchars($exam['time_minutes']); ?> دقيقة</p>
                            <a class="btn btn-primary btn-sm" href="?page=token_exam&exam_id=<?php echo $exam['id']; ?>">دخول برمز</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($exams)): ?>
                <div class="col-12">
                    <div class="alert alert-info">لم يتم تسجيل أي امتحانات بعد.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
