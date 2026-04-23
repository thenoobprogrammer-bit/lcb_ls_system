<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        $employeeId = post_int('employee_id');
        $start = post_string('payroll_period_start');
        $end = post_string('payroll_period_end');
        $basic = post_float('basic_salary');
        $allowance = post_float('allowance');
        $deductions = post_float('deductions');
        $netPay = ($basic + $allowance) - $deductions;
        $notes = post_string('notes');

        if ($action === 'add') {
            $stmt = db()->prepare('INSERT INTO payrolls (employee_id, payroll_period_start, payroll_period_end, basic_salary, allowance, deductions, net_pay, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issddddsi', $employeeId, $start, $end, $basic, $allowance, $deductions, $netPay, $notes, $userId);
            $stmt->execute();
            log_activity($userId, 'Create Payroll', 'Payroll', "Created payroll for employee ID {$employeeId}.");
            set_flash('success', 'Payroll record created.');
        }
        if ($action === 'edit') {
            $id = post_int('id');
            $stmt = db()->prepare('UPDATE payrolls SET employee_id = ?, payroll_period_start = ?, payroll_period_end = ?, basic_salary = ?, allowance = ?, deductions = ?, net_pay = ?, notes = ? WHERE id = ?');
            $stmt->bind_param('issddddsi', $employeeId, $start, $end, $basic, $allowance, $deductions, $netPay, $notes, $id);
            $stmt->execute();
            log_activity($userId, 'Update Payroll', 'Payroll', "Updated payroll ID {$id}.");
            set_flash('success', 'Payroll record updated.');
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('admin/payroll.php');
}

$employees = fetch_all("SELECT id, full_name, employee_role FROM users WHERE account_type = 'employee' ORDER BY full_name");
$payrolls = fetch_all('SELECT p.*, u.full_name FROM payrolls p JOIN users u ON u.id = p.employee_id ORDER BY p.payroll_period_end DESC');
$pageTitle = 'Payroll';
$pageKey = 'admin-payroll';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title">Payroll Management</div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPayrollModal">Create Payroll</button>
    </div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Employee</th><th>Period</th><th>Basic</th><th>Allowance</th><th>Deductions</th><th>Net Pay</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($payrolls as $row): ?>
                <tr>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['payroll_period_start']) ?> to <?= e($row['payroll_period_end']) ?></td>
                    <td><?= e(money((float) $row['basic_salary'])) ?></td>
                    <td><?= e(money((float) $row['allowance'])) ?></td>
                    <td><?= e(money((float) $row['deductions'])) ?></td>
                    <td><?= e(money((float) $row['net_pay'])) ?></td>
                    <td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPayroll<?= (int) $row['id'] ?>">Edit</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addPayrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Create Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Employee</label><select class="form-select" name="employee_id"><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= e($employee['full_name'] . ' - ' . $employee['employee_role']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Start</label><input type="date" class="form-control" name="payroll_period_start" required></div>
            <div class="col-md-3"><label class="form-label">End</label><input type="date" class="form-control" name="payroll_period_end" required></div>
            <div class="col-md-4"><label class="form-label">Basic Salary</label><input type="number" step="0.01" name="basic_salary" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Allowance</label><input type="number" step="0.01" name="allowance" class="form-control" value="0"></div>
            <div class="col-md-4"><label class="form-label">Deductions</label><input type="number" step="0.01" name="deductions" class="form-control" value="0"></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Payroll</button></div>
    </form></div></div>
</div>

<?php foreach ($payrolls as $row): ?>
<div class="modal fade" id="editPayroll<?= (int) $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Update Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Employee</label><select class="form-select" name="employee_id"><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>" <?= (int) $employee['id'] === (int) $row['employee_id'] ? 'selected' : '' ?>><?= e($employee['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Start</label><input type="date" class="form-control" name="payroll_period_start" value="<?= e($row['payroll_period_start']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">End</label><input type="date" class="form-control" name="payroll_period_end" value="<?= e($row['payroll_period_end']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Basic Salary</label><input type="number" step="0.01" name="basic_salary" class="form-control" value="<?= e($row['basic_salary']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Allowance</label><input type="number" step="0.01" name="allowance" class="form-control" value="<?= e($row['allowance']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Deductions</label><input type="number" step="0.01" name="deductions" class="form-control" value="<?= e($row['deductions']) ?>"></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= e($row['notes']) ?></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Update Payroll</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

