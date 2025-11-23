<?php
include('connection.php');

// ✅ Ensure PHP uses same timezone as your DB
date_default_timezone_set('Asia/Manila'); 

$error = "";
$correct = "";
$token = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $newPassword = $_POST['new_password']; // no hashing for now

    // Verify token and expiry (check against current PHP time)
    $stmt = $conn->prepare("SELECT * FROM usermanagement WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update password + clear token
        $stmt2 = $conn->prepare("UPDATE usermanagement 
                                SET password=?, reset_token=NULL, token_expiry=NULL 
                                WHERE reset_token=?");
        $stmt2->bind_param("ss", $newPassword, $token);

        if ($stmt2->execute()) {
            $correct = "✅ Password updated successfully! <a href='login.php'>Click here to log in</a>.";
        } else {
            $error = "❌ Error updating password: " . $conn->error;
        }
    } else {
        $error = "❌ Invalid or expired token.";
    }

    $stmt->close();
    $conn->close();
} else if (isset($_GET['token'])) {
    $token = $_GET['token'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - 1Garage</title>
    <link rel="stylesheet" href="css/style.css">
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
    </style>
</head>
<body class="text-gray-100">
    <div class="w-full max-w-md bg-gray-900/90 border border-gray-800 rounded-3xl shadow-2xl p-8 space-y-6">
        <div class="text-center space-y-2">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-white/10 bg-white/5">
                <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                <span class="text-xs tracking-[0.3em] uppercase text-gray-400">1Garage</span>
            </div>
            <h1 class="text-3xl font-bold text-white">Reset Password</h1>
            <p class="text-sm text-gray-400">Enter your new password below.</p>
        </div>

        <?php if (!empty($correct)): ?>
            <div class="bg-green-500/10 border border-green-500/40 text-green-200 text-sm rounded-xl px-4 py-3 text-center space-y-2">
                <p>Password updated successfully!</p>
                <a href="login.php" class="inline-flex items-center gap-2 text-green-300 hover:text-green-100 underline transition">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>Click here to log in</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/40 text-red-200 text-sm rounded-xl px-4 py-3 text-center">
                <?= htmlspecialchars(str_replace('❌', '', $error)) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="reset_password.php" class="space-y-5">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="space-y-2">
                <label for="new_password" class="text-sm uppercase tracking-[0.3em] text-gray-500">New Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-4 top-3.5 text-gray-500 w-5 h-5"></i>
                    <input type="password" id="new_password" name="new_password" required autofocus
                           class="w-full bg-slate-950/60 border border-gray-800 rounded-2xl py-3.5 pl-12 pr-4 text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                           placeholder="Enter new password">
                </div>
            </div>

            <button type="submit"
                    class="w-full py-3.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-2xl shadow-lg shadow-red-900/30 transition">
                Reset Password
            </button>
        </form>

        <div class="flex items-center justify-center text-sm text-gray-400">
            <a href="login.php" class="flex items-center gap-2 hover:text-red-400 transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back to Login</span>
            </a>
        </div>
    </div>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>

