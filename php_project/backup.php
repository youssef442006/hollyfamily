<?php
define('PAGE_TITLE', 'النسخ الاحتياطي والاسترجاع');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

// متاح فقط لمدير النظام (يوسف عاطف)
require_role(['admin']);
$user = current_user();

// 1. تحميل نسخة احتياطية كاملة بصيغة SQL (تعمل على XAMPP و Hostinger بدون mysqldump)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_backup'])) {
    require_csrf();

    $filename = 'church_backup_' . date('Y-m-d_H-i') . '.sql';
    record_audit($pdo, 'backup_download', 'system', null, null, "تحميل نسخة احتياطية: $filename");

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- Coptic Church Management System Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- By: " . $user['full_name'] . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET NAMES utf8mb4;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create_row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        echo "-- --------------------------------------------------------\n";
        echo "-- Table structure for `$table`\n";
        echo "-- --------------------------------------------------------\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $create_row[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_map(fn($c) => "`$c`", array_keys($rows[0]));
            $col_list = implode(', ', $cols);

            foreach (array_chunk($rows, 100) as $chunk) {
                $values_arr = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ($row as $v) {
                        $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                    }
                    $values_arr[] = '(' . implode(', ', $vals) . ')';
                }
                echo "INSERT INTO `$table` ($col_list) VALUES\n" . implode(",\n", $values_arr) . ";\n";
            }
            echo "\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

