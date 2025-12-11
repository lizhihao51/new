<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>用户首页</title>
    <link rel="stylesheet" href="../css/css.css" />
</head>
<body>
    <div class="U_app">
        <p class="tit1">用户首页</p>
        <div class="card">
            <h3>欢迎使用</h3>
            <p>欢迎使用销假管理系统</p>
        </div>
    </div>
</body>
</html>