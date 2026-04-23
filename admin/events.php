<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$userId = (int) current_user()['id'];
$employees = fetch_all("SELECT id, full_name, employee_role FROM users WHERE account_type = 'employee' AND is_active = 1 ORDER BY full_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startedTransaction = false;
    try {
        $id = post_int('id');
        $action = post_string('action');
        if (in_array($action, ['approve', 'deny', 'complete'], true)) {
            $status = match ($action) {
                'approve' => 'Approved',
                'deny' => 'Denied',
                default => 'Completed',
            };
            $stmt = db()->prepare('UPDATE events SET event_status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $event = fetch_one('SELECT rental_user_id, event_name FROM events WHERE id = ?', 'i', [$id]);
            if ($event) {
                create_notification((int) $event['rental_user_id'], 'Event status updated', "{$event['event_name']} is now {$status}.");
            }
            log_activity($userId, ucfirst($action) . ' Event', 'Events', "Changed event ID {$id} to {$status}.");
            set_flash('success', "Event {$status}.");
        }
        if ($action === 'assign_team') {
            $event = fetch_one('SELECT id, event_name, event_status FROM events WHERE id = ?', 'i', [$id]);
            if (!$event) {
                throw new RuntimeException('Event not found.');
            }
            if ($event['event_status'] !== 'Approved') {
                throw new RuntimeException('Only approved events can have an assigned crew.');
            }

            $selectedEmployees = array_map('intval', (array) ($_POST['employee_ids'] ?? []));
            db()->begin_transaction();
            $startedTransaction = true;
            $deleteStmt = db()->prepare('DELETE FROM event_assignments WHERE event_id = ?');
            $deleteStmt->bind_param('i', $id);
            $deleteStmt->execute();

            if ($selectedEmployees) {
                $insertStmt = db()->prepare('INSERT INTO event_assignments (event_id, employee_id, assigned_by) VALUES (?, ?, ?)');
                foreach ($selectedEmployees as $employeeId) {
                    $insertStmt->bind_param('iii', $id, $employeeId, $userId);
                    $insertStmt->execute();
                    create_notification($employeeId, 'Event assignment', "You have been assigned to {$event['event_name']}.");
                }
            }
            db()->commit();
            $startedTransaction = false;
            log_activity($userId, 'Assign Team', 'Events', "Assigned crew to event {$event['event_name']}.");
            set_flash('success', 'Event crew updated.');
        }
    } catch (Throwable $exception) {
        if ($startedTransaction) {
            db()->rollback();
        }
        set_flash('danger', $exception->getMessage());
    }
    redirect('admin/events.php');
}

$events = fetch_all('
    SELECT e.*, p.package_name, u.full_name AS customer_name,
           GROUP_CONCAT(DISTINCT crew.full_name ORDER BY crew.full_name SEPARATOR ", ") AS assigned_employees
    FROM events e
    LEFT JOIN packages p ON p.id = e.package_id
    JOIN users u ON u.id = e.rental_user_id
    LEFT JOIN event_assignments ea ON ea.event_id = e.id
    LEFT JOIN users crew ON crew.id = ea.employee_id
    GROUP BY e.id, p.package_name, u.full_name
    ORDER BY e.event_date DESC, e.start_time DESC
');
$assignmentMap = [];
foreach (fetch_all('SELECT event_id, employee_id FROM event_assignments') as $assignment) {
    $assignmentMap[(int) $assignment['event_id']][] = (int) $assignment['employee_id'];
}
$pageTitle = 'Event Requests';
$pageKey = 'admin-events';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="content-card">
            <div class="section-title mb-3">Bookings and Event Requests</div>
            <div class="table-responsive">
                <table class="table datatable align-middle">
                    <thead><tr><th>Event</th><th>Customer</th><th>Package</th><th>Schedule</th><th>Status</th><th>Assigned Crew</th><th>Attachment</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e($event['event_name']) ?></div><div class="small text-muted"><?= e($event['event_type']) ?></div></td>
                            <td><?= e($event['customer_name']) ?><div class="small text-muted"><?= e($event['contact_number']) ?></div></td>
                            <td><?= e($event['package_name'] ?? 'Custom') ?></td>
                            <td><?= e($event['event_date']) ?><div class="small text-muted"><?= e(substr($event['start_time'], 0, 5)) ?> - <?= e(substr($event['end_time'], 0, 5)) ?></div></td>
                            <td><span class="status-pill status-<?= strtolower($event['event_status']) ?>"><?= e($event['event_status']) ?></span></td>
                            <td><?= e($event['assigned_employees'] ?: 'No crew assigned') ?></td>
                            <td><?= $event['attachment_filename'] ? '<a href="' . e(app_url('uploads/events/' . $event['attachment_filename'])) . '" target="_blank">Open</a>' : 'None' ?></td>
                            <td class="d-flex gap-2 flex-wrap">
                                <?php if ($event['event_status'] !== 'Approved'): ?><form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int) $event['id'] ?>"><button class="btn btn-sm btn-outline-success">Approve</button></form><?php endif; ?>
                                <?php if ($event['event_status'] !== 'Denied'): ?><form method="post"><input type="hidden" name="action" value="deny"><input type="hidden" name="id" value="<?= (int) $event['id'] ?>"><button class="btn btn-sm btn-outline-danger">Deny</button></form><?php endif; ?>
                                <?php if ($event['event_status'] === 'Approved'): ?><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#assignCrew<?= (int) $event['id'] ?>">Assign Crew</button><?php endif; ?>
                                <?php if ($event['event_status'] === 'Approved'): ?><form method="post"><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?= (int) $event['id'] ?>"><button class="btn btn-sm btn-outline-primary">Complete</button></form><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="calendar-card" data-calendar data-events='<?= e(json_encode(array_map(static fn ($row): array => [
            'event_name' => $row['event_name'],
            'event_date' => $row['event_date'],
            'event_type' => $row['event_type'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'event_status' => $row['event_status'],
            'package_name' => $row['package_name'],
            'customer_name' => $row['customer_name'],
            'assigned_employees' => $row['assigned_employees'],
        ], $events))) ?>'></div>
    </div>
</div>
<?php foreach ($events as $event): $selectedCrew = $assignmentMap[(int) $event['id']] ?? [];
    if ($event['event_status'] !== 'Approved') {
        continue;
    }
?>
<div class="modal fade" id="assignCrew<?= (int) $event['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="assign_team">
        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Assign Crew to <?= e($event['event_name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3 text-muted">Select which employees should work on this approved event.</div>
            <div class="row g-2">
                <?php foreach ($employees as $employee): ?>
                    <div class="col-md-6">
                        <div class="form-check border rounded-3 p-3">
                            <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= (int) $employee['id'] ?>" id="event<?= (int) $event['id'] ?>employee<?= (int) $employee['id'] ?>" <?= in_array((string) $employee['id'], array_map('strval', $selectedCrew), true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="event<?= (int) $event['id'] ?>employee<?= (int) $employee['id'] ?>">
                                <?= e($employee['full_name']) ?>
                                <span class="text-muted">- <?= e((string) $employee['employee_role']) ?></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Crew</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
