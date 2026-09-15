<?php

use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceReviewController;
use App\Http\Controllers\Admin\BranchCatalogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchSwitchController;
use App\Http\Controllers\Admin\CashTransactionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeAssignmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeShiftPlanController;
use App\Http\Controllers\Admin\InventoryMovementController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoicePaymentController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PayrollAdjustmentController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\PayrollStatusController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\RiskFlagController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ShiftRequestController;
use App\Http\Controllers\Admin\ShiftScheduleController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\WorkShiftController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffAttendanceController;
use Illuminate\Support\Facades\Route;

/*
 * Kiểm tra tình trạng hệ thống cho công cụ giám sát.
 *
 * Nằm ngoài mọi middleware xác thực vì monitor không đăng nhập được; đổi lại
 * phản hồi chỉ nêu tên thành phần và có trả lời hay không, không mô tả nội bộ.
 */
Route::get('/health', HealthController::class)->name('health');

Route::get('/', HomeController::class)->name('home');
Route::post('/dat-lich', [BookingController::class, 'store'])->name('booking.store');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
     * Chấm công của nhân viên.
     *
     * Nằm ngoài nhóm admin vì đây là màn hình duy nhất mà nhân viên thường
     * thao tác, và nó chỉ tác động lên chính tài khoản đang đăng nhập.
     * Throttle để một nút bị bấm liên tục không tạo hàng loạt yêu cầu.
     */
    Route::middleware(['role:owner,manager,employee', 'branch.context'])->group(function (): void {
        Route::get('cham-cong', [StaffAttendanceController::class, 'index'])->name('attendance.board');
        Route::post('cham-cong/vao-ca', [StaffAttendanceController::class, 'checkIn'])
            ->middleware('throttle:12,1')->name('attendance.check-in');
        Route::post('cham-cong/ra-ca', [StaffAttendanceController::class, 'checkOut'])
            ->middleware('throttle:12,1')->name('attendance.check-out');
    });

    Route::prefix('admin')->as('admin.')->middleware(['role:owner,manager,employee', 'branch.context'])->group(function (): void {
        Route::post('branch-switch', BranchSwitchController::class)->name('branch.switch');

        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('services', ServiceController::class)->except('show', 'destroy');

        Route::resource('appointments', AppointmentController::class)->except('show', 'destroy');
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
            ->name('appointments.status');
        Route::post('appointments/{appointment}/convert-to-invoice', [AppointmentController::class, 'convertToInvoice'])
            ->name('appointments.convert-to-invoice');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/payment', [InvoicePaymentController::class, 'store'])->name('invoices.pay');
        Route::patch('invoices/{invoice}/bill-kpi', [InvoiceController::class, 'verifyBillKpi'])->name('invoices.bill-kpi');
        Route::delete('invoices/{invoice}/payment', [InvoicePaymentController::class, 'destroy'])->name('invoices.cancel');

        Route::resource('cash', CashTransactionController::class)
            ->parameters(['cash' => 'cash_transaction'])
            ->except('show');

        Route::resource('products', ProductController::class)->except('show', 'destroy');
        Route::resource('suppliers', SupplierController::class)->except('show', 'destroy');
        Route::resource('inventory', InventoryMovementController::class)->only('index', 'create', 'store');

        Route::resource('stock-transfers', StockTransferController::class)
            ->parameters(['stock-transfers' => 'stock_transfer'])
            ->only('index', 'create', 'store', 'show');
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete'])->name('stock-transfers.complete');
        Route::delete('stock-transfers/{stock_transfer}', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');

        // Đổi cấu hình GPS của chi nhánh là đổi công của người khác, nên nằm
        // cùng nhóm phải xác nhận lại mật khẩu.
        Route::resource('branches', BranchController::class)
            ->except('show', 'destroy')
            ->middlewareFor(['store', 'update'], 'password.confirm');
        Route::get('branches/{branch}/catalog', [BranchCatalogController::class, 'edit'])->name('branches.catalog.edit');
        Route::patch('branches/{branch}/catalog', [BranchCatalogController::class, 'update'])->name('branches.catalog.update');

        // Tạo và sửa nhân sự mang theo role, quyền nhạy cảm và trạng thái
        // hoạt động — đây chính là nơi leo thang đặc quyền xảy ra.
        Route::resource('employees', EmployeeController::class)
            ->except('show', 'destroy')
            ->middlewareFor(['store', 'update'], 'password.confirm');
        Route::post('employees/{employee}/assignments', [EmployeeAssignmentController::class, 'store'])->name('employees.assignments.store');
        Route::patch('employees/{employee}/assignments/{assignment}', [EmployeeAssignmentController::class, 'update'])->name('employees.assignments.update');

        Route::resource('work-shifts', WorkShiftController::class)->except('show', 'destroy');

        Route::get('shift-schedule', [ShiftScheduleController::class, 'index'])->name('shift-schedule.index');
        Route::post('shift-schedule', [ShiftScheduleController::class, 'store'])->name('shift-schedule.store');
        Route::delete('shift-schedule/{shift_assignment}', [ShiftScheduleController::class, 'destroy'])
            ->name('shift-schedule.destroy');

        Route::get('employee-shift-plans', [EmployeeShiftPlanController::class, 'index'])->name('employee-shift-plans.index');
        Route::post('employee-shift-plans/fixed-shifts', [EmployeeShiftPlanController::class, 'storeFixedShift'])
            ->name('employee-shift-plans.fixed-shifts.store');
        Route::post('employee-shift-plans/generate', [EmployeeShiftPlanController::class, 'generate'])
            ->name('employee-shift-plans.generate');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

        Route::get('shift-requests', [ShiftRequestController::class, 'index'])->name('shift-requests.index');
        Route::post('shift-requests/leave', [ShiftRequestController::class, 'storeLeave'])->name('shift-requests.leave.store');
        Route::post('shift-requests/swap', [ShiftRequestController::class, 'storeSwap'])->name('shift-requests.swap.store');
        Route::post('shift-requests/{shift_request}/cancel', [ShiftRequestController::class, 'cancel'])->name('shift-requests.cancel');
        Route::post('shift-requests/{shift_request}/respond', [ShiftRequestController::class, 'respond'])->name('shift-requests.respond');
        Route::post('shift-requests/{shift_request}/decide', [ShiftRequestController::class, 'decide'])->name('shift-requests.decide');
        Route::post('shift-requests/{shift_request}/replacement', [ShiftRequestController::class, 'assignReplacement'])->name('shift-requests.replacement.assign');

        Route::get('attendance/review', [AttendanceReviewController::class, 'index'])->name('attendance.review');
        Route::patch('attendance/{attendance}/overtime', [AttendanceReviewController::class, 'update'])
            ->name('attendance.overtime');

        Route::resource('attendance', AttendanceController::class)->except('show', 'create');

        Route::resource('payrolls', PayrollController::class)->except('edit', 'destroy');
        Route::post('payrolls/{payroll}/recalculate', [PayrollController::class, 'recalculate'])->name('payrolls.recalculate');
        Route::post('payrolls/{payroll}/finalize', [PayrollStatusController::class, 'finalize'])->name('payrolls.finalize');
        Route::post('payrolls/{payroll}/adjustments', [PayrollAdjustmentController::class, 'store'])->name('payrolls.adjustments.store');
        Route::delete('payrolls/{payroll}/adjustments/{adjustment}', [PayrollAdjustmentController::class, 'destroy'])->name('payrolls.adjustments.destroy');
        Route::post('payrolls/{payroll}/variance', [PayrollStatusController::class, 'approveVariance'])->name('payrolls.variance');

        /*
         * Thao tác đụng thẳng vào tiền và quyền: hỏi lại mật khẩu.
         *
         * Một phiên đang mở là toàn bộ phần thưởng — máy bỏ quên ở quầy hay
         * cookie bị lấy cắp là đủ để trả lương hoặc phát quyền. Hỏi lại mật
         * khẩu tốn của người thật hai giây, và tốn của người mượn phiên tất cả.
         */
        Route::middleware('password.confirm')->group(function (): void {
            Route::post('payrolls/{payroll}/payment', [PayrollStatusController::class, 'pay'])->name('payrolls.pay');
            Route::delete('payrolls/{payroll}', [PayrollStatusController::class, 'cancel'])->name('payrolls.cancel');
        });

        // Hàng đợi cảnh báo bất thường.
        Route::get('risk-flags', [RiskFlagController::class, 'index'])->name('risk-flags.index');
        Route::patch('risk-flags/{risk_flag}', [RiskFlagController::class, 'update'])->name('risk-flags.update');
        Route::patch('risk-flags/{risk_flag}/assign-employee', [RiskFlagController::class, 'assignEmployee'])
            ->name('risk-flags.assign-employee');

        Route::get('reports', ReportController::class)->name('reports.index');

        /*
         * Xuất dữ liệu có giới hạn tần suất.
         *
         * Mỗi file mang theo tiền và dữ liệu khách hàng, nên việc tải hàng loạt
         * liên tục là cách rút ruột cơ sở dữ liệu mà không cần khai thác lỗ hổng
         * nào. Ngưỡng đặt rộng hơn nhu cầu thật của một ngày làm việc.
         */
        Route::middleware('throttle:20,1')->group(function (): void {
            Route::get('reports/export/invoices', [ReportExportController::class, 'invoices'])->name('reports.export.invoices');
            Route::get('reports/export/cash', [ReportExportController::class, 'cash'])->name('reports.export.cash');
            Route::get('reports/export/inventory', [ReportExportController::class, 'inventory'])->name('reports.export.inventory');
            // Bảng lương là dữ liệu nhạy cảm nhất trong các bản xuất.
            Route::get('reports/export/payrolls', [ReportExportController::class, 'payrolls'])
                ->middleware('password.confirm')->name('reports.export.payrolls');
        });
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::delete('/profile/sessions', [ProfileController::class, 'revokeOtherSessions'])
        ->name('profile.sessions.revoke');
});

require __DIR__.'/auth.php';