// 2. استرجاع قاعدة البيانات من ملف SQL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    require_csrf();

    if (empty($_FILES['backup_file']['tmp_name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'يرجى اختيار ملف نسخة احتياطية (.sql) صالح.');
        header("Location: backup.php");
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        set_flash('error', 'صيغة الملف غير مدعومة. يجب أن يكون الملف بامتداد .sql فقط.');
        header("Location: backup.php");
        exit;
    }

    $sql_content = file_get_contents($_FILES['backup_file']['tmp_name']);
    // إزالة BOM أو التحويل من UTF-16 إذا كان الملف مصدّراً من PowerShell أو Windows
    if (str_starts_with($sql_content, "\xFF\xFE")) {
        $sql_content = mb_convert_encoding(substr($sql_content, 2), 'UTF-8', 'UTF-16LE');
    } elseif (str_starts_with($sql_content, "\xFE\xFF")) {
        $sql_content = mb_convert_encoding(substr($sql_content, 2), 'UTF-8', 'UTF-16BE');
    } elseif (str_starts_with($sql_content, "\xEF\xBB\xBF")) {
        $sql_content = substr($sql_content, 3);
    }

    if (empty(trim($sql_content))) {
        set_flash('error', 'ملف النسخة الاحتياطية فارغ.');
        header("Location: backup.php");
        exit;
    }

    try {
        // تقسيم أوامر SQL مع احترام النصوص والتعليقات
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $statements = [];
        $buffer = '';
        $in_string = false;
        $string_char = '';
        $len = strlen($sql_content);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql_content[$i];
            $next = ($i + 1 < $len) ? $sql_content[$i + 1] : '';

            // تخطي التعليقات خارج النصوص
            if (!$in_string && $ch === '-' && $next === '-') {
                while ($i < $len && $sql_content[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if (!$in_string && ($ch === "'" || $ch === '"')) {
                $in_string = true;
                $string_char = $ch;
            } elseif ($in_string && $ch === '\\') {
                $buffer .= $ch;
                if ($i + 1 < $len) {
                    $buffer .= $sql_content[++$i];
                }
                continue;
            } elseif ($in_string && $ch === $string_char) {
                if ($next === $string_char) {
                    $buffer .= $ch . $next;
                    $i++;
                    continue;
                }
                $in_string = false;
            }

            if (!$in_string && $ch === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
            } else {
                $buffer .= $ch;
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        foreach ($statements as $stmt_sql) {
            $pdo->exec($stmt_sql);
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        record_audit($pdo, 'backup_restore', 'system', null, null, 'استرجاع نسخة احتياطية: ' . $_FILES['backup_file']['name']);
        set_flash('success', 'تم استرجاع النسخة الاحتياطية بنجاح كامل! (' . count($statements) . ' أمر SQL)');
    } catch (Exception $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        set_flash('error', 'حدث خطأ أثناء الاسترجاع: ' . $e->getMessage());
    }

    header("Location: backup.php");
    exit;
}

// إحصائيات سريعة للقاعدة
$db_stats = [
    'families'    => (int)$pdo->query("SELECT COUNT(*) FROM families")->fetchColumn(),
    'persons'     => (int)$pdo->query("SELECT COUNT(*) FROM persons")->fetchColumn(),
    'visits'      => (int)$pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn(),
    'confessions' => (int)$pdo->query("SELECT COUNT(*) FROM confessions")->fetchColumn(),
    'audit_logs'  => (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn(),
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center">
            <i class="fas fa-database ml-2.5 text-church-gold"></i>
            النسخ الاحتياطي والاسترجاع
        </h1>
        <p class="text-xs text-slate-500 mt-1">حفظ نسخة كاملة من بيانات الكنيسة أو استرجاعها في أي وقت (متاح لمدير النظام فقط)</p>
    </div>
</div>

<!-- Quick DB Overview -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm text-center">
        <p class="text-[11px] text-slate-500 font-bold">الأسر</p>
        <p class="text-xl font-extrabold text-church-navy mt-1"><?= number_format($db_stats['families']) ?></p>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm text-center">
        <p class="text-[11px] text-slate-500 font-bold">الأفراد</p>
        <p class="text-xl font-extrabold text-church-navy mt-1"><?= number_format($db_stats['persons']) ?></p>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm text-center">
        <p class="text-[11px] text-slate-500 font-bold">الافتقادات</p>
        <p class="text-xl font-extrabold text-church-navy mt-1"><?= number_format($db_stats['visits']) ?></p>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm text-center">
        <p class="text-[11px] text-slate-500 font-bold">الاعترافات</p>
        <p class="text-xl font-extrabold text-church-navy mt-1"><?= number_format($db_stats['confessions']) ?></p>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm text-center col-span-2 sm:col-span-1">
        <p class="text-[11px] text-slate-500 font-bold">سجل النشاطات</p>
        <p class="text-xl font-extrabold text-amber-600 mt-1"><?= number_format($db_stats['audit_logs']) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- 1. تحميل نسخة احتياطية -->
    <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80 flex flex-col justify-between">
        <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl mb-4 border border-emerald-100">
                <i class="fas fa-download"></i>
            </div>
            <h2 class="text-lg font-extrabold text-slate-900 mb-2">أخذ نسخة احتياطية الآن (Download Backup)</h2>
            <p class="text-xs text-slate-500 leading-relaxed mb-6">
                يقوم بتصدير نسخة كاملة ومضبوطة الترميز (`utf8mb4`) من جميع جداول قاعدة البيانات (الأسر، المخدومين، الخدمات، الاعترافات، الافتقادات، المستخدمين) في ملف `.sql` واحد لتحفظه على جهازك أو فلاشة.
            </p>
        </div>

        <form method="POST" action="backup.php">
            <?= csrf_field() ?>
            <input type="hidden" name="download_backup" value="1">
            <button type="submit" class="w-full py-3.5 gold-gradient text-church-navy font-extrabold rounded-2xl text-sm shadow-md hover:brightness-105 transition flex items-center justify-center">
                <i class="fas fa-cloud-arrow-down ml-2 text-base"></i>
                تحميل النسخة الاحتياطية (.sql)
            </button>
        </form>
    </div>

    <!-- 2. استرجاع نسخة احتياطية -->
    <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-rose-200/80 flex flex-col justify-between">
        <div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl mb-4 border border-rose-100">
                <i class="fas fa-upload"></i>
            </div>
            <h2 class="text-lg font-extrabold text-slate-900 mb-2">استرجاع من نسخة احتياطية (Restore)</h2>
            <p class="text-xs text-slate-500 leading-relaxed mb-4">
                ارفع ملف `.sql` تم تحميله مسبقاً من النظام لاستعادة كافة البيانات.
                <strong class="text-rose-600 block mt-1">تنبيه: سيحل الاسترجاع محل البيانات الحالية في القاعدة، يفضل أخذ نسخة احتياطية قبل الاسترجاع.</strong>
            </p>
        </div>

        <form method="POST" action="backup.php" enctype="multipart/form-data" id="restoreForm" onsubmit="return confirmRestore(event)" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="restore_backup" value="1">
            <div>
                <input type="file" name="backup_file" accept=".sql" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold file:ml-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-church-navy file:text-white file:text-xs file:font-bold hover:file:bg-church-blue">
            </div>
            <button type="submit" class="w-full py-3.5 bg-rose-600 hover:bg-rose-700 text-white font-extrabold rounded-2xl text-sm shadow-md transition flex items-center justify-center">
                <i class="fas fa-rotate-left ml-2 text-base"></i>
                استرجاع قاعدة البيانات
            </button>
        </form>
    </div>

</div>

<script>
    function confirmRestore(e) {
        e.preventDefault();
        Swal.fire({
            title: 'تأكيد استرجاع النسخة الاحتياطية',
            text: 'سيتم استبدال البيانات الحالية بالنسخة المرفوعة. هل أنت متأكد من المتابعة؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#15264a',
            confirmButtonText: 'نعم، استرجع البيانات',
            cancelButtonText: 'إلغاء'
        }).then((res) => {
            if (res.isConfirmed) {
                document.getElementById('restoreForm').submit();
            }
        });
        return false;
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
