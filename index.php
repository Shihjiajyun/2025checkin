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

    <!-- SweetAlert2 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* 汉堡菜单 */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background: #667eea;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .hamburger:hover {
            background: #e0e0e0;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
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

        /* QR Code 扫描容器 */
        .scanner-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            margin-top: 30px;
        }

        .scanner-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        #qr-reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border: 2px solid #667eea;
            border-radius: 12px;
            overflow: hidden;
        }

        #qr-reader__dashboard_section_csr {
            display: none !important;
        }

        .scanner-status {
            text-align: center;
            margin-top: 20px;
            font-size: 16px;
            color: #666;
        }

        .scanner-controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-scanner {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-scanner:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-scanner.stop {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-scanner.stop:hover {
            background: #dc3545;
            color: white;
        }

        /* RWD - 平板和小屏幕 */
        @media (max-width: 768px) {
            header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
            }

            .logo {
                height: 60px;
            }

            .hamburger {
                display: flex;
            }

            .header-right {
                position: fixed;
                top: 0;
                right: -100%;
                width: 280px;
                height: 100vh;
                background: white;
                box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
                flex-direction: column;
                padding: 20px;
                transition: right 0.3s ease;
                z-index: 999;
                overflow-y: auto;
            }

            .header-right.active {
                right: 0;
            }

            .header-buttons {
                flex-direction: column;
                width: 100%;
                gap: 10px;
                margin-top: 20px;
            }

            .btn {
                padding: 12px 20px;
                font-size: 14px;
                width: 100%;
            }

            .user-info {
                width: 100%;
                justify-content: center;
            }

            main {
                padding: 20px;
            }

            .event-card {
                padding: 30px 20px;
            }

            .event-title {
                font-size: 24px;
                margin-bottom: 20px;
                padding-bottom: 15px;
            }

            .event-info {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-top: 20px;
            }

            .info-item {
                padding: 20px;
            }

            .info-label {
                font-size: 16px;
            }

            .info-value {
                font-size: 24px;
            }

            .event-date {
                grid-column: 1;
            }

            .scanner-container {
                padding: 20px;
                margin-top: 20px;
            }

            .scanner-title {
                font-size: 20px;
                margin-bottom: 15px;
            }

            #qr-reader {
                max-width: 100%;
            }

            .scanner-controls {
                flex-direction: column;
                gap: 10px;
            }

            .btn-scanner {
                width: 100%;
                padding: 12px 20px;
            }
        }

        /* RWD - 超小屏幕（手机竖屏） */
        @media (max-width: 480px) {
            header {
                padding: 10px 15px;
            }

            .logo {
                height: 50px;
            }

            .user-info {
                padding: 8px 15px;
                font-size: 14px;
            }

            .user-name {
                font-size: 14px;
            }

            .user-role {
                font-size: 10px;
            }

            .btn {
                padding: 8px 15px;
                font-size: 13px;
            }

            main {
                padding: 15px;
            }

            .event-card {
                padding: 20px 15px;
                border-radius: 15px;
            }

            .event-title {
                font-size: 20px;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #667eea;
            }

            .event-info {
                gap: 12px;
                margin-top: 15px;
            }

            .info-item {
                padding: 15px;
            }

            .info-label {
                font-size: 14px;
                margin-bottom: 8px;
            }

            .info-value {
                font-size: 20px;
            }

            .scanner-container {
                padding: 15px;
                margin-top: 15px;
                border-radius: 15px;
            }

            .scanner-title {
                font-size: 18px;
                margin-bottom: 12px;
            }

            .scanner-status {
                font-size: 14px;
                margin-top: 15px;
            }

            .btn-scanner {
                padding: 10px 15px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="./src/logo.png" alt="活動Logo" class="logo">

        <div class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="header-right" id="headerRight">
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

        <!-- QR Code 扫描容器 -->
        <div class="scanner-container">
            <h2 class="scanner-title">QR Code 掃描報到</h2>
            <div id="qr-reader"></div>
            <div class="scanner-status" id="scanner-status">準備就緒，請掃描 QR Code</div>
            <div class="scanner-controls">
                <button class="btn-scanner" id="start-scan" onclick="startScanning()">開始掃描</button>
                <button class="btn-scanner stop" id="stop-scan" onclick="stopScanning()" style="display: none;">停止掃描</button>
            </div>
        </div>
    </main>

    <!-- html5-qrcode 库 -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        // 汉堡菜单功能
        const hamburger = document.getElementById('hamburger');
        const headerRight = document.getElementById('headerRight');

        hamburger.addEventListener('click', function() {
            this.classList.toggle('active');
            headerRight.classList.toggle('active');
        });

        // 点击菜单项后关闭菜单
        document.querySelectorAll('.header-right .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                hamburger.classList.remove('active');
                headerRight.classList.remove('active');
            });
        });

        // 点击外部关闭菜单
        document.addEventListener('click', function(e) {
            if (!hamburger.contains(e.target) && !headerRight.contains(e.target)) {
                hamburger.classList.remove('active');
                headerRight.classList.remove('active');
            }
        });

        // QR Code 扫描功能
        let html5QrCode = null;
        let isScanning = false;

        function startScanning() {
            if (isScanning) return;

            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };

            html5QrCode = new Html5Qrcode("qr-reader");

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).then(() => {
                isScanning = true;
                document.getElementById('start-scan').style.display = 'none';
                document.getElementById('stop-scan').style.display = 'inline-block';
                updateStatus('掃描中...', '#667eea');
            }).catch(err => {
                console.error('無法啟動掃描器:', err);
                Swal.fire({
                    icon: 'error',
                    title: '啟動失敗',
                    text: '無法啟動相機，請檢查權限設置'
                });
            });
        }

        function stopScanning() {
            if (!isScanning || !html5QrCode) return;

            html5QrCode.stop().then(() => {
                isScanning = false;
                document.getElementById('start-scan').style.display = 'inline-block';
                document.getElementById('stop-scan').style.display = 'none';
                updateStatus('掃描已停止', '#999');
            }).catch(err => {
                console.error('停止掃描失敗:', err);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            // 暂停扫描，处理结果
            if (!isScanning) return;

            // 解析 QR code（格式：CHECKIN:ID）
            let participantId = null;
            if (decodedText.startsWith('CHECKIN:')) {
                participantId = decodedText.substring(8);
            } else {
                // 兼容直接扫描 ID 的情况
                participantId = decodedText;
            }

            if (!participantId || isNaN(participantId)) {
                Swal.fire({
                    icon: 'warning',
                    title: '無效的 QR Code',
                    html: '<div style="font-size: 18px; padding: 20px 0;">請掃描有效的報到 QR Code<br>或至<strong style="color: #667eea;">人工櫃檯</strong>協助</div>',
                    confirmButtonText: '確定',
                    confirmButtonColor: '#667eea',
                    allowOutsideClick: false
                }).then(() => {
                    // 重新启动扫描
                    startScanning();
                });
                return;
            }

            // 停止扫描
            stopScanning();

            // 先获取用户信息，显示确认弹窗
            getParticipantInfo(participantId);
        }

        function getParticipantInfo(participantId) {
            updateStatus('查詢中...', '#ffc107');

            const formData = new FormData();
            formData.append('action', 'get_participant');
            formData.append('participant_id', participantId);

            Swal.fire({
                title: '查詢中...',
                text: '正在獲取參加者資料',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('./search.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text().then(text => {
                if (!response.ok) {
                    throw new Error(`服務器錯誤 (${response.status}): ${text}`);
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('無法解析的響應:', text);
                    throw new Error('服務器返回了無效的數據格式');
                }
            }))
            .then(data => {
                if (data.success) {
                    // 显示确认弹窗
                    showConfirmDialog(data.data);
                } else {
                    // 检查是否为找不到参加者
                    if (data.message.includes('不存在') || data.message.includes('未找到') || data.message.includes('查無')) {
                        Swal.fire({
                            icon: 'warning',
                            title: '查無報名資料',
                            html: '<div style="font-size: 18px; padding: 20px 0;">請至<strong style="color: #667eea;">人工櫃檯</strong>協助</div>',
                            confirmButtonText: '確定',
                            confirmButtonColor: '#667eea',
                            allowOutsideClick: false
                        }).then(() => {
                            // 重新启动扫描
                            startScanning();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '查詢失敗',
                            text: data.message,
                            confirmButtonColor: '#dc3545'
                        }).then(() => {
                            // 重新启动扫描
                            startScanning();
                        });
                    }
                }
            })
            .catch(error => {
                console.error('查詢錯誤:', error);
                Swal.fire({
                    icon: 'error',
                    title: '查詢失敗',
                    text: error.message,
                    confirmButtonColor: '#dc3545'
                }).then(() => {
                    // 重新启动扫描
                    startScanning();
                });
            });
        }

        function showConfirmDialog(participant) {
            const statusText = participant.checked_in ?
                `<span style="color: #28a745; font-weight: bold;">✓ 已報到</span>` :
                `<span style="color: #ffc107; font-weight: bold;">未報到</span>`;

            const checkInTime = participant.check_in_time ?
                new Date(participant.check_in_time).toLocaleString('zh-TW') :
                '-';

            const html = `
                <div style="text-align: left; padding: 10px 0;">
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">姓名：</div>
                        <div style="color: #333; flex: 1;">${escapeHtml(participant.name)}</div>
                    </div>
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">電話：</div>
                        <div style="color: #333; flex: 1;">${escapeHtml(participant.phone || '-')}</div>
                    </div>
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">信箱：</div>
                        <div style="color: #333; flex: 1;">${escapeHtml(participant.email || '-')}</div>
                    </div>
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">身分別：</div>
                        <div style="color: #333; flex: 1;">${escapeHtml(participant.identity || '-')}</div>
                    </div>
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">備註：</div>
                        <div style="color: #333; flex: 1;">${escapeHtml(participant.remark || '-')}</div>
                    </div>
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">報到狀態：</div>
                        <div style="flex: 1;">${statusText}</div>
                    </div>
                    ${participant.checked_in ? `
                    <div style="display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: bold; color: #667eea; min-width: 80px;">報到時間：</div>
                        <div style="color: #333; flex: 1;">${checkInTime}</div>
                    </div>
                    ` : ''}
                </div>
            `;

            // 如果已经报到，只显示信息
            if (participant.checked_in) {
                Swal.fire({
                    title: '參加者資料',
                    html: html,
                    icon: 'info',
                    confirmButtonText: '確定',
                    confirmButtonColor: '#667eea'
                }).then(() => {
                    // 重新启动扫描
                    startScanning();
                });
            } else {
                // 未报到，显示报到按钮
                Swal.fire({
                    title: '參加者資料',
                    html: html,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '確認報到',
                    cancelButtonText: '取消',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        performCheckIn(participant.id);
                    } else {
                        // 重新启动扫描
                        startScanning();
                    }
                });
            }
        }

        function escapeHtml(text) {
            if (!text) return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function onScanFailure(error) {
            // 扫描失败（正常情况，不需要处理）
        }

        function performCheckIn(participantId) {
            updateStatus('處理中...', '#ffc107');

            const formData = new FormData();
            formData.append('action', 'checkin');
            formData.append('participant_id', participantId);

            Swal.fire({
                title: '處理中...',
                text: '正在執行報到',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('./search.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text().then(text => {
                if (!response.ok) {
                    throw new Error(`服務器錯誤 (${response.status}): ${text}`);
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('無法解析的響應:', text);
                    throw new Error('服務器返回了無效的數據格式');
                }
            }))
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '報到成功！',
                        text: data.message,
                        confirmButtonColor: '#28a745',
                        timer: 3000
                    }).then(() => {
                        // 刷新页面统计数据
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '報到失敗',
                        text: data.message,
                        confirmButtonColor: '#dc3545'
                    }).then(() => {
                        // 重新启动扫描
                        startScanning();
                    });
                }
            })
            .catch(error => {
                console.error('報到錯誤:', error);
                Swal.fire({
                    icon: 'error',
                    title: '報到失敗',
                    text: error.message,
                    confirmButtonColor: '#dc3545'
                }).then(() => {
                    // 重新启动扫描
                    startScanning();
                });
            });
        }

        function updateStatus(message, color) {
            const statusEl = document.getElementById('scanner-status');
            statusEl.textContent = message;
            statusEl.style.color = color;
        }

        // 页面加载完成后自动启动扫描
        window.addEventListener('load', function() {
            // 延迟启动，确保 DOM 完全加载
            setTimeout(() => {
                startScanning();
            }, 500);
        });
    </script>
</body>
</html>
