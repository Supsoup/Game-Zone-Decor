<?php
session_start();
require_once __DIR__."/config/db.php";
require_once __DIR__."/lib/user.php";

require_user('checkout.php');  // ต้องเป็นสมาชิกก่อนเช็คเอาต์
$me = $_SESSION['user'] ?? null;

if (empty($_SESSION['cart'])) { header("Location: cart.php"); exit; }

$ids = array_map('intval', array_keys($_SESSION['cart']));
$in  = implode(',', array_fill(0,count($ids),'?'));
$st  = $pdo->prepare("SELECT id,name,price FROM products WHERE id IN ($in)");
$st->execute($ids); $map=[];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) $map[$p['id']]=$p;

$items=[]; $total=0;
foreach ($_SESSION['cart'] as $pid=>$row) {
  if (!isset($map[$pid])) continue;
  $qty=(int)$row['qty']; $price=(float)$map[$pid]['price']; $line=$qty*$price;
  $total += $line; $items[]=['name'=>$map[$pid]['name'],'qty'=>$qty,'price'=>$price,'line'=>$line];
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
$csrf=$_SESSION['csrf'];
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>เช็คเอาต์ - Game Zone Decor</title>
<link rel="stylesheet" href="assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($me['name']) ?></span>
  <a class="btn-outline" href="account/orders.php" style="margin-right:8px">คำสั่งซื้อของฉัน</a>
  <a class="btn-outline" href="auth/logout.php">ออก</a>
</header>

<main class="container">
  <h2 class="section-title">เช็คเอาต์</h2>
  <div class="checkout-form">
    <div>
      <h3>สรุปรายการ</h3>
      <table class="cart-table">
        <tr><th>สินค้า</th><th>ราคา</th><th>จำนวน</th><th>รวม</th></tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['name']) ?></td>
            <td><?= number_format($it['price']) ?> THB</td>
            <td><?= (int)$it['qty'] ?></td>
            <td><?= number_format($it['line']) ?> THB</td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" align="right"><b>รวมทั้งหมด</b></td>
          <td><b><?= number_format($total) ?> THB</b></td>
        </tr>
      </table>
    </div>

    <form action="place_order.php" method="post">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <label>ชื่อ-นามสกุล <input type="text" name="fullname" required></label>
      <label>ที่อยู่จัดส่ง <textarea name="address" required></textarea></label>
      <label>เบอร์โทร <input type="text" name="phone" required></label>
      <button class="primary-btn" type="submit">ยืนยันคำสั่งซื้อ</button>
    </form>
  </div>
</main>
</body></html>
