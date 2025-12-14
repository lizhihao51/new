<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

// 检查是否为管理员
check_admin();

// 获取当前用户信息
$current_user = validate_user_cookie();
if (!$current_user) {
    echo "错误：用户未登录";
    exit;
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $users = $_POST['users'] ?? [];
    $classes = $_POST['classes'] ?? [];
    $assignments = json_decode($_POST['assignments'], true) ?? [];
    
    // 验证必填字段
    if (empty($title)) {
        echo "错误：请输入任务标题";
        exit;
    }
    
    if (empty($users)) {
        echo "错误：请至少选择一个辅导员";
        exit;
    }
    
    if (empty($classes)) {
        echo "错误：请至少选择一个班级";
        exit;
    }
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 插入任务
        $stmt = $pdo->prepare("INSERT INTO tasks (task_title, task_des, task_json, task_by_user) VALUES (?, ?, ?, ?)");
        
        // 创建标准化的JSON结构来存储任务信息
        // 统一格式：
        // {
        //   "users": ["用户ID数组"],
        //   "classes": ["班级ID数组"],
        //   "assignments": {
        //     "用户ID": {
        //       "name": "用户名",
        //       "classes": ["该用户分配的班级ID数组"]
        //     }
        //   }
        // }
        $taskJson = json_encode([
            'users' => array_values($users), // 确保是索引数组
            'classes' => array_values($classes), // 确保是索引数组
            'assignments' => $assignments
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt->execute([$title, $description, $taskJson, $current_user['u_id']]);
        $taskId = $pdo->lastInsertId();
        
        // 记录日志
        write_operate_log($current_user['u_id'], '创建任务: ' . $title);
        
        // 提交事务
        $pdo->commit();
        
        // 重定向到成功页面或返回成功消息
        echo "任务创建成功！";
        // header("Location: A_Default.php?success=1");
        // exit;
    } catch (PDOException $e) {
        // 回滚事务
        $pdo->rollback();
        
        // 记录错误日志
        error_log("创建任务失败: " . $e->getMessage());
        echo "创建任务失败，请稍后重试";
    }
} else {
    // 非POST请求，重定向到创建任务页面
    header("Location: A_Add_Tasks.php");
    exit;
}
?>