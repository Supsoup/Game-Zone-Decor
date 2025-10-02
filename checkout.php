<?php
// checkout.php — สรุปออเดอร์ + ฟอร์มที่อยู่ จบที่ place_order.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/user.php';
require_user('/checkout.php');

// ดึงตะกร้า
$cart = $_SESSION['cart'] ?? ($_SESSION['cart_items'] ?? []);
if (!$cart || !is_array($cart)) {
  header('Location: cart.php'); exit;
}

// โหลดสินค้าในตะกร้า
$ids = array_map('intval', array_keys($cart));
$in  = implode(',', array_fill(0, count($ids), '?'));
$st  = $pdo->prepare("SELECT id, name, price, COALESCE(image_url,'assets/no-image.png') AS img FROM products WHERE id IN ($in)");
$st->execute($ids);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// คำนวณยอด
$items = []; $total = 0.0;
foreach ($rows as $r) {
  $qty = max(1, (int)($cart[$r['id']] ?? 0));
  $line = $qty * (float)$r['price'];
  $items[] = ['id'=>$r['id'], 'name'=>$r['name'], 'price'=>$r['price'], 'qty'=>$qty, 'line'=>$line, 'img'=>$r['img']];
  $total += $line;
}
$err = $_GET['err'] ?? '';
$me  = $_SESSION['user'] ?? null;
$prefillName  = $me['name'] ?? '';
$prefillAddr  = '';
$prefillPhone = '';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เช็คเอาต์</title>
<link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="topbar">
  <a class="brand-pill" href="index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="account/orders.php" style="margin-right:8px">คำสั่งซื้อของฉัน</a>
  <a class="btn-outline" href="auth/logout.php">ออกจากระบบ</a>
</header>

<main class="container">
  <h2 class="section-title">เช็คเอาต์</h2>

  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>

  <div class="cart-table" style="padding:0">
    <table class="cart-table" style="margin:0">
      <tr><th>สินค้า</th><th>ราคา</th><th>จำนวน</th><th>รวม</th></tr>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['name']) ?></td>
          <td><?= number_format((float)$it['price']) ?> THB</td>
          <td><?= (int)$it['qty'] ?></td>
          <td><?= number_format((float)$it['line']) ?> THB</td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="3" style="text-align:right;font-weight:700">รวมทั้งหมด</td>
        <td style="font-weight:700"><?= number_format((float)$total) ?> THB</td>
      </tr>
    </table>
  </div>

  <form class="checkout-form" method="post" action="place_order.php" style="max-width:820px">
    <label>ชื่อ-นามสกุล
      <input type="text" name="shipping_name" value="<?= htmlspecialchars($prefillName) ?>" required>
    </label>
    <label>ที่อยู่จัดส่ง
      <textarea name="shipping_address" rows="3" required><?= htmlspecialchars($prefillAddr) ?></textarea>
    </label>
    <label>เบอร์โทร
      <input type="text" name="shipping_phone" value="<?= htmlspecialchars($prefillPhone) ?>" required>
    </label>
    <button class="primary-btn" type="submit">ยืนยันคำสั่งซื้อ</button>
  </form>
</main>
</body>
</html>
