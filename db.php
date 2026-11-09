<?php
$config = require __DIR__ . '/config.php';

$dsn = "mysql:host={$config['db_host']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$config['db_name']}`");

    // Заявки
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        phone       VARCHAR(50)  NOT NULL,
        date        VARCHAR(100) NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Пользователи
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        login       VARCHAR(50) NOT NULL UNIQUE,
        pass_hash   VARCHAR(255) NOT NULL,
        role        ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Если пользователей нет — создаём первого админа
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO users (login, pass_hash, role) VALUES (?, ?, 'admin')");
        $stmt->execute([
            $config['default_admin_login'],
            password_hash($config['default_admin_pass'], PASSWORD_DEFAULT),
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Ошибка БД: ' . $e->getMessage());
}