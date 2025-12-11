<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="card-title mb-0">قائمة الامتحانات</h5>
                <small class="text-muted">اختر امتحانك المناسب ثم استخدم رمز الدخول.</small>
            </div>
            <a class="btn btn-outline-secondary" href="?page=student_dashboard">عودة</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>المقرر</th>
                        <th>القسم</th>
                        <th>الأسئلة</th>
                        <th>الوقت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($exams as $exam): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($exam['name']); ?></td>
                        <td><?php echo htmlspecialchars($exam['course']); ?></td>
                        <td><?php echo htmlspecialchars($exam['department_id']); ?></td>
                        <td><?php echo htmlspecialchars($exam['number_of_questions']); ?></td>
                        <td><?php echo htmlspecialchars($exam['time_minutes']); ?> دقيقة</td>
                        <td><a class="btn btn-sm btn-primary" href="?page=token_exam&exam_id=<?php echo $exam['id']; ?>">رمز الدخول</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($exams)): ?>
                    <tr><td colspan="6" class="text-center text-muted">لا توجد امتحانات متاحة حالياً.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
