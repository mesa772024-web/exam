<div class="row g-3">
    <div class="col-lg-3">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">تنقل الأسئلة</div>
            <div class="card-body p-2">
                <div class="d-grid gap-2 exam-nav">
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                        <button class="btn btn-outline-secondary btn-sm">سؤال <?php echo $i; ?></button>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">اسم الامتحان</h5>
                    <small class="text-muted">المقرر: الطب الباطني | الزمن المتبقي: 20:00</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-warning">مراجعة</button>
                    <button class="btn btn-danger">إنهاء</button>
                </div>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span class="badge bg-primary fs-6">1</span>
                    <div class="flex-grow-1">
                        <p class="mb-2">سؤال مثالي يوضح كيفية عرض السؤال والخيارات مع إمكانية إظهار القيم المخبرية المرافقة أو الصورة.</p>
                        <div class="alert alert-info py-2 px-3">Lab Values: Hgb 11 g/dL, Hct 34%</div>
                        <div class="list-group">
                            <?php foreach (['A', 'B', 'C', 'D', 'E'] as $option): ?>
                                <label class="list-group-item d-flex align-items-center">
                                    <input type="radio" name="q1" class="form-check-input me-2">
                                    <span>الخيار <?php echo $option; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-outline-secondary btn-sm">شك</button>
                            <button class="btn btn-outline-primary btn-sm">حفظ والإنتقال</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
