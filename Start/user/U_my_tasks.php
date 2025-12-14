
<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

// 验证用户登录（辅导员身份）
check_login('user');

// 核心修复：获取当前用户ID（仅依赖SESSION，简洁且安全）
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php"); // 未登录跳转登录页
    exit;
}
$currentUserIdStr = (string)$_SESSION['user_id'];

// 修复1：JSON查询条件改为当前辅导员ID，且参数绑定避免SQL注入
$stmt = $pdo->prepare("
    SELECT t.* 
    FROM tasks t
    WHERE JSON_CONTAINS_PATH(t.task_json, 'one', :userPath)
    ORDER BY t.task_updated DESC, t.task_created DESC
");
// 构造JSON路径（如：$.\"123\"，匹配当前辅导员ID的键）
$userPath = '$."' . $currentUserIdStr . '"';
$stmt->bindParam(':userPath', $userPath);
$stmt->execute();
$allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 解析任务分配的班级信息（JSON中存储的是class_code）
$taskClassMap = [];
foreach ($allTasks as $task) {
    // 修复2：JSON解码错误处理，避免格式错误导致空值
    $assignments = json_decode($task['task_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $assignments = [];
    }
    // 关联任务ID和对应的class_code列表
    $taskClassMap[$task['task_id']] = $assignments[$currentUserIdStr] ?? [];
}

// 获取班级名称映射（修复3：匹配class_code，而非id）
$allClassCodes = [];
foreach ($taskClassMap as $classCodes) {
    $allClassCodes = array_merge($allClassCodes, $classCodes);
}
$classNames = [];
if (!empty($allClassCodes)) {
    $placeholders = implode(',', array_fill(0, count($allClassCodes), '?'));
    // 查询条件改为class_code，字段映射正确（class_code对应JSON值，name为班级名称）
    $stmt = $pdo->prepare("SELECT class_code, class_name FROM classes WHERE class_code IN ($placeholders)");
    $stmt->execute($allClassCodes);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $classNames[$row['class_code']] = $row['class_name'];
    }
}

// 修复4：定义task_status状态映射（1=激活/进行中，2=未开始，3=暂停，4=已结束）
$statusMap = [
    1 => ['text' => '进行中', 'class' => 'badge-success'],
    2 => ['text' => '未开始', 'class' => 'badge-warning'],
    3 => ['text' => '暂停', 'class' => 'badge-secondary'],
    4 => ['text' => '已结束', 'class' => 'badge-danger']
];
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
            <h3>我的所有销假任务</h3> 
            <?php if (empty($allTasks)): ?>
                <p class="no-tasks">暂无分配给您的销假任务</p>
            <?php else: ?>
                    <table class="List_BJ">
                        <thead>
                            <tr>
                                <th>任务ID</th>
                                <th>任务标题</th>
                                <th>创建时间</th>
                                <th>负责班级</th>
                                <th>任务状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTasks as $task): ?>
                                <tr>
                                    <!-- 修复5：显示正确的任务主键task_id -->
                                    <td><?= htmlspecialchars($task['task_id']) ?></td>
                                    <td><?= htmlspecialchars($task['task_title']) ?></td>
                                    <td><?= htmlspecialchars($task['task_created']) ?></td>
                                    <td>
                                        <?php 
                                        $classCodes = $taskClassMap[$task['task_id']];
                                        if (empty($classCodes)): 
                                        ?>
                                            无
                                        <?php else: ?>
                                            <ul style="margin: 0; padding-left: 20px; list-style: disc;">
                                                <?php foreach ($classCodes as $code): ?>
                                                    <li><?= $classNames[$code] ?? "未知班级（编号:{$code}）" ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- 修复6：根据task_status值显示对应状态文本和样式 -->
                                        <?php
                                        $status = $task['task_status'] ?? 0;
                                        $statusInfo = $statusMap[$status] ?? ['text' => '未知状态', 'class' => 'badge-light'];
                                        ?>
                                        <span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['text'] ?></span>
                                    </td>
                                    <td>
                                        <!-- 核心修复：跳转路径改为正确的U_edit_tasks.php，补充默认班级ID -->
                                        <?php if ($task['task_status'] == 1): ?>
                                            <?php 
                                            // 获取该任务下第一个班级作为默认跳转参数
                                            $defaultClassId = !empty($taskClassMap[$task['task_id']]) ? $taskClassMap[$task['task_id']][0] : '';
                                            ?>
                                            <a href="U_edit_tasks.php?task_id=<?= $task['task_id'] ?>&class_id=<?= $defaultClassId ?>" 
                                               class="btn btn-sm btn-primary" 
                                               target="_self">处理任务</a>
                                        <?php endif; ?>
                                        <!-- 导出记录按钮（所有状态都显示） -->
                                        <a href="export_records.php?task_id=<?= $task['task_id'] ?>" 
                                           class="btn btn-sm btn-info" 
                                           target="_self">导出记录</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 新增JS，排查并修复点击事件冲突 -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 为所有操作按钮绑定点击事件，确保跳转生效
            const actionButtons = document.querySelectorAll('.btn');
            actionButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // 阻止可能的事件冒泡/默认行为冲突
                    e.stopPropagation();
                    // 手动触发跳转（兜底方案）
                    const href = this.getAttribute('href');
                    if (href) {
                        window.location.href = href;
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>