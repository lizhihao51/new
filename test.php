<?php
require 'vendor/autoload.php'; // 引入Composer自动加载文件

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Hello PhpSpreadsheet');

$writer = new Xlsx($spreadsheet);
$writer->save('test.xlsx');

echo "Excel文件已生成！";