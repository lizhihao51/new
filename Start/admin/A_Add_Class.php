<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';

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
            
            <?php if ($message): ?>
            <div class="card">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>
			
			<div class="card">
				<h3>创建班级</h3>
				<form method="POST">
					<div class="form-group">
						<label for="class_code">班级code <span style="color:red">*</span></label>
						<input type="text" id="class_code" name="class_code" class="form-control" required="" placeholder="如:jsj24001">
					</div>
					
					<div class="form-group">
						<label for="class_name">班级名称 <span style="color:red">*</span></label>
						<input type="text" id="class_name" name="class_name" class="form-control" required="" placeholder="如：计算机24001班">
					</div>
					
					<div class="form-group">
						<label for="academic_year">年级</label>
						<input type="text" id="academic_year" name="academic_year" class="form-control" placeholder="如：2023">
					</div>
					
					<button type="submit" class="btn btn-success">创建班级</button>
				</form>
			</div>
			
			<div class="card">
				<h3>班级列表</h3>
				<div id="List_BJ" class="tit3 list5">
					<ul>
						<li class="li_id">班级ID</li>
						<li>班级名称</li>
						<li>年级</li>
						<li>修改</li>
						<li>删除</li>
					</ul>
                    
                    <?php foreach ($classes as $class): ?>
                    <ul>
                        <li class="li_id"><?php echo htmlspecialchars($class['class_id']); ?></li>
                        <li><?php echo htmlspecialchars($class['class_name']); ?></li>
                        <li><?php echo htmlspecialchars($class['class_year']); ?></li>
                        <li><a href="#">修改</a></li>
                        <li><a href="#">删除</a></li>
                    </ul>
                    <?php endforeach; ?>
				</div>
			</div>
		</div>
	</body>
</html>