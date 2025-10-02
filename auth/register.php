<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $pass2 = $_POST['password2'] ?? '';

  if ($email==='' || $pass==='' || $pass2==='') {
    $err = 'กรุณากรอกข้อมูลให้ครบ';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'อีเมลไม่ถูกต้อง';
  } elseif ($pass !== $pass2) {
    $err = 'รหัสผ่านไม่ตรงกัน';
  } elseif (strlen($pass) < 6) {
    $err = 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร';
  } else {
    // ตรวจซ้ำอีเมล
    $st = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $st->execute([$email]);
    if ($st->fetch()) {
      $err = 'อีเมลนี้ถูกใช้แล้ว';
    } else {
      // กำหนด name อัตโนมัติจากอีเมล (ส่วนหน้าก่อน @)
      $autoName = strstr($email, '@', true);
      if ($autoName === false || $autoName === '') $autoName = $email;

      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $ins  = $pdo->prepare("INSERT INTO users(name,email,password_hash,created_at) VALUES (?,?,?,NOW())");
      $ins->execute([$autoName,$email,$hash]);

      $_SESSION['user'] = ['id'=>$pdo->lastInsertId(),'name'=>$autoName,'email'=>$email];
      $to = $_SESSION['redirect_to'] ?? '../index.php';
      unset($_SESSION['redirect_to']);
      header("Location: $to");
      exit;
    }
  }
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>สมัครสมาชิก</title>
<link rel="stylesheet" href="../assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="../index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="login.php">เข้าสู่ระบบ</a>
</header>

<main class="container">
  <h2 class="section-title">สมัครสมาชิก</h2>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" class="checkout-form" style="max-width:520px">
    <label>อีเมล <input type="email" name="email" required></label>
    <label>รหัสผ่าน <input type="password" name="password" required></label>
    <label>ยืนยันรหัสผ่าน <input type="password" name="password2" required></label>
    <button class="primary-btn" type="submit">สมัครสมาชิก</button>
    <p class="muted">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
  </form>
</main>
</body></html>
