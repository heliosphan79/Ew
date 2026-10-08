<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('newsletters.php');
}
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);

$stmt = $mysqli->prepare('SELECT subject, content FROM newsletters WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$source = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$source) {
    set_flash('error', 'Nieuwsbrief niet gevonden.');
    redirect('newsletters.php');
}

// Always lands as a fresh draft, regardless of the source's own status
// (draft/sending/sent/cancelled) — sent_at/last_send_error are never
// copied, and newsletter_sends/newsletter_clicks (per-send history) are
// about the source newsletter, not relevant to a new one.
$subject = 'Kopie van ' . $source['subject'];
$stmt = $mysqli->prepare("INSERT INTO newsletters (subject, content, status, created_at) VALUES (?, ?, 'draft', NOW())");
$stmt->bind_param('ss', $subject, $source['content']);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

set_flash('success', 'Nieuwsbrief gekopieerd als nieuw concept.');
redirect('newsletter-edit.php?id=' . $newId);
