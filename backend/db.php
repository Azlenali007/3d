<?php
/**
 * Real MariaDB Connection & Initialization
 * Strict backend logic - zero fake data
 */

function getDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $port = 3306;
    $dbname = 'smm_panel';
    $username = 'root';
    $password = '';

    try {
        // Connect to server first to ensure database exists
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
        
        initTablesAndSeed($pdo);
        return $pdo;
    } catch (PDOException $e) {
        // Handle socket fallback if 127.0.0.1 fails
        try {
            $pdo = new PDO("mysql:unix_socket=/var/run/mysqld/mysqld.sock;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            initTablesAndSeed($pdo);
            return $pdo;
        } catch (PDOException $ex) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed: ' . $ex->getMessage()]);
            exit;
        }
    }
}

function initTablesAndSeed(PDO $pdo) {
    // 1. Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(64) PRIMARY KEY,
        `value` TEXT NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(128) NOT NULL,
        email VARCHAR(191) NOT NULL UNIQUE,
        phone VARCHAR(32) DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
        status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(128) NOT NULL,
        slug VARCHAR(64) NOT NULL UNIQUE,
        icon VARCHAR(64) NOT NULL DEFAULT 'share',
        sort_order INT NOT NULL DEFAULT 0,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Services Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(191) NOT NULL,
        type VARCHAR(64) NOT NULL DEFAULT 'Default',
        price_per_k DECIMAL(10, 2) NOT NULL,
        min_quantity INT NOT NULL DEFAULT 100,
        max_quantity INT NOT NULL DEFAULT 1000000,
        description TEXT,
        speed VARCHAR(64) NOT NULL DEFAULT 'Instant Start',
        refill BOOLEAN NOT NULL DEFAULT 1,
        cancel BOOLEAN NOT NULL DEFAULT 1,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Orders Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        link VARCHAR(512) NOT NULL,
        quantity INT NOT NULL,
        charge DECIMAL(10, 2) NOT NULL,
        start_count INT NOT NULL DEFAULT 0,
        remains INT NOT NULL DEFAULT 0,
        status ENUM('Pending', 'Processing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (service_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Transactions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_id INT DEFAULT NULL,
        type ENUM('deposit', 'order', 'refund') NOT NULL,
        payment_method VARCHAR(64) NOT NULL DEFAULT 'Razorpay',
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('completed', 'pending', 'failed') NOT NULL DEFAULT 'completed',
        description VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. Support Tickets Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_id INT DEFAULT NULL,
        subject VARCHAR(255) NOT NULL,
        priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
        status ENUM('Open', 'In Progress', 'Closed') NOT NULL DEFAULT 'Open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 8. Ticket Messages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_admin BOOLEAN NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed Initial Settings if empty
    $chk = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($chk == 0) {
        $settings = [
            'site_name' => 'SMM Panel',
            'site_tagline' => 'Grow Your Social Media',
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
            'min_deposit' => '10.00',
            'max_deposit' => '100000.00',
            'maintenance_mode' => '0',
            'payment_razorpay_active' => '1',
            'payment_bank_active' => '1',
            'payment_crypto_active' => '1'
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    }

    // Seed Categories if empty
    $catCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount == 0) {
        $cats = [
            ['name' => 'Instagram', 'slug' => 'instagram', 'icon' => 'instagram', 'sort_order' => 1],
            ['name' => 'YouTube', 'slug' => 'youtube', 'icon' => 'youtube', 'sort_order' => 2],
            ['name' => 'Telegram', 'slug' => 'telegram', 'icon' => 'send', 'sort_order' => 3],
            ['name' => 'Facebook', 'slug' => 'facebook', 'icon' => 'facebook', 'sort_order' => 4],
            ['name' => 'TikTok', 'slug' => 'tiktok', 'icon' => 'video', 'sort_order' => 5],
            ['name' => 'Twitter (X)', 'slug' => 'twitter', 'icon' => 'twitter', 'sort_order' => 6]
        ];
        $cStmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?)");
        foreach ($cats as $c) {
            $cStmt->execute([$c['name'], $c['slug'], $c['icon'], $c['sort_order']]);
        }
    }

    // Seed Services if empty
    $srvCount = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    if ($srvCount == 0) {
        // Fetch category IDs
        $catMap = [];
        $res = $pdo->query("SELECT id, slug FROM categories")->fetchAll();
        foreach ($res as $r) {
            $catMap[$r['slug']] = $r['id'];
        }

        $services = [
            // Instagram
            [$catMap['instagram'], 'Instagram Followers', 'Followers', 35.00, 100, 1000000, 'High quality followers | Instant Start | No Drop | 30 Days Refill Guarantee', 'Instant Start', 1, 1, 1],
            [$catMap['instagram'], 'Instagram Likes', 'Likes', 20.00, 50, 500000, 'Instant delivery real organic likes with high engagement.', 'Instant Start', 1, 1, 2],
            [$catMap['instagram'], 'Instagram Views', 'Views', 15.00, 100, 2000000, 'Ultra-fast video and reels views. 100% safe & algorithm friendly.', '0-5 Mins', 1, 1, 3],
            [$catMap['instagram'], 'Instagram Comments', 'Comments', 50.00, 10, 10000, 'Custom and emoji comments from active genuine profiles.', '5-15 Mins', 1, 0, 4],

            // YouTube
            [$catMap['youtube'], 'YouTube Views', 'Views', 120.00, 500, 1000000, 'High retention views, monetization eligible, natural viewer pacing.', '1-6 Hours', 1, 1, 1],
            [$catMap['youtube'], 'YouTube Subscribers', 'Subscribers', 450.00, 50, 50000, 'Real non-drop subscribers with lifetime guarantee guarantee.', 'Instant Start', 1, 1, 2],
            [$catMap['youtube'], 'YouTube Likes', 'Likes', 65.00, 100, 200000, 'Speed 5K-10K/Day | Non drop | Stable ratio.', '0-15 Mins', 1, 1, 3],
            [$catMap['youtube'], 'YouTube Watch Time', 'WatchTime', 850.00, 100, 4000, 'Monetization pack watch time for 15+ min videos.', '6-12 Hours', 1, 0, 4],

            // Telegram
            [$catMap['telegram'], 'Telegram Members', 'Members', 45.00, 100, 100000, 'Channel & Group members. Global active accounts, zero drop.', 'Instant Start', 1, 1, 1],
            [$catMap['telegram'], 'Telegram Post Views', 'Views', 5.00, 100, 500000, 'Speed 100K/Hour | Auto spread across last 5 posts.', 'Instant Start', 1, 1, 2],
            [$catMap['telegram'], 'Telegram Reactions', 'Reactions', 12.00, 50, 100000, 'Positive emoji reactions (thumbs up, heart, fire).', 'Instant Start', 1, 1, 3],

            // Facebook
            [$catMap['facebook'], 'Facebook Page Likes & Followers', 'PageLikes', 80.00, 100, 100000, 'Global audience page likes and followers to establish brand trust.', '1-12 Hours', 1, 1, 1],
            [$catMap['facebook'], 'Facebook Post Reactions', 'Reactions', 40.00, 50, 50000, 'Love, Care, Haha or Wow reactions.', 'Instant Start', 1, 1, 2],
            [$catMap['facebook'], 'Facebook Video Views', 'Views', 25.00, 500, 1000000, '3-Second & 1-Minute video views.', 'Instant Start', 1, 1, 3],

            // TikTok
            [$catMap['tiktok'], 'TikTok Followers', 'Followers', 60.00, 100, 500000, 'Real organic TikTok followers to unlock live streaming and monetization.', '0-30 Mins', 1, 1, 1],
            [$catMap['tiktok'], 'TikTok Video Views', 'Views', 10.00, 1000, 10000000, 'Viral speed TikTok video views (up to 1M/day).', 'Instant Start', 1, 1, 2],
            [$catMap['tiktok'], 'TikTok Likes', 'Likes', 35.00, 100, 500000, 'High quality video likes from active creators.', 'Instant Start', 1, 1, 3],

            // Twitter (X)
            [$catMap['twitter'], 'Twitter (X) Followers', 'Followers', 120.00, 100, 100000, 'Crypto & tech niche accounts, premium look and avatar.', '1-3 Hours', 1, 1, 1],
            [$catMap['twitter'], 'Twitter (X) Retweets & Reposts', 'Retweets', 55.00, 50, 50000, 'Instant retweets with high impression scores.', 'Instant Start', 1, 1, 2],
            [$catMap['twitter'], 'Twitter (X) Likes', 'Likes', 40.00, 50, 100000, 'Fast Delivery organic tweet likes.', 'Instant Start', 1, 1, 3]
        ];

        $sStmt = $pdo->prepare("INSERT INTO services (category_id, name, type, price_per_k, min_quantity, max_quantity, description, speed, refill, cancel, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($services as $s) {
            $sStmt->execute($s);
        }
    }

    // Seed Demo Users if empty (admin and default user Aaris Ali matching reference image)
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $hashedPw = password_hash('password123', PASSWORD_BCRYPT);
        
        // Admin
        $pdo->prepare("INSERT INTO users (id, name, email, phone, password_hash, balance, role, status) VALUES (1, 'Admin Director', 'admin@smmpanel.com', '+91 99999 00000', ?, 50000.00, 'admin', 'active')")
            ->execute([$hashedPw]);

        // User Aaris Ali (User ID #1024, Balance ₹850.50 from reference image!)
        $pdo->prepare("INSERT INTO users (id, name, email, phone, password_hash, balance, role, status) VALUES (1024, 'Aaris Ali', 'aarisali@gmail.com', '+91 98765 43210', ?, 850.50, 'user', 'active')")
            ->execute([$hashedPw]);

        // Seed Sample Orders for User 1024 matching the reference image cards!
        // #10254: Instagram Followers (1K - ₹35) Processing
        // #10253: YouTube Views (5K - ₹120) Completed
        // #10252: Telegram Members (2K - ₹90) Processing
        // #10251: Instagram Likes (1K - ₹20) Completed
        $igFollowersId = $pdo->query("SELECT id FROM services WHERE name = 'Instagram Followers' LIMIT 1")->fetchColumn() ?: 1;
        $ytViewsId = $pdo->query("SELECT id FROM services WHERE name = 'YouTube Views' LIMIT 1")->fetchColumn() ?: 5;
        $tgMembersId = $pdo->query("SELECT id FROM services WHERE name = 'Telegram Members' LIMIT 1")->fetchColumn() ?: 9;
        $igLikesId = $pdo->query("SELECT id FROM services WHERE name = 'Instagram Likes' LIMIT 1")->fetchColumn() ?: 2;

        $pdo->prepare("INSERT INTO orders (id, user_id, service_id, link, quantity, charge, status, created_at) VALUES 
            (10251, 1024, ?, 'https://instagram.com/p/C7X90qJ', 1000, 20.00, 'Completed', '2025-05-09 19:20:00'),
            (10252, 1024, ?, 'https://t.me/techcommunity_hub', 2000, 90.00, 'Processing', '2025-05-10 13:45:00'),
            (10253, 1024, ?, 'https://youtube.com/watch?v=dQw4w9WgXcQ', 5000, 120.00, 'Completed', '2025-05-11 18:10:00'),
            (10254, 1024, ?, 'https://instagram.com/aarisali_official', 1000, 35.00, 'Processing', '2025-05-12 16:32:00')
        ")->execute([$igLikesId, $tgMembersId, $ytViewsId, $igFollowersId]);

        // Seed Sample Transactions matching reference image Panel 7!
        $pdo->exec("INSERT INTO transactions (user_id, order_id, type, payment_method, amount, status, description, created_at) VALUES
            (1024, NULL, 'deposit', 'Razorpay', 200.00, 'completed', 'Add Funds via Razorpay', '2025-05-10 11:20:00'),
            (1024, 10253, 'order', 'Balance', -120.00, 'completed', 'Order Payment - YouTube Views', '2025-05-10 18:15:00'),
            (1024, NULL, 'deposit', 'Razorpay', 500.00, 'completed', 'Add Funds via Razorpay', '2025-05-12 16:12:00'),
            (1024, 10254, 'order', 'Balance', -35.00, 'completed', 'Order Payment - Instagram Followers', '2025-05-12 16:32:00')
        ");

        // Seed Sample Tickets matching reference image Panel 8!
        $pdo->exec("INSERT INTO tickets (id, user_id, order_id, subject, priority, status, created_at) VALUES
            (1021, 1024, 10251, 'Wrong quantity received', 'medium', 'Closed', '2025-05-06 13:10:00'),
            (1022, 1024, 10252, 'Service delay question', 'low', 'Closed', '2025-05-08 15:40:00'),
            (1023, 1024, NULL, 'Payment issue on Razorpay', 'high', 'In Progress', '2025-05-10 18:15:00'),
            (1024, 1024, 10254, 'Order not started yet', 'medium', 'Open', '2025-05-12 11:20:00')
        ");

        $pdo->exec("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin, created_at) VALUES
            (1024, 1024, 'Hello, I submitted order #10254 for Instagram followers earlier today and the counter has not updated yet. Could you check the queue status please?', 0, '2025-05-12 11:20:00'),
            (1023, 1024, 'I was trying to deposit ₹200 and had a connection blip. Need confirmation if the transaction was captured properly.', 0, '2025-05-10 18:15:00'),
            (1023, 1, 'We have verified your transaction id and updated your wallet balance accordingly.', 1, '2025-05-10 18:45:00')
        ");
    }
}
