<?php
/**
 * Services and Categories Logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function getActiveCategories(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name, slug, icon, sort_order FROM categories WHERE status = 'active' ORDER BY sort_order ASC");
    return $stmt->fetchAll();
}

function getServicesList(PDO $pdo, $categoryId = null, $categorySlug = null, $search = null) {
    $sql = "SELECT s.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon 
            FROM services s 
            JOIN categories c ON s.category_id = c.id 
            WHERE s.status = 'active'";
    $params = [];

    if ($categoryId) {
        $sql .= " AND s.category_id = ?";
        $params[] = (int)$categoryId;
    } elseif ($categorySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $categorySlug;
    }

    if ($search) {
        $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY c.sort_order ASC, s.sort_order ASC, s.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
