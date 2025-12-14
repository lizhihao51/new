<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

$message = '';

// 处理创建用户请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($username) || empty($password)) {
        $message = '请填写所有必填字段';
    } elseif (strlen($password) < 6) {
        $message = '密码长度不能少于6位';
    } else {
        try {
            // 使用明文密码存储（仅用于测试）
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$username, $password, $name]);
            $message = '用户创建成功';
        } catch(PDOException $e) {
            $message = '用户创建失败: ' . $e->getMessage();
        }
    }
}

// 获取用户列表
try {
    $stmt = $pdo->query("SELECT u_id, username, name, role, user_status FROM users ORDER BY u_id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $users = [];
    $message = '获取用户列表失败: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>用户管理</title>
		<link rel="stylesheet" href="../css/css.css" />
		<link rel="stylesheet" href="../css/Admin.css" />
		<style>

		</style>
		
	</head>
	<body>
		<div class="A_app">
			<P class="tit1">用户管理</P>
            
            <?php if ($message): ?>
            <div class="card">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>
			
			<div class="card">
				<h3>创建用户</h3>
				<form method="POST">
					<div class="form-group">
						<label for="name">姓名 <span style="color:red">*</span></label>
						<input type="text" id="name" name="name" class="form-control" required="">
					</div>
					<div class="form-group">
						<label for="username">登录账号 <span style="color:red">*</span></label>
						<input type="text" id="username" name="username" class="form-control" required="">
					</div>
					<div class="form-group">
						<label for="password">登录密码 <span style="color:red">*</span></label>
						<input type="password" id="password" name="password" class="form-control" minlength="6"
							required="" placeholder="密码长度不能少于6位">
					</div>
					<button type="submit" class="btn btn-success">创建用户</button>
				</form>
			</div>
			
			<div class="card">
				<h3>用户列表</h3>
				<table class="List_BJ">
					<thead>
						<tr>
						<th>用户ID</th>
						<th>用户名</th>
						<th>姓名</th>
						<th>角色</th>
						<th>状态</th>
						<th>修改</th>
						<th>删除</th>
						</tr>
					</thead>
                    
                    <?php foreach ($users as $user): ?>
                    <tbody>
                        <td><?php echo htmlspecialchars($user['u_id']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['user_status']); ?></td>
                        <td><a href="#">修改</a></td>
                        <td><a href="#">删除</a></td>
                    </tbody>
                    <?php endforeach; ?>
				</table>
			</div>
			
		</div>
	</body>
</html>