<?php
// 禁止任何输出，确保 JSON 格式纯净
ini_set('display_errors', '0');
error_reporting(0);

session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// 确保输出 JSON 格式
header('Content-Type: application/json; charset=utf-8');

// 檢查登錄和管理員權限
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '無權限訪問']);
    exit;
}

// 只處理 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允許']);
    exit;
}

// 檢查文件上傳
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上傳失敗']);
    exit;
}

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
    // 讀取 Excel 文件
    $spreadsheet = IOFactory::load($tmpPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

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
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 清空舊數據
    // 注意：不重置 AUTO_INCREMENT，因为 ID 不对用户可见，让它自然增长即可
    $pdo->exec("DELETE FROM participants");

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
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => '導入失敗：' . $e->getMessage()
    ]);
}
