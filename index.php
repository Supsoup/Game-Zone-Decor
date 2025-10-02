<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config/db.php";

$me  = $_SESSION['user'] ?? null;
$q   = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? 'All');

/* ---------- ตัวช่วยดึงสินค้าตามแนวเกม ---------- */
function fetch_products_by_genre(PDO $pdo, string $code, int $limit = 8): array {
  $sql = "SELECT p.id, p.name, p.price,
                 COALESCE(p.image_url,'assets/no-image.png') AS img
          FROM product_genres pg
          JOIN genres g   ON g.id = pg.genre_id
          JOIN products p ON p.id = pg.product_id
          WHERE g.code = ?
          ORDER BY p.id DESC
          LIMIT ?";
  $st = $pdo->prepare($sql);
  $st->bindValue(1, $code);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- โหลดหมวดสินค้าไว้สำหรับตัวกรอง ---------- */
$categories = ['All'];
$rs = $pdo->query("SELECT name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$categories = array_merge($categories, $rs);

/* ---------- ดึง 'สินค้าทั้งหมด' (หรือผลค้นหา/กรอง) ---------- */
$sql = "SELECT p.id, p.name, p.price,
               COALESCE(p.image_url,'assets/no-image.png') AS img,
               c.name AS category
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1";
$params = [];
if ($q !== '') { $sql .= " AND (p.name LIKE ? OR p.brand LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
if ($cat !== '' && $cat !== 'All') { $sql .= " AND c.name = ?"; $params[]=$cat; }
$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* หน้าแรก (ไม่มีค้นหา/กรอง) จะโชว์: สินค้าทั้งหมด + FPS + MOBA */
$isFiltered = ($q !== '' || ($cat !== '' && $cat !== 'All'));
$fps  = !$isFiltered ? fetch_products_by_genre($pdo, 'FPS', 8)  : [];
$moba = !$isFiltered ? fetch_products_by_genre($pdo, 'MOBA', 8) : [];
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Game Zone Decor</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="index.php"><span>Game Zone Decor</span></a>

  <form class="search-pill" action="index.php" method="get" style="flex:1;display:flex;gap:8px;margin:0 12px">
    <input type="text" name="q" placeholder="ค้นหาสินค้า..." value="<?= htmlspecialchars($q) ?>" style="flex:1">
    <?php if ($cat && $cat!=='All'): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
  </form>

  <?php if ($me): ?>
    <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($me['name']) ?></span>
    <a class="btn-outline" href="account/profile.php" style="margin-right:8px">โปรไฟล์</a>
    <a class="btn-outline" href="account/orders.php" style="margin-right:8px">คำสั่งซื้อของฉัน</a>
    <a class="btn-outline" href="auth/logout.php">ออกจากระบบ</a>
  <?php else: ?>
    <a class="btn-outline" href="auth/login.php">เข้าสู่ระบบ</a>
    <a class="primary-btn" href="auth/register.php" style="margin-left:8px">สมัครสมาชิก</a>
  <?php endif; ?>

  <a class="cart-btn" href="cart.php" title="ตะกร้าสินค้า" style="margin-left:8px">🛒</a>
</header>

<div class="subbar">
  <div style="display:flex;gap:8px;align-items:center">
    <span>หมวด:</span>
    <select onchange="location.href='index.php?cat='+encodeURIComponent(this.value)+'<?= $q!==''?'&q='.urlencode($q):'' ?>'">
      <?php foreach ($categories as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $c===$cat?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <a class="btn-outline" href="recommend.php">สินค้าแนะนำตามแนวเกม</a>
  <a class="primary-btn" href="index.php">หน้าแรก</a>
</div>

<main class="container">

  <?php if ($isFiltered): ?>
    <!-- โหมดค้นหา/กรอง: แสดงเฉพาะผลลัพธ์ -->
    <h2 class="section-title">สินค้า</h2>
    <section class="grid">
      <?php foreach ($products as $p): ?>
        <article class="card">
          <div class="thumb"><img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'"></div>
          <div class="divider"></div>
          <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
          <div class="price-row"><span class="price"><?= number_format((float)$p['price']) ?> THB</span></div>
          <a class="btn-outline" href="product.php?id=<?= (int)$p['id'] ?>">ดูรายละเอียด</a>
        </article>
      <?php endforeach; ?>
      <?php if (empty($products)): ?><p class="muted">ไม่พบสินค้าที่ตรงกับการค้นหา/หมวด</p><?php endif; ?>
    </section>

  <?php else: ?>
    <!-- โหมดหน้าแรก: โชว์ 'สินค้าทั้งหมด' ก่อน แล้วตามด้วย FPS/MOBA -->
    <h2 class="section-title">สินค้าทั้งหมด</h2>
    <section class="grid">
      <?php foreach ($products as $p): ?>
        <article class="card">
          <div class="thumb"><img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'"></div>
          <div class="divider"></div>
          <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
          <div class="price-row"><span class="price"><?= number_format((float)$p['price']) ?> THB</span></div>
          <a class="btn-outline" href="product.php?id=<?= (int)$p['id'] ?>">ดูรายละเอียด</a>
        </article>
      <?php endforeach; ?>
      <?php if (empty($products)): ?><p class="muted">ยังไม่มีสินค้า</p><?php endif; ?>
    </section>

    <h2 class="section-title" style="margin-top:28px">สินค้าแนะนำ • FPS</h2>
    <section class="grid">
      <?php foreach ($fps as $p): ?>
        <article class="card">
          <div class="thumb"><img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'"></div>
          <div class="divider"></div>
          <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
          <div class="price-row"><span class="price"><?= number_format((float)$p['price']) ?> THB</span></div>
          <a class="btn-outline" href="product.php?id=<?= (int)$p['id'] ?>">ดูรายละเอียด</a>
        </article>
      <?php endforeach; ?>
      <?php if (empty($fps)): ?><p class="muted">ยังไม่มีสินค้าที่แท็กแนวนี้</p><?php endif; ?>
    </section>
    <div style="text-align:right;margin-top:6px">
      <a class="btn-outline" href="recommend.php?genre=FPS">ดูทั้งหมด (FPS)</a>
    </div>

    <h2 class="section-title" style="margin-top:28px">สินค้าแนะนำ • MOBA</h2>
    <section class="grid">
      <?php foreach ($moba as $p): ?>
        <article class="card">
          <div class="thumb"><img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'"></div>
          <div class="divider"></div>
          <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
          <div class="price-row"><span class="price"><?= number_format((float)$p['price']) ?> THB</span></div>
          <a class="btn-outline" href="product.php?id=<?= (int)$p['id'] ?>">ดูรายละเอียด</a>
        </article>
      <?php endforeach; ?>
      <?php if (empty($moba)): ?><p class="muted">ยังไม่มีสินค้าที่แท็กแนวนี้</p><?php endif; ?>
    </section>
    <div style="text-align:right;margin-top:6px">
      <a class="btn-outline" href="recommend.php?genre=MOBA">ดูทั้งหมด (MOBA)</a>
    </div>
  <?php endif; ?>
</main>

<footer class="footer"><p>© <?= date('Y') ?> Game Zone Decor</p></footer>
</body></html>
