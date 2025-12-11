<?php
// 更灵活的路径处理方式
$configPath = __DIR__ . '/../config/db.php';

// 如果上面的路径不存在，则尝试另一种路径结构
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../../config/db.php';
}

// 再次检查是否存在
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../../../config/db.php';
}

// 如果仍然找不到，输出错误信息
if (!file_exists($configPath)) {
    die("无法找到数据库配置文件，请检查项目结构");
}

// 数据库连接（configPath 需确保已定义，指向数据库配置文件）
require_once $configPath;

// 公共函数：重定向（适配 login.php 上级路径）
function redirect($url) {
    header("Location: $url");
    exit();
}

// 公共函数：输入过滤
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 新增：写入操作日志到 logs 表
function write_operate_log($u_id, $action) {
    global $pdo; // 复用数据库连接的 $pdo 变量
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (u_id, action) VALUES (?, ?)");
        $stmt->execute([$u_id, $action]);
    } catch (PDOException $e) {
        // 日志写入失败不影响主流程，可记录到服务器日志
        error_log("日志写入失败：" . $e->getMessage());
    }
}

// 新增：生成加密的用户 Cookie（替代 Session）
function set_user_cookie($user) {
    // 加密内容：u_id + role + 时间戳（防止篡改）
    $expire = time() + 3600; // Cookie 有效期1小时
    $token = md5($user['u_id'] . $user['role'] . $expire . 'your_secret_key'); // 自定义秘钥，务必修改
    // 拼接 Cookie 内容：u_id|role|过期时间|校验码
    $cookie_value = $user['u_id'] . '|' . $user['role'] . '|' . $expire . '|' . $token;
    // 设置 Cookie（HttpOnly 防止XSS，路径根目录，有效期1小时）
    setcookie(
        'user_auth', 
        $cookie_value, 
        $expire, 
        '/', 
        '', 
        isset($_SERVER['HTTPS']), 
        true
    );
}

// 新增：验证本地 Cookie 有效性（核心）
function validate_user_cookie() {
    if (!isset($_COOKIE['user_auth'])) {
        return false; // 无 Cookie，验证失败
    }
    // 拆分 Cookie 内容
    $cookie_parts = explode('|', $_COOKIE['user_auth']);
    if (count($cookie_parts) !== 4) {
        return false; // 格式错误
    }
    list($u_id, $role, $expire, $token) = $cookie_parts;
    
    // 验证有效期
    if (time() > $expire) {
        return false; // Cookie 过期
    }
    // 重新计算校验码，验证是否被篡改
    $check_token = md5($u_id . $role . $expire . 'your_secret_key'); // 与设置时的秘钥一致
    if ($check_token !== $token) {
        return false; // 内容被篡改
    }
    // 验证通过，返回用户基础信息
    return [
        'u_id' => $u_id,
        'role' => $role
    ];
}

// 重构：检查用户是否已登录（基于 Cookie 验证）
function check_login() {
    $user = validate_user_cookie();
    if (!$user) {
        // 无有效 Cookie，跳转到上级目录的 login.php
        redirect('../login.php'); 
        exit();
    }
}

// 重构：检查用户是否为管理员（基于 Cookie 验证）
function check_admin() {
    $user = validate_user_cookie();
    if (!$user || $user['role'] !== 'admin') {
        // 非管理员/无 Cookie，跳转到上级目录的 login.php
        redirect('../login.php'); 
        exit();
    }
}

// 新增：清除用户 Cookie（退出登录用）
function clear_user_cookie() {
    setcookie('user_auth', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}
?>