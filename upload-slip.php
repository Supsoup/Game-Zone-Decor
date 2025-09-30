<?php
session_start();
require_once __DIR__."/config/db.php";
require_once __DIR__."/lib/helpers.php";

if (function_exists('expireStaleOrders')) expireStaleOrders($pdo);

$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
if ($orderId<=0) { echo "ไม่มีหมายเลขคำสั่งซื้อ"; exit; }

$st=$pdo->prepare("SELECT id,user_id,status,expires_at FROM orders WHERE id=?");
$st->execute([$orderId]);
$order=$st->fetch(PDO::FETCH_ASSOC);
if (!$order) { echo "ไม่พบคำสั่งซื้อ"; exit; }

// ถ้าล็อกอินอยู่ ต้องเป็นเจ้าของออเดอร์เท่านั้น
if (!empty($_SESSION['user']['id']) && (int)$order['user_id'] !== (int)$_SESSION['user']['id']) {
  http_response_code(403);
  echo "<p style='font-family:Kanit,sans-serif;padding:16px;color:red'>คุณไม่มีสิทธิ์ในออเดอร์นี้</p>";
  exit;
}

if ($order['status']!=='PENDING_PAYMENT') {
  echo "<p style='font-family:Kanit,sans-serif;padding:16px'>สถานะปัจจุบัน: <b>{$order['status']}</b></p>";
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (empty($_FILES['slip']['tmp_name'])) { $err="กรุณาเลือกไฟล์"; }
  else {
    $allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = mime_content_type($_FILES['slip']['tmp_name']);
    if (!isset($allow[$mime])) $err="อนุญาต JPG/PNG/WEBP";
    if ($_FILES['slip']['size']>5*1024*1024) $err="ไฟล์ใหญ่เกิน 5MB";

    if (empty($err)) {
      if (!is_dir(__DIR__."/uploads/slips")) mkdir(__DIR__."/uploads/slips",0777,true);
      $fname="slip_{$orderId}_".time().".".$allow[$mime];
      if (!move_uploaded_file($_FILES['slip']['tmp_name'], __DIR__."/uploads/slips/".$fname)) {
        $err="อัปโหลดไฟล์ไม่สำเร็จ";
      } else {
        $pdo->beginTransaction();
        try{
          $pdo->prepare("INSERT INTO payments(order_id,slip_path,paid_at) VALUES (?,?,NOW())")->execute([$orderId,"uploads/slips/".$fname]);
          $pdo->prepare("UPDATE orders SET status='PAID_CHECKING' WHERE id=?")->execute([$orderId]);
          $pdo->commit();
          echo "<p style='font-family:Kanit,sans-serif;padding:20px'>อัปโหลดสลิปเรียบร้อย! สถานะ: <b>รอตรวจสอบ</b></p>";
          echo "<p><a href='account/orders.php'>กลับไปดูคำสั่งซื้อของฉัน</a></p>";
          exit;
        }catch(Throwable $e){ $pdo->rollBack(); $err="บันทึกไม่สำเร็จ: ".$e->getMessage(); }
      }
    }
  }
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8">
<title>อัปโหลดสลิป - Order #<?= $orderId ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/styles.css">
</head><body>
<header class="topbar"><a class="brand-pill" href="index.php">Game Zone Decor</a></header>
<main class="container">
  <h2 class="section-title">อัปโหลดสลิป (Order #<?= $orderId ?>)</h2>
  <?php if (!empty($err)): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form action="upload-slip.php" method="post" enctype="multipart/form-data" class="checkout-form">
    <input type="hidden" name="order_id" value="<?= $orderId ?>">
    <label>ไฟล์สลิป (JPG/PNG/WEBP ≤ 5MB)
      <input type="file" name="slip" accept="image/*" required>
    </label>
    <button class="primary-btn" type="submit">อัปโหลด</button>
  </form>
  <p class="muted" style="margin-top:8px"><a href="account/orders.php">กลับไปดูคำสั่งซื้อของฉัน</a></p>
</main>
</body></html>
