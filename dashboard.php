<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Process New Booking
if ($role == 'frontdesk' && isset($_POST['create_booking'])) {
    $guest_name = $_POST['guest_name'];
    $room_number = $_POST['room_number'];
    $package_id = $_POST['package_id'];
    $amount_paid = $_POST['amount_paid'];

    // Get package title
    $pkg_q = $conn->query("SELECT package_name FROM packages WHERE id = '$package_id'")->fetch_assoc();
    $package_type = $pkg_q['package_name'];

    $stmt = $conn->prepare("INSERT INTO bookings (guest_name, room_number, package_type, amount_paid) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $guest_name, $room_number, $package_type, $amount_paid);
    $stmt->execute();
    header("Location: dashboard.php?tab=active");
    exit();
}

// Process Checkout
if ($role == 'frontdesk' && isset($_GET['checkout_id'])) {
    $id = $_GET['checkout_id'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'checked_out' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: dashboard.php?tab=active");
    exit();
}

$tab = $_GET['tab'] ?? ($role == 'manager' ? 'revenue' : 'active');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hotel Management Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8f9fa; }
        .header { background: #343a40; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .nav { background: #e9ecef; padding: 10px 20px; border-bottom: 1px solid #ccc; }
        .nav a { margin-right: 15px; text-decoration: none; color: #007bff; font-weight: bold; }
        .container { padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background: #f1f1f1; }
        .form-box { background: white; padding: 20px; width: 450px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 6px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>

<div class="header">
    <h2>Prince & Princess Int'l Hotel & Suites</h2>
    <div>Logged in as: <strong><?= ucfirst($username) ?> (<?= ucfirst($role) ?>)</strong> | <a href="logout.php" style="color: #ffc107;">Logout</a></div>
</div>

<div class="nav">
    <?php if ($role == 'frontdesk'): ?>
        <a href="dashboard.php?tab=new">New Booking</a>
        <a href="dashboard.php?tab=active">Active Reservations</a>
        <a href="dashboard.php?tab=receipts">Receipt Phase (30 Days)</a>
    <?php endif; ?>
    <a href="dashboard.php?tab=revenue">Revenue Report</a>
</div>

<div class="container">

    <!-- FRONT DESK: NEW BOOKING -->
    <?php if ($role == 'frontdesk' && $tab == 'new'): ?>
        <h3>Create New Booking</h3>
        <div class="form-box">
            <form method="POST">
                <p>
                    <label>Guest Name:</label><br>
                    <input type="text" name="guest_name" required style="width: 100%; padding: 8px;">
                </p>
                <p>
                    <label>Package Type:</label><br>
                    <select name="package_id" id="package_id" onchange="fetchPackageDetails(this.value)" required style="width: 100%; padding: 8px;">
                        <option value="">-- Select Package --</option>
                        <?php
                        $pkgs = $conn->query("SELECT * FROM packages");
                        while($p = $pkgs->fetch_assoc()):
                        ?>
                            <option value="<?= $p['id'] ?>"><?= $p['package_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </p>
                <p>
                    <label>Available Room Number:</label><br>
                    <select name="room_number" id="room_number" required style="width: 100%; padding: 8px;">
                        <option value="">-- Select Package First --</option>
                    </select>
                </p>
                <p>
                    <label>Amount Paid (₦):</label><br>
                    <input type="number" step="0.01" name="amount_paid" id="amount_paid" readonly required style="width: 100%; padding: 8px; background: #e9ecef;">
                </p>
                <button type="submit" name="create_booking" class="btn">Process Booking</button>
            </form>
        </div>

       <script>
function fetchPackageDetails(packageId) {
    if (!packageId) {
        document.getElementById('amount_paid').value = '';
        document.getElementById('room_number').innerHTML = '<option value="">-- Select Package First --</option>';
        return;
    }

    fetch('get_package_details.php?package_id=' + packageId)
        .then(response => response.json())
        .then(data => {
            // Update price
            document.getElementById('amount_paid').value = data.price;

            let roomSelect = document.getElementById('room_number');
            
            if (!data.rooms || data.rooms.length === 0) {
                roomSelect.innerHTML = '<option value="">No Rooms Assigned</option>';
                return;
            }

            let optionsHTML = '<option value="">-- Select Room --</option>';
            let availableCount = 0;

            data.rooms.forEach(item => {
                if (item.is_occupied) {
                    optionsHTML += '<option value="" disabled style="color: red;">Room ' + item.room_number + ' (Occupied)</option>';
                } else {
                    optionsHTML += '<option value="' + item.room_number + '">Room ' + item.room_number + '</option>';
                    availableCount++;
                }
            });

            if (availableCount === 0) {
                optionsHTML = '<option value="" disabled selected>No Rooms Available (Occupied)</option>' + optionsHTML;
            }

            roomSelect.innerHTML = optionsHTML;
        })
        .catch(error => console.error('Error fetching details:', error));
}
</script>
    <?php endif; ?>

    <!-- FRONT DESK: ACTIVE RESERVATIONS -->
    <?php if ($role == 'frontdesk' && $tab == 'active'): ?>
        <h3>Active Reservations</h3>
        <table>
            <tr>
                <th>S/N</th>
                <th>Booking ID</th>
                <th>Guest Name</th>
                <th>Room</th>
                <th>Package</th>
                <th>Amount Paid</th>
                <th>Action</th>
            </tr>
            <?php
            $sn = 1;
            $res = $conn->query("SELECT * FROM bookings WHERE status = 'active' ORDER BY booking_date DESC");
            while ($row = $res->fetch_assoc()):
            ?>
            <tr>
                <td><?= $sn++ ?></td>
                <td>#<?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['guest_name']) ?></td>
                <td>Room <?= htmlspecialchars($row['room_number']) ?></td>
                <td><?= htmlspecialchars($row['package_type']) ?></td>
                <td>₦<?= number_format($row['amount_paid'], 2) ?></td>
                <td><a href="dashboard.php?tab=active&checkout_id=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Check out this guest?')">Checkout</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>

    <!-- FRONT DESK: RECEIPT PHASE -->
    <?php if ($role == 'frontdesk' && $tab == 'receipts'): ?>
        <h3>Receipt Archive (Past 30 Days)</h3>
        <table>
            <tr>
                <th>Receipt ID</th>
                <th>Guest Name</th>
                <th>Room</th>
                <th>Amount Paid</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            <?php
            $res = $conn->query("SELECT * FROM bookings WHERE booking_date >= NOW() - INTERVAL 30 DAY ORDER BY booking_date DESC");
            while ($row = $res->fetch_assoc()):
            ?>
            <tr>
                <td>REC-<?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['guest_name']) ?></td>
                <td>Room <?= htmlspecialchars($row['room_number']) ?></td>
                <td>₦<?= number_format($row['amount_paid'], 2) ?></td>
                <td><?= $row['booking_date'] ?></td>
                <td><a href="print_receipt.php?id=<?= $row['id'] ?>" target="_blank" class="btn">Print Official Receipt</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>

    <!-- BOTH ROLES: REVENUE REPORT -->
    <?php if ($tab == 'revenue'): ?>
        <h3>Revenue & Occupancy Report</h3>
        <?php
        $daily = $conn->query("SELECT COUNT(*) as clients, SUM(amount_paid) as total FROM bookings WHERE DATE(booking_date) = CURDATE()")->fetch_assoc();
        $monthly = $conn->query("SELECT COUNT(*) as clients, SUM(amount_paid) as total FROM bookings WHERE MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())")->fetch_assoc();
        ?>
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="background: #e2e3e5; padding: 15px; border-radius: 5px; flex: 1;">
                <h4>Today's Total</h4>
                <p>Clients Booked: <strong><?= $daily['clients'] ?? 0 ?></strong></p>
                <p>Total Revenue: <strong>₦<?= number_format($daily['total'] ?? 0, 2) ?></strong></p>
            </div>
            <div style="background: #d4edda; padding: 15px; border-radius: 5px; flex: 1;">
                <h4>This Month's Total</h4>
                <p>Clients Booked: <strong><?= $monthly['clients'] ?? 0 ?></strong></p>
                <p>Total Revenue: <strong>₦<?= number_format($monthly['total'] ?? 0, 2) ?></strong></p>
            </div>
        </div>

        <h4>All Booking Transactions</h4>
        <table>
            <tr>
                <th>S/N</th>
                <th>Date</th>
                <th>Guest Name</th>
                <th>Room</th>
                <th>Package</th>
                <th>Amount Paid</th>
            </tr>
            <?php
            $sn = 1;
            $res = $conn->query("SELECT * FROM bookings ORDER BY booking_date DESC");
            while ($row = $res->fetch_assoc()):
            ?>
            <tr>
                <td><?= $sn++ ?></td>
                <td><?= $row['booking_date'] ?></td>
                <td><?= htmlspecialchars($row['guest_name']) ?></td>
                <td>Room <?= htmlspecialchars($row['room_number']) ?></td>
                <td><?= htmlspecialchars($row['package_type']) ?></td>
                <td>₦<?= number_format($row['amount_paid'], 2) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>

</div>

</body>
</html>