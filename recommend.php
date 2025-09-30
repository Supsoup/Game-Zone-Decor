<?php
// recommend.php — สินค้าแนะนำตามแนวเกม
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config/db.php";

$me = $_SESSION['user'] ?? null;

// โหลดรายการแนวเกมทั้งหมด
$genres = $pdo->query("SELECT id, code, name FROM genres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// รับค่าที่เลือก (รับได้ทั้ง ?genre=FPS, ?g=FPS หรือ ?gid=1)
$code = trim($_GET['genre'] ?? ($_GET['g'] ?? ''));
$gid  = (int)($_GET['gid'] ?? 0);

// หาแนวเกมที่เลือก
$selected = null;
if ($code !== '') {
  $st = $pdo->prepare("SELECT id, code, name FROM genres WHERE code = ?");
  $st->execute([$code]);
  $selected = $st->fetch(PDO::FETCH_ASSOC);
} elseif ($gid > 0) {
  $st = $pdo->prepare("SELECT id, code, name FROM genres WHERE id = ?");
  $st->execute([$gid]);
  $selected = $st->fetch(PDO::FETCH_ASSOC);
}

// ดึงสินค้าตามแนวเกมที่เลือก
$products = [];
if ($selected) {
  $sql = "SELECT p.id, p.name, p.price, COALESCE(p.image_url,'assets/no-image.png') AS img
          FROM product_genres pg
          JOIN products p ON p.id = pg.product_id
          WHERE pg.genre_id = ?
          ORDER BY p.id DESC";
  $st = $pdo->prepare($sql);
  $st->execute([(int)$selected['id']]);
  $products = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>สินค้าแนะนำตามแนวเกม<?= $selected ? ' - '.htmlspecialchars($selected['name']) : '' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="topbar">
  <a class="brand-pill" href="index.php"><span>Game Zone Decor</span></a>
  <div style="flex:1"></div>
  <?php if ($me): ?>
    <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($me['name']) ?></span>
    <a class="btn-outline" href="account/orders.php" style="margin-right:8px">คำสั่งซื้อของฉัน</a>
    <a class="btn-outline" href="auth/logout.php">ออก</a>
  <?php else: ?>
    <a class="btn-outline" href="auth/login.php">เข้าสู่ระบบ</a>
    <a class="primary-btn" href="auth/register.php" style="margin-left:8px">สมัครสมาชิก</a>
  <?php endif; ?>
  <a class="cart-btn" href="cart.php" title="ตะกร้าสินค้า" style="margin-left:8px">🛒</a>
</header>

<div class="subbar">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span>เลือกแนวเกม:</span>
    <?php foreach ($genres as $g): ?>
      <a class="btn-outline"
         href="recommend.php?genre=<?= urlencode($g['code']) ?>"
         title="<?= htmlspecialchars($g['code']) ?>"
         <?= $selected && $selected['code']===$g['code'] ? 'style="font-weight:600"' : '' ?>>
        <?= htmlspecialchars($g['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <a class="primary-btn" href="index.php">หน้าแรก</a>
</div>

<main class="container">
  <h2 class="section-title">สินค้าแนะนำ<?= $selected ? ' • '.htmlspecialchars($selected['name']) : '' ?></h2>

  <?php if (!$selected): ?>
    <p class="muted">กรุณาเลือกแนวเกมด้านบน</p>
  <?php else: ?>
    <section class="grid">
      <?php foreach ($products as $p): ?>
        <article class="card">
          <div class="thumb">
            <img src="<?= htmlspecialchars($p['img']) ?>"
                 alt="<?= htmlspecialchars($p['name']) ?>"
                 loading="lazy"
                 onerror="this.onerror=null;this.src='assets/no-image.png'">
          </div>
          <div class="divider"></div>
          <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
          <div class="price-row">
            <span class="price"><?= number_format((float)$p['price']) ?> THB</span>
          </div>
          <a class="btn-outline" href="product.php?id=<?= (int)$p['id'] ?>">ดูรายละเอียด</a>
        </article>
      <?php endforeach; ?>
      <?php if (empty($products)): ?>
        <p class="muted">ยังไม่มีสินค้าที่ตรงกับแนวเกมนี้</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>

<footer class="footer"><p>© <?= date('Y') ?> Game Zone Decor</p></footer>
</body>
</html>
