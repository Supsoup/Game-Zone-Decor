<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/user.php';

require_user('/upload-slip.php');

// หมดอายุตามเงื่อนไขและคืนสต็อกทุกครั้งที่เข้าหน้า
expireStaleOrders($pdo);

$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
if ($orderId <= 0) { echo 'ไม่พบคำสั่งซื้อ'; exit; }

// โหลดออเดอร์ของผู้ใช้ พร้อม “วินาทีที่เหลือ” ให้ฐานข้อมูลคำนวณ
$st = $pdo->prepare("
  SELECT o.*,
         (SELECT slip_path FROM payments p WHERE p.order_id=o.id ORDER BY id DESC LIMIT 1) AS slip_path,
         -- เหลือกี่วินาทีจาก NOW() จนถึง expires_at (ถ้า expires_at เป็น NULL ให้ใช้ placed_at + 10 นาที)
         GREATEST(0, TIMESTAMPDIFF(
           SECOND, NOW(), COALESCE(o.expires_at, DATE_ADD(o.placed_at, INTERVAL 10 MINUTE))
         )) AS remain_seconds
  FROM orders o
  WHERE o.id=? AND o.user_id=?
");
$st->execute([$orderId, $_SESSION['user']['id']]);
$order = $st->fetch(PDO::FETCH_ASSOC);
if (!$order) { echo 'ไม่พบคำสั่งซื้อของคุณ'; exit; }

$csrf = ensure_csrf();
$pendingStatuses = ['PENDING_PAYMENT','PENDING']; // เผื่อข้อมูลเก่า
$remain = (int)$order['remain_seconds'];
$canCancel = in_array($order['status'],$pendingStatuses,true) && $remain > 0;

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip'])) {
  if (!in_array($order['status'], $pendingStatuses, true)) {
    $err = 'คำสั่งซื้อนี้ไม่อยู่ในสถานะที่อัปโหลดสลิปได้';
  } else {
    $tmp = $_FILES['slip']['tmp_name'] ?? '';
    if (!$tmp) $err = 'กรุณาเลือกไฟล์';
    else {
      $mime  = @mime_content_type($tmp);
      $allow = ['image/jpeg','image/png','image/webp'];
      if (!in_array($mime, $allow, true))          $err = 'ต้องเป็นไฟล์ JPG/PNG/WEBP เท่านั้น';
      elseif (($_FILES['slip']['size'] ?? 0) > 5*1024*1024) $err = 'ไฟล์ใหญ่เกิน 5MB';
      else {
        $dir = __DIR__ . '/uploads/slips'; if (!is_dir($dir)) mkdir($dir,0777,true);
        $ext = $mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');
        $name = 'SLIP_'.$orderId.'_'.time().'_'.rand(1000,9999).'.'.$ext;
        move_uploaded_file($tmp, $dir.'/'.$name);
        $rel = 'uploads/slips/'.$name;

        $pdo->prepare("INSERT INTO payments(order_id, slip_path, paid_at) VALUES (?,?,NOW())")
            ->execute([$orderId, $rel]);
        $pdo->prepare("UPDATE orders SET status='PAID_CHECKING' WHERE id=? AND status IN ('PENDING_PAYMENT','PENDING')")
            ->execute([$orderId]);

        header('Location: upload-slip.php?order_id='.$orderId.'&ok=1'); exit;
      }
    }
  }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>อัปโหลดสลิป • ออเดอร์ #<?= (int)$order['id'] ?></title>
<link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="topbar">
  <a class="brand-pill" href="index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="account/orders.php">คำสั่งซื้อของฉัน</a>
  <a class="btn-outline" href="auth/logout.php">ออก</a>
</header>

<main class="container">
  <h2 class="section-title">อัปโหลดสลิป • ออเดอร์ #<?= (int)$order['id'] ?></h2>

  <?php if (!empty($_GET['ok'])): ?>
    <p style="color:green">อัปโหลดสลิปเรียบร้อย ระบบจะตรวจสอบโดยผู้ดูแล</p>
  <?php endif; ?>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>

  <p>
    สถานะปัจจุบัน: <strong><?= htmlspecialchars($order['status']) ?></strong>
    <?php if (in_array($order['status'],$pendingStatuses,true)): ?>
      • หมดเวลา: <?= htmlspecialchars($order['expires_at']) ?>
      <?php if ($canCancel): ?>
        • <span class="muted">เหลือเวลา:</span>
          <strong class="countdown" data-remaining="<?= (int)$remain ?>">--:--</strong>
      <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if (!empty($order['slip_path'])): ?>
    <div class="pd-media" style="max-width:420px"><img src="<?= htmlspecialchars($order['slip_path']) ?>" alt="slip"></div>
  <?php endif; ?>

  <?php if (in_array($order['status'],$pendingStatuses,true)): ?>
    <form method="post" enctype="multipart/form-data" class="checkout-form" style="max-width:520px">
      <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
      <label>แนบสลิป (JPG/PNG/WEBP ≤ 5MB)
        <input type="file" name="slip" accept="image/*" required>
      </label>
      <button class="primary-btn" type="submit">อัปโหลดสลิป</button>
    </form>

    <div style="margin-top:12px">
      <?php if ($canCancel): ?>
        <form method="post" action="account/cancel_order.php"
              data-cancel
              onsubmit="return confirm('ยืนยันยกเลิกออเดอร์นี้?');" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
          <button class="btn-outline" type="submit">ยกเลิกออเดอร์ (ภายใน 5 นาที)</button>
        </form>
      <?php else: ?>
        <span class="muted">หมดช่วงเวลาการยกเลิกอัตโนมัติแล้ว</span>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="muted">คำสั่งซื้อนี้ไม่อยู่ในสถานะที่อัปโหลดสลิปได้</p>
  <?php endif; ?>

  <p style="margin-top:16px"><a class="btn-outline" href="account/orders.php">← กลับไปคำสั่งซื้อของฉัน</a></p>
</main>

<script>
// นับถอยหลังจาก "วินาทีที่เหลือ" โดยตรง (ไม่พึ่งเวลาเครื่อง/โซนเวลา)
(function(){
  document.querySelectorAll('.countdown').forEach(el=>{
    let sec = parseInt(el.dataset.remaining,10);
    const form = document.querySelector('form[data-cancel]');
    const btn  = form ? form.querySelector('button') : null;

    function tick(){
      if (isNaN(sec) || sec <= 0){
        el.textContent = '00:00';
        if (btn){ btn.disabled = true; btn.textContent = 'หมดเวลายกเลิก'; }
        return;
      }
      const m = String(Math.floor(sec/60)).padStart(2,'0');
      const s = String(sec%60).padStart(2,'0');
      el.textContent = m+':'+s;
      sec--;
      setTimeout(tick, 1000);
    }
    tick();
  });
})();
</script>
</body>
</html>
