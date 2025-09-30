<?php
require_once __DIR__ . '/_bootstrap.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $st = $pdo->prepare("SELECT id,name,email,password_hash,role FROM admins WHERE email = ?");
  $st->execute([$email]);
  $ad = $st->fetch(PDO::FETCH_ASSOC);
  if ($ad && password_verify($pass, $ad['password_hash'])) {
  $_SESSION['admin'] = ['id'=>$ad['id'],'name'=>$ad['name'],'role'=>$ad['role']];
  header('Location: orders.php'); exit;
} else {
  $err = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}
$ok = false;
if ($ad) {
  // ถ้าเป็น hash จริง ๆ (bcrypt ฯลฯ) จะมี algo
  $info = password_get_info($ad['password_hash'] ?? '');
  if (!empty($info['algo'])) {
    // เก็บแบบ hash -> ตรวจด้วย password_verify
    $ok = password_verify($pass, $ad['password_hash']);
  } else {
    // เก็บเป็นตัวอักษรดิบ (เช่น "123") -> ยอมรับชั่วคราว
    $ok = hash_equals($ad['password_hash'], $pass);
  }
}

if ($ok) {
  $_SESSION['admin'] = ['id'=>$ad['id'],'name'=>$ad['name'],'role'=>$ad['role']];
  header('Location: orders.php'); exit;
} else {
  $err = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}


}
?>

<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<link rel="stylesheet" href="../assets/styles.css">
</head><body>
<header class="topbar"><a class="brand-pill" href="../index.php">Game Zone Decor (Admin)</a></header>
<main class="container">
  <h2 class="section-title">เข้าสู่ระบบผู้ดูแล</h2>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" class="checkout-form" style="max-width:420px">
    <label>อีเมล <input type="email" name="email" required></label>
    <label>รหัสผ่าน <input type="password" name="password" required></label>
    <button class="primary-btn" type="submit">เข้าสู่ระบบ</button>
    <p class="muted">ยังไม่มีผู้ดูแล? ไปที่ <a href="install.php">ติดตั้งแอดมินคนแรก</a></p>
  </form>
</main>
</body></html>
