<?php
/**
 * Orders Business Logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

function placeOrder(PDO $pdo, $userId, $serviceId, $link, $quantity) {
    if ($serviceId <= 0) {
        throw new InvalidArgumentException('Please select a valid service');
    }
    if (empty($link)) {
        throw new InvalidArgumentException('Target link or username is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();

    if (!$service) {
        throw new RuntimeException('The selected service is not available');
    }

    if ($quantity < $service['min_quantity']) {
        throw new InvalidArgumentException("Minimum order quantity is {$service['min_quantity']}");
    }
    if ($quantity > $service['max_quantity']) {
        throw new InvalidArgumentException("Maximum order quantity is {$service['max_quantity']}");
    }

    // Exact formula
    $charge = round(($quantity / 1000) * (float)$service['price_per_k'], 2);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $currentBalance = (float)$stmt->fetchColumn();

        if ($currentBalance < $charge) {
            $pdo->rollBack();
            throw new RuntimeException("Insufficient balance. Required ₹{$charge}, Current balance ₹{$currentBalance}. Please add funds.");
        }

        $newBalance = round($currentBalance - $charge, 2);

        // Deduct balance
        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, service_id, link, quantity, charge, status, created_at) VALUES (?, ?, ?, ?, ?, 'Processing', NOW())");
        $stmt->execute([$userId, $serviceId, $link, $quantity, $charge]);
        $orderId = (int)$pdo->lastInsertId();

        // Insert transaction ledger
        $desc = "Order #{$orderId} - {$service['name']} ({$quantity})";
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, order_id, type, payment_method, amount, status, description, created_at) VALUES (?, ?, 'order', 'Balance', ?, 'completed', ?, NOW())");
        $stmt->execute([$userId, $orderId, -$charge, $desc]);

        // Push notification
        createNotification($pdo, $userId, "Order #{$orderId} Placed", "Your order for {$service['name']} ({$quantity} units) has been recorded and is currently Processing.", 'order');

        $pdo->commit();

        return [
            'id' => $orderId,
            'service_name' => $service['name'],
            'quantity' => $quantity,
            'charge' => $charge,
            'link' => $link,
            'status' => 'Processing',
            'new_balance' => $newBalance
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getUserOrders(PDO $pdo, $userId, $status = null) {
    $sql = "SELECT o.*, s.name as service_name, c.name as category_name, c.slug as category_slug, c.icon as category_icon 
            FROM orders o 
            JOIN services s ON o.service_id = s.id 
            JOIN categories c ON s.category_id = c.id 
            WHERE o.user_id = ?";
    $params = [$userId];

    if (!empty($status) && in_array($status, ['Processing', 'Completed', 'Cancelled', 'Pending'])) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY o.id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
