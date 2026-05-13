<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_RENTAL]);

$userId = (int) current_user()['id'];
$packages = fetch_all('SELECT * FROM packages WHERE approval_status = "Approved" AND availability_status = "Available" ORDER BY package_name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if ($action === 'book') {
            $attachment = null;
            if (!empty($_FILES['attachment']['name'])) {
                $attachment = upload_image($_FILES['attachment'], 'events', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
            }
            $packageId = post_int('package_id');
            $package = fetch_one('SELECT * FROM packages WHERE id = ?', 'i', [$packageId]);
            if (!$package) {
                throw new RuntimeException('Selected package is not currently available.');
            }
            if (event_has_conflict($packageId, post_string('event_date'), post_string('start_time'), post_string('end_time'))) {
                throw new RuntimeException('Schedule conflict detected for the selected package.');
            }
            $stmt = db()->prepare('INSERT INTO events (rental_user_id, package_id, event_name, event_type, event_date, start_time, end_time, address, full_name, contact_number, notes, attachment_filename) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $eventName = post_string('event_name');
            $eventType = post_string('event_type');
            $eventDate = post_string('event_date');
            $startTime = post_string('start_time');
            $endTime = post_string('end_time');
            $address = post_string('address');
            $fullName = post_string('full_name');
            $contactNumber = post_string('contact_number');
            $notes = post_string('notes');
            $stmt->bind_param('iissssssssss', $userId, $packageId, $eventName, $eventType, $eventDate, $startTime, $endTime, $address, $fullName, $contactNumber, $notes, $attachment);
            $stmt->execute();
            create_notification(1, 'New event request', "{$eventName} was submitted for approval.");
            log_activity($userId, 'Create Event', 'Events', "Created event request {$eventName}.");
            set_flash('success', 'Event booking submitted.');
        }
        if ($action === 'message') {
            $stmt = db()->prepare('INSERT INTO messages (sender_id, recipient_id, sender_name, sender_email, subject, message_body) VALUES (?, ?, ?, ?, ?, ?)');
            $recipient = 1;
            $sender = current_user();
            $senderEmail = post_string('sender_email');
            $subject = post_string('subject');
            $messageBody = post_string('message_body');
            $stmt->bind_param('iissss', $userId, $recipient, $sender['full_name'], $senderEmail, $subject, $messageBody);
            $stmt->execute();
            create_notification(1, 'New inquiry', "New message received from {$sender['full_name']}.");
            set_flash('success', 'Message sent to admin.');
        }
        if ($action === 'feedback') {
            $eventId = post_int('event_id');
            $rating = post_int('rating');
            $comment = post_string('comment');
            $stmt = db()->prepare('INSERT INTO feedback (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiis', $eventId, $userId, $rating, $comment);
            $stmt->execute();
            set_flash('success', 'Feedback submitted.');
        }
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('rental/events.php');
}

$events = fetch_all('SELECT e.*, p.package_name FROM events e LEFT JOIN packages p ON p.id = e.package_id WHERE e.rental_user_id = ? ORDER BY e.event_date DESC', 'i', [$userId]);
$feedbackEventIds = array_column(fetch_all('SELECT event_id FROM feedback WHERE user_id = ?', 'i', [$userId]), 'event_id');
$pageTitle = 'My Events';
$pageKey = 'rental-events';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title">Event Bookings</div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookEventModal">Create Event Booking</button>
            </div>
            <div class="table-responsive">
                <table class="table datatable align-middle">
                    <thead><tr><th>Event</th><th>Package</th><th>Schedule</th><th>Status</th><th>Attachment</th><th>Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= e($event['event_name']) ?><div class="small text-muted"><?= e($event['event_type']) ?></div></td>
                            <td><?= e($event['package_name'] ?? '-') ?></td>
                            <td><?= e(format_display_date($event['event_date'])) ?><div class="small text-muted"><?= e(format_display_time($event['start_time'])) ?> - <?= e(format_display_time($event['end_time'])) ?></div></td>
                            <td><span class="status-pill status-<?= strtolower($event['event_status']) ?>"><?= e($event['event_status']) ?></span></td>
                            <td><?= $event['attachment_filename'] ? '<a href="' . e(app_url('uploads/events/' . $event['attachment_filename'])) . '" target="_blank">Open</a>' : 'None' ?></td>
                            <td>
                                <?php if ($event['event_status'] === 'Completed' && !in_array((int) $event['id'], array_map('intval', $feedbackEventIds), true)): ?>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#feedback<?= (int) $event['id'] ?>">Rate</button>
                                <?php else: ?>
                                    <span class="small text-muted"><?= $event['event_status'] === 'Completed' ? 'Submitted' : 'After completion' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="content-card mb-4">
            <div class="section-title mb-3">Contact Owner / Request Assistance</div>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="message">
                <div class="col-12"><label class="form-label">Email</label><input name="sender_email" class="form-control"></div>
                <div class="col-12"><label class="form-label">Subject</label><input name="subject" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Message</label><textarea name="message_body" class="form-control" rows="4" required></textarea></div>
                <div class="col-12"><button class="btn btn-primary">Send Message</button></div>
            </form>
        </div>
        <div class="calendar-card" data-calendar data-events='<?= e(json_encode(array_map(static fn ($row): array => [
            'event_name' => $row['event_name'],
            'event_date' => $row['event_date'],
            'event_type' => $row['event_type'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'event_status' => $row['event_status'],
            'package_name' => $row['package_name'],
        ], $events))) ?>'></div>
    </div>
</div>

<div class="modal fade" id="bookEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="book">
        <div class="modal-header"><h5 class="modal-title">Create Event Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-6"><label class="form-label">Event Name</label><input class="form-control" name="event_name" required></div>
            <div class="col-md-6"><label class="form-label">Event Type</label><input class="form-control" name="event_type" required></div>
            <div class="col-md-6"><label class="form-label">Selected Package</label><select class="form-select" name="package_id"><?php foreach ($packages as $package): ?><option value="<?= (int) $package['id'] ?>"><?= e($package['package_name']) ?> - <?= e(money((float) $package['price'])) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Event Date</label><input type="date" class="form-control" name="event_date" required></div>
            <div class="col-md-3"><label class="form-label">Start Time</label><input type="time" class="form-control" name="start_time" required></div>
            <div class="col-md-3"><label class="form-label">End Time</label><input type="time" class="form-control" name="end_time" required></div>
            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e(current_user()['full_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number" value="<?= e(current_user()['contact_number']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Attachment (layout/reference)</label><input type="file" class="form-control" name="attachment" accept="image/*,.pdf"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2" required></textarea></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Submit Request</button></div>
    </form></div></div>
</div>

<?php foreach ($events as $event): if ($event['event_status'] !== 'Completed' || in_array((int) $event['id'], array_map('intval', $feedbackEventIds), true)) continue; ?>
<div class="modal fade" id="feedback<?= (int) $event['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="feedback"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Rate Service</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label">Rating</label>
            <select class="form-select mb-3" name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?></select>
            <label class="form-label">Comment</label>
            <textarea class="form-control" name="comment" rows="3"></textarea>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Submit Feedback</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>
