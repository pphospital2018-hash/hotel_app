<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);

    // Soft delete: flag transaction as deleted from revenue calculations
    $stmt = $conn->prepare("UPDATE bookings SET is_deleted_from_revenue = 1 WHERE id = ?");
    $stmt->bind_param("i", $booking_id);

    if ($stmt->execute()) {
        header("Location: dashboard.php?tab=revenue&msg=Transaction deleted successfully");
    } else {
        header("Location: dashboard.php?tab=revenue&error=Failed to delete transaction");
    }
    exit();
}
?>