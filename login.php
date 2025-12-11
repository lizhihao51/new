<?php
if(isset($_COOKIE['admin'])){
    echo "<script>window.location.href=\"index.php\";</script>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>登录页面</title>
    <link rel="stylesheet" href="css/style.css" type="text/css" />
    <link rel="stylesheet" href="css/login.css" type="text/css" />
</head>
<body>
    <div id="bg">
        <div id="tit1">测试系统v2.0</div>
        <div id="login-form">
            <h2 class="form-title">用户登录</h2>
            <?php if ($message) : ?>
                <div style="color: red; text-align: center; margin: 10px 0;"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST" >
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn-login">登录</button>
            </form>
        </div>
    </div>
</body>
</html>