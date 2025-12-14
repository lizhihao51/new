<?php
// 更灵活的路径处理方式
$configPath = __DIR__ . '/db.php'; // 修复：添加斜杠

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

// 修改：基于现有的 admin Cookie 验证用户
function validate_user_cookie() {
    if (!isset($_COOKIE['admin'])) {
        return false; // 无 admin Cookie，验证失败
    }
    
    $username = $_COOKIE['admin'];
    global $pdo;
    
    try {
        // 查询用户信息
        $stmt = $pdo->prepare("SELECT u_id, username, name, role FROM users WHERE username = ? AND user_status = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            return [
                'u_id' => $user['u_id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role']
            ];
        }
    } catch (PDOException $e) {
        error_log("用户验证失败：" . $e->getMessage());
    }
    
    return false;
}

// 重构：检查用户是否已登录（基于 admin Cookie 验证）
function check_login() {
    $user = validate_user_cookie();
    if (!$user) {
        // 无有效 Cookie，跳转到上级目录的 login.php
        redirect('../login.php'); 
        exit();
    }
}

// 重构：检查用户是否为管理员（基于 admin Cookie 验证）
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
    setcookie('admin', '', time() - 3600, '/');
}

// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<?php 
if(isset($_COOKIE['admin'])){
	//防止用户查看页面各个子页面
	$headers = apache_request_headers();
	if(strstr($_SERVER["PHP_SELF"],"t_") or strstr($_SERVER["PHP_SELF"],"s_")){
		if(!strstr($headers["Referer"],$_SERVER['HTTP_HOST'])){
			echo "<script>
		window.location.href=\"../../index.php\";</script>";
		}
	}
	elseif (strstr($_SERVER["PHP_SELF"],"teacher.php") or strstr($_SERVER["PHP_SELF"],"student.php")){
		if(!strstr($headers["Referer"],$_SERVER['HTTP_HOST'])){
			echo "<script>
		window.location.href=\"../index.php\";</script>";
		}
	}
}
else{
	//防止用户未登录则使用该系统
	if(strstr($_SERVER["PHP_SELF"],"t_") or strstr($_SERVER["PHP_SELF"],"s_")){
		echo "<script>alert(\"请先登录1!\");
		window.location.href=\"../../login.php\";</script>";	
	}
	elseif (strstr($_SERVER["PHP_SELF"],"teacher.php") or strstr($_SERVER["PHP_SELF"],"student.php")){
		echo "<script>alert(\"请先登录2!\");
		window.location.href=\"../login.php\";</script>";	
	}
	else {
		echo "<script>alert(\"请先登录3!\");
		window.location.href=\"login.php\";</script>";	
	}
}
?>