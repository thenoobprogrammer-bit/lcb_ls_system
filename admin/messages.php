<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_auth([ACCOUNT_ADMIN]);

$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = post_int('id');
        $reply = post_string('admin_reply');
        $stmt = db()->prepare('UPDATE messages SET admin_reply = ? WHERE id = ?');
        $stmt->bind_param('si', $reply, $id);
        $stmt->execute();
        $message = fetch_one('SELECT sender_id, subject FROM messages WHERE id = ?', 'i', [$id]);
        if ($message) {
            create_notification((int) $message['sender_id'], 'Admin replied', "Your message about {$message['subject']} has a reply.");
        }
        log_activity($userId, 'Reply Message', 'Messages', "Replied to message ID {$id}.");
        set_flash('success', 'Reply saved.');
    } catch (Throwable $exception) {
        set_flash('danger', $exception->getMessage());
    }
    redirect('admin/messages.php');
}

$messages = fetch_all('SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id ORDER BY m.created_at DESC');
$pageTitle = 'Messages';
$pageKey = 'admin-messages';
require BASE_PATH . '/partials/layout_top.php';
?>
<div class="content-card">
    <div class="section-title mb-3">Customer and User Messages</div>
    <div class="table-responsive">
        <table class="table datatable align-middle">
            <thead><tr><th>Sender</th><th>Subject</th><th>Message</th><th>Reply</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($messages as $message): ?>
                <tr>
                    <td><?= e($message['sender_name']) ?><div class="small text-muted"><?= e($message['username']) ?></div></td>
                    <td><?= e($message['subject']) ?></td>
                    <td><?= e($message['message_body']) ?></td>
                    <td><?= e($message['admin_reply'] ?: 'No reply yet') ?></td>
                    <td><?= e($message['created_at']) ?></td>
                    <td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reply<?= (int) $message['id'] ?>">Reply</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php foreach ($messages as $message): ?>
<div class="modal fade" id="reply<?= (int) $message['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Reply to <?= e($message['sender_name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><strong>Subject:</strong> <?= e($message['subject']) ?></div>
            <div class="mb-3"><?= e($message['message_body']) ?></div>
            <label class="form-label">Reply</label>
            <textarea class="form-control" name="admin_reply" rows="4"><?= e($message['admin_reply']) ?></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Reply</button></div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require BASE_PATH . '/partials/layout_bottom.php'; ?>

