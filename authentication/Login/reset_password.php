<?php
require_once __DIR__ . '/../../config/config.php';   // $conn (mysqli)

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$valid = false;
$email = '';

if ($token !== '') {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT email FROM password_resets
         WHERE token_hash = ? AND used = 0 AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) { $valid = true; $email = $row['email']; }
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pwHash = password_hash($new, PASSWORD_DEFAULT);

        $up = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $up->bind_param('ss', $pwHash, $email);
        $up->execute();
        $up->close();

        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ?");
        $mark->bind_param('s', $email);
        $mark->execute();
        $mark->close();

        header('Location: login.php?reset=success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password | CHECKMATE</title>
  <link rel="stylesheet" href="../../asset/css/forgot_password.css">
</head>
<body>
  <section class="fp-left">
    <div class="fp-brand">&#9822; CHECKMATE</div>
    <div class="fp-eyebrow">ACCOUNT RECOVERY</div>
    <h2 class="fp-hero">Make your<br><span>next move.</span></h2>
    <p class="fp-sub">Choose a strong new password you haven't used before.</p>
  </section>

  <section class="fp-right">
  <?php if (!$valid): ?>
    <div class="eyebrow">LINK PROBLEM</div>
    <h1>Link invalid or expired</h1>
    <p class="lead">This reset link is no longer valid. Please request a new one.</p>
    <a href="forgot_password.php" class="btn" style="display:flex;align-items:center;justify-content:center;text-decoration:none">Request new link</a>
  <?php else: ?>
    <div class="eyebrow">NEW PASSWORD</div>
    <h1>Set a new password</h1>
    <p class="lead">Enter and confirm your new password below.</p>

    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" novalidate>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <label for="new_password">New password</label>
      <div class="field">
        <input type="password" id="new_password" name="new_password" placeholder="At least 8 characters" required>
        <button type="button" class="toggle" data-target="new_password">Show</button>
      </div>

      <label for="confirm_password">Confirm password</label>
      <div class="field">
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
        <button type="button" class="toggle" data-target="confirm_password">Show</button>
      </div>

      <button type="submit" class="btn">Reset password &rarr;</button>
    </form>
    <p class="back"><a href="login.php">Back to sign in</a></p>

    <script>
      document.querySelectorAll('.toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var input = document.getElementById(btn.dataset.target);
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.textContent = show ? 'Hide' : 'Show';
        });
      });
    </script>
  <?php endif; ?>
  </section>
</body>
</html>