<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

$message = '';

// 处理创建班级请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_code = $_POST['class_code'] ?? '';
    $class_name = $_POST['class_name'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    
    if (empty($class_code) || empty($class_name) || empty($academic_year)) {
        $message = '请填写所有必填字段';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (class_code, class_name, class_year) VALUES (?, ?, ?)");
            $stmt->execute([$class_code, $class_name, $academic_year]);
            $message = '班级创建成功';
        } catch(PDOException $e) {
            $message = '班级创建失败: ' . $e->getMessage();
        }
    }
}

// 获取班级列表
try {
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY class_id DESC");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $classes = [];
    $message = '获取班级列表失败: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>班级管理</title>
		<link rel="stylesheet" href="../css/css.css" />
		<link rel="stylesheet" href="../css/Admin.css" />
	</head>
	<body>
		<div class="A_app">
			<p class="tit1">班级管理</p>
            
            <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, '成功') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
			<div class="card">
				<h3>创建新班级</h3>
				<form method="POST">
					<div class="form-group">
						<label for="class_code">班级代码 <span style="color: red;">*</span></label>
						<input type="text" id="class_code" name="class_code" class="form-control" required>
					</div>
					<div class="form-group">
						<label for="class_name">班级名称 <span style="color: red;">*</span></label>
						<input type="text" id="class_name" name="class_name" class="form-control" required>
					</div>
					<div class="form-group">
						<label for="academic_year">学年 <span style="color: red;">*</span></label>
						<input type="text" id="academic_year" name="academic_year" class="form-control" required>
					</div>
					<button type="submit" class="btn btn-primary">创建班级</button>
				</form>
			</div>
            
			<div class="card">
				<h3>班级列表</h3>
				<table class="List_BJ">
					<thead>
						<tr>
						<th>ID</th>
						<th>班级代码</th>
						<th>班级名称</th>
						<th>学年</th>
						<th>状态</th>
						</tr>
					</thead>
                    
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                        <tbody>
                            <td><?php echo htmlspecialchars($class['class_id']); ?></td>
                            <td><?php echo htmlspecialchars($class['class_code']); ?></td>
                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['class_year']); ?></td>
                            <td><?php echo $class['class_status'] ? '启用' : '禁用'; ?></td>
                        </tbody>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>暂无班级</p>
                    <?php endif; ?>
				</table>
			</div>
		</div>
	</body>
</html>