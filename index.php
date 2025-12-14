<?php 
require_once 'config/header.php'; 

// 基于 Cookie 判断要加载的页面
$page_to_load = 'login.php';
if (isset($_COOKIE['admin'])) {
    $username = $_COOKIE['admin'];
    // 查询用户信息
    $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    
    if ($row) {
        $page_to_load = $row['role'] === 'admin' ? 'Start/Admin.php' : 'Start/User.php';
    }
}

// 直接包含页面而不是使用 iframe
if (file_exists($page_to_load)) {
    include $page_to_load;
    exit;
} else {
    // 如果文件不存在，则显示错误信息
    echo "页面文件不存在: " . htmlspecialchars($page_to_load);
    exit;
}
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <title>销假系统</title>
</head>
<body>
    <div id="bg">
        <div id="top">
            <div class="tit">欢迎使用销假管理系统</div>
        </div>
        <iframe id="kuang" src="<?php echo $page_to_load; ?>" width="100%" height="90%" scrolling="auto" frameborder="0">
        </iframe>
    </div>
</body>
</html>