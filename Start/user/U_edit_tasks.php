<?php
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';
check_login('user');
// 获取参数
$taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$currentClassCode = isset($_GET['class_id']) ? trim($_GET['class_id']) : '';
$userId = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 1. 验证任务有效性
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ? AND task_status != 4");
$stmt->execute([$taskId]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    $_SESSION['error_message'] = "无效或已结束的任务";
    header("Location: U_my_tasks.php");
    exit;
}

// 2. 解析辅导员班级分配
$counselorAssignments = [];
if (!empty($task['task_json'])) {
    $counselorAssignments = json_decode($task['task_json'], true) ?: [];
}
if (empty($counselorAssignments)) {
    $_SESSION['error_message'] = "任务配置异常，无辅导员分配信息";
    header("Location: U_my_tasks.php");
    exit;
}

// 3. 匹配当前用户的班级列表
$currentUserIdStr = (string)$userId;
$currentUserIdInt = intval($userId);
$classCodes = [];
if (isset($counselorAssignments[$currentUserIdStr])) {
    $classCodes = $counselorAssignments[$currentUserIdStr];
} else if (isset($counselorAssignments[$currentUserIdInt])) {
    $classCodes = $counselorAssignments[$currentUserIdInt];
}
if (empty($classCodes)) {
    $_SESSION['error_message'] = "您未被分配该任务的任何班级";
    header("Location: U_my_tasks.php");
    exit;
}

// 4. 验证并重置班级Code
if (!empty($currentClassCode) && !in_array($currentClassCode, $classCodes)) {
    $currentClassCode = $classCodes[0];
} elseif (empty($currentClassCode)) {
    $currentClassCode = $classCodes[0];
}

// 5. 获取班级列表
$classes = [];
if (!empty($classCodes)) {
    $placeholders = implode(',', array_fill(0, count($classCodes), '?'));
    $stmt = $pdo->prepare("SELECT class_code, class_name FROM classes WHERE class_code IN ($placeholders)");
    $stmt->execute($classCodes);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $classes[$row['class_code']] = $row['class_name'];
    }
}

// 6. 获取学生数据
$students = [];
if (!empty($currentClassCode)) {
    $sql = "SELECT 
                s.s_id AS id, 
                s.s_name AS name, 
                s.tags AS tags 
            FROM student s 
            WHERE s.s_class_code = ? 
            AND s.student_status = 1";
    
    $params = [$currentClassCode];
    if (!empty($search)) {
        $sql .= " AND s.s_name LIKE ?";
        $params[] = "%{$search}%";
    }
    $sql .= " ORDER BY s.s_id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $studentIds = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $studentIds[] = $row['id'];
            $students[$row['id']] = [
                'id' => $row['id'],
                'name' => $row['name'] ?: '未知姓名',
                'tags' => !empty($row['tags']) ? explode(',', $row['tags']) : [],
                'status_id' => 1,
                'remarks' => ''
            ];
        }

        // 7. 批量查询销假记录
        if (!empty($studentIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
            $stmtRecord = $pdo->prepare("SELECT 
                                            student_id, 
                                            status_id, 
                                            remarks 
                                        FROM records 
                                        WHERE task_id = ? 
                                        AND student_id IN ($idPlaceholders)");
            array_unshift($studentIds, $taskId);
            $stmtRecord->execute($studentIds);
            
            $recordCount = 0;
            while ($record = $stmtRecord->fetch(PDO::FETCH_ASSOC)) {
                $recordCount++;
                if (isset($students[$record['student_id']])) {
                    $students[$record['student_id']]['status_id'] = $record['status_id'];
                    $students[$record['student_id']]['remarks'] = $record['remarks'];
                }
            }
        }

        $students = array_values($students);
    } catch (PDOException $e) {
        $students = [];
    }
}

// 8. 获取状态颜色配置
$statusOptions = [];
$stmt = $pdo->query("SELECT 
                        status_id AS id, 
                        c_name AS name, 
                        c_color AS color 
                    FROM color_config 
                    ORDER BY c_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $statusOptions[$row['id']] = [
        'name' => $row['name'] ?: "状态{$row['id']}",
        'color' => $row['color'] ?: '#0083f6ff'
    ];
}

