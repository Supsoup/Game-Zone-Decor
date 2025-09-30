<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { header('Location: orders.php'); exit; }

$err=''; $ok='';

// โหลดออเดอร์ + รายการสินค้า + สลิป
$st=$pdo->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$id]);
$order=$st->fetch();

$items = $pdo->prepare("
  SELECT oi.product_id, oi.qty, oi.unit_price, oi.line_total,
         p.name AS product_name
  FROM order_items oi
  JOIN products p ON p.id=oi.product_id
  WHERE oi.order_id=?");
$items->execute([$id]); $items=$items->fetchAll();

$slip = $pdo->prepare("SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1");
$slip->execute([$id]); $slip=$slip->fetch();

$token = admin_csrf_token();

// จัดการปุ่ม action
if ($_SERVER['REQUEST_METHOD']==='POST' && admin_csrf_check($_POST['csrf'] ?? '')) {
  try {
    if (isset($_POST['approve']) && $order['status']==='PAID_CHECKING') {
      $pdo->beginTransaction();
      $pdo->prepare("UPDATE orders SET status='PAID_CONFIRMED' WHERE id=?")->execute([$id]);
      if ($slip) {
        $pdo->prepare("UPDATE payments SET verified_by_admin=?, verified_at=NOW() WHERE id=?")
            ->execute([$_SESSION['admin']['id'], $slip['id']]);
      }
      $pdo->commit(); $ok='อนุมัติการชำระเงินเรียบร้อย'; $order['status']='PAID_CONFIRMED';
    }
    if (isset($_POST['reject']) && in_array($order['status'],['PENDING_PAYMENT','PAID_CHECKING'],true)) {
      // คืนสต็อก + ยกเลิก
      $pdo->beginTransaction();
      foreach ($items as $it) {
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id=?")->execute([$it['qty'],$it['product_id']]);
      }
      $pdo->prepare("UPDATE orders SET status='CANCELLED_INVALID' WHERE id=?")->execute([$id]);
      $pdo->commit(); $ok='ปฏิเสธการชำระเงินและคืนสต็อกแล้ว'; $order['status']='CANCELLED_INVALID';
    }
    if (isset($_POST['ship']) && $order['status']==='PAID_CONFIRMED') {
      $pdo->prepare("UPDATE orders SET status='SHIPPING' WHERE id=?")->execute([$id]);
      $ok='อัปเดตสถานะเป็นกำลังจัดส่งแล้ว'; $order['status']='SHIPPING';
    }
  } catch (Throwable $e) {
    $err='ผิดพลาด: '.$e->getMessage();
  }
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order #<?= $id ?> - Admin</title>
<link rel="stylesheet" href="../assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="orders.php">← กลับรายการ</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="logout.php">ออกจากระบบ</a>
</header>

<main class="container">
  <h2 class="section-title">คำสั่งซื้อ #<?= $id ?></h2>
  <?php if ($ok): ?><p style="color:green"><?= htmlspecialchars($ok) ?></p><?php endif; ?>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>

  <div class="checkout-form">
    <p><b>สถานะ:</b> <?= htmlspecialchars($order['status']) ?></p>
    <p><b>ยอดรวม:</b> <?= number_format((float)$order['total_amount'],2) ?> THB</p>
    <p><b>ชื่อผู้รับ:</b> <?= htmlspecialchars($order['shipping_name'] ?? '-') ?></p>
    <p><b>โทร:</b> <?= htmlspecialchars($order['shipping_phone'] ?? '-') ?></p>
    <p><b>ที่อยู่:</b><br><?= nl2br(htmlspecialchars($order['shipping_address'] ?? '-')) ?></p>
  </div>

  <h3 class="section-title">สินค้าในคำสั่งซื้อ</h3>
  <table class="cart-table">
    <tr><th>สินค้า</th><th>จำนวน</th><th>ราคา/ชิ้น</th><th>รวม</th></tr>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['product_name']) ?></td>
        <td><?= (int)$it['qty'] ?></td>
        <td><?= number_format((float)$it['unit_price']) ?> THB</td>
        <td><?= number_format((float)$it['line_total']) ?> THB</td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h3 class="section-title">สลิปชำระเงิน</h3>
  <?php if ($slip): ?>
    <div class="pd-media" style="max-width:520px">
      <img src="../<?= htmlspecialchars($slip['slip_path']) ?>" alt="slip" style="max-height:520px">
    </div>
  <?php else: ?>
    <p class="muted">ยังไม่มีการอัปโหลดสลิป</p>
  <?php endif; ?>

  <form method="post" style="margin-top:14px">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
    <?php if ($order['status']==='PAID_CHECKING'): ?>
      <button class="primary-btn" name="approve" value="1" onclick="return confirm('ยืนยันอนุมัติการชำระเงิน?')">อนุมัติ</button>
      <button class="btn-outline" name="reject" value="1" onclick="return confirm('ปฏิเสธสลิปและยกเลิกออเดอร์?')">ปฏิเสธ</button>
    <?php endif; ?>
    <?php if ($order['status']==='PAID_CONFIRMED'): ?>
      <button class="primary-btn" name="ship" value="1">อัปเดตเป็นกำลังจัดส่ง</button>
    <?php endif; ?>
  </form>
</main>
</body></html>
