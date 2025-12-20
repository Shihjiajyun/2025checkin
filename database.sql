-- ====================================
-- 活动签到系统数据库
-- ====================================

-- 1. 创建数据库
CREATE DATABASE IF NOT EXISTS `checkin_2025`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `checkin_2025`;

-- 2. 创建参与者表
CREATE TABLE IF NOT EXISTS `participants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主鍵ID',
    `name` VARCHAR(100) NOT NULL COMMENT '姓名',
    `phone` VARCHAR(20) NOT NULL COMMENT '電話',
    `email` VARCHAR(255) DEFAULT NULL COMMENT '信箱',
    `identity` VARCHAR(50) DEFAULT NULL COMMENT '身分别',
    `remark` TEXT DEFAULT NULL COMMENT '備註',
    `checked_in` TINYINT(1) DEFAULT 0 COMMENT '是否已報到 (0=未報到, 1=已報到)',
    `check_in_time` DATETIME DEFAULT NULL COMMENT '報到時間',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '創建時間',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',

    -- 索引
    UNIQUE KEY `uk_phone` (`phone`) COMMENT '電話號碼唯一索引',
    KEY `idx_checked_in` (`checked_in`) COMMENT '報到狀態索引',
    KEY `idx_created_at` (`created_at`) COMMENT '創建時間索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='參與者信息表';

-- 3. 創建導入記錄表（可選，用於追蹤每次導入）
CREATE TABLE IF NOT EXISTS `import_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主鍵ID',
    `filename` VARCHAR(255) NOT NULL COMMENT '文件名',
    `total_rows` INT UNSIGNED NOT NULL COMMENT '總行數',
    `success_rows` INT UNSIGNED NOT NULL COMMENT '成功導入行數',
    `failed_rows` INT UNSIGNED NOT NULL COMMENT '失敗行數',
    `error_message` TEXT DEFAULT NULL COMMENT '錯誤信息',
    `uploaded_by` VARCHAR(50) NOT NULL COMMENT '上傳者用戶名',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '導入時間',

    KEY `idx_created_at` (`created_at`) COMMENT '導入時間索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='導入日誌表';
