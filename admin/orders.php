<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$status = $_GET['status'] ?? 'ALL';
$valid  = ['ALL','PENDING_PAYMENT','PAID_CHECKING','PAID_CONFIRMED','SHIPPING','CANCELLED_TIMEOUT','CANCELLED_INVALID','CANCELLED_BY_USER'];
if (!in_array($status,$valid,true)) $status='ALL';

$sql = "SELECT o.*,
        (SELECT slip_path FROM payments p WHERE p.order_id=o.id ORDER BY id DESC LIMIT 1) AS slip_path
        FROM orders o";
$params=[];
if ($status!=='ALL') { $sql.=" WHERE o.status=?"; $params[]=$status; }
$sql.=" ORDER BY o.placed_at DESC LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin • Orders</title>
<link rel="stylesheet" href="../assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="orders.php">Admin: Orders</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="logout.php">ออกจากระบบ</a>
</header>

<main class="container">
  <h2 class="section-title">รายการคำสั่งซื้อ</h2>

  <div class="checkout-form" style="flex-direction:row;gap:8px;align-items:center">
    <span>กรองสถานะ:</span>
    <select onchange="location.href='orders.php?status='+encodeURIComponent(this.value)">
      <?php foreach ($valid as $v): ?>
        <option value="<?= $v ?>" <?= $v===$status?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <table class="cart-table" style="margin-top:12px">
    <tr><th>ID</th><th>สถานะ</th><th>ยอดรวม</th><th>เวลาสั่ง</th><th>หมดเวลา</th><th>สลิป</th><th></th></tr>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td>#<?= (int)$o['id'] ?></td>
        <td><?= htmlspecialchars($o['status']) ?></td>
        <td><?= number_format((float)$o['total_amount'],2) ?></td>
        <td><?= htmlspecialchars($o['placed_at']) ?></td>
        <td><?= htmlspecialchars($o['expires_at']) ?></td>
        <td><?= $o['slip_path'] ? 'มี' : '-' ?></td>
        <td><a class="btn-outline" href="order_view.php?id=<?= (int)$o['id'] ?>">เปิดดู</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?>
      <tr><td colspan="7" class="muted">ยังไม่มีคำสั่งซื้อ</td></tr>
    <?php endif; ?>
  </table>
</main>
</body></html>
