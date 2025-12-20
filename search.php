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

// API 处理部分
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? '';
    $pdo = getDbConnection();

    // 搜索参加者
    if ($action === 'search') {
        $keyword = trim($_POST['keyword'] ?? '');

        if (empty($keyword)) {
            echo json_encode(['success' => false, 'message' => '請輸入姓名或電話']);
            exit;
        }

        try {
            // 支持姓名或电话搜索（模糊匹配）
            $sql = "SELECT id, name, phone, email, identity, remark, checked_in, check_in_time
                    FROM participants
                    WHERE name LIKE ? OR phone LIKE ?
                    LIMIT 10";

            $stmt = $pdo->prepare($sql);
            $likeKeyword = "%{$keyword}%";
            $stmt->execute([$likeKeyword, $likeKeyword]);
            $results = $stmt->fetchAll();

            if (empty($results)) {
                echo json_encode(['success' => false, 'message' => '未找到相關參加者']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $results]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => '查詢失敗：' . $e->getMessage()]);
        }
        exit;
    }

    // 执行报到
    if ($action === 'checkin') {
        $participantId = intval($_POST['participant_id'] ?? 0);

        if ($participantId <= 0) {
            echo json_encode(['success' => false, 'message' => '無效的參加者ID']);
            exit;
        }

        try {
            // 检查参加者是否存在
            $checkSql = "SELECT id, name, checked_in FROM participants WHERE id = :id";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute(['id' => $participantId]);
            $participant = $checkStmt->fetch();

            if (!$participant) {
                echo json_encode(['success' => false, 'message' => '參加者不存在']);
                exit;
            }

            if ($participant['checked_in']) {
                echo json_encode(['success' => false, 'message' => '該參加者已經報到過了']);
                exit;
            }

            // 更新报到状态
            $updateSql = "UPDATE participants
                         SET checked_in = 1, check_in_time = NOW()
                         WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute(['id' => $participantId]);

            echo json_encode([
                'success' => true,
                'message' => "報到成功！\n參加者：{$participant['name']}"
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => '報到失敗：' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => '無效的操作']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>參加者查詢 - 活動簽到系統</title>

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

        .search-container {
            background: white;
            border-radius: 20px;
            padding: 50px 60px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }

        .page-title {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .search-form {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .search-input {
            flex: 1;
            padding: 15px 20px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-button {
            padding: 15px 40px;
            font-size: 16px;
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

        .placeholder-text {
            text-align: center;
            color: #999;
            padding: 40px 20px;
        }

        /* SweetAlert2 自定义样式 */
        .swal2-popup {
            font-family: "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
        }

        .participant-info {
            text-align: left;
            padding: 10px 0;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-label {
            font-weight: bold;
            color: #667eea;
            min-width: 80px;
        }

        .info-value {
            color: #333;
            flex: 1;
        }

        .status-checked {
            color: #28a745;
            font-weight: bold;
        }

        .status-pending {
            color: #ffc107;
            font-weight: bold;
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
                <a href="index.php" class="btn">返回首頁</a>
                <?php if ($isAdmin): ?>
                    <a href="report.php" class="btn">報到總表</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-logout">登出</a>
            </div>
        </div>
    </header>

    <main>
        <div class="search-container">
            <h1 class="page-title">參加者查詢</h1>

            <form class="search-form" id="searchForm">
                <input
                    type="text"
                    id="searchInput"
                    class="search-input"
                    placeholder="請輸入姓名或電話號碼"
                    required
                >
                <button type="submit" class="search-button" id="searchBtn">查詢</button>
            </form>

            <div class="placeholder-text">
                請輸入參加者的姓名或電話號碼進行查詢
            </div>
        </div>
    </main>

    <script>
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
                    text: '請輸入姓名或電話號碼'
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
                const status = p.checked_in ? '✓ 已報到' : '未報到';
                options[p.id] = `${p.name} - ${p.phone || '無電話'} (${status})`;
            });

            Swal.fire({
                title: '找到多位參加者',
                text: '請選擇要查看的參加者',
                input: 'select',
                inputOptions: options,
                inputPlaceholder: '選擇參加者',
                showCancelButton: true,
                cancelButtonText: '取消',
                confirmButtonText: '查看',
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
                `<span class="status-checked">✓ 已報到</span>` :
                `<span class="status-pending">未報到</span>`;

            const checkInTime = participant.check_in_time ?
                new Date(participant.check_in_time).toLocaleString('zh-TW') :
                '-';

            const html = `
                <div class="participant-info">
                    <div class="info-row">
                        <div class="info-label">姓名：</div>
                        <div class="info-value">${escapeHtml(participant.name)}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">電話：</div>
                        <div class="info-value">${escapeHtml(participant.phone || '-')}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">信箱：</div>
                        <div class="info-value">${escapeHtml(participant.email || '-')}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">身分別：</div>
                        <div class="info-value">${escapeHtml(participant.identity || '-')}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">備註：</div>
                        <div class="info-value">${escapeHtml(participant.remark || '-')}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">報到狀態：</div>
                        <div class="info-value">${statusText}</div>
                    </div>
                    ${participant.checked_in ? `
                    <div class="info-row">
                        <div class="info-label">報到時間：</div>
                        <div class="info-value">${checkInTime}</div>
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
                        // 清空搜索框
                        searchInput.value = '';
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
