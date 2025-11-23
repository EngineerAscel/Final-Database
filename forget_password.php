<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

include('db.php');

$correct = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Normalize email (remove spaces + lowercase)
    $email = trim(strtolower($_POST['email']));

    // Check if email exists in usermanagement (case-insensitive)
    $stmt = $conn->prepare("SELECT * FROM usermanagement WHERE LOWER(email) = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Generate token + expiry
        $token = bin2hex(random_bytes(15));
        $expiry = date("Y-m-d H:i:s", strtotime('+24 hours'));

        // Save token into DB
        $stmt2 = $conn->prepare("UPDATE usermanagement SET reset_token=?, token_expiry=? WHERE LOWER(email)=?");
        $stmt2->bind_param("sss", $token, $expiry, $email);

        if ($stmt2->execute()) {
            $resetLink = "http://localhost/fix/reset_password.php?token=" . $token;

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'ascelglimer2@gmail.com';
                $mail->Password = 'gpty cwmm gmhr onxg'; // Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('ascelglimer2@gmail.com', '1Garage Support');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset - 1Garage';
                $mail->Body = "Click the link to reset your password:<br><a href='$resetLink'>$resetLink</a>";

                $mail->send();
                $correct = "✅ Password reset link has been sent to your email.";
            } catch (Exception $e) {
                $error = "❌ Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "❌ Error saving token: " . $conn->error;
        }
    } else {
        $error = "❌ No account found with that email.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - 1Garage</title>
    <link rel="stylesheet" href="csss/forget_password.css">
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
            <p class="text-sm text-gray-400">Enter your email to receive a password reset link.</p>
        </div>

        <?php if (!empty($correct)): ?>
            <div class="bg-green-500/10 border border-green-500/40 text-green-200 text-sm rounded-xl px-4 py-3 text-center">
                <?= htmlspecialchars($correct) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/40 text-red-200 text-sm rounded-xl px-4 py-3 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forget_password.php" class="space-y-5">
            <div class="space-y-2">
                <label for="email" class="text-sm uppercase tracking-[0.3em] text-gray-500">Email Address</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-4 top-3.5 text-gray-500 w-5 h-5"></i>
                    <input type="email" id="email" name="email" required autofocus
                           class="w-full bg-slate-950/60 border border-gray-800 rounded-2xl py-3.5 pl-12 pr-4 text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                           placeholder="your.email@example.com">
                </div>
            </div>

            <button type="submit"
                    class="w-full py-3.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-2xl shadow-lg shadow-red-900/30 transition">
                Send Reset Link
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
