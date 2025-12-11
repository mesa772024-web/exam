<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title">إدارة المستخدمين</h5>
        <p class="text-muted">أضف المشرفين أو الطلاب وحدد الصلاحيات.</p>
        <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>الاسم</th><th>البريد</th><th>الدور</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>مشرف</td><td>admin@example.com</td><td>Admin</td><td><button class="btn btn-sm btn-outline-secondary">تعديل</button></td></tr>
                    <tr><td>طالب</td><td>student@example.com</td><td>Student</td><td><button class="btn btn-sm btn-outline-secondary">تعديل</button></td></tr>
                </tbody>
            </table>
        </div>
        <form class="row g-3">
            <div class="col-md-4"><input class="form-control" placeholder="الاسم"></div>
            <div class="col-md-4"><input class="form-control" placeholder="البريد"></div>
            <div class="col-md-4">
                <select class="form-select"><option>Student</option><option>Teacher</option><option>Super Admin</option></select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">حفظ المستخدم</button>
            </div>
        </form>
    </div>
</div>
