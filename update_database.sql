-- ====================================
-- 数据库更新脚本
-- 移除 phone 字段的 UNIQUE 约束（因为电话非必填）
-- ====================================

USE `checkin_2025`;

-- 删除 phone 字段的唯一索引
ALTER TABLE `participants` DROP INDEX `uk_phone`;

-- 确保 phone 字段可以为 NULL
ALTER TABLE `participants` MODIFY `phone` VARCHAR(20) DEFAULT NULL COMMENT '電話（非必填）';
