<?php
// 引入数据库连接（复用你原有项目的db.php）
require_once('../db.php'); // 注意路径：cookies.php在Connections，db.php在上级
header("Content-Type:text/html;charset=utf-8");

// 日志写入函数（写入logs表，时间字段自动添加）
function write_operate_log($pdo, $u_id, $action) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (u_id, action) VALUES (?, ?)");
        $stmt->execute([$u_id, $action]);
    } catch (PDOException $e) {
        // 日志写入失败仅记录服务器日志，不影响主流程
        error_log("【日志写入失败】：" . $e->getMessage() . " | 时间：" . date('Y-m-d H:i:s'));
    }
}

// ========== 退出登录逻辑 ==========
if (isset($_COOKIE['admin']) && isset($_POST['exit'])) {
    // 根据admin Cookie中的unam查询用户ID（用于写退出日志）
    $unam = $login->escapeString($_COOKIE['admin']);
    $stmt = $pdo->prepare("SELECT u_id FROM users WHERE unam = ? LIMIT 1");
    $stmt->execute([$unam]);
    $row = $stmt->fetch();

    // 写入退出日志
    if ($row) {
        write_operate_log($pdo, $row['u_id'], '用户退出登录');
    }

    // 清除所有登录Cookie
    setcookie('username', '', time() - 3600, '/');
    // 退出后跳回登录页
    echo "<script>alert('您已退出');location.href='../login.php';</script>";
    exit;
}

// ========== 登录逻辑 ==========
if (!isset($_COOKIE['admin']) && isset($_POST['username']) && isset($_POST['password'])) {
    $uname = $_POST["username"];
    $password = $_POST["password"];

    // 验证输入非空
    if (empty($uname) || empty($password)) {
        $msg = urlencode('账号或密码不能留空');
        header("Location: ../login.php?msg={$msg}");
        exit;
    }

    // 转义特殊字符防SQL注入（复用你原有逻辑）
    $uname = $login->escapeString($uname);
    $password = $login->escapeString($password);

    // 查询用户信息
    $stmt = $pdo->prepare("SELECT u_id, username, password, unam, level, fun1, fun2, fun3, banji FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$uname]);
    $row = $stmt->fetch();

    // 验证用户和密码
    if ($row && $row['password'] === $password) {
        // 登录成功：设置你原有逻辑的Cookie
        setcookie("admin", $row["username"], time() + 3600, '/'); // 有效期1小时，根目录生效

        // 写入登录日志
        write_operate_log($pdo, $row['u_id'], '用户登录');

        // 跳转到首页
        echo "<script>location.href='../index.php';</script>";
        exit;
    } else {
        // 账号密码错误，返回登录页并提示
        $msg = urlencode('没有注册，或账号或密码错误');
        header("Location: ../login.php?msg={$msg}");
        exit;
    }
}

// ========== 异常情况：无操作/未登录访问 ==========
if (!isset($_COOKIE['admin'])) {
    // 未登录且无登录提交，跳回登录页
    header("Location: ../login.php");
    exit;
} else {
    // 已登录但无退出操作，跳回首页
    echo "<script>location.href='../index.php';</script>";
    exit;
}
?>