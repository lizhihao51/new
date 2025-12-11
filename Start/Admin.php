<?php
require_once '../includes/header.php';

// 检查是否为管理员
check_admin();
?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>管理员导航</title>
<link href="../css/style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div id="top">
    <div id="user-info">
        欢迎, <?php echo htmlspecialchars($_SESSION['name']); ?> (管理员)
        <a href="../config/logout.php" id="logout-btn">安全退出</a>
    </div>
    <div class="tit">管理员导航</div>
</div>

<div id="box">
  <div id="left" class="two">
    <div class="Btn-cd">
      <a href="admin/A_Add_Tasks.php" target="right-kuang">创建任务</a>
    </div>
	
	<div class="Btn-cd">
	  <a href="admin/A_Edit_Tasks.php" target="right-kuang">管理任务</a>
	</div>
	
	<div class="Btn-cd">
	  <a href="admin/A_Add_User.php" target="right-kuang">用户管理</a>
	</div>
	
	<div class="Btn-cd">
	  <a href="admin/A_Add_Class.php" target="right-kuang">班级管理</a>
	</div>
	
	<div class="Btn-cd">
	  <a href="admin/A_Add_Student.php" target="right-kuang">学生管理</a>
	</div>
	
    <div class="Btn-cd">
      <a href="user/U_Edit.php" target="right-kuang">修改密码</a>
    </div>
  </div>
  
  <div id="right" class="two">
    <div id="main-bg">
      <iframe id="right-kuang" src="admin/A_Default.php" scrolling="auto" name="right-kuang"></iframe>
    </div>
  </div>
</div>
</body>
</html>