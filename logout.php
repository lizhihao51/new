<?php
require_once 'includes/header.php';

// 销毁会话
session_destroy();

// 重定向到登录页面
redirect('login.php');
exit();
?>