<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>我的任务</title>
    <link rel="stylesheet" href="../css/css.css" />
</head>
<body>
    <div class="U_app">
        <p class="tit1">我的任务</p>
        <div class="card">
            <h3>任务列表</h3>
            <?php
            // 从数据库获取任务列表
            try {
                $stmt = $pdo->prepare("SELECT t.task_id, t.task_title, t.task_des, t.task_status, t.task_created 
                                       FROM tasks t 
                                       ORDER BY t.task_created DESC");
                $stmt->execute();
                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($tasks) > 0) {
                    foreach ($tasks as $task) {
                        echo "<div class='task-card'>";
                        echo "<h4>" . htmlspecialchars($task['task_title']) . "</h4>";
                        echo "<p>" . htmlspecialchars($task['task_des']) . "</p>";
                        echo "<small>创建时间: " . $task['task_created'] . "</small>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>暂无任务</p>";
                }
            } catch(PDOException $e) {
                echo "获取任务列表出错: " . $e->getMessage();
            }
            ?>
        </div>
    </div>
</body>
</html>