<?php
session_start();
require 'db.php';

// ✅ Check if logged in
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized action.']);
    exit();
}

// ✅ Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_username'])) {
    $username = trim($_POST['delete_username']);

    // Prevent deleting yourself
    if ($username === $_SESSION['username']) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: '.$stmt->error]);
        exit();
    }

    // Delete securely
    $stmt = $conn->prepare("DELETE FROM usermanagement WHERE username = ?");
    $stmt->bind_param("s", $username);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
    }

    $stmt->close();
    $conn->close();
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
