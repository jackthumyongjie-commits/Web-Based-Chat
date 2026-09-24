<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_admin_session();
$admin = require_admin();

$message = '';
$error = '';

if (request_method() === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('admin.invalid_token');
    } else {
        $id = (int) ($_POST['report_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'reviewed');
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        if (!in_array($status, ['open', 'reviewed', 'resolved'], true)) {
            $error = __('admin.invalid_status');
        } else {
            db()->prepare(
                'UPDATE reports SET status = ?, admin_note = ?, reviewed_by = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$status, $note, (int) $admin['id'], $id]);
            $message = __('admin.report_updated');
        }
    }
}

$reports = db()->query(
    'SELECT r.*, u.username AS reporter_name
     FROM reports r
     JOIN users u ON u.id = r.reporter_id
     ORDER BY FIELD(r.status, "open","reviewed","resolved"), r.created_at DESC
     LIMIT 100'
)->fetchAll();

$statusLabels = [
    'open' => __('admin.status_open'),
    'reviewed' => __('admin.status_reviewed'),
    'resolved' => __('admin.status_resolved'),
];

$pageTitle = __('admin.reports');
$adminNav = 'reports';
require __DIR__ . '/layout_start.php';
?>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="wc-admin-card">
    <div class="table-responsive">
        <table class="table wc-admin-table mb-0 align-middle">
            <thead>
            <tr>
                <th><?= e(__('admin.id')) ?></th>
                <th><?= e(__('admin.reporter')) ?></th>
                <th><?= e(__('admin.target')) ?></th>
                <th><?= e(__('admin.reason')) ?></th>
                <th><?= e(__('admin.status')) ?></th>
                <th><?= e(__('admin.review')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $r): ?>
                <?php
                $st = (string) $r['status'];
                $badgeClass = $st === 'open' ? 'danger' : ($st === 'reviewed' ? 'warn' : 'success');
                ?>
                <tr>
                    <td class="text-muted"><?= (int) $r['id'] ?></td>
                    <td><?= e($r['reporter_name']) ?></td>
                    <td><code><?= e($r['target_type']) ?></code> #<?= (int) $r['target_id'] ?></td>
                    <td style="max-width:260px;"><?= e($r['reason']) ?></td>
                    <td><span class="wc-badge-soft <?= e($badgeClass) ?>"><?= e($statusLabels[$st] ?? $st) ?></span></td>
                    <td>
                        <form method="post" class="wc-admin-inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach ($statusLabels as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= $st === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="admin_note" class="form-control form-control-sm"
                                   placeholder="<?= e(__('admin.note')) ?>" value="<?= e((string) $r['admin_note']) ?>">
                            <button class="btn btn-sm btn-wc"><?= e(__('common.save')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reports): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('admin.no_reports')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout_end.php'; ?>
