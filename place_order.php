<?php
// place_order.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/user.php';
require_user('/checkout.php');

$uid = (int)$_SESSION['user']['id'];

// รับข้อมูลที่อยู่จัดส่ง
$name    = trim($_POST['shipping_name']    ?? '');
$addr    = trim($_POST['shipping_address'] ?? '');
$phone   = trim($_POST['shipping_phone']   ?? '');
if ($name==='' || $addr==='' || $phone==='') {
  header('Location: checkout.php?err=กรอกข้อมูลให้ครบ'); exit;
}

// อ่านตะกร้า (รองรับทั้ง $_SESSION['cart'] และ 'cart_items')
$cart = $_SESSION['cart'] ?? ($_SESSION['cart_items'] ?? []);
if (!$cart || !is_array($cart)) {
  header('Location: cart.php'); exit;
}

// เตรียมสินค้า/ราคา
$items = []; $total = 0.0;
$st = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id=?");
foreach ($cart as $pid => $qty) {
  $pid = (int)$pid; $qty = max(1, (int)$qty);
  $st->execute([$pid]);
  $p = $st->fetch(PDO::FETCH_ASSOC);
  if (!$p) continue;

  // ตรวจสต็อกพอหรือไม่
  if ((int)$p['stock'] < $qty) {
    header('Location: cart.php?err=สินค้า "'.urlencode($p['name']).'" สต็อกไม่พอ'); exit;
  }
  $line = $qty * (float)$p['price'];
  $items[] = ['id'=>$pid,'name'=>$p['name'],'qty'=>$qty,'price'=>(float)$p['price'],'line'=>$line];
  $total  += $line;
}
if (empty($items)) { header('Location: cart.php'); exit; }

try {
  $pdo->beginTransaction();

  // สร้างออเดอร์: สถานะเริ่ม PENDING_PAYMENT และให้หมดอายุใน 10 นาที
  $ins = $pdo->prepare("
    INSERT INTO orders (user_id, status, total_amount, placed_at, expires_at,
                        shipping_name, shipping_address, shipping_phone)
    VALUES (?, 'PENDING_PAYMENT', ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, ?, ?)
  ");
  $ins->execute([$uid, $total, $name, $addr, $phone]);
  $oid = (int)$pdo->lastInsertId();

  // รายการสินค้า + ตัดสต็อก
  $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, unit_price, line_total)
                            VALUES (?,?,?,?,?)");
  $dec = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");
  foreach ($items as $it) {
    $insItem->execute([$oid, $it['id'], $it['qty'], $it['price'], $it['line']]);
    $dec->execute([$it['qty'], $it['id'], $it['qty']]);
    if ($dec->rowCount() === 0) { // กัน race condition
      throw new RuntimeException('สต็อกไม่พอ');
    }
  }

  $pdo->commit();

  // ล้างตะกร้า
  unset($_SESSION['cart'], $_SESSION['cart_items']);

  // ไปหน้าอัปโหลดสลิป/ยกเลิกภายใน 5 นาที
  header('Location: upload-slip.php?order_id=' . $oid); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: checkout.php?err=' . urlencode('สร้างคำสั่งซื้อไม่สำเร็จ: '.$e->getMessage())); exit;
}
