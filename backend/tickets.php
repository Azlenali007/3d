<?php
/**
 * Support Tickets Logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

function getUserTickets(PDO $pdo, $userId, $status = null) {
    $sql = "SELECT t.*, 
            (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count,
            (SELECT message FROM ticket_messages WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) as last_message
            FROM tickets t 
            WHERE t.user_id = ?";
    $params = [$userId];

    if (!empty($status) && in_array($status, ['Open', 'In Progress', 'Closed'])) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY t.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createTicket(PDO $pdo, $userId, $subject, $orderId, $priority, $message) {
    if (empty($subject) || empty($message)) {
        throw new InvalidArgumentException('Subject and message are required');
    }

    $validPriority = in_array($priority, ['low', 'medium', 'high']) ? $priority : 'medium';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, order_id, subject, priority, status, created_at) VALUES (?, ?, ?, ?, 'Open', NOW())");
        $stmt->execute([$userId, $orderId ?: null, $subject, $validPriority]);
        $ticketId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->execute([$ticketId, $userId, $message]);

        createNotification($pdo, $userId, "Ticket #T{$ticketId} Created", "Your support request regarding '{$subject}' was submitted.", 'info');

        $pdo->commit();
        return $ticketId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
