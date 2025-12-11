<?php
$servername = "localhost";
$username = "root";
$password = "15829931165";
$dbname = "sn_edu";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch(PDOException $e) {
    error_log("数据库连接失败: " . $e->getMessage());
    die("数据库连接失败，请联系系统管理员");
}
?>