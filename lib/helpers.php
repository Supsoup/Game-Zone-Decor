<?php
// lib/helpers.php

// สร้าง/ดึง CSRF token จาก session
if (!function_exists('ensure_csrf')) {
  function ensure_csrf(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }
}

// หมดอายุออเดอร์ที่เกินเวลา และ "คืนสต็อก" อัตโนมัติ
if (!function_exists('expireStaleOrders')) {
  function expireStaleOrders(PDO $pdo): void {
    // ดึงออเดอร์ที่จะหมดอายุทีละก้อน (กันรันนานเกิน)
    $ids = $pdo->query("
      SELECT id FROM orders
      WHERE status='PENDING_PAYMENT'
        AND expires_at IS NOT NULL
        AND expires_at < NOW()
      LIMIT 50
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $oid) {
      $pdo->beginTransaction();
      try {
        // ล็อกออเดอร์เพื่อความถูกต้องในสภาวะพร้อมกัน
        $st = $pdo->prepare("SELECT id, status FROM orders WHERE id=? FOR UPDATE");
        $st->execute([$oid]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o || $o['status'] !== 'PENDING_PAYMENT') { $pdo->rollBack(); continue; }

        // คืนสต็อกตามรายการ
        $it = $pdo->prepare("SELECT product_id, qty FROM order_items WHERE order_id=?");
        $it->execute([$oid]);
        $rows = $it->fetchAll(PDO::FETCH_ASSOC);

        $up = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($rows as $r) {
          $up->execute([(int)$r['qty'], (int)$r['product_id']]);
        }

        // เปลี่ยนสถานะเป็น EXPIRED
        $pdo->prepare("UPDATE orders SET status='EXPIRED' WHERE id=?")->execute([$oid]);

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // เงียบไว้ (ไม่ให้ล้มทั้งหน้า) — จะลองรอบต่อไป
      }
    }
  }
}
