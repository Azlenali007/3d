<?php
/**
 * User Notifications Logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function getUserNotifications(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$userId]);
    $unreadCount = (int)$stmtUnread->fetchColumn();

    return [
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ];
}

function markNotificationAsRead(PDO $pdo, $userId, $notificationId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsAsRead(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

function createNotification(PDO $pdo, $userId, $title, $message, $type = 'info') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    return $stmt->execute([$userId, $title, $message, $type]);
}
