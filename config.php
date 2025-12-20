<?php
// 設置時區為台灣時間 (UTC+8)
date_default_timezone_set('Asia/Taipei');

// 資料庫配置
define('DB_HOST', '13.114.174.139');
define('DB_USER', 'myuser');
define('DB_PASS', '123456789');
define('DB_PORT', '3306');
define('DB_NAME', 'checkin_2025');

// 獲取數據庫連接
function getDbConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // 設置 MySQL 時區為 UTC+8
        $pdo->exec("SET time_zone = '+08:00'");

        return $pdo;
    } catch (PDOException $e) {
        die("數據庫連接失敗: " . $e->getMessage());
    }
}