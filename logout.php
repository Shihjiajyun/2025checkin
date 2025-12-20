<?php
session_start();

// 清除所有 session 数据
$_SESSION = array();

// 销毁 session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// 销毁 session
session_destroy();

// 跳转到登录页
header('Location: login.php');
exit;
