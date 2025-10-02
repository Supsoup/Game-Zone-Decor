<?php
// /account/cancel_order.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/helpers.php';

if (empty($_SESSION['user'])) {
  $_SESSION['redirect_to'] = '/account/orders.php';
  header('Location: ../auth/login.php'); exit;
}

$uid  = (int)$_SESSION['user']['id'];
$csrf = $_POST['csrf'] ?? '';
if ($csrf !== ($_SESSION['csrf'] ?? '')) {
  header('Location: orders.php?cancel_error=CSRF+invalid'); exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) { header('Location: orders.php?cancel_error=ไม่พบคำสั่งซื้อ'); exit; }

try {
  $pdo->beginTransaction();

  // ล็อกออเดอร์ก่อนเพื่อตรวจสอบ
  $st = $pdo->prepare("SELECT id, user_id, status, placed_at FROM orders WHERE id=? FOR UPDATE");
  $st->execute([$orderId]);
  $o = $st->fetch(PDO::FETCH_ASSOC);
  if (!$o) { throw new RuntimeException('ไม่พบคำสั่งซื้อ'); }
  if ((int)$o['user_id'] !== $uid) { throw new RuntimeException('คุณไม่มีสิทธิ์ในคำสั่งซื้อนี้'); }
  if ($o['status'] !== 'PENDING_PAYMENT') { throw new RuntimeException('สถานะปัจจุบันไม่สามารถยกเลิกได้'); }

  // ตรวจเวลาไม่เกิน 5 นาทีจาก placed_at
  $elapsed = time() - strtotime($o['placed_at']);
  if ($elapsed > 300) { throw new RuntimeException('เกินเวลายกเลิก 5 นาทีแล้ว'); }

  // คืนสต็อก
  $it = $pdo->prepare("SELECT product_id, qty FROM order_items WHERE order_id=?");
  $it->execute([$orderId]);
  $rows = $it->fetchAll(PDO::FETCH_ASSOC);

  $up = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
  foreach ($rows as $r) {
    $up->execute([(int)$r['qty'], (int)$r['product_id']]);
  }

  // เปลี่ยนสถานะเป็น CANCELLED
  $pdo->prepare("UPDATE orders SET status='CANCELLED' WHERE id=?")->execute([$orderId]);

  $pdo->commit();
  header('Location: orders.php?cancel_success=1'); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $msg = urlencode($e->getMessage());
  header("Location: orders.php?cancel_error={$msg}"); exit;
}
