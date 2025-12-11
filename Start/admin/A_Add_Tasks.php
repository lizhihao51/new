<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';

// 获取用户和班级列表用于选择
try {
    $usersStmt = $pdo->query("SELECT u_id, name FROM users WHERE user_status = 1 ORDER BY name");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $classesStmt = $pdo->query("SELECT class_code, class_name FROM classes WHERE class_status = 1 ORDER BY class_name");
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $users = [];
    $classes = [];
    $message = '获取数据失败: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>创建任务</title>
		<link rel="stylesheet" href="../css/css.css" />
		<link rel="stylesheet" href="../css/Admin.css" />
		<style>

		</style>
	</head>
	<body>
		<div class="A_app ">
			<P class="tit1">创建任务</P>
			<div class="card">
				<h3>任务基本信息</h3>
				<div class="form-group">
					<label for="title">任务标题 <span style="color: red;">*</span></label>
					<input type="text" id="title" name="title" class="form-control" required=""
						placeholder="请输入任务标题">
				</div>
				<div class="form-group">
					<label for="description">任务描述</label>
					<textarea id="description" name="description" class="form-control"
						placeholder="请输入任务详细说明可以填写本次销假的具体要求、时间范围等信息" style="height: 130px;"></textarea>
				</div>
			</div>
			<div class="grid">
				<div class="card step-section">
					<h3>选择参与的成员</h3>
                    <?php if (!empty($users)): ?>
                    <div class="form-group">
                        <?php foreach ($users as $user): ?>
                        <label>
                            <input type="checkbox" name="users[]" value="<?php echo $user['u_id']; ?>">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </label><br>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>暂无可用用户</p>
                    <?php endif; ?>
				</div>
				<div class="card step-section">
					<h3>选择参与的班级</h3>
                    <?php if (!empty($classes)): ?>
                    <div class="form-group">
                        <?php foreach ($classes as $class): ?>
                        <label>
                            <input type="checkbox" name="classes[]" value="<?php echo htmlspecialchars($class['class_code']); ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </label><br>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>暂无可用班级</p>
                    <?php endif; ?>
				</div>
			</div>

			<div class="card">
				<h3>分配辅导员负责的班级</h3>

				<p style="margin-bottom: 15px; color: #555;">请为每个选中的辅导员分配负责的班级：</p>

				<div class="assignment-container" id="assignmentContainer">
					<!-- 动态生成分配区域 -->
					<div class="text-muted">请先在左侧选择参与的辅导员</div>
				</div>
			</div>
		</div>
	</body>
</html>