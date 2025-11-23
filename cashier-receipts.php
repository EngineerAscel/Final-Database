<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Cashier")) {
    header("Location: login.php");
    exit();
}

include "db.php";
include "cashier-sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Receipt - Cashier</title>
    <link rel="stylesheet" href="css/cashier.css">
</head>
<body>

    <div class="main-content">
        <div class="topbar">
            <h1>Generate Receipt</h1>
        </div>

        <!-- Receipt to PDF -->
        <section>
            <h2>Generate Receipt (Cash / Onsite)</h2>
            <form action="generate-receipt.php" method="POST" target="_blank">
                <label>Customer Name:</label><br>
                <input type="text" name="customerName" required><br><br>

                <label>Total Amount:</label><br>
                <input type="number" step="0.01" name="totalAmount" required><br><br>

                <label>Payment Type:</label><br>
                <select name="paymentType" required>
                    <option value="cash">Cash</option>
                </select><br><br>

                <button type="submit">Generate PDF Receipt</button>
            </form>
        </section>
    </div>
</body>
</html>