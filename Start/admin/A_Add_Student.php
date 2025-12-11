<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';

$message = '';

// 处理创建学生请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_name = $_POST['s_name'] ?? '';
    $s_num = $_POST['s_num'] ?? '';
    $s_class_code = $_POST['s_class_code'] ?? '';
    $tags = $_POST['tags'] ?? '';
    
    if (empty($s_name) || empty($s_class_code)) {
        $message = '请填写学生姓名和选择所属班级';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO student (s_name, s_class_code, s_num, tags) VALUES (?, ?, ?, ?)");
            $stmt->execute([$s_name, $s_class_code, $s_num, $tags]);
            $message = '学生创建成功';
        } catch(PDOException $e) {
            $message = '学生创建失败: ' . $e->getMessage();
        }
    }
}

// 获取班级列表用于下拉选择
try {
    $stmt = $pdo->query("SELECT class_code, class_name FROM classes WHERE class_status = 1 ORDER BY class_name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $classes = [];
    $message = '获取班级列表失败: ' . $e->getMessage();
}

// 获取学生列表
try {
    $stmt = $pdo->query("SELECT s.s_id, s.s_name, s.s_num, s.tags, c.class_name 
                         FROM student s 
                         LEFT JOIN classes c ON s.s_class_code = c.class_code 
                         ORDER BY s.s_id DESC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $students = [];
    $message = '获取学生列表失败: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>学生管理</title>
		<link rel="stylesheet" href="../css/css.css" />
		<link rel="stylesheet" href="../css/Admin.css" />
		<style>
		</style>
	</head>
	<body>
		<div class="A_app">
			<p class="tit1">学生管理</p>
            
            <?php if ($message): ?>
            <div class="card">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>
            
			<div class="card">
				<div class="card-header">
					<h3>创建新学生</h3>
				</div>

				<form method="POST">
					<div class="form-group">
						<label for="s_name">学生姓名 <span style="color:red">*</span></label>
						<input type="text" id="s_name" name="s_name" class="form-control" required="">
					</div>
					<div class="form-group">
						<label for="s_num">学生学号 </label>
						<input type="text" id="s_num" name="s_num" class="form-control" >
					</div>
					<div class="form-group">
						<label for="s_class_code">所属班级 <span style="color:red">*</span></label>
						<select id="s_class_code" name="s_class_code" class="form-control" required="">
							<option value="">-- 请选择班级 --</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class_code']); ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                            <?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label for="tags">标签</label>
						<input type="text" id="tags" name="tags" class="form-control" placeholder="多个标签用逗号分隔，如：团委,学生会">
					</div>
					<button type="submit" class="btn btn-success">创建学生</button>
				</form>
			</div>

			<div class="card">
				<h3>学生列表</h3>
				
				<div id="List_User" class="tit3 list7">
					<ul>
						<li>学生ID</li>
						<li>姓名</li>
						<li>班级</li>
						<li>学号</li>
						<li>标签</li>
						<li>修改</li>
						<li>删除</li>
					</ul>
                    
                    <?php foreach ($students as $student): ?>
                    <ul>
                        <li><?php echo htmlspecialchars($student['s_id']); ?></li>
                        <li><?php echo htmlspecialchars($student['s_name']); ?></li>
                        <li><?php echo htmlspecialchars($student['class_name']); ?></li>
                        <li><?php echo htmlspecialchars($student['s_num']); ?></li>
                        <li><?php echo htmlspecialchars($student['tags']); ?></li>
                        <li><a href="#">修改</a></li>
                        <li><a href="#">删除</a></li>
                    </ul>
                    <?php endforeach; ?>
				</div>
			</div>
		</div>
	</body>
</html>