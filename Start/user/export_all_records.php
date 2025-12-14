<?php
// 1. 原有配置引入（保留）
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/config/header.php';

// 2. 替换Composer的autoload为手动加载（核心修改）
require __DIR__ . '/phpspreadsheet/Autoloader.php';

// 3. 正常使用PhpSpreadsheet类（后续代码不变）
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 以下原有导出逻辑完全保留...