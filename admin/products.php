<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$q   = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);

$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$sql = "SELECT p.*,
        c.name AS category_name,
        (SELECT GROUP_CONCAT(g.code ORDER BY g.code SEPARATOR ', ')
           FROM product_genres pg
           JOIN genres g ON g.id = pg.genre_id
          WHERE pg.product_id = p.id) AS genres_codes
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE 1";
$params = [];
if ($q !== '') { $sql .= " AND (p.name LIKE ? OR p.brand LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
if ($cat > 0)  { $sql .= " AND p.category_id = ?"; $params[]=$cat; }
$sql .= " ORDER BY p.id DESC LIMIT 300";
$st = $pdo->prepare($sql); $st->execute($params); $rows = $st->fetchAll();

$token = admin_csrf_token();
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin • Products</title>
<link rel="stylesheet" href="../assets/styles.css">
<style>.thumb img{max-height:60px;border-radius:8px}</style>
</head><body>
<header class="topbar">
  <a class="brand-pill" href="orders.php">Admin</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="products.php">สินค้า</a>
  <a class="btn-outline" href="logout.php">ออกจากระบบ</a>
</header>

<main class="container">
  <h2 class="section-title">จัดการสินค้า</h2>

  <div class="checkout-form" style="flex-direction:row;gap:8px;align-items:center">
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="text" name="q" placeholder="ค้นหาชื่อ/แบรนด์" value="<?= htmlspecialchars($q) ?>">
      <select name="cat">
        <option value="0">ทุกหมวด</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $cat===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn-outline" type="submit">ค้นหา</button>
    </form>
    <div style="flex:1"></div>
    <a class="primary-btn" href="product_form.php">+ เพิ่มสินค้า</a>
  </div>

  <table class="cart-table" style="margin-top:12px">
    <tr><th>#</th><th>รูป</th><th>ชื่อ</th><th>หมวด</th><th>ราคา</th><th>สต็อก</th><th>แนวเกม</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td class="thumb">
          <?php $img = $r['image_url'] ?: 'assets/no-image.png';
            if (strpos($img, 'uploads/products/') === 0) {
              $fn    = pathinfo($img, PATHINFO_FILENAME).'.jpg';
              $thumb = 'uploads/products/thumbs/'.$fn;
              if (is_file(__DIR__.'/../'.$thumb)) $img = $thumb;
            } ?>
          <img src="../<?= htmlspecialchars($img) ?>" alt="">
        </td>
        <td><?= htmlspecialchars($r['name']) ?><br><span class="muted"><?= htmlspecialchars($r['brand'] ?? '') ?></span></td>
        <td><?= htmlspecialchars($r['category_name'] ?? '-') ?></td>
        <td><?= number_format((float)$r['price']) ?> THB</td>
        <td><?= (int)$r['stock'] ?></td>
        <td><?= htmlspecialchars($r['genres_codes'] ?? '-') ?></td>
        <td>
          <a class="btn-outline" href="product_form.php?id=<?= (int)$r['id'] ?>">แก้ไข</a>
          <form method="post" action="product_delete.php" style="display:inline" onsubmit="return confirm('ลบสินค้านี้?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn-outline" type="submit">ลบ</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" class="muted">ยังไม่มีสินค้า</td></tr>
    <?php endif; ?>
  </table>
</main>
</body></html>
