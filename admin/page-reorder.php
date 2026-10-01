<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

function reorder_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reorder_fail('Ongeldige methode.', 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    reorder_fail('Ongeldige of verlopen aanvraag. Herlaad de pagina en probeer opnieuw.');
}

$ids = json_decode((string) ($_POST['order'] ?? ''), true);
if (!is_array($ids) || empty($ids)) {
    reorder_fail('Ongeldige volgorde.');
}

$stmt = $mysqli->prepare('UPDATE pages SET nav_order = ? WHERE id = ?');
foreach (array_values($ids) as $index => $id) {
    $id = (int) $id;
    if ($id <= 0) {
        continue;
    }
    $stmt->bind_param('ii', $index, $id);
    $stmt->execute();
}
$stmt->close();

echo json_encode(['ok' => true]);
