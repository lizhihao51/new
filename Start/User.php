<?php
require_once '../includes/header.php';

// 检查是否已登录
check_login();
?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>用户端导航</title>
<link href="../css/style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div id="top">
    <div id="user-info">
        欢迎, <?php echo htmlspecialchars($_SESSION['name']); ?>
        <a href="../logout.php" id="logout-btn">安全退出</a>
    </div>
    <div class="tit">用户导航</div>
</div>

<div id="box">
  <div id="left">
    <div class="Btn-cd">
      <a href="user/U_Default.php" target="right-kuang">首页</a>
    </div>

    <div class="Btn-cd">
      <a href="user/U_my_tasks.php" target="right-kuang">我的任务</a>
    </div>
	
    <div class="Btn-cd">
      <a href="user/U_Edit.php" target="right-kuang">修改密码</a>
    </div>
  </div>
  
  <div id="right">
    <div id="main-bg">
      <iframe id="right-kuang" src="user/U_Default.php" scrolling="auto" name="right-kuang"></iframe>
    </div>
  </div>
</div>
</body>
</html>