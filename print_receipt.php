<?php
session_start();
require_once 'db.php';

if (!isset($_GET['id'])) { die("Receipt ID missing."); }
$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

if (!$receipt) { die("Receipt not found."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #REC-<?= $receipt['id'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0 auto; padding: 10px; border: 1px solid #000; }
        .text-center { text-align: center; }
        .line { border-bottom: 1px dashed #000; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        @media print {
            body { border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="text-center">
        <h3>PRINCE & PRINCESS INT'L HOTEL & SUITES</h3>
        <p>OFFICIAL PAYMENT RECEIPT</p>
    </div>
    <div class="line"></div>
    <div class="row"><span>Receipt No:</span> <strong>REC-<?= $receipt['id'] ?></strong></div>
    <div class="row"><span>Date:</span> <span><?= $receipt['booking_date'] ?></span></div>
    <div class="row"><span>Guest Name:</span> <span><?= htmlspecialchars($receipt['guest_name']) ?></span></div>
    <div class="row"><span>Room Assigned:</span> <span>Room <?= $receipt['room_number'] ?></span></div>
    <div class="row"><span>Package:</span> <span><?= htmlspecialchars($receipt['package_type']) ?></span></div>
    <div class="line"></div>
    <div class="row" style="font-size: 16px;"><span>AMOUNT PAID:</span> <strong>₦<?= number_format($receipt['amount_paid'], 2) ?></strong></div>
    <div class="line"></div>
    <div class="text-center">
        <p>Thank you for staying with us!</p>
        <button class="no-print" onclick="window.print()">Print Receipt</button>
    </div>
</body>
</html>