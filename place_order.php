<?php
session_start();
require_once __DIR__."/config/db.php";
require_once __DIR__."/lib/user.php";

require_user('checkout.php');
$userId = (int)($_SESSION['user']['id'] ?? 0);

if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); echo "Invalid CSRF"; exit; }
if (empty($_SESSION['cart'])) { header("Location: cart.php"); exit; }

$fullname = trim($_POST['fullname'] ?? '');
$address  = trim($_POST['address'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
if ($fullname==='' || $address==='' || $phone==='') {
  echo "<p style='font-family:Kanit,sans-serif;padding:20px;color:red'>กรอกข้อมูลจัดส่งให้ครบ</p>";
  echo "<p><a href='checkout.php'>ย้อนกลับ</a></p>"; exit;
}

$ids = array_map('intval', array_keys($_SESSION['cart']));
$in  = implode(',', array_fill(0,count($ids),'?'));
$st  = $pdo->prepare("SELECT id,price,stock FROM products WHERE id IN ($in) FOR UPDATE");

$pdo->beginTransaction();
try {
  $st->execute($ids);
  $rows = $st->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

  $total=0; $items=[];
  foreach ($_SESSION['cart'] as $pid=>$row) {
    if (empty($rows[$pid])) throw new Exception("ไม่พบสินค้า ID $pid");
    $qty   = (int)$row['qty'];
    $price = (float)$rows[$pid]['price'];
    $stock = (int)$rows[$pid]['stock'];
    if ($qty<=0) continue;
    if ($stock < $qty) throw new Exception("สต็อกไม่พอสำหรับสินค้า ID $pid");
    $total += $price*$qty;
    $items[] = ['id'=>$pid,'qty'=>$qty,'price'=>$price];
  }

  $o = $pdo->prepare("
    INSERT INTO orders(user_id,status,total_amount,placed_at,expires_at,shipping_name,shipping_address,shipping_phone)
    VALUES (?,?,?,?,?,?,?,?)
  ");
  $o->execute([
    $userId, 'PENDING_PAYMENT', $total,
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s', time()+600), // 10 นาที
    $fullname, $address, $phone
  ]);
  $orderId = (int)$pdo->lastInsertId();

  $oi = $pdo->prepare("INSERT INTO order_items(order_id,product_id,qty,unit_price,line_total) VALUES (?,?,?,?,?)");
  $de = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
  foreach ($items as $it) {
    $oi->execute([$orderId,$it['id'],$it['qty'],$it['price'],$it['price']*$it['qty']]);
    $de->execute([$it['qty'],$it['id']]);
  }

  $pdo->commit();
  unset($_SESSION['cart']);
  $_SESSION['last_order_id'] = $orderId;
  header("Location: upload-slip.php?order_id=".$orderId);
  exit;
} catch (Throwable $e) {
  $pdo->rollBack();
  echo "<p style='font-family:Kanit,sans-serif;padding:20px;color:red'>ผิดพลาด: ".$e->getMessage()."</p>";
  echo "<p><a href='cart.php'>กลับตะกร้า</a></p>";
  exit;
}
