<?php
$configPath = '../includes/config.php';
require_once '../includes/header.php';

// 验证 Cookie 并获取用户ID
$user = validate_user_cookie();
if ($user) {
    // 写入退出日志
    write_operate_log($user['u_id'], '用户退出登录');
    // 清除本地 Cookie
    clear_user_cookie();
}

// 退出后跳回登录页
redirect('login.php');
?>