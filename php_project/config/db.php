<?php
/**
 * Database Connection (PDO)
 * اتصال قاعدة بيانات MySQL (XAMPP)
 */

$host = '127.0.0.1';
$dbname = 'church_management';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log('Church DB Connection Error: ' . $e->getMessage());
    http_response_code(503);
    die('<div dir="rtl" style="font-family:sans-serif;margin:40px auto;max-width:560px;padding:25px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:12px;text-align:right;">
        <h3 style="margin-top:0;">⚠️ تعذر الاتصال بقاعدة البيانات</h3>
        <p>حدثت مشكلة فنية مؤقتة أثناء الاتصال بقاعدة البيانات. يرجى المحاولة مرة أخرى أو مراجعة مدير النظام.</p>
    </div>');
}
