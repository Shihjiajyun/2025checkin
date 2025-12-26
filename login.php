<?php
session_start();

// 如果已登录，跳转到首页
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// 账号密码配置
$credentials = [
    '2025con' => [
        'password' => '8812025',
        'role' => 'admin',
        'name' => '管理員'
    ],
    'staff' => [
        'password' => 'staff123',
        'role' => 'staff',
        'name' => '工作人員'
    ]
];

$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (isset($credentials[$username]) && $credentials[$username]['password'] === $password) {
        // 登录成功
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $credentials[$username]['role'];
        $_SESSION['name'] = $credentials[$username]['name'];

        header('Location: index.php');
        exit;
    } else {
        $error = '帳號或密碼錯誤';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 活動簽到系統</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .logo-wrapper {
        text-align: center;
        margin-bottom: 30px;
    }

    .logo {
        max-width: 300px;
        width: 100%;
        height: auto;
    }

    .login-container {
        background: white;
        border-radius: 20px;
        padding: 50px 60px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 450px;
        width: 100%;
    }

    .login-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .login-title {
        font-size: 28px;
        font-weight: bold;
        color: #333;
        margin-bottom: 10px;
    }

    .login-subtitle {
        font-size: 14px;
        color: #666;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin-bottom: 8px;
    }

    .form-input {
        width: 100%;
        padding: 12px 15px;
        font-size: 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .form-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .error-message {
        background: #fee;
        color: #c33;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        border: 1px solid #fcc;
    }

    .login-button {
        width: 100%;
        padding: 15px;
        font-size: 18px;
        font-weight: bold;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .login-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .login-button:active {
        transform: translateY(0);
    }

    /* RWD */
    @media (max-width: 480px) {
        .logo {
            max-width: 200px;
        }

        .login-container {
            padding: 30px 25px;
        }

        .login-title {
            font-size: 24px;
        }

        .login-subtitle {
            font-size: 13px;
        }
    }
    </style>
</head>

<body>
    <div class="logo-wrapper">
        <img src="./src/logo.png" alt="活動Logo" class="logo">
    </div>

    <div class="login-container">
        <div class="login-header">
            <h1 class="login-title">活動簽到系統</h1>
            <p class="login-subtitle">靈感日日村 × 拉麵社聯合年會</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">帳號</label>
                <input type="text" id="username" name="username" class="form-input" required autofocus
                    autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">密碼</label>
                <input type="password" id="password" name="password" class="form-input" required
                    autocomplete="current-password">
            </div>

            <button type="submit" class="login-button">登入</button>
        </form>
    </div>
</body>

</html>