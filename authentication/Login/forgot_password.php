<?php
require_once __DIR__ . '/../../config/config.php';   // $conn (mysqli)
require_once __DIR__ . '/../../config/mailer.php';

$message = '';
$type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);

            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param('s', $email);
            $del->execute();
            $del->close();

            $ins = $conn->prepare(
                "INSERT INTO password_resets (email, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
            );
            $ins->bind_param('ss', $email, $hash);
            $ins->execute();
            $ins->close();

            $link = 'http://localhost/checkmate/authentication/Login/reset_password.php?token=' . $token;
            $html = "
              <div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto;background:#0f1318;color:#ece8df;padding:32px;border-radius:8px\">
                <h2 style=\"font-family:Georgia,serif;color:#c49b50;letter-spacing:2px\">CHECKMATE</h2>
                <p>We received a request to reset your password. Click the button below to set a new one. This link expires in 30 minutes.</p>
                <p style=\"margin:28px 0\"><a href=\"$link\" style=\"background:#c49b50;color:#0b0e12;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:bold\">Reset Password</a></p>
                <p style=\"color:#8a919b;font-size:13px\">If you didn't request this, you can safely ignore this email.</p>
              </div>";

            // Pang-test sa localhost: nakasave ang link sa C:\xampp\apache\logs\error.log
            // Burahin ito pag live na.
            

            sendMail($email, 'Reset your CHECKMATE password', $html);
        }
        // parehong message kahit hindi registered ang email
        $message = 'If that email is registered, a reset link has been sent. Please check your inbox.';
        $type = 'ok';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password | CHECKMATE</title>
  <link rel="stylesheet" href="../../asset/css/forgot_password.css">
</head>
<body>
  <section class="fp-left">
    <div class="fp-brand">&#9822; CHECKMATE</div>
    <div class="fp-eyebrow">ACCOUNT RECOVERY</div>
    <h2 class="fp-hero">Locked out?<br><span>We'll get you back in.</span></h2>
    <p class="fp-sub">Enter the email linked to your account and we'll send you a secure link to set a new password.</p>
  </section>

  <section class="fp-right">
    <div class="eyebrow">FORGOT PASSWORD</div>
    <h1>Reset your password</h1>
    <p class="lead">Enter your email address and we'll send you a reset link.</p>

    <?php if ($message): ?>
      <div class="alert <?= $type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <label for="email">Email address</label>
      <div class="field">
        <input type="email" id="email" name="email" placeholder="you@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <button type="submit" class="btn">Send reset link &rarr;</button>
    </form>

    <p class="back">Remembered it? <a href="login.php">Back to sign in</a></p>
  </section>
</body>
</html>