// ========== 批量更新逻辑 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action'])) {

    
    $selectedStudents = isset($_POST['selected_students']) && is_array($_POST['selected_students']) 
        ? array_filter(array_map('intval', $_POST['selected_students'])) 
        : [];
    
    $statusId = intval($_POST['batch_status']);
    

    
    if (empty($selectedStudents)) {
        $_SESSION['error_message'] = "未选择有效的学生（选中数：" . count($selectedStudents) . "）";
        header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
        exit;
    }
    
    if (!isset($statusOptions[$statusId])) {
        $_SESSION['error_message'] = "无效的状态ID（ID：{$statusId}）";
        header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
        exit;
    }
    
    $affected = 0;
    $pdo->beginTransaction();
    try {
        foreach ($selectedStudents as $studentId) {

            
            $stmtCheck = $pdo->prepare("SELECT remarks FROM records WHERE task_id = ? AND student_id = ?");
            $stmtCheck->execute([$taskId, $studentId]);
            $existingRecord = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            $remarks = $existingRecord ? $existingRecord['remarks'] : '';

            $sql = "REPLACE INTO records 
                    (task_id, student_id, status_id, remarks, updated_by, rec_created, rec_updated) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$taskId, $studentId, $statusId, $remarks, $userId]);
            $affected++;

        }
        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();

        $_SESSION['error_message'] = "批量更新失败：" . $e->getMessage();
        header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO logs (u_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$userId, "批量更新任务#{$taskId}的{$affected}名学生状态为ID:{$statusId}"]);

    $_SESSION['success_message'] = "已成功更新{$affected}名学生的状态（共选中" . count($selectedStudents) . "名）";
    header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
    exit;
}

