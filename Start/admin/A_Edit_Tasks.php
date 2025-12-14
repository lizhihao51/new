<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

// 获取任务列表
try {
    $stmt = $pdo->query("
	SELECT * ,u.name as creator_name  
	FROM tasks t
	join users u on t.task_by_user = u.u_id
	ORDER BY task_id DESC
	");
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
					<table class="List_BJ">
                        <thead>
                            <tr>
                                <th>任务ID</th>
                                <th>任务标题</th>
                                <th>创建人</th>
                                <th>创建时间</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
						
						<tbody>
                    <?php if (!empty($tasks)): ?>
                        <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($task['task_id']); ?></td>
                            <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                            <td><?php echo htmlspecialchars($task['creator_name']); ?></td>
							<td><?php echo htmlspecialchars($task['task_created']); ?></td>
							<td><?php
								// 1. 定义新状态映射（编码→中文状态+样式类）
								$statusMap = [
									1 => ['text' => '进行中', 'class' => 'badge-success'],
									2 => ['text' => '未开始', 'class' => 'badge-primary'],
									3 => ['text' => '已暂停', 'class' => 'badge-warning'],
									4 => ['text' => '已结束', 'class' => 'badge-secondary']
								];
								// 2. 获取当前任务的状态编码（假设新字段名为status，需根据实际字段名调整）
								$taskStatus = $task['task_status'] ?? 0;
								// 3. 匹配状态文本和样式（默认显示“未知状态”）
								$statusText = $statusMap[$taskStatus]['text'] ?? '未知状态';
								$statusClass = $statusMap[$taskStatus]['class'] ?? 'badge-dark';
								?>
								<!-- 4. 渲染状态标签 -->
								<span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
							</td>
                            <td><a href="delete_task.php?id=<?php echo $task['task_id']; ?>" onclick="return confirm('确定要删除这个任务吗？')">删除</a></td>
                        </tr>
                        <?php endforeach; ?>
						<tbody>
                    <?php else: ?>
                        <p>暂无任务</p>
                    <?php endif; ?>
				</table>
			</div>
		</div>
	</body>
</html>