<?php
ob_start();
session_start();
require_once __DIR__ . "/config/db.php";

$me = $_SESSION['user'] ?? null;

// เพิ่มจาก product.php
if (($_POST['action'] ?? '') === 'add') {
  $id  = max(0, (int)($_POST['id'] ?? 0));
  $qty = max(1, (int)($_POST['qty'] ?? 1));
  if ($id > 0) {
    if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = ['qty'=>0];
    $_SESSION['cart'][$id]['qty'] += $qty;
  }
  header("Location: cart.php?added={$id}");
  exit;
}

// อัปเดต / ลบ / ล้าง
if (($_POST['action'] ?? '') === 'update') {
  foreach (($_POST['qty'] ?? []) as $pid=>$q) {
    $pid=(int)$pid; $q=max(0,(int)$q);
    if ($q===0) unset($_SESSION['cart'][$pid]); else $_SESSION['cart'][$pid]['qty']=$q;
  }
  header("Location: cart.php?updated=1"); exit;
}
if (($_GET['action'] ?? '') === 'remove') { unset($_SESSION['cart'][(int)($_GET['id']??0)]); header("Location: cart.php?removed=1"); exit; }
if (($_GET['action'] ?? '') === 'clear')  { unset($_SESSION['cart']); header("Location: cart.php?cleared=1"); exit; }

// โหลดรายการจาก DB
$items=[]; $total=0.0;
if (!empty($_SESSION['cart'])) {
  $ids=array_values(array_filter(array_map('intval', array_keys($_SESSION['cart'])), fn($v)=>$v>0));
  if ($ids) {
    $in=implode(',', array_fill(0,count($ids),'?'));
    $st=$pdo->prepare("SELECT id,name,price,COALESCE(image_url,'assets/no-image.png') AS img FROM products WHERE id IN ($in)");
    $st->execute($ids); $map=[];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) $map[(int)$p['id']]=$p;
    foreach ($ids as $pid) {
      if (!isset($map[$pid])) continue;
      $qty=(int)($_SESSION['cart'][$pid]['qty'] ?? 0); if ($qty<=0) continue;
      $price=(float)$map[$pid]['price']; $line=$price*$qty; $total+=$line;
      $items[]=['id'=>$pid,'name'=>$map[$pid]['name'],'img'=>$map[$pid]['img'],'price'=>$price,'qty'=>$qty,'line'=>$line];
    }
  }
}

// CSRF (สำรองไว้ใช้ต่อ)
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
$csrf=$_SESSION['csrf'];
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตะกร้าสินค้า - Game Zone Decor</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <?php if ($me): ?>
    <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($me['name']) ?></span>
    <a class="btn-outline" href="account/orders.php" style="margin-right:8px">คำสั่งซื้อของฉัน</a>
    <a class="btn-outline" href="auth/logout.php">ออก</a>
  <?php else: ?>
    <a class="btn-outline" href="auth/login.php">เข้าสู่ระบบ</a>
    <a class="primary-btn" href="auth/register.php" style="margin-left:8px">สมัครสมาชิก</a>
  <?php endif; ?>
</header>

<main class="container">
  <h2 class="section-title">ตะกร้าสินค้า</h2>

  <?php if (!empty($_GET['added'])): ?><p class="muted">เพิ่มสินค้า #<?= (int)$_GET['added'] ?> แล้ว</p><?php endif; ?>
  <?php if (!empty($_GET['updated'])): ?><p class="muted">อัปเดตจำนวนแล้ว</p><?php endif; ?>
  <?php if (!empty($_GET['removed'])): ?><p class="muted">ลบสินค้าแล้ว</p><?php endif; ?>
  <?php if (!empty($_GET['cleared'])): ?><p class="muted">ล้างตะกร้าแล้ว</p><?php endif; ?>

  <?php if (empty($items)): ?>
    <p class="muted">ตะกร้าว่างเปล่า</p>
  <?php else: ?>
    <form method="post" action="cart.php">
      <input type="hidden" name="action" value="update">
      <table class="cart-table">
        <tr><th>สินค้า</th><th>ราคา</th><th>จำนวน</th><th>รวม</th><th></th></tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><img src="<?= htmlspecialchars($it['img']) ?>" width="60" alt="" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'"> <?= htmlspecialchars($it['name']) ?></td>
            <td><?= number_format($it['price']) ?> THB</td>
            <td><input type="number" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>" min="0" style="width:80px"></td>
            <td><?= number_format($it['line']) ?> THB</td>
            <td><a href="cart.php?action=remove&id=<?= (int)$it['id'] ?>">ลบ</a></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" align="right"><b>รวมทั้งหมด</b></td>
          <td><b><?= number_format($total) ?> THB</b></td>
          <td></td>
        </tr>
      </table>
      <button class="btn-outline" type="submit">อัปเดตจำนวน</button>
      <a class="btn-outline" href="cart.php?action=clear" onclick="return confirm('ล้างตะกร้าทั้งหมด?')">ล้างตะกร้า</a>
      <a class="primary-btn" href="checkout.php">ดำเนินการสั่งซื้อ</a>
    </form>
  <?php endif; ?>
</main>

<footer class="footer"><p>© <?= date('Y') ?> Game Zone Decor</p></footer>
</body></html>
