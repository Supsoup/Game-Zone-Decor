<?php
// /account/profile.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/user.php';

require_user('/account/profile.php');

$uid = (int)$_SESSION['user']['id'];

// โหลดข้อมูลปัจจุบัน
$st = $pdo->prepare("SELECT id, email, name FROM users WHERE id=?");
$st->execute([$uid]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me) { echo "ไม่พบผู้ใช้"; exit; }

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $display = trim($_POST['name'] ?? '');

  // ถ้าผู้ใช้ลบให้ว่าง → กลับไปใช้ 'ส่วนหน้าของอีเมล'
  if ($display === '') {
    $display = strstr($me['email'], '@', true) ?: $me['email'];
  }

  // จำกัดความยาวแบบเบา ๆ
  if (mb_strlen($display) > 120) $display = mb_substr($display, 0, 120);

  $up = $pdo->prepare("UPDATE users SET name=? WHERE id=?");
  $up->execute([$display, $uid]);

  // อัปเดตเซสชันเพื่อให้หัวเว็บเปลี่ยนทันที
  $_SESSION['user']['name'] = $display;

  $msg = 'บันทึกแล้ว';
  // โหลดใหม่เผื่อมีทริกเกอร์/เปลี่ยนแปลงอื่น
  $st->execute([$uid]);
  $me = $st->fetch(PDO::FETCH_ASSOC);
}

// ชื่อที่แสดง (ถ้า name ว่าง ใช้ส่วนหน้าของอีเมล)
$defaultName = $me['name'];
if ($defaultName === null || $defaultName === '') {
  $defaultName = strstr($me['email'], '@', true) ?: $me['email'];
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>โปรไฟล์ของฉัน</title>
<link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
<header class="topbar">
  <a class="brand-pill" href="../index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($_SESSION['user']['name'] ?? $defaultName) ?></span>
  <a class="btn-outline" href="profile.php" style="margin-right:8px">โปรไฟล์</a>
  <a class="btn-outline" href="orders.php" style="margin-right:8px">คำสั่งซื้อของฉัน</a>
  <a class="btn-outline" href="../auth/logout.php">ออกจากระบบ</a>
</header>

<main class="container">
  <h2 class="section-title">โปรไฟล์ของฉัน</h2>

  <?php if ($msg): ?><p style="color:green"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>

  <form method="post" class="checkout-form" style="max-width:520px">
    <label>อีเมล (อ่านอย่างเดียว)
      <input type="email" value="<?= htmlspecialchars($me['email']) ?>" disabled>
    </label>

    <label>ชื่อที่แสดง
      <input type="text" name="name" value="<?= htmlspecialchars($defaultName) ?>" placeholder="ถ้าเว้นว่าง จะใช้ส่วนหน้าของอีเมล">
    </label>

    <button class="primary-btn" type="submit">บันทึก</button>
  </form>
</main>
</body>
</html>
