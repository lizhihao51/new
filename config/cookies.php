<?php
// 引入数据库连接
require_once(__DIR__ . '/db.php');
header("Content-Type:text/html;charset=utf-8");

// 启动Session（全局可用，确保同步）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    session_start([
        'cookie_lifetime' => 3600,
        'gc_maxlifetime' => 3600
    ]);
}

// 日志写入函数（写入logs表，时间字段自动添加）
function write_operate_log($pdo, $u_id, $action) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (u_id, action, create_time) VALUES (?, ?, NOW())");
        $stmt->execute([$u_id, $action]);
    } catch (PDOException $e) {
        // 日志写入失败仅记录服务器日志，不影响主流程
        error_log("【日志写入失败】：" . $e->getMessage() . " | 时间：" . date('Y-m-d H:i:s'));
    }
}

// ========== 核心修复：验证Cookie并同步Session ==========
function validate_user_cookie($pdo) {
    // 无admin Cookie直接返回false
    if (!isset($_COOKIE['admin'])) {
        return false;
    }
    
    $username = $_COOKIE['admin'];
    try {
        // 查询用户信息（确保取到最新的u_id）
        $stmt = $pdo->prepare("SELECT u_id, username, name, role FROM users WHERE username = ? AND user_status = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // 同步：将数据库最新的u_id更新到Session（覆盖旧值）
            $_SESSION['user_id'] = (int)$user['u_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            return $user; // 返回完整用户信息
        }
    } catch (PDOException $e) {
        error_log("用户验证失败：" . $e->getMessage());
    }
    
    return false;
}

// ========== 退出登录逻辑（修复：清空Cookie+Session） ==========
if (isset($_COOKIE['admin']) && isset($_POST['exit'])) {
    // 验证Cookie并获取最新u_id（用于写退出日志）
    $user = validate_user_cookie($pdo);
    if ($user) {
        write_operate_log($pdo, $user['u_id'], '用户退出登录');
    }

    // 清除所有登录Cookie（根路径生效）
    setcookie('admin', '', time() - 3600, '/');
    // 清空Session
    $_SESSION = [];
    session_destroy();
    
    // 退出后跳回登录页
    header("Location: ../login.php");
    exit;
}

// ========== 登录逻辑（修复：登录成功同步Session） ==========
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // 验证输入非空
    if (empty($username) || empty($password)) {
        $msg = urlencode('账号或密码不能留空');
        header("Location: ../login.php?msg={$msg}");
        exit;
    }

    // 查询用户信息
    $stmt = $pdo->prepare("SELECT u_id, username, password, name, role FROM users WHERE username = ? AND user_status = 1 LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    // 验证用户和密码（注意：生产环境建议用密码哈希，不要明文比对）
    if ($row && $row['password'] === $password) {
        // 清空旧Session，避免残留
        $_SESSION = [];
        
        // 登录成功：设置Cookie（有效期1小时，根目录生效）
        setcookie("admin", $row["username"], time() + 3600, '/');
        
        // 同步：将最新u_id写入Session（核心修复点）
        $_SESSION['user_id'] = (int)$row['u_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['name'] = $row['name'];
        $_SESSION['role'] = $row['role'];

        // 写入登录日志（用最新的u_id）
        write_operate_log($pdo, $row['u_id'], '用户登录');

        // 跳转到首页
        header("Location: ../index.php");
        exit;
    } else {
        // 账号密码错误，返回登录页并提示
        $msg = urlencode('没有注册，或账号或密码错误');
        header("Location: ../login.php?msg={$msg}");
        exit;
    }
}

// ========== 通用登录检查函数（所有页面可调用） ==========
function check_login($required_role = null) {
    global $pdo;
    $user = validate_user_cookie($pdo);
    
    // 未登录/验证失败，跳登录页
    if (!$user) {
        header("Location: ../login.php");
        exit;
    }
    
    // 角色权限验证
    if ($required_role && $user['role'] !== $required_role) {
        $_SESSION['error_message'] = "权限不足，无法访问该页面！";
        header("Location: ../index.php");
        exit;
    }
    
    return $user; // 返回用户信息，方便页面调用
}

// ========== 异常情况：无操作/未登录访问 ==========
if (!isset($_POST['username']) && !isset($_POST['password']) && !isset($_POST['exit'])) {
    $user = validate_user_cookie($pdo);
    if (!$user) {
        header("Location: ../login.php");
        exit;
    } else {
        header("Location: ../index.php");
        exit;
    }
}
?>