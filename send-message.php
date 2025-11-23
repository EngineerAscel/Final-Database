<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Cashier")) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $message = htmlspecialchars(trim($_POST['message']));
    $sender = $_SESSION['username']; // Cashier username
    $recipient = 'sales'; // Fixed recipient

    if (empty($message)) {
        // Redirect back with error
        header("Location: cashier-messages.php?error=Message cannot be empty");
        exit();
    }

    // Insert into messages table (assume table: messages(id, sender, recipient, message, timestamp))
    $stmt = $conn->prepare("INSERT INTO messages (sender, recipient, message, timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $sender, $recipient, $message);
    if ($stmt->execute()) {
        // Success: redirect back with success message
        header("Location: cashier-messages.php?success=Message sent successfully");
        exit();
    } else {
        // Error: redirect back with error
        header("Location: cashier-messages.php?error=Failed to send message");
        exit();
    }
    $stmt->close();
} else {
    // If not POST, redirect back
    header("Location: cashier-messages.php");
    exit();
}
?>