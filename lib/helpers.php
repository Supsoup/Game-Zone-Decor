<?php
if (!function_exists('expireStaleOrders')) {
  function expireStaleOrders(PDO $pdo) {
    $pdo->beginTransaction();
    try {
      $q = $pdo->prepare("SELECT id FROM orders WHERE status='PENDING_PAYMENT' AND expires_at <= NOW() FOR UPDATE");
      $q->execute();
      $ids = $q->fetchAll(PDO::FETCH_COLUMN);

      if ($ids) {
        $in = implode(',', array_fill(0,count($ids),'?'));
        $it = $pdo->prepare("SELECT product_id, qty FROM order_items WHERE order_id IN ($in)");
        $it->execute($ids);
        $items = $it->fetchAll(PDO::FETCH_ASSOC);

        $sum=[];
        foreach ($items as $row) {
          $pid=(int)$row['product_id']; $sum[$pid]=($sum[$pid]??0)+(int)$row['qty'];
        }
        foreach ($sum as $pid=>$qty) {
          $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$qty,$pid]);
        }
        $pdo->prepare("UPDATE orders SET status='CANCELLED_TIMEOUT' WHERE id IN ($in)")->execute($ids);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      // TODO: log
    }
  }
}