// ========== 手动保存逻辑（核心日志增强） ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selected'])) {

    // 解析选中学生
    $selectedStudents = [];
    if (isset($_POST['selected_students_json']) && !empty($_POST['selected_students_json'])) {
        $selectedStudents = json_decode($_POST['selected_students_json'], true) ?: [];
        $selectedStudents = array_filter(array_map('intval', $selectedStudents));
    } elseif (isset($_POST['selected_students']) && is_array($_POST['selected_students'])) {
        $selectedStudents = array_filter(array_map('intval', $_POST['selected_students']));
    }
    
    // 解析records数据
    $records = [];
    if (isset($_POST['records_json']) && !empty($_POST['records_json'])) {
        $records = json_decode($_POST['records_json'], true) ?: [];
    } elseif (isset($_POST['records']) && is_array($_POST['records'])) {
        $records = $_POST['records'];
    }
    

    
    if (empty($selectedStudents)) {
        $_SESSION['error_message'] = "未选择需要更新的学生（选中数：" . count($selectedStudents) . "）";
        header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
        exit;
    }
    
    $updated = 0;
    $pdo->beginTransaction();
    try {
        foreach ($selectedStudents as $studentId) {

            
            // 检查records中是否有该学生数据
            if (!isset($records[$studentId])) {

                continue;
            }
            
            // 检查status_id是否存在
            if (!isset($records[$studentId]['status_id'])) {

                continue;
            }
            
            $statusId = intval($records[$studentId]['status_id']);
            $remarks = trim($records[$studentId]['remarks'] ?? '');
            

            
            // 检查状态ID是否有效
            if (!isset($statusOptions[$statusId])) {

                continue;
            }
            
            if ($studentId <= 0) {

                continue;
            }
            
            // 执行更新
            $sql = "REPLACE INTO records 
                    (task_id, student_id, status_id, remarks, updated_by, rec_created, rec_updated) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$taskId, $studentId, $statusId, $remarks, $userId]);
            
            $rowCount = $stmt->rowCount();
            $updated++;

        }
        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();

        $_SESSION['error_message'] = "手动更新失败：" . $e->getMessage();
        header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
        exit;
    }
    
    // 写入操作日志
    $stmt = $pdo->prepare("INSERT INTO logs (u_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$userId, "手动更新任务#{$taskId}的{$updated}条选中学生记录"]);

    
    $_SESSION['success_message'] = "已成功更新{$updated}条选中学生的记录（共选中" . count($selectedStudents) . "名）";
    header("Location: U_edit_tasks.php?task_id={$taskId}&class_id={$currentClassCode}");
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>处理销假 - <?= htmlspecialchars($task['task_title']) ?></title>
    <link rel="stylesheet" href="../css/css.css" />
    <link rel="stylesheet" href="../css/User.css" />
    <style>
        <?php foreach ($statusOptions as $id => $status): ?>
        .status-option-<?= $id ?> {
            background-color: <?= $status['color'] ?> !important;
            color: white !important;
        }
        .status-select option[value="<?= $id ?>"],
        .batch-status-select option[value="<?= $id ?>"] {
            background-color: <?= $status['color'] ?> !important;
            color: white !important;
        }
        <?php endforeach; ?>
    </style>
</head>
<body>
    
    <div class="U_app">

        <?php if (isset($_SESSION['error_message']) || isset($_SESSION['success_message'])): ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="message error-message"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message success-message"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
        <?php endif; ?>

        <p class="tit1">我的任务</p>
        
        <div class="card page-header">
            <h3>处理任务：<?= htmlspecialchars($task['task_title']) ?></h3>
            <div class="header-actions">
                <a href="export_records.php?task_id=<?= $taskId ?>&class_id=<?= $currentClassCode ?>" 
                class="btn btn-primary">导出当前班级记录</a>
                <a href="export_records.php?task_id=<?= $taskId ?>" 
                class="btn btn-secondary">导出所有班级记录</a>
                <a href="U_my_tasks.php" class="btn btn-secondary">返回任务列表</a>
            </div>
        </div>
        
        <div class="card ">
            <h3>选择班级</h3>
            <?php foreach ($classes as $classCode => $className): ?>
            <a href="U_edit_tasks.php?task_id=<?= $taskId ?>&class_id=<?= $classCode ?>" 
               class="class-tab <?= $currentClassCode == $classCode ? 'active' : '' ?>">
                <?= htmlspecialchars($className) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="grid">

        

        <div class="card search-box">
            <h3>搜索学生</h3>
            <form method="GET">
                <input type="hidden" name="task_id" value="<?= $taskId ?>">
                <input type="hidden" name="class_id" value="<?= $currentClassCode ?>">
                <input type="text" class="search-input" name="search" placeholder="搜索学生姓名..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">搜索</button>
                <?php if (!empty($search)): ?>
                <a href="U_edit_tasks.php?task_id=<?= $taskId ?>&class_id=<?= $currentClassCode ?>" 
                   class="btn btn-secondary">清除搜索</a>
                <?php endif; ?>
            </form>
        </div>

        
        <div class="card batch-operation">
        <?php if (!empty($currentClassCode) && !empty($students)): ?>
        <!-- 批量操作表单（包含学生列表） -->
        <form method="POST" id="batchForm">
            <input type="hidden" name="batch_action" value="1">
                <h3>批量操作</h3>
                <div class="batch-fields">
                    <select name="batch_status" class="batch-status-select" required>
                        <option value="">请选择要设置的销假状态</option>
                        <?php foreach ($statusOptions as $id => $status): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($status['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="batch-actions">
                        <button type="button" class="btn btn-secondary" id="selectAll">全选</button>
                        <button type="button" class="btn btn-secondary" id="deselectAll">取消全选</button>
                        <button type="submit" class="btn batch-submit" id="batchSubmit">批量设置状态</button>
                    </div>
                </div>
            </div>

        </div>

            <!-- 学生列表 -->
            <div class="card table-wrapper">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th class="select-col" style="width: 60px;">选择</th>
                            <th class="index-col" style="width: 80px;">序号</th>
                            <th class="name-col" style="width: 150px;">姓名</th>
                            <th class="tags-col" style="width: 200px;">标签</th>
                            <th class="status-col" style="width: 180px;">销假状态</th>
                            <th class="remarks-col">备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                        <tr class="student-row">
                            <td class="select-col">
                                <input type="checkbox" name="selected_students[]" 
                                       value="<?= $student['id'] ?>" class="student-checkbox">
                            </td>
                            <td class="index-col"><?= $index + 1 ?></td>
                            <td class="name-col"><?= htmlspecialchars($student['name']) ?></td>
                            <td class="tags-col">
                                <?= empty($student['tags']) ? '-' : implode('、', array_map('htmlspecialchars', $student['tags'])) ?>
                            </td>
                            <td class="status-col">
                                <select name="records[<?= $student['id'] ?>][status_id]" 
                                        class="status-select status-option-<?= $student['status_id'] ?>"
                                        data-selected="<?= $student['status_id'] ?>"
                                        required>
                                    <?php foreach ($statusOptions as $id => $status): ?>
                                    <option value="<?= $id ?>" <?= $student['status_id'] == $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="remarks-col">
                                <textarea name="records[<?= $student['id'] ?>][remarks]" 
                                          class="remarks-textarea"
                                          placeholder="请输入备注"><?= htmlspecialchars($student['remarks']) ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- 手动保存表单 -->
        <form method="POST" id="studentForm">
            <input type="hidden" name="save_selected" value="1">
            <input type="hidden" name="selected_students_json" id="selectedStudentsJson">
            <input type="hidden" name="records_json" id="recordsJson">
            
            <div class="form-actions">
                <button type="submit" class="save-selected" id="saveSelectedBtn">保存选中学生的修改</button>
            </div>
        </form>
        <?php elseif (empty($currentClassCode)): ?>
        <p>请选择一个班级进行操作</p>
        <?php else: ?>
        <p>当前班级没有启用状态的学生数据</p>
        <?php endif; ?>
    </div>

    <script>


        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const selectAllBtn = document.getElementById('selectAll');
            const deselectAllBtn = document.getElementById('deselectAll');
            const batchForm = document.getElementById('batchForm');
            const batchSubmit = document.getElementById('batchSubmit');
            const studentForm = document.getElementById('studentForm');
            const selectedStudentsJson = document.getElementById('selectedStudentsJson');
            const recordsJson = document.getElementById('recordsJson');
            const saveSelectedBtn = document.getElementById('saveSelectedBtn');

            // 全选/取消全选
            selectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                syncFormData();
                this.textContent = '已全选';
                setTimeout(() => {
                    this.textContent = '全选';
                }, 1000);
            });
            deselectAllBtn.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                syncFormData();
                this.textContent = '已取消';
                setTimeout(() => {
                    this.textContent = '取消全选';
                }, 1000);
            });

            // 同步表单数据
            function syncFormData() {
                // 1. 同步选中的学生ID
                const selectedIds = [];
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        selectedIds.push(checkbox.value);
                    }
                });
                selectedStudentsJson.value = JSON.stringify(selectedIds);

                // 2. 同步所有学生的状态和备注数据
                const recordsData = {};
                document.querySelectorAll('.student-row').forEach(row => {
                    const studentId = row.querySelector('.student-checkbox').value;
                    const statusSelect = row.querySelector('.status-select');
                    const remarksTextarea = row.querySelector('.remarks-textarea');
                    
                    recordsData[studentId] = {
                        status_id: statusSelect.value,
                        remarks: remarksTextarea.value
                    };
                });
                recordsJson.value = JSON.stringify(recordsData);
            }

            // 监听变化，实时同步
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', syncFormData);
            });
            document.querySelectorAll('.status-select').forEach(select => {
                select.addEventListener('change', syncFormData);
            });
            document.querySelectorAll('.remarks-textarea').forEach(textarea => {
                textarea.addEventListener('input', syncFormData);
            });

            // 批量提交验证
            batchForm.addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
                const statusValue = document.querySelector('.batch-status-select').value;
                
                if (selectedCount === 0) {
                    alert('请先选择需要批量操作的学生');
                    e.preventDefault();
                    return;
                }
                if (!statusValue) {
                    alert('请选择要设置的销假状态');
                    e.preventDefault();
                    return;
                }
                if (!confirm(`确认要将选中的${selectedCount}名学生的销假状态设置为"${document.querySelector('.batch-status-select option[value='+statusValue+']').textContent}"吗？`)) {
                    e.preventDefault();
                    return;
                }
                batchSubmit.disabled = true;
                batchSubmit.textContent = '处理中...';
            });

            // 手动保存提交
            studentForm.addEventListener('submit', function(e) {
                const selectedIds = JSON.parse(selectedStudentsJson.value || '[]');
                if (selectedIds.length === 0) {
                    alert('请先选择需要保存的学生');
                    e.preventDefault();
                    return;
                }
                
                if (!confirm(`确认要保存选中的${selectedIds.length}名学生的修改吗？`)) {
                    e.preventDefault();
                    return;
                }
                
                saveSelectedBtn.disabled = true;
                saveSelectedBtn.textContent = '保存中...';
            });

            // 状态颜色实时更新
            document.querySelectorAll('.status-select').forEach(select => {
                const initValue = select.getAttribute('data-selected');
                select.classList.add(`status-option-${initValue}`);
                
                select.addEventListener('change', function() {
                    const selectedValue = this.value;
                    Object.keys(<?= json_encode($statusOptions) ?>).forEach(id => {
                        this.classList.remove(`status-option-${id}`);
                    });
                    this.classList.add(`status-option-${selectedValue}`);
                });
            });

            const batchStatusSelect = document.querySelector('.batch-status-select');
            if (batchStatusSelect) {
                batchStatusSelect.addEventListener('change', function() {
                    const selectedValue = this.value;
                    Object.keys(<?= json_encode($statusOptions) ?>).forEach(id => {
                        this.classList.remove(`status-option-${id}`);
                    });
                    if (selectedValue) {
                        this.classList.add(`status-option-${selectedValue}`);
                    }
                });
            }

            // 初始化同步
            syncFormData();
        });
    </script>
</body>
</html>