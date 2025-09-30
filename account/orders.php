<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['user'])) {
  $_SESSION['redirect_to'] = '/account/orders.php';
  header('Location: ../auth/login.php'); exit;
}

$uid = (int)$_SESSION['user']['id'];
$st = $pdo->prepare("
  SELECT o.id, o.status, o.total_amount, o.placed_at, o.expires_at,
         (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_count,
         (SELECT slip_path FROM payments p WHERE p.order_id=o.id ORDER BY id DESC LIMIT 1) AS slip_path
  FROM orders o
  WHERE o.user_id = ?
  ORDER BY o.placed_at DESC
  LIMIT 100
");
$st->execute([$uid]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>คำสั่งซื้อของฉัน</title>
<link rel="stylesheet" href="../assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="../index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
  <a class="btn-outline" href="../auth/logout.php">ออก</a>
</header>

<main class="container">
  <h2 class="section-title">คำสั่งซื้อของฉัน</h2>
  <table class="cart-table">
    <tr><th>#</th><th>สถานะ</th><th>รายการ</th><th>ยอดรวม</th><th>สั่งเมื่อ</th><th>สลิป</th><th>การชำระเงิน</th></tr>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td>#<?= (int)$o['id'] ?></td>
        <td><?= htmlspecialchars($o['status']) ?></td>
        <td><?= (int)$o['item_count'] ?> ชิ้น</td>
        <td><?= number_format((float)$o['total_amount']) ?> THB</td>
        <td><?= htmlspecialchars($o['placed_at']) ?></td>
        <td><?= $o['slip_path'] ? 'มี' : '-' ?></td>
        <td>
          <?php if ($o['status']==='PENDING_PAYMENT'): ?>
            <a class="btn-outline" href="../upload-slip.php?order_id=<?= (int)$o['id'] ?>">อัปโหลดสลิป</a>
          <?php elseif ($o['status']==='PAID_CHECKING'): ?>
            รอตรวจสอบ
          <?php elseif ($o['status']==='PAID_CONFIRMED'): ?>
            ชำระแล้ว
          <?php elseif ($o['status']==='SHIPPING'): ?>
            กำลังจัดส่ง
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?>
      <tr><td colspan="7" class="muted">ยังไม่มีคำสั่งซื้อ</td></tr>
    <?php endif; ?>
  </table>
</main>
</body></html>
