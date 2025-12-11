<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';

// 获取任务列表
try {
    $stmt = $pdo->query("SELECT task_id, task_title, task_status FROM tasks ORDER BY task_id DESC");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $tasks = [];
    $message = '获取任务列表失败: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>管理任务</title>
		<link rel="stylesheet" href="../css/css.css" />
		<link rel="stylesheet" href="../css/Admin.css" />
	</head>
	<body>
		<div class="A_app">
			<P class="tit1">管理任务</P>
            
			<div class="card">
				<h3>任务列表</h3>
				<div id="List_BJ" class="tit3 list5">
					<ul>
						<li>任务ID</li>
						<li>任务标题</li>
						<li>任务状态</li>
						<li>修改</li>
						<li>删除</li>
					</ul>
                    
                    <?php if (!empty($tasks)): ?>
                        <?php foreach ($tasks as $task): ?>
                        <ul>
                            <li><?php echo htmlspecialchars($task['task_id']); ?></li>
                            <li><?php echo htmlspecialchars($task['task_title']); ?></li>
                            <li><?php echo htmlspecialchars($task['task_status']); ?></li>
                            <li><a href="#">修改</a></li>
                            <li><a href="#">删除</a></li>
                        </ul>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <ul>
                            <li colspan="5">暂无任务</li>
                        </ul>
                    <?php endif; ?>
				</div>
			</div>
		</div>
	</body>
</html>