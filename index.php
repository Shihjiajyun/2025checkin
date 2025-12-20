<?php
session_start();
require_once 'config.php';

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$isAdmin = ($role === 'admin');

// 获取数据库连接并查询统计数据
$pdo = getDbConnection();

$totalSql = "SELECT COUNT(*) as total FROM participants";
$checkedInSql = "SELECT COUNT(*) as checked_in FROM participants WHERE checked_in = 1";

$totalResult = $pdo->query($totalSql)->fetch();
$checkedInResult = $pdo->query($checkedInSql)->fetch();

$totalCount = $totalResult['total'];
$checkedInCount = $checkedInResult['checked_in'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>靈感日日村 × 拉麵社聯合年會 - 活動簽到系統</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            height: 80px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 8px;
        }

        .user-name {
            font-weight: bold;
            color: #333;
        }

        .user-role {
            font-size: 12px;
            color: #666;
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-logout {
            background: #f5f5f5;
            border-color: #999;
            color: #666;
        }

        .btn-logout:hover {
            background: #e0e0e0;
            border-color: #666;
            color: #333;
        }

        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .event-card {
            background: white;
            border-radius: 20px;
            padding: 60px 80px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
        }

        .event-title {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 40px;
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }

        .event-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .info-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            color: white;
        }

        .info-label {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .info-value {
            font-size: 32px;
            font-weight: bold;
        }

        .event-date {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
    </style>
</head>
<body>
    <header>
        <img src="./src/logo.png" alt="活動Logo" class="logo">

        <div class="header-right">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($name) ?></span>
                <span class="user-role"><?= $isAdmin ? '管理員' : '工作人員' ?></span>
            </div>

            <div class="header-buttons">
                <?php if ($isAdmin): ?>
                    <a href="report.php" class="btn">報到總表</a>
                <?php endif; ?>
                <a href="search.php" class="btn">電話號碼查詢</a>
                <a href="logout.php" class="btn btn-logout">登出</a>
            </div>
        </div>
    </header>

    <main>
        <div class="event-card">
            <h1 class="event-title">靈感日日村 × 拉麵社聯合年會</h1>

            <div class="event-info">
                <div class="info-item event-date">
                    <div class="info-label">活動日期</div>
                    <div class="info-value">2025/12/28</div>
                </div>

                <div class="info-item">
                    <div class="info-label">報名人數</div>
                    <div class="info-value"><?= $totalCount ?> 人</div>
                </div>

                <div class="info-item">
                    <div class="info-label">報到人數</div>
                    <div class="info-value"><?= $checkedInCount ?> 人</div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
