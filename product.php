<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config/db.php";
$me = $_SESSION['user'] ?? null;

function fetchProduct(PDO $pdo, int $id) {
  $sql = "SELECT p.id, p.name, p.brand, p.description AS `desc`,
                 COALESCE(p.image_url,'assets/no-image.png') AS img,
                 p.price, c.name AS category
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.id = ?
          LIMIT 1";
  $st = $pdo->prepare($sql); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function fetchLatestId(PDO $pdo){ return (int)($pdo->query("SELECT id FROM products ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0); }
function fetchSome(PDO $pdo, int $limit=6){
  $st=$pdo->prepare("SELECT id,name,COALESCE(image_url,'assets/no-image.png') AS img, price FROM products ORDER BY id DESC LIMIT ?");
  $st->bindValue(1,$limit,PDO::PARAM_INT); $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC);
}

$id = (int)($_GET['id'] ?? 0);
$product = $id ? fetchProduct($pdo,$id) : null;
$fallback=false;
if(!$product){ $latest=fetchLatestId($pdo); if($latest){ $product=fetchProduct($pdo,$latest); $fallback=true; } }
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $product ? htmlspecialchars($product['name']).' - ' : '' ?>Game Zone Decor</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/styles.css">
</head><body>
<header class="topbar">
  <a class="brand-pill" href="index.php"><span>Game Zone Decor</span></a>
  <div style="flex:1"></div>
  <?php if ($me): ?>
    <span class="muted" style="margin-right:8px">สวัสดี, <?= htmlspecialchars($me['name']) ?></span>
    <a class="btn-outline" href="account/profile.php" style="margin-right:8px">โปรไฟล์</a>
    <a class="btn-outline" href="auth/logout.php">ออกจากระบบ</a>
  <?php else: ?>
    <a class="btn-outline" href="auth/login.php">เข้าสู่ระบบ</a>
    <a class="primary-btn" href="auth/register.php" style="margin-left:8px">สมัครสมาชิก</a>
  <?php endif; ?>
  <a class="cart-btn" href="cart.php" title="ตะกร้าสินค้า" style="margin-left:8px">🛒</a>
</header>
<div class="subbar"><a class="primary-btn" href="index.php">หน้าแรก</a></div>

<main class="container">
<?php if (!$product): ?>
  <h2 class="section-title">ยังไม่พบสินค้า</h2>
  <p class="muted">กรุณาเพิ่มสินค้าในตาราง <code>products</code> ก่อน</p>
<?php else: ?>
  <?php if ($fallback): ?><p class="muted">ไม่พบ id ที่ระบุ แสดงสินค้าล่าสุดแทน</p><?php endif; ?>
  <div class="pd-wrap">
    <div class="pd-media">
      <img src="<?= htmlspecialchars($product['img']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'">
    </div>
    <div class="pd-info">
      <h1 class="pd-name"><?= htmlspecialchars($product['name']) ?></h1>
      <div class="pd-meta">
        <span class="tag"><?= htmlspecialchars($product['brand'] ?? '-') ?></span>
        <span class="tag"><?= htmlspecialchars($product['category'] ?? '-') ?></span>
      </div>
      <div class="pd-price"><?= number_format((float)$product['price']) ?> THB</div>
      <p class="pd-desc"><?= nl2br(htmlspecialchars($product['desc'] ?? '')) ?></p>

      <form method="post" action="cart.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
        <label class="qty">จำนวน: <input type="number" name="qty" value="1" min="1" required></label>
        <button class="primary-btn" type="submit">หยิบใส่ตะกร้า</button>
      </form>
    </div>
  </div>

  <h3 class="section-title" style="margin-top:28px">สินค้าอื่น ๆ</h3>
  <section class="grid">
    <?php foreach (fetchSome($pdo,6) as $p): ?>
      <article class="card">
        <div class="thumb"><img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/no-image.png'"></div>
        <div class="divider"></div>
        <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
        <div class="price-row"><span class="price"><?= number_format((float)$p['price']) ?> THB</span></div>
        <a class="btn-outline" href="product.php?id=<?= (int)$p['id'] ?>">ดูรายละเอียด</a>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
</main>

<footer class="footer"><p>© <?= date('Y') ?> Game Zone Decor</p></footer>
</body></html>
