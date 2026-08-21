<?php
require_once 'db.php';

if (isset($_GET['package_id'])) {
    $package_id = intval($_GET['package_id']);

    // Fetch package price
    $stmt = $conn->prepare("SELECT price FROM packages WHERE id = ?");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $pkg = $stmt->get_result()->fetch_assoc();

    // Fetch all assigned rooms and check if they are currently occupied
    $room_stmt = $conn->prepare("
        SELECT pr.room_number,
               CASE WHEN b.id IS NOT NULL THEN 1 ELSE 0 END AS is_occupied
        FROM package_rooms pr
        LEFT JOIN bookings b ON pr.room_number = b.room_number AND b.status = 'active'
        WHERE pr.package_id = ?
        ORDER BY CAST(pr.room_number AS UNSIGNED) ASC
    ");
    $room_stmt->bind_param("i", $package_id);
    $room_stmt->execute();
    $rooms_res = $room_stmt->get_result();

    $rooms = [];
    while ($r = $rooms_res->fetch_assoc()) {
        $rooms[] = [
            'room_number' => $r['room_number'],
            'is_occupied' => (bool)$r['is_occupied']
        ];
    }

    echo json_encode([
        'price' => $pkg['price'] ?? 0,
        'rooms' => $rooms
    ]);
}
?>