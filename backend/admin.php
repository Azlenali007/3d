<?php
/**
 * Administrator Operations Logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

function getAdminMetrics(PDO $pdo) {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('Pending', 'Processing')")->fetchColumn();
    $completedOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Completed'")->fetchColumn();
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(charge), 0) FROM orders WHERE status != 'Cancelled'")->fetchColumn();
    $totalDeposits = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'deposit' AND status = 'completed'")->fetchColumn();

    $recentOrders = $pdo->query("SELECT o.*, u.name as user_name, u.email as user_email, s.name as service_name 
                                  FROM orders o 
                                  JOIN users u ON o.user_id = u.id 
                                  JOIN services s ON o.service_id = s.id 
                                  ORDER BY o.id DESC LIMIT 10")->fetchAll();

    return [
        'stats' => [
            'total_users' => (int)$totalUsers,
            'total_orders' => (int)$totalOrders,
            'pending_orders' => (int)$pendingOrders,
            'completed_orders' => (int)$completedOrders,
            'total_revenue' => (float)$totalRevenue,
            'total_deposits' => (float)$totalDeposits
        ],
        'recent_orders' => $recentOrders
    ];
}

function adjustUserBalanceByAdmin(PDO $pdo, $userId, $amount, $action) {
    $delta = ($action === 'deduct') ? -$amount : $amount;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$delta, $userId]);

        $desc = "Admin manual balance adjustment (" . ucfirst($action) . " ₹{$amount})";
        $pdo->prepare("INSERT INTO transactions (user_id, order_id, type, payment_method, amount, status, description, created_at) VALUES (?, NULL, 'deposit', 'Admin Adjustment', ?, 'completed', ?, NOW())")
            ->execute([$userId, $delta, $desc]);

        $notifTitle = ($action === 'deduct') ? 'Balance Adjusted' : 'Balance Credited';
        createNotification($pdo, $userId, $notifTitle, "An administrator {$action}ed ₹{$amount} in your wallet balance.", 'wallet');

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
