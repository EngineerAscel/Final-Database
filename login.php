<?php
session_start();
require 'db.php'; // database connection

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT userID, username, password, role, fullName, status 
                            FROM usermanagement 
                            WHERE username = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $db_username, $db_password, $db_role, $db_fullName, $db_status);
        $stmt->fetch();

        if ($password === $db_password) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user_id;
            $_SESSION['username'] = $db_username;
            $_SESSION['role']     = $db_role;
            $_SESSION['fullName'] = $db_fullName;

            if ($db_role === "Admin") {
                header("Location: admin-dashboard.php");
            } elseif ($db_role === "sales") {
                header("Location: sales-dashboard.php");
            } elseif ($db_role === "Cashier") {
                header("location: cashier-dashboard.php");
            } else {
                $error = "Unknown role assigned.";
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1Garage Portal</title>
    <link rel="stylesheet" type="text/css" href="css/login.css">
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top, rgba(239,68,68,0.2), transparent 60%), #05060a;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        /* Override login.css button gradient with solid red */
        button[type="submit"] {
            background: #dc2626 !important;
            background-color: #dc2626 !important;
            background-image: none !important;
        }
        button[type="submit"]:hover {
            background: #b91c1c !important;
            background-color: #b91c1c !important;
            background-image: none !important;
        }
    </style>
</head>
<body class="text-gray-100">
    <div class="w-full max-w-md bg-gray-900/90 border border-gray-800 rounded-3xl shadow-2xl p-8 space-y-6">
        <div class="text-center space-y-2">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-white/10 bg-white/5">
                <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                <span class="text-xs tracking-[0.3em] uppercase text-gray-400">1Garage</span>
            </div>
            <h1 class="text-3xl font-bold text-white">Sign in</h1>
            <p class="text-sm text-gray-400">Use your admin, sales, or cashier credentials.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/40 text-red-200 text-sm rounded-xl px-4 py-3 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-5">
            <div class="space-y-2">
                <label class="text-sm uppercase tracking-[0.3em] text-gray-500">Username</label>
                <div class="relative">
                    <i data-lucide="user" class="absolute left-4 top-3.5 text-gray-500 w-5 h-5"></i>
                    <input type="text" name="username" required autofocus
                           class="w-full bg-slate-950/60 border border-gray-800 rounded-2xl py-3.5 pl-12 pr-4 text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                           placeholder="admin01">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm uppercase tracking-[0.3em] text-gray-500">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-4 top-3.5 text-gray-500 w-5 h-5"></i>
                    <input type="password" name="password" required
                           class="w-full bg-slate-950/60 border border-gray-800 rounded-2xl py-3.5 pl-12 pr-4 text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" name="login"
                    class="w-full py-3.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-2xl shadow-lg shadow-red-900/30 transition">
                Enter Garage
            </button>
        </form>

        <div class="flex items-center justify-between text-sm text-gray-400">
            <a href="forget_password.php" class="hover:text-red-400 transition">Forgot Password?</a>
            <span class="uppercase tracking-[0.3em] text-gray-500">1garage</span>
        </div>
    </div>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>

