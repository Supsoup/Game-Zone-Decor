<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/helpers.php';

if (empty($_SESSION['user'])) {
  $_SESSION['redirect_to'] = '/account/orders.php';
  header('Location: ../auth/login.php'); exit;
}

// หมดอายุ/คืนสต็อก
expireStaleOrders($pdo);

$uid = (int)$_SESSION['user']['id'];
$st = $pdo->prepare("
  SELECT o.id, o.status, o.total_amount, o.placed_at, o.expires_at,
         (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_count,
         (SELECT slip_path FROM payments p WHERE p.order_id=o.id ORDER BY id DESC LIMIT 1) AS slip_path,
         GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), COALESCE(o.expires_at, DATE_ADD(o.placed_at, INTERVAL 10 MINUTE)))) AS remain_seconds
  FROM orders o
  WHERE o.user_id = ?
  ORDER BY o.placed_at DESC
  LIMIT 100
");
$st->execute([$uid]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

$csrf = ensure_csrf();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>คำสั่งซื้อของฉัน</title>
<link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
<header class="topbar">
  <a class="brand-pill" href="../index.php">Game Zone Decor</a>
  <div style="flex:1"></div>
  <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
  <a class="btn-outline" href="../auth/logout.php">ออก</a>
</header>

<main class="container">
  <h2 class="section-title">คำสั่งซื้อของฉัน</h2>

  <?php if (!empty($_GET['cancel_success'])): ?>
    <p style="color:green">ยกเลิกคำสั่งซื้อเรียบร้อย และคืนสต็อกแล้ว</p>
  <?php elseif (!empty($_GET['cancel_error'])): ?>
    <p style="color:red"><?= htmlspecialchars($_GET['cancel_error']) ?></p>
  <?php endif; ?>

  <table class="cart-table">
    <tr>
      <th>#</th><th>สถานะ</th><th>รายการ</th><th>ยอดรวม</th>
      <th>สั่งเมื่อ</th><th>สลิป</th><th>การชำระเงิน</th><th>การดำเนินการ</th>
    </tr>
    <?php foreach ($orders as $o): ?>
      <?php
        $remain = (int)$o['remain_seconds'];
        $canCancel = ($o['status']==='PENDING_PAYMENT' && $remain > 0);
      ?>
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
          <?php elseif ($o['status']==='EXPIRED'): ?>
            หมดเวลา
          <?php elseif ($o['status']==='CANCELLED'): ?>
            ยกเลิกแล้ว
          <?php else: ?>-<?php endif; ?>
        </td>
        <td>
          <?php if ($canCancel): ?>
            <div>
              <small class="muted">เหลือเวลา:</small>
              <strong class="countdown" data-remaining="<?= (int)$remain ?>">--:--</strong>
            </div>
            <form method="post" action="cancel_order.php"
                  data-cancel
                  onsubmit="return confirm('ยืนยันยกเลิกคำสั่งซื้อ #<?= (int)$o['id'] ?> ?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <button class="btn-outline" type="submit">ยกเลิก (ภายใน 5 นาที)</button>
            </form>
          <?php else: ?>
            <span class="muted">-</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?>
      <tr><td colspan="8" class="muted">ยังไม่มีคำสั่งซื้อ</td></tr>
    <?php endif; ?>
  </table>
</main>

<script>
// นับถอยหลังจากวินาทีที่เหลือแบบอิสระจากโซนเวลา
(function(){
  document.querySelectorAll('.countdown').forEach(el=>{
    let sec = parseInt(el.dataset.remaining,10);
    const row  = el.closest('tr');
    const form = row ? row.querySelector('form[data-cancel]') : null;
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
