<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

// Public, read-only: upcoming available slots for the calendar block.
// Capped at 90 days out so this never has to paginate.
$stmt = $mysqli->prepare(
    "SELECT slot_date, slot_time FROM calendar_slots
     WHERE status = 'available' AND slot_date >= CURDATE() AND slot_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY slot_date ASC, slot_time ASC"
);
$stmt->execute();
$result = $stmt->get_result();

$slots = [];
while ($row = $result->fetch_assoc()) {
    $slots[] = [
        'date' => $row['slot_date'],
        'time' => substr($row['slot_time'], 0, 5),
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'slots' => $slots]);
