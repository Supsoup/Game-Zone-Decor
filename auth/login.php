<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $st = $pdo->prepare("SELECT id,name,email,password_hash FROM users WHERE email=?");
  $st->execute([$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  $ok=false;
  if ($u) {
    $info = password_get_info($u['password_hash'] ?? '');
    if (!empty($info['algo'])) $ok = password_verify($pass, $u['password_hash']);
    else $ok = hash_equals($u['password_hash'], $pass); // กันพังก่อน
  }

  if ($ok) {
    $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email']];
    $to = $_SESSION['redirect_to'] ?? '../index.php';
    unset($_SESSION['redirect_to']);
    header("Location: $to"); exit;
  } else {
    $err = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
  }
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ</title>
<link rel="stylesheet" href="../assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="../index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="register.php">สมัครสมาชิก</a>
</header>
<main class="container">
  <h2 class="section-title">เข้าสู่ระบบ</h2>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" class="checkout-form" style="max-width:420px">
    <label>อีเมล <input type="email" name="email" required></label>
    <label>รหัสผ่าน <input type="password" name="password" required></label>
    <button class="primary-btn" type="submit">เข้าสู่ระบบ</button>
    <p class="muted">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
  </form>
</main>
</body></html>
