<?php
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';
require_once $basePath . '/vendor/autoload.php'; // 引入PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// 1. 获取所有班级的学生数据（新增按专业分组逻辑）
$taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$majorGroups = []; // 按专业分组存储：major => [班级数据列表]
$classCodes = []; // 所有班级的class_code

// 先获取当前任务分配的所有班级
$stmt = $pdo->prepare("SELECT task_json FROM tasks WHERE task_id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
$counselorAssignments = json_decode($task['task_json'], true) ?: [];
$currentUserId = (string)$_SESSION['user_id'];
$classCodes = $counselorAssignments[$currentUserId] ?? [];

// 循环获取每个班级的信息（含major）+学生+销假记录
foreach ($classCodes as $classCode) {
    // 获取班级名称+所属专业（新增major字段查询）
    $stmt = $pdo->prepare("SELECT class_name, major FROM classes WHERE class_code = ?");
    $stmt->execute([$classCode]);
    $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $className = $classInfo['class_name'] ?: '未知班级';
    $major = $classInfo['major'] ?: '未分类专业'; // 兜底：无专业则显示“未分类专业”

    // 获取该班级的学生数据
    $stmt = $pdo->prepare("SELECT 
                            s.s_id AS id, 
                            s.s_name AS name, 
                            s.tags AS tags 
                        FROM student s 
                        WHERE s.s_class_code = ? 
                        AND s.student_status = 1 
                        ORDER BY s.s_id");
    $stmt->execute([$classCode]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== 核心修复1：调整查询+获取方式，兼容3列数据 ==========
    $studentIds = array_column($students, 'id');
    $records = []; // 存储：student_id => ['status_id' => xx, 'remarks' => xx]
    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare("SELECT 
                                student_id, 
                                status_id, 
                                remarks 
                            FROM records 
                            WHERE task_id = ? 
                            AND student_id IN ($placeholders)");
        $stmt->execute(array_merge([$taskId], $studentIds));
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawRecords as $item) {
            $records[$item['student_id']] = [
                'status_id' => $item['status_id'],
                'remarks' => $item['remarks']
            ];
        }
    }

    // 整理班级数据（过滤statusId=1和3）
    $classData = [];
    $validIndex = 0; // 仅统计有效数据的序号
    foreach ($students as $student) {
        $record = $records[$student['id']] ?? ['status_id' => 1, 'remarks' => ''];
        $statusId = $record['status_id'];
        
        // ========== 核心过滤：排除statusId=1和3的记录 ==========
        if (in_array($statusId, [1, 3])) {
            continue; // 跳过，不加入导出数据
        }

        $remarks = $record['remarks'];
        $validIndex++; // 仅有效数据递增序号

        $classData[] = [
            '序号' => $validIndex,
            '专业班级' => $className,
            '姓名' => $student['name'],
            '缺勤' => ($statusId == 4) ? '-2' : '',
            '违纪' => ($statusId == 5) ? '' : '',
            '早退' => '',
            '迟到' => '',
            '请假' => ($statusId == 2) ? '√' : '',
            // 精细化总分逻辑
            '总分' => match($statusId) {
                4 => '-2',   // 缺勤：总分-2
                5 => '请手动输入',   // 违纪：总分-1
                2 => '0',    // 请假：总分0
                default => '0' // 其他：总分0（实际已过滤，不会触发）
            }
        ];
    }

    // 仅当班级有有效数据时，才加入专业分组（避免空班级导出）
    if (!empty($classData)) {
        if (!isset($majorGroups[$major])) {
            $majorGroups[$major] = [];
        }
        $majorGroups[$major][] = [
            'className' => $className,
            'data' => $classData
        ];
    }
}

// 2. 生成Excel表格（按专业创建Sheet）
$spreadsheet = new Spreadsheet();
// 删除默认的Sheet1（避免空Sheet）
$spreadsheet->removeSheetByIndex(0);

// ========== 修复：全局默认样式（兼容低版本PhpSpreadsheet） ==========
$globalDefaultStyle = $spreadsheet->getDefaultStyle();
$globalDefaultStyle->getFont()
    ->setName('宋体')
    ->setSize(11);

// 遍历每个专业，创建独立Sheet
foreach ($majorGroups as $majorName => $classList) {
    // 创建新Sheet，命名为专业名称（过滤特殊字符）
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(preg_replace('/[\/:*?"<>|]/', '', $majorName)); // 移除Excel不支持的Sheet名称字符
    
    $currentRow = 1; // 当前Sheet的行号

    // 循环处理当前专业下的所有班级
    foreach ($classList as $class) {
        $className = $class['className'];
        $classData = $class['data'];

        // 步骤1：写入班级标题（合并单元格）
        $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "{$className}青年政治理论学习通报");
        // 标题样式：宋体、加粗居中、22号字
        $titleStyle = $sheet->getStyle("A{$currentRow}:I{$currentRow}");
        $titleStyle->getFont()
            ->setName('宋体')
            ->setBold(true)
            ->setSize(22);
        $titleStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $currentRow++;

        // 步骤2：写入表头
        $headers = ['序号', '专业班级', '姓名', '缺勤', '违纪', '早退', '迟到', '请假', '总分'];
        $sheet->fromArray($headers, null, "A{$currentRow}");
        // 表头样式：宋体、加粗、边框、居中
        $headerStyle = $sheet->getStyle("A{$currentRow}:I{$currentRow}");
        $headerStyle->getFont()
            ->setName('宋体')
            ->setBold(true)
            ->setSize(11);
        $headerStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $headerStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // 表头列宽：专业班级自适应，其余8.36字符
        $columns = ['A','B','C','D','E','F','G','H','I'];
        foreach ($columns as $col) {
            if ($col == 'B') {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            } else {
                $sheet->getColumnDimension($col)->setWidth(8.36);
            }
        }
        $currentRow++;

        // 步骤3：写入学生数据
        foreach ($classData as $rowData) {
            $sheet->fromArray(array_values($rowData), null, "A{$currentRow}");
            // 数据样式：宋体11号、边框、居中
            $dataStyle = $sheet->getStyle("A{$currentRow}:I{$currentRow}");
            $dataStyle->getFont()->setName('宋体')->setSize(11);
            $dataStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $dataStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $currentRow++;
        }

        // 步骤4：写入备注（修复空行bug）
        $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "注：缺勤-2 早退-1.5 违纪-1 迟到-0.5 请假 √");
        // 备注样式：#E54C5E颜色、居中、宋体11号
        $remarkStyle = $sheet->getStyle("A{$currentRow}:I{$currentRow}");
        $remarkStyle->getFont()
            ->setName('宋体')
            ->setSize(11)
            ->getColor()->setARGB('FFE54C5E');
        $remarkStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        
        // 备注后空两行，接下一个班级
        $currentRow += 3;
    }
}

// 兜底：若所有专业都无有效数据，提示并退出
if (empty($majorGroups)) {
    die("暂无符合条件的学生数据（已过滤statusId=1/3），无法导出Excel");
}

// 3. 输出Excel文件到浏览器下载
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="按专业分组_班级学习通报_' . date('Ymd') . '.xlsx"');
header('Cache-Control: max-age=0');

// 解决PHP8.0+输出缓存问题
ob_end_clean(); 
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;