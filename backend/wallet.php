<?php
/**
 * Wallet & Transaction Processing Logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

function depositFunds(PDO $pdo, $userId, $amount, $paymentMethod = 'Razorpay') {
    if ($amount < MIN_DEPOSIT) {
        throw new InvalidArgumentException('Minimum deposit amount is ₹' . number_format(MIN_DEPOSIT, 2));
    }
    if ($amount > MAX_DEPOSIT) {
        throw new InvalidArgumentException('Maximum deposit limit is ₹' . number_format(MAX_DEPOSIT, 2));
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $currentBalance = (float)$stmt->fetchColumn();

        $newBalance = round($currentBalance + $amount, 2);

        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);

        $desc = "Deposit via {$paymentMethod}";
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, order_id, type, payment_method, amount, status, description, created_at) VALUES (?, NULL, 'deposit', ?, ?, 'completed', ?, NOW())");
        $stmt->execute([$userId, $paymentMethod, $amount, $desc]);
        $txId = (int)$pdo->lastInsertId();

        createNotification($pdo, $userId, 'Deposit Received', "Successfully credited ₹" . number_format($amount, 2) . " via {$paymentMethod}.", 'wallet');

        $pdo->commit();

        return [
            'transaction_id' => $txId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'new_balance' => $newBalance
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getUserTransactions(PDO $pdo, $userId, $type = null) {
    $sql = "SELECT * FROM transactions WHERE user_id = ?";
    $params = [$userId];

    if (!empty($type) && in_array($type, ['deposit', 'order', 'refund'])) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
