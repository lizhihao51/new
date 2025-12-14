<?php
// 更正相对路径
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

// 强制开启PDO错误提示（关键）
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// 检查是否为管理员
check_admin();

// 初始化提交状态
$success = false;
$errorMsg = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 接收表单数据
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $selectedCounselors = $_POST['users'] ?? []; // 选中的辅导员ID数组
        $selectedClasses = $_POST['classes'] ?? []; // 选中的班级ID数组
        $assignments = $_POST['assignments'] ?? []; // 分配关系
        
        // 基础验证
        if (empty($title)) throw new Exception('任务标题不能为空');
        if (empty($selectedCounselors)) throw new Exception('请至少选择一个辅导员');
        if (empty($selectedClasses)) throw new Exception('请至少选择一个班级');
        
        // 构建辅导员-班级分配数组（强制转为纯数组，去除索引）
        $counselorAssignments = [];
        foreach ($selectedCounselors as $counselorId) {
            $counselorId = (string)intval($counselorId); // 统一转为字符串ID
            // 处理分配的班级（确保是数组，过滤空值，去除索引）
            $assignedClasses = isset($assignments[$counselorId]) ? (array)$assignments[$counselorId] : [];
            $assignedClasses = array_filter($assignedClasses, function($v) {
                return !empty(trim($v));
            });
            $assignedClasses = array_values($assignedClasses); // 关键：转为纯数组（去除索引）
            
            if (empty($assignedClasses)) {
                throw new Exception('请为每个选中的辅导员至少分配一个班级');
            }
            $counselorAssignments[$counselorId] = $assignedClasses;
        }
        
        // 处理选中的班级（转为纯数组）
        $selectedClasses = array_filter($selectedClasses, function($v) {
            return !empty(trim($v));
        });
        $selectedClasses = array_values($selectedClasses); // 去除索引
        
        
        // 插入任务数据（确认表名/字段名正确）
        $stmt = $pdo->prepare("INSERT INTO tasks 
                              (task_title, task_des, task_json, task_by_user) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $title,
            $description,
            json_encode($counselorAssignments),
            $_SESSION['user_id']
        ]);
        
        // 手动获取自增ID（兼容所有场景）
        $taskId = $pdo->lastInsertId() ?: $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        
        // 记录操作日志（适配logs表结构）
        $stmt = $pdo->prepare("INSERT INTO logs (u_id, action) 
                              VALUES (?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            "创建了销假任务 #{$taskId}: " . mb_substr($title, 0, 50)
        ]);
        
        $success = true;
        $successMsg = "任务创建成功！任务ID：{$taskId}";
        
    } catch (PDOException $e) {
        // 捕获数据库错误（方便排查）
        $errorMsg = '数据库错误：' . $e->getMessage() . ' | SQLSTATE：' . $e->getCode();
    } catch (Exception $e) {
        $errorMsg = '创建失败：' . $e->getMessage();
    }
}

