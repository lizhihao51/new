<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $message = '请填写所有字段';
    } elseif ($password !== $confirm_password) {
        $message = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $message = '密码长度不能少于6位';
    } else {
        // 使用明文密码存储（仅用于测试）
        /*
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE u_id = ?");
            $stmt->execute([$password, $_SESSION['user_id']]);
            $message = '密码修改成功';
        } catch(PDOException $e) {
            $message = '密码修改失败: ' . $e->getMessage();
        }
        */
        $message = '密码修改功能已提交（演示模式）';
    }
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>修改密码</title>
        <link rel="stylesheet" href="../css/css.css" />
	</head>
	<body>
		<div class="U_app">
            <div class="card">
                <h3>修改密码</h3>
                <?php if ($message): ?>
                    <div class="alert"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="password">新密码</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">确认密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-success">修改密码</button>
                </form>
            </div>
		</div>
	</body>
</html>