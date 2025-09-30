<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();
require_once __DIR__ . '/../lib/image.php';

$id = (int)($_GET['id'] ?? 0);
$err=''; $ok='';
$token = admin_csrf_token();

$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
$genres = $pdo->query("SELECT id,code,name FROM genres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$prod = null; $selectedGenres = [];
if ($id > 0) {
  $st = $pdo->prepare("SELECT * FROM products WHERE id=?");
  $st->execute([$id]); $prod = $st->fetch();
  if (!$prod) { header('Location: products.php'); exit; }
  $pg = $pdo->prepare("SELECT genre_id FROM product_genres WHERE product_id=?");
  $pg->execute([$id]); $selectedGenres = array_map('intval', $pg->fetchAll(PDO::FETCH_COLUMN));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_csrf_check($_POST['csrf'] ?? '')) {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $brand = trim($_POST['brand'] ?? '');
  $name  = trim($_POST['name'] ?? '');
  $desc  = trim($_POST['description'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $stock = (int)($_POST['stock'] ?? 0);
  $picked = array_map('intval', $_POST['genres'] ?? []);

  if ($name === '' || $price < 0) $err = 'กรอกชื่อสินค้าและราคาที่ถูกต้อง';
  if (!$err) {
    $image_url = $prod['image_url'] ?? null;
    if (!empty($_FILES['image']['tmp_name'])) {
      $mime = mime_content_type($_FILES['image']['tmp_name']);
      $allow = ['image/jpeg','image/png','image/webp'];
      if (!in_array($mime, $allow, true))       $err = 'ไฟล์รูปต้องเป็น JPG/PNG/WEBP';
      elseif ($_FILES['image']['size'] > 5*1024*1024) $err = 'ไฟล์ใหญ่เกิน 5MB';
      else {
        try {
          $basename = 'IMG_'.time().'_'.rand(1000,9999);
          $paths = resize_and_save($_FILES['image']['tmp_name'], $mime, $basename, 1000, 400);
          if (!empty($image_url)) delete_image_files($image_url);
          $image_url = $paths['large'];
        } catch (Throwable $e) { $err = 'อัปโหลดรูปไม่สำเร็จ: '.$e->getMessage(); }
      }
    }

    if (!$err) {
      if ($id > 0) {
        $sql = "UPDATE products SET category_id=?, brand=?, name=?, description=?, price=?, stock=?, image_url=? WHERE id=?";
        $pdo->prepare($sql)->execute([$category_id ?: null, $brand, $name, $desc, $price, $stock, $image_url, $id]);
        $pdo->prepare("DELETE FROM product_genres WHERE product_id=?")->execute([$id]);
        if ($picked) {
          $ins = $pdo->prepare("INSERT IGNORE INTO product_genres(product_id, genre_id) VALUES (?,?)");
          foreach ($picked as $gid) $ins->execute([$id,$gid]);
        }
        $ok = 'บันทึกการแก้ไขเรียบร้อย';
      } else {
        $sql = "INSERT INTO products (category_id, brand, name, description, price, stock, image_url)
                VALUES (?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([$category_id ?: null, $brand, $name, $desc, $price, $stock, $image_url]);
        $id = (int)$pdo->lastInsertId();
        if ($picked) {
          $ins = $pdo->prepare("INSERT IGNORE INTO product_genres(product_id, genre_id) VALUES (?,?)");
          foreach ($picked as $gid) $ins->execute([$id,$gid]);
        }
        $ok = 'เพิ่มสินค้าเรียบร้อย';
        $st = $pdo->prepare("SELECT * FROM products WHERE id=?"); $st->execute([$id]); $prod = $st->fetch();
      }
      $selectedGenres = $picked;
    }
  }
}

$val = function($k, $def='') use ($prod) { return htmlspecialchars($prod[$k] ?? $def); };
$img = $prod['image_url'] ?? 'assets/no-image.png';
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $id>0?'แก้ไข':'เพิ่ม' ?> สินค้า</title>
<link rel="stylesheet" href="../assets/styles.css">
<style>
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chips label{display:flex;align-items:center;gap:6px;border:1px solid #dde; padding:6px 10px;border-radius:999px;cursor:pointer}
</style>
</head><body>
<header class="topbar">
  <a class="brand-pill" href="products.php">← กลับรายการสินค้า</a>
  <div style="flex:1"></div>
  <a class="btn-outline" href="logout.php">ออกจากระบบ</a>
</header>

<main class="container">
  <h2 class="section-title"><?= $id>0?'แก้ไข':'เพิ่ม' ?> สินค้า</h2>
  <?php if ($ok): ?><p style="color:green"><?= htmlspecialchars($ok) ?></p><?php endif; ?>
  <?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="checkout-form" style="max-width:820px">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">

    <label>หมวดหมู่
      <select name="category_id">
        <option value="0">— ไม่ระบุหมวด —</option>
        <?php foreach ($cats as $cid=>$cname): ?>
          <option value="<?= (int)$cid ?>" <?= (isset($prod['category_id']) && (int)$prod['category_id']===(int)$cid)?'selected':'' ?>>
            <?= htmlspecialchars($cname) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>แบรนด์ <input type="text" name="brand" value="<?= $val('brand') ?>"></label>
    <label>ชื่อสินค้า <input type="text" name="name" value="<?= $val('name') ?>" required></label>
    <label>รายละเอียด <textarea name="description" rows="4"><?= $val('description') ?></textarea></label>

    <div style="display:flex; gap:12px; flex-wrap:wrap">
      <label style="flex:1">ราคา (บาท) <input type="number" step="0.01" name="price" value="<?= $val('price','0') ?>" required></label>
      <label style="flex:1">สต็อก <input type="number" name="stock" value="<?= $val('stock','0') ?>" required></label>
    </div>

    <fieldset style="border:1px solid #eef;padding:12px;border-radius:12px">
      <legend>เหมาะสำหรับแนวเกม</legend>
      <div class="chips">
        <?php foreach ($genres as $g): ?>
          <label>
            <input type="checkbox" name="genres[]" value="<?= (int)$g['id'] ?>"
              <?= in_array((int)$g['id'],$selectedGenres,true)?'checked':'' ?>>
            <span><?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['code']) ?>)</span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div>
      <label>รูปภาพ (JPG/PNG/WEBP ≤ 5MB)
        <input type="file" name="image" accept="image/*">
      </label>
      <div class="pd-media" style="max-width:320px;margin-top:8px">
        <img src="../<?= htmlspecialchars($img) ?>" alt="preview" style="max-height:240px">
      </div>
      <?php if (!empty($prod['image_url'])): ?>
        <p class="muted">ไฟล์ปัจจุบัน: <code><?= htmlspecialchars($prod['image_url']) ?></code></p>
      <?php endif; ?>
    </div>

    <button class="primary-btn" type="submit">บันทึก</button>
    <a class="btn-outline" href="products.php">ยกเลิก</a>
  </form>
</main>
</body></html>
