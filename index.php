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

        /* 電話末三碼搜尋容器 */
        .search-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            margin-top: 30px;
        }

        .search-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .search-form {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 15px 20px;
            font-size: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
            text-align: center;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-button {
            padding: 15px 40px;
            font-size: 18px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .search-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .search-hint {
            text-align: center;
            color: #999;
            font-size: 14px;
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

            .search-container {
                padding: 20px;
                margin-top: 20px;
            }

            .search-title {
                font-size: 20px;
                margin-bottom: 15px;
            }

            .search-form {
                flex-direction: column;
                gap: 12px;
            }

            .search-input {
                padding: 12px 15px;
                font-size: 16px;
            }

            .search-button {
                padding: 12px 30px;
                font-size: 16px;
                width: 100%;
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

            .search-container {
                padding: 15px;
                margin-top: 15px;
                border-radius: 15px;
            }

            .search-title {
                font-size: 18px;
                margin-bottom: 12px;
            }

            .search-form {
                gap: 10px;
            }

            .search-input {
                padding: 10px 15px;
                font-size: 14px;
            }

            .search-button {
                padding: 10px 20px;
                font-size: 14px;
            }

            .search-hint {
                font-size: 12px;
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

        <!-- 電話末三碼搜尋容器 -->
        <div class="search-container">
            <h2 class="search-title">電話末三碼查詢</h2>
            <form class="search-form" id="searchForm">
                <input
                    type="text"
                    id="searchInput"
                    class="search-input"
                    placeholder="請輸入電話號碼末三碼"
                    required
                    maxlength="3"
                    pattern="[0-9]*"
                    inputmode="numeric"
                    autocomplete="off"
                >
                <button type="submit" class="search-button" id="searchBtn">查詢</button>
            </form>
            <div class="search-hint">請輸入參加者電話號碼的末三碼進行查詢</div>
        </div>
    </main>

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

        // 電話末三碼搜尋功能
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');

        // 搜索表单提交
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const keyword = searchInput.value.trim();
            if (!keyword) {
                Swal.fire({
                    icon: 'warning',
                    title: '請輸入查詢關鍵字',
                    text: '請輸入電話號碼末三碼'
                });
                return;
            }

            // 驗證是否為數字
            if (!/^\d+$/.test(keyword)) {
                Swal.fire({
                    icon: 'warning',
                    title: '格式錯誤',
                    text: '請輸入有效的數字'
                });
                return;
            }

            searchBtn.disabled = true;
            searchBtn.textContent = '查詢中...';

            const formData = new FormData();
            formData.append('action', 'search');
            formData.append('keyword', keyword);

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
                    if (data.data.length === 1) {
                        // 只有一个结果，直接显示
                        showParticipantDetail(data.data[0]);
                    } else {
                        // 多个结果，让用户选择
                        showParticipantList(data.data);
                    }
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: '未找到結果',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('搜索錯誤:', error);
                Swal.fire({
                    icon: 'error',
                    title: '查詢失敗',
                    text: error.message
                });
            })
            .finally(() => {
                searchBtn.disabled = false;
                searchBtn.textContent = '查詢';
            });
        });

        // 显示参加者列表（多个结果）
        function showParticipantList(participants) {
            const options = {};
            participants.forEach(p => {
                const status = p.checked_in ? '（已報到）' : '';
                options[p.id] = `${p.name}${status}`;
            });

            Swal.fire({
                title: '找到多位參加者',
                text: '請詢問貴姓後選擇',
                input: 'select',
                inputOptions: options,
                inputPlaceholder: '選擇參加者',
                showCancelButton: true,
                cancelButtonText: '取消',
                confirmButtonText: '選擇此人',
                confirmButtonColor: '#667eea'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const selectedParticipant = participants.find(p => p.id == result.value);
                    showParticipantDetail(selectedParticipant);
                }
            });
        }

        // 显示参加者详细信息
        function showParticipantDetail(participant) {
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
                        performCheckIn(participant.id, participant.name);
                    }
                });
            }
        }

        // 执行报到
        function performCheckIn(participantId, participantName) {
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
                });
            });
        }

        // HTML 转义函数
        function escapeHtml(text) {
            if (!text) return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 回车键触发搜索
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchForm.dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>
