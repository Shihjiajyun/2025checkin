<?php
session_start();
require_once 'config.php';

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 检查是否为管理员
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$name = $_SESSION['name'];

// 获取数据库连接
$pdo = getDbConnection();

// 查询统计数据
$totalSql = "SELECT COUNT(*) as total FROM participants";
$checkedInSql = "SELECT COUNT(*) as checked_in FROM participants WHERE checked_in = 1";

$totalResult = $pdo->query($totalSql)->fetch();
$checkedInResult = $pdo->query($checkedInSql)->fetch();

$totalCount = $totalResult['total'];
$checkedInCount = $checkedInResult['checked_in'];
$checkInRate = $totalCount > 0 ? round(($checkedInCount / $totalCount) * 100) : 0;

// 查询所有参与者（按 Excel 导入顺序，即 ID 升序）
$participantsSql = "SELECT id, name, phone, email, identity, remark, checked_in, check_in_time, created_at
                    FROM participants
                    ORDER BY id ASC";
$participants = $pdo->query($participantsSql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>報到總表 - 活動簽到系統</title>
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

    .btn-upload {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }

    .btn-upload:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    main {
        flex: 1;
        padding: 40px;
    }

    .page-header {
        max-width: 85vw;
        margin: 0 auto 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-title {
        font-size: 32px;
        font-weight: bold;
        color: #333;
    }

    .report-container {
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .report-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 8px;
        color: white;
        text-align: center;
    }

    .stat-label {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 28px;
        font-weight: bold;
    }

    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead {
        background: #f5f5f5;
    }

    th {
        padding: 15px;
        text-align: left;
        font-weight: bold;
        color: #333;
        border-bottom: 2px solid #e0e0e0;
    }

    td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        color: #666;
    }

    tbody tr:hover {
        background: #f9f9f9;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    .status-checked {
        background: #d4edda;
        color: #155724;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-icon {
        font-size: 48px;
        margin-bottom: 20px;
    }

    /* 上传对话框样式 */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
    }

    .modal-header {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 20px;
        color: #333;
    }

    .modal-body {
        margin-bottom: 20px;
    }

    .file-input-wrapper {
        border: 2px dashed #ccc;
        border-radius: 8px;
        padding: 40px 20px;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
    }

    .file-input-wrapper:hover {
        border-color: #667eea;
        background: #f9f9ff;
    }

    .file-input-wrapper.has-file {
        border-color: #667eea;
        background: #f0f0ff;
    }

    #excelFile {
        display: none;
    }

    .modal-footer {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
    }

    .message {
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
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

        .page-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .page-title {
            font-size: 24px;
        }

        .btn-upload {
            width: 100%;
        }

        .report-container {
            padding: 20px;
        }

        .report-stats {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            font-size: 14px;
        }

        th,
        td {
            padding: 10px;
        }
    }

    /* RWD - 超小屏幕 */
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

        main {
            padding: 15px;
        }

        .page-title {
            font-size: 20px;
        }

        .report-container {
            padding: 15px;
        }

        .stat-card {
            padding: 15px;
        }

        .stat-label {
            font-size: 12px;
        }

        .stat-value {
            font-size: 20px;
        }

        table {
            font-size: 12px;
        }

        th,
        td {
            padding: 8px;
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
                <span class="user-role">管理員</span>
            </div>

            <div class="header-buttons">
                <a href="index.php" class="btn">返回首頁</a>
                <a href="search.php" class="btn">電話號碼查詢</a>
                <a href="logout.php" class="btn btn-logout">登出</a>
            </div>
        </div>
    </header>

    <main>
        <div class="page-header">
            <h1 class="page-title">報到總表</h1>
            <button class="btn btn-upload" onclick="openUploadModal()">上傳 Excel 名單</button>
        </div>

        <div id="messageContainer"></div>

        <div class="report-container">
            <div class="report-stats">
                <div class="stat-card">
                    <div class="stat-label">總報名人數</div>
                    <div class="stat-value"><?= $totalCount ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">已報到人數</div>
                    <div class="stat-value"><?= $checkedInCount ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">報到率</div>
                    <div class="stat-value"><?= $checkInRate ?>%</div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>序號</th>
                            <th>姓名</th>
                            <th>電話</th>
                            <th>信箱</th>
                            <th>身分別</th>
                            <th>備註</th>
                            <th>報到狀態</th>
                            <th>報到時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon">📋</div>
                                    <div>目前尚無報名資料，請上傳 Excel 名單</div>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($participants as $index => $participant): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($participant['name']) ?></td>
                            <td><?= htmlspecialchars($participant['phone']) ?></td>
                            <td><?= htmlspecialchars($participant['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($participant['identity'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($participant['remark'] ?? '-') ?></td>
                            <td>
                                <?php if ($participant['checked_in']): ?>
                                <span class="status-badge status-checked">已報到</span>
                                <?php else: ?>
                                <span class="status-badge status-pending">未報到</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $participant['check_in_time'] ? date('Y-m-d H:i', strtotime($participant['check_in_time'])) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- 上传对话框 -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">上傳 Excel 名單</div>
            <div class="modal-body">
                <p style="color: #666; margin-bottom: 15px;">
                    請選擇包含以下欄位的 Excel 文件：<br>
                    <strong>姓名、電話、信箱、身分別、備註</strong>
                </p>
                <div class="file-input-wrapper" onclick="document.getElementById('excelFile').click()">
                    <div id="fileInputText">點擊選擇文件或拖拽文件到此處</div>
                    <input type="file" id="excelFile" accept=".xlsx,.xls">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeUploadModal()">取消</button>
                <button class="btn btn-upload" onclick="uploadExcel()">上傳</button>
            </div>
        </div>
    </div>

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

    // 上传功能
    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('active');
    }

    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('active');
        document.getElementById('excelFile').value = '';
        document.getElementById('fileInputText').textContent = '點擊選擇文件或拖拽文件到此處';
        document.querySelector('.file-input-wrapper').classList.remove('has-file');
    }

    document.getElementById('excelFile').addEventListener('change', function() {
        if (this.files.length > 0) {
            document.getElementById('fileInputText').textContent = '已選擇：' + this.files[0].name;
            document.querySelector('.file-input-wrapper').classList.add('has-file');
        }
    });

    function uploadExcel() {
        const fileInput = document.getElementById('excelFile');
        if (!fileInput.files.length) {
            showMessage('請選擇文件', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);

        const uploadBtn = event.target;
        uploadBtn.disabled = true;
        uploadBtn.textContent = '上傳中...';

        fetch('upload_excel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // 先获取原始文本，便于调试
                return response.text().then(text => {
                    // 检查 HTTP 状态
                    if (!response.ok) {
                        throw new Error(`服務器錯誤 (${response.status}): ${text}`);
                    }
                    // 安全地解析 JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('無法解析的響應:', text);
                        throw new Error('服務器返回了無效的數據格式，請檢查控制台');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    let message = `成功導入 ${data.data.success} 條記錄`;
                    if (data.data.failed > 0) {
                        message += `，失敗 ${data.data.failed} 條`;
                    }
                    showMessage(message, 'success');
                    closeUploadModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('上傳錯誤:', error);
                showMessage('上傳失敗：' + error.message, 'error');
            })
            .finally(() => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = '上傳';
            });
    }

    function showMessage(message, type) {
        const container = document.getElementById('messageContainer');
        const div = document.createElement('div');
        div.className = `message ${type}`;
        div.textContent = message;
        container.innerHTML = '';
        container.appendChild(div);
        setTimeout(() => div.remove(), 5000);
    }
    </script>
</body>

</html>