// 获取用户列表（仅展示role=user且user_status=1的辅导员）
try {
    $usersStmt = $pdo->query("SELECT u_id, name FROM users WHERE role = 'user' AND user_status = 1 ORDER BY name");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $classesStmt = $pdo->query("SELECT class_code, class_name, class_year FROM classes WHERE class_status = 1 ORDER BY class_year, class_name");
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 按年级分组班级
    $classesByYear = [];
    foreach ($classes as $class) {
        $year = $class['class_year'];
        if (!isset($classesByYear[$year])) {
            $classesByYear[$year] = [];
        }
        $classesByYear[$year][] = $class;
    }
} catch(PDOException $e) {
    $users = [];
    $classes = [];
    $classesByYear = [];
    $errorMsg = '获取数据失败: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>创建任务</title>
    <link rel="stylesheet" href="../css/css.css" />
    <link rel="stylesheet" href="../css/Admin.css" />
    </style>
</head>
<body>
    <div class="A_app">
        <!-- 提交结果提示 -->
        <?php if ($success): ?>
        <div class="success-msg"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="error-msg"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form id="taskForm" method="POST">
            <P class="tit1">创建任务</P>
            <div class="card">
                <h3>任务基本信息</h3>
                <div class="form-group">
                    <label for="title">任务标题 <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" required
                        placeholder="请输入任务标题" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="description">任务描述</label>
                    <textarea id="description" name="description" class="form-control"
                        placeholder="请输入任务详细说明可以填写本次销假的具体要求、时间范围等信息" style="height: 130px;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="grid">
                <div class="card step-section">
                    <h3>选择参与的辅导员（仅普通用户）</h3>
                    <?php if (!empty($users)): ?>
                    <div class="form-group">
                        <?php foreach ($users as $user): ?>
                        <label>
                            <input type="checkbox" name="users[]" value="<?php echo $user['u_id']; ?>" class="user-checkbox" 
                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                <?php echo (isset($_POST['users']) && in_array($user['u_id'], $_POST['users'])) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </label><br>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>暂无可用的辅导员</p>
                    <?php endif; ?>
                </div>
                <div class="card step-section">
                    <h3>选择参与的班级</h3>
                    <?php if (!empty($classesByYear)): ?>
                        <?php foreach ($classesByYear as $year => $classList): ?>
                        <div class="year-group">
                            <div class="year-header">
                                <input type="checkbox" id="year_<?php echo $year; ?>" class="year-checkbox" data-year="<?php echo $year; ?>">
                                <label for="year_<?php echo $year; ?>"><?php echo $year; ?>级</label>
                            </div>
                            <div class="class-list">
                                <?php foreach ($classList as $class): ?>
                                <label class="class-checkbox">
                                    <input type="checkbox" name="classes[]" value="<?php echo htmlspecialchars($class['class_code']); ?>" class="class-checkbox-main" 
                                        data-year="<?php echo $year; ?>"
                                        <?php echo (isset($_POST['classes']) && in_array($class['class_code'], $_POST['classes'])) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </label><br>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
            
            <button type="submit" class="btn">创建任务</button>
        </form>
    </div>
    
    <script>
        // 存储选中的用户和班级
        let selectedUsers = {};
        let selectedClasses = {};
        
        // 获取DOM元素
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const classCheckboxesMain = document.querySelectorAll('.class-checkbox-main');
        const yearCheckboxes = document.querySelectorAll('.year-checkbox');
        const assignmentContainer = document.getElementById('assignmentContainer');
        const taskForm = document.getElementById('taskForm');

        // 全局兜底函数：确保用户的classes是数组
        function ensureClassesArray(userId) {
            if (!selectedUsers[userId]) return;
            if (!Array.isArray(selectedUsers[userId].classes)) {
                selectedUsers[userId].classes = [];
            }
            // 确保班级ID为字符串类型（避免类型不匹配）
            selectedUsers[userId].classes = selectedUsers[userId].classes.map(String);
        }

        // 页面加载时初始化选中状态
        window.addEventListener('DOMContentLoaded', function() {
            // 初始化selectedUsers
            userCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const userId = String(checkbox.value); // 强制转为字符串
                    const userName = checkbox.getAttribute('data-name');
                    // 强制初始化classes为空数组
                    selectedUsers[userId] = {
                        name: userName,
                        classes: []
                    };
                }
            });

            // 初始化selectedClasses
            classCheckboxesMain.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedClasses[String(checkbox.value)] = true; // 强制转为字符串
                }
            });

            // 初始化年级复选框状态
            yearCheckboxes.forEach(checkbox => {
                const year = checkbox.getAttribute('data-year');
                syncYearCheckboxStatus(year);
            });

            // 回显分配的班级（强制转为字符串数组）
            <?php if (isset($_POST['assignments'])): ?>
                const postAssignments = <?php 
                    $assignments = $_POST['assignments'] ?? [];
                    // 强制转为字符串数组
                    $cleanAssignments = [];
                    foreach ($assignments as $userId => $classes) {
                        $cleanAssignments[String($userId)] = is_array($classes) ? array_map(String, $classes) : [];
                    }
                    echo json_encode($cleanAssignments);
                ?>;
                Object.keys(selectedUsers).forEach(userId => {
                    // 强制转为数组，兜底处理
                    const assignedClasses = postAssignments[userId] ?? [];
                    selectedUsers[userId].classes = Array.isArray(assignedClasses) 
                        ? assignedClasses.map(String) // 转为字符串数组
                        : []; // 非数组则置为空数组
                    // 兜底检查
                    ensureClassesArray(userId);
                });
            <?php endif; ?>

            // 更新分配区域
            updateAssignmentSection();
        });
        
        // 1. 年级全选/取消班级功能
        yearCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const year = this.getAttribute('data-year');
                const yearClassCheckboxes = document.querySelectorAll(`.class-checkbox-main[data-year="${year}"]`);
                
                yearClassCheckboxes.forEach(classCb => {
                    classCb.checked = this.checked;
                    const classId = String(classCb.value); // 强制转为字符串
                    
                    if (this.checked) {
                        selectedClasses[classId] = true;
                    } else {
                        delete selectedClasses[classId];
                    }
                });
                
                // 更新所有用户的班级分配（确保classes是数组）
                Object.keys(selectedUsers).forEach(userId => {
                    ensureClassesArray(userId);
                    selectedUsers[userId].classes = selectedUsers[userId].classes.filter(classId => selectedClasses[classId]);
                });
                
                updateAssignmentSection();
            });
        });
        
        // 2. 用户复选框事件监听
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const userId = String(this.value); // 强制转为字符串
                const userName = this.getAttribute('data-name');
                
                if (this.checked) {
                    // 强制初始化classes为空数组
                    selectedUsers[userId] = {
                        name: userName,
                        classes: []
                    };
                } else {
                    delete selectedUsers[userId];
                }
                
                updateAssignmentSection();
            });
        });
        
        // 3. 主班级复选框事件监听
        classCheckboxesMain.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const classId = String(this.value); // 强制转为字符串
                
                if (this.checked) {
                    selectedClasses[classId] = true;
                    syncYearCheckboxStatus(this.getAttribute('data-year'));
                } else {
                    delete selectedClasses[classId];
                    syncYearCheckboxStatus(this.getAttribute('data-year'));
                }
                
                // 更新所有用户的班级分配（确保classes是数组）
                Object.keys(selectedUsers).forEach(userId => {
                    ensureClassesArray(userId);
                    selectedUsers[userId].classes = selectedUsers[userId].classes.filter(id => selectedClasses[id]);
                });
                
                updateAssignmentSection();
            });
        });
        
        // 辅助：同步年级复选框状态
        function syncYearCheckboxStatus(year) {
            const yearCheckbox = document.getElementById(`year_${year}`);
            const yearClassCheckboxes = document.querySelectorAll(`.class-checkbox-main[data-year="${year}"]`);
            const allChecked = Array.from(yearClassCheckboxes).every(cb => cb.checked);
            const noneChecked = Array.from(yearClassCheckboxes).every(cb => !cb.checked);
            
            if (allChecked) {
                yearCheckbox.checked = true;
                yearCheckbox.indeterminate = false;
            } else if (noneChecked) {
                yearCheckbox.checked = false;
                yearCheckbox.indeterminate = false;
            } else {
                yearCheckbox.indeterminate = true;
            }
        }
        
        // 4. 更新分配区域（核心渲染，修复includes报错）
        function updateAssignmentSection() {
            assignmentContainer.innerHTML = '';
            
            const hasSelectedUsers = Object.keys(selectedUsers).length > 0;
            const hasSelectedClasses = Object.keys(selectedClasses).length > 0;
            
            if (!hasSelectedUsers) {
                assignmentContainer.innerHTML = '<div class="text-muted">请先在左侧选择参与的辅导员</div>';
                return;
            }
            if (!hasSelectedClasses) {
                assignmentContainer.innerHTML = '<div class="text-muted">请先在右侧选择参与的班级</div>';
                return;
            }
            
            // 为每个选中的用户创建分配区域
            Object.keys(selectedUsers).forEach(userId => {
                // 兜底确保classes是数组
                ensureClassesArray(userId);
                const user = selectedUsers[userId];
                const userClasses = user.classes; // 已确保是字符串数组
                
                const section = document.createElement('div');
                section.className = 'assignment-section';
                section.innerHTML = `
                    <div class="assignment-header">${user.name} 的班级分配</div>
                    <div class="class-checkboxes" id="classCheckboxes_${userId}">
                        ${Object.keys(selectedClasses).map(classId => {
                            const className = getClassnameById(classId);
                            // 使用已确保为字符串数组的userClasses调用includes
                            const isChecked = userClasses.includes(classId) ? 'checked' : '';
                            return `
                                <label class="class-checkbox">
                                    <input type="checkbox" name="assignments[${userId}][]" value="${classId}" 
                                        class="class-assignment-checkbox" data-user="${userId}" data-class="${classId}" ${isChecked}>
                                    ${className}
                                </label>
                            `;
                        }).join('')}
                    </div>
                `;
                
                assignmentContainer.appendChild(section);
            });
            
            // 监听分配区域复选框变化
            document.querySelectorAll('.class-assignment-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const userId = String(this.getAttribute('data-user')); // 强制转为字符串
                    const classId = String(this.getAttribute('data-class')); // 强制转为字符串
                    // 兜底确保classes是数组
                    ensureClassesArray(userId);
                    
                    if (this.checked) {
                        if (!selectedUsers[userId].classes.includes(classId)) {
                            selectedUsers[userId].classes.push(classId);
                        }
                    } else {
                        selectedUsers[userId].classes = selectedUsers[userId].classes.filter(id => id !== classId);
                    }
                });
            });
        }
        
        // 辅助：根据班级ID获取班级名称
        function getClassnameById(classId) {
            const classElement = Array.from(classCheckboxesMain).find(cb => String(cb.value) === classId);
            if (classElement) {
                return classElement.nextSibling.textContent.trim();
            }
            return classId;
        }
        
        // 5. 表单提交前端验证
        taskForm.addEventListener('submit', function(e) {
            // 过滤特殊字符
            const titleInput = document.getElementById('title');
            titleInput.value = titleInput.value.trim().replace(/[\n\t\r]/g, '');
            const descInput = document.getElementById('description');
            descInput.value = descInput.value.trim().replace(/[\x00-\x1F\x7F]/g, '');
            
            // 验证标题
            const title = titleInput.value;
            if (!title) {
                e.preventDefault();
                alert('请输入任务标题');
                return;
            }
            
            // 验证至少选择一个辅导员
            if (Object.keys(selectedUsers).length === 0) {
                e.preventDefault();
                alert('请至少选择一个辅导员');
                return;
            }
            
            // 验证至少选择一个班级
            if (Object.keys(selectedClasses).length === 0) {
                e.preventDefault();
                alert('请至少选择一个班级');
                return;
            }
            
            // 验证每个辅导员至少分配一个班级（确保classes是数组）
            let validAssignments = true;
            Object.keys(selectedUsers).forEach(userId => {
                ensureClassesArray(userId);
                if (selectedUsers[userId].classes.length === 0) {
                    validAssignments = false;
                }
            });
            
            if (!validAssignments) {
                e.preventDefault();
                alert('请为每个选中的辅导员至少分配一个班级');
                return;
            }
        });
    </script>
</body>
</html>