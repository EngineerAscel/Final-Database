<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "sales") {
    header("Location: login.php");
    exit();
}

include "db.php";

// Fetch messages sent to Sales
$stmt = $conn->prepare("SELECT sender, message, timestamp FROM messages WHERE recipient = 'Sales' ORDER BY timestamp DESC");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Messages from Cashiers</title>
</head>
<body>
    <h2>Messages from Cashiers</h2>

    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Sender (Cashier)</th>
            <th>Message</th>
            <th>Sent At</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['sender']); ?></td>
                <td><?php echo htmlspecialchars($row['message']); ?></td>
                <td><?php echo htmlspecialchars($row['timestamp']); ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
