<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title">إنشاء امتحان جديد</h5>
        <p class="text-muted">املأ الحقول التالية لتوليد امتحان مع رمز دخول.</p>
        <form class="row g-3">
            <div class="col-md-6">
                <label class="form-label">اسم الامتحان</label>
                <input type="text" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">المقرر</label>
                <input type="text" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">القسم</label>
                <select class="form-select"><option>الطب</option><option>الجراحة</option></select>
            </div>
            <div class="col-md-4">
                <label class="form-label">الفئة</label>
                <select class="form-select"><option>أطفال</option><option>كبار</option></select>
            </div>
            <div class="col-md-4">
                <label class="form-label">عدد الأسئلة</label>
                <input type="number" class="form-control" value="50">
            </div>
            <div class="col-md-4">
                <label class="form-label">الوقت بالدقائق</label>
                <input type="number" class="form-control" value="90">
            </div>
            <div class="col-md-4">
                <label class="form-label">تاريخ البدء</label>
                <input type="datetime-local" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">رمز الدخول</label>
                <input type="text" class="form-control" value="ABC123">
            </div>
            <div class="col-12">
                <label class="form-label">وصف قصير</label>
                <textarea class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary" type="reset">إلغاء</button>
                <button class="btn btn-primary" type="submit">حفظ الامتحان</button>
            </div>
        </form>
    </div>
</div>
