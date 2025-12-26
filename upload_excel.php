<?php
// 错误记录到日志文件
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/upload_errors.log');
error_reporting(E_ALL);

// 辅助日志函数
function writeLog($message) {
    $logFile = __DIR__ . '/logs/upload_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("=== 开始处理上传请求 ===");

session_start();
writeLog("Session started");

require_once 'config.php';
writeLog("Config loaded");

require_once 'vendor/autoload.php';
writeLog("Autoload loaded");

use PhpOffice\PhpSpreadsheet\IOFactory;

// 确保输出 JSON 格式
header('Content-Type: application/json; charset=utf-8');
writeLog("Header set");

// 檢查登錄和管理員權限
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    writeLog("权限检查失败");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '無權限訪問']);
    exit;
}
writeLog("权限检查通过");

// 只處理 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog("非POST请求: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允許']);
    exit;
}
writeLog("请求方法正确");

// 檢查文件上傳
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = isset($_FILES['excel_file']) ? $_FILES['excel_file']['error'] : 'no file';
    writeLog("文件上传失败: error=" . $uploadError);
    echo json_encode(['success' => false, 'message' => '文件上傳失敗']);
    exit;
}
writeLog("文件上传成功: " . $_FILES['excel_file']['name']);

$file = $_FILES['excel_file'];
$filename = $file['name'];
$tmpPath = $file['tmp_name'];

// 驗證文件類型
$allowedExtensions = ['xlsx', 'xls'];
$fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => '僅支持 .xlsx 和 .xls 格式']);
    exit;
}

try {
    writeLog("开始读取Excel文件: $tmpPath");
    // 讀取 Excel 文件
    $spreadsheet = IOFactory::load($tmpPath);
    writeLog("IOFactory::load 成功");
    $worksheet = $spreadsheet->getActiveSheet();
    writeLog("获取活动工作表成功");
    $rows = $worksheet->toArray();
    writeLog("转换为数组成功，共 " . count($rows) . " 行");

    if (empty($rows) || count($rows) < 2) {
        throw new Exception('Excel 文件為空或格式不正確');
    }

    // 驗證表頭（第一行）- 智能搜索简繁体
    $header = array_map('trim', $rows[0]);

    // 辅助函数：搜索列索引（支持多个可能的名称）
    $findColumn = function($possibleNames) use ($header) {
        foreach ($possibleNames as $name) {
            $index = array_search($name, $header);
            if ($index !== false) {
                return $index;
            }
        }
        return false;
    };

    $nameCol = $findColumn(['姓名', '名字']);
    $phoneCol = $findColumn(['電話', '电话', '手機', '手机', '联系电话', '聯繫電話']);
    $emailCol = $findColumn(['信箱', 'Email', 'email', 'E-mail', '電子郵件', '电子邮件']);
    $identityCol = $findColumn(['身分別', '身分别', '身份', '身份別']);
    $remarkCol = $findColumn(['備註', '备注', '註記', '注记']);

    // 只有姓名是必填
    if ($nameCol === false) {
        throw new Exception('Excel 表頭必須包含"姓名"字段');
    }

    // 資料庫連接
    writeLog("开始连接数据库");
    $pdo = getDbConnection();
    writeLog("数据库连接成功");
    $pdo->beginTransaction();
    writeLog("开始事务");

    // 清空舊數據
    // 注意：不重置 AUTO_INCREMENT，因为 ID 不对用户可见，让它自然增长即可
    $pdo->exec("DELETE FROM participants");
    writeLog("清空旧数据完成");

    $totalRows = 0;
    $successRows = 0;
    $failedRows = 0;
    $errors = [];

    // 跳过表头，从第二行开始处理
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        // 提取姓名
        $name = ($nameCol !== false && isset($row[$nameCol])) ? trim($row[$nameCol]) : '';

        // 如果姓名为空，跳过这一行（不统计）
        if (empty($name)) {
            continue;
        }

        // 只有有姓名的行才计入总数
        $totalRows++;

        // 提取其他数据（安全检查索引）
        $phone = ($phoneCol !== false && isset($row[$phoneCol])) ? trim($row[$phoneCol]) : null;
        $email = ($emailCol !== false && isset($row[$emailCol])) ? trim($row[$emailCol]) : null;
        $identity = ($identityCol !== false && isset($row[$identityCol])) ? trim($row[$identityCol]) : null;
        $remark = ($remarkCol !== false && isset($row[$remarkCol])) ? trim($row[$remarkCol]) : null;

        // 清理空字符串为 null
        if ($phone === '') $phone = null;
        if ($email === '') $email = null;
        if ($identity === '') $identity = null;
        if ($remark === '') $remark = null;

        try {
            // 直接插入（允许重复）
            $sql = "INSERT INTO participants (name, phone, email, identity, remark)
                    VALUES (:name, :phone, :email, :identity, :remark)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'identity' => $identity,
                'remark' => $remark
            ]);

            $successRows++;
        } catch (PDOException $e) {
            $failedRows++;
            $errors[] = "第 " . ($i + 1) . " 行導入失敗：" . $e->getMessage();
        }
    }

    // 記錄導入日誌
    $logSql = "INSERT INTO import_logs (filename, total_rows, success_rows, failed_rows, error_message, uploaded_by)
               VALUES (:filename, :total_rows, :success_rows, :failed_rows, :error_message, :uploaded_by)";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([
        'filename' => $filename,
        'total_rows' => $totalRows,
        'success_rows' => $successRows,
        'failed_rows' => $failedRows,
        'error_message' => empty($errors) ? null : implode("\n", $errors),
        'uploaded_by' => $_SESSION['username']
    ]);

    $pdo->commit();
    writeLog("事务提交成功，总计: $totalRows, 成功: $successRows, 失败: $failedRows");

    echo json_encode([
        'success' => true,
        'message' => "導入完成",
        'data' => [
            'total' => $totalRows,
            'success' => $successRows,
            'failed' => $failedRows,
            'errors' => $errors
        ]
    ]);
    writeLog("=== 处理完成，返回成功响应 ===");
} catch (Exception $e) {
    writeLog("捕获异常: " . $e->getMessage());
    writeLog("异常位置: " . $e->getFile() . ":" . $e->getLine());
    writeLog("异常堆栈: " . $e->getTraceAsString());

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        writeLog("事务已回滚");
    }

    echo json_encode([
        'success' => false,
        'message' => '導入失敗：' . $e->getMessage()
    ]);
    writeLog("=== 处理失败，返回错误响应 ===");
}
