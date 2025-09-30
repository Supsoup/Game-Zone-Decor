<?php
// lib/image.php - ตัวช่วยอัปโหลด/รีไซซ์รูปสินค้า

function ensure_dirs() {
  $base = __DIR__ . '/../uploads/products';
  if (!is_dir($base)) mkdir($base, 0777, true);
  if (!is_dir($base . '/thumbs')) mkdir($base . '/thumbs', 0777, true);
}

function load_gd_from_file(string $path, string $mime) {
  switch ($mime) {
    case 'image/jpeg': return imagecreatefromjpeg($path);
    case 'image/png':  return imagecreatefrompng($path);
    case 'image/webp': return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null;
    default: return null;
  }
}

function exif_orientate($im, string $path): void {
  if (function_exists('exif_read_data')) {
    $exif = @exif_read_data($path);
    if (!empty($exif['Orientation'])) {
      switch ($exif['Orientation']) {
        case 3: $im = imagerotate($im, 180, 0); break;
        case 6: $im = imagerotate($im, -90, 0); break;
        case 8: $im = imagerotate($im, 90, 0); break;
      }
    }
  }
}

function resize_and_save(string $tmpPath, string $mime, string $basename, int $maxW = 1000, int $thumbW = 400): array {
  ensure_dirs();
  $src = load_gd_from_file($tmpPath, $mime);
  if (!$src) throw new RuntimeException("ไม่รองรับไฟล์รูปแบบนี้");

  $w = imagesx($src); $h = imagesy($src);
  // Large
  $scale = $w > $maxW ? $maxW / $w : 1.0;
  $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
  $dst = imagecreatetruecolor($nw, $nh);
  imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $w,$h);

  $largeRel = "uploads/products/{$basename}.jpg";
  $thumbRel = "uploads/products/thumbs/{$basename}.jpg";

  imagejpeg($dst, __DIR__ . "/../$largeRel", 85);
  imagedestroy($dst);

  // Thumb
  $scaleT = $w > $thumbW ? $thumbW / $w : 1.0;
  $tw = (int)round($w * $scaleT); $th = (int)round($h * $scaleT);
  $dstT = imagecreatetruecolor($tw, $th);
  imagecopyresampled($dstT, $src, 0,0,0,0, $tw,$th, $w,$h);
  imagejpeg($dstT, __DIR__ . "/../$thumbRel", 85);
  imagedestroy($dstT);

  imagedestroy($src);
  return ['large' => $largeRel, 'thumb' => $thumbRel];
}

function delete_image_files(?string $imageUrl): void {
  if (!$imageUrl) return;
  // ลบเฉพาะไฟล์ใน uploads/products เท่านั้น (กันไปลบ assets)
  if (strpos($imageUrl, 'uploads/products/') !== 0) return;
  $large = __DIR__ . '/../' . $imageUrl;
  @unlink($large);
  // หาไฟล์ thumb ที่ชื่อเดียวกัน
  $base = basename($imageUrl, '.jpg'); // เช่น uploads/products/IMG_123 -> ใช้ไม่ได้กับพาธเต็ม
  // สร้างจากชื่อไฟล์ large
  $name = pathinfo($imageUrl, PATHINFO_FILENAME); // IMG_123
  $thumb = __DIR__ . '/../uploads/products/thumbs/' . $name . '.jpg';
  @unlink($thumb);
}
