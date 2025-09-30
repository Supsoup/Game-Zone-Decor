<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();
require_once __DIR__ . '/../lib/image.php';

if (!admin_csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); echo "CSRF invalid"; exit; }
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: products.php'); exit; }

$st = $pdo->prepare("SELECT image_url FROM products WHERE id=?");
$st->execute([$id]); $img = $st->fetchColumn();

try {
  $pdo->beginTransaction();
  $pdo->prepare("DELETE FROM product_genres WHERE product_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  $pdo->commit();
  if ($img) delete_image_files($img);
} catch (Throwable $e) {
  $pdo->rollBack();
  echo "ลบไม่สำเร็จ: ".$e->getMessage(); exit;
}
header('Location: products.php'); exit;
