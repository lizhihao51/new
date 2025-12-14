<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>管理员首页</title>
    <link rel="stylesheet" href="../css/css.css" />
</head>
<body>
    <div class="A_app">
        <p class="tit1">管理员首页</p>
        <div class="card">
            <h3>系统概览</h3>
            <p>欢迎使用销假管理系统</p>
            <!-- 这里可以添加统计信息等内容 -->
        </div>
    </div>
</body>
</html>