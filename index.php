<?php 
$configPath = 'config.php';
require_once 'includes/header.php'; 

// 基于 Cookie 判断 iframe 源
$iframe_src = 'login.php';
$user = validate_user_cookie();
if ($user) {
    $iframe_src = $user['role'] === 'admin' ? 'Start/Admin.php' : 'Start/User.php';
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
        <iframe id="kuang" src="<?php echo $iframe_src; ?>" width="100%" height="90%" scrolling="auto" frameborder="0">
        </iframe>
    </div>
</body>
</html>

<?php 
// 检查是否已经登录，如果登录了就判断权限
if (!isset($_COOKIE['admin'])) {
    echo "<script>window.location.href=\"login.php\";</script>";
} else {

        $cookee = $_COOKIE["admin"];
        // 使用新的数据库连接方式查询用户权限
        $cookee = $login->escapeString($cookee);
        $sql = "SELECT power FROM user WHERE unam='$cookee'";
        $row = $login->fetchRow($sql);
        
        if ($row && $row['role'] == 'admin') {
            // 管理员
            echo "<script>var link=document.getElementById(\"kuang\");</script>";
            echo "<script>link.src=\"Start/Admin.php\";</script>";
        } else {
            // 用户
            echo "<script>var link=document.getElementById(\"kuang\");</script>";
            echo "<script>link.src=\"Start/User.php\";</script>";
        }
    
}
?>