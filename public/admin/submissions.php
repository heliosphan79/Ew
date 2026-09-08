<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    csrf_verify();
    $id = (int) $_POST['mark_read_id'];
    $stmt = $mysqli->prepare('UPDATE contact_submissions SET is_read = 1 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    redirect('submissions.php');
}

$submissions = $mysqli->query(
    'SELECT id, name, email, message, is_read, created_at FROM contact_submissions ORDER BY created_at DESC'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Contactberichten';
require __DIR__ . '/includes/header.php';
?>

<h1>Contactberichten</h1>

<?php if (empty($submissions)): ?>
    <p>Nog geen berichten ontvangen.</p>
<?php else: ?>
    <div class="submission-list">
        <?php foreach ($submissions as $submission): ?>
            <div class="submission-card <?= $submission['is_read'] ? '' : 'submission-unread' ?>">
                <div class="submission-meta">
                    <strong><?= e($submission['name']) ?></strong>
                    &lt;<a href="mailto:<?= e($submission['email']) ?>"><?= e($submission['email']) ?></a>&gt;
                    <span class="submission-date"><?= e(date('d/m/Y H:i', strtotime($submission['created_at']))) ?></span>
                </div>
                <p class="submission-message"><?= nl2br(e($submission['message'])) ?></p>
                <div class="submission-actions">
                    <?php if (!$submission['is_read']): ?>
                        <form method="post" action="submissions.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mark_read_id" value="<?= (int) $submission['id'] ?>">
                            <button type="submit" class="link-button">Markeer als gelezen</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="submission-delete.php" onsubmit="return confirm('Dit bericht definitief verwijderen?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $submission['id'] ?>">
                        <button type="submit" class="link-button link-button-danger">Verwijderen</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
