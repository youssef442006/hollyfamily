<?php
define('PAGE_TITLE', 'التقارير والإحصائيات');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_permission('reports');
$user = current_user();

$report_type = $_GET['type'] ?? 'stage';
$stage_param = $_GET['stage'] ?? '';

// Data based on report type
$report_title = 'تقرير عام';
$data = [];

if ($report_type === 'stage') {
    $report_title = 'كشف المخدومين حسب المرحلة التعليمية';
    $sql = "
        SELECT p.*, f.family_name, f.family_code 
        FROM persons p 
        LEFT JOIN families f ON p.family_id = f.id 
        WHERE p.is_active = 1 AND p.is_archived = 0
    ";
    $params = [];
    if (!empty($stage_param)) {
        $sql .= " AND p.education_stage = ?";
        $params[] = $stage_param;
        $report_title .= ' (' . get_stage_name($stage_param) . ')';
    }
    $sql .= " ORDER BY p.education_stage ASC, p.first_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

} elseif ($report_type === 'servants') {
    $report_title = 'كشف خدام الكنيسة والأنشطة';
    $stmt = $pdo->query("
        SELECT p.*, f.family_name 
        FROM persons p 
        LEFT JOIN families f ON p.family_id = f.id 
        WHERE p.is_servant = 1 AND p.is_active = 1 
        ORDER BY p.first_name ASC
    ");
    $data = $stmt->fetchAll();

} elseif ($report_type === 'prep') {
    $report_title = 'كشف مخدومي وطلبة إعداد الخدام';
    $stmt = $pdo->query("
        SELECT p.*, f.family_name 
        FROM persons p 
        LEFT JOIN families f ON p.family_id = f.id 
        WHERE p.in_prep_center = 1 AND p.is_active = 1 
        ORDER BY p.first_name ASC
    ");
    $data = $stmt->fetchAll();

} elseif ($report_type === 'deacons') {
    $report_title = 'كشف شمامسة الكنيسة';
    $stmt = $pdo->query("
        SELECT p.*, f.family_name 
        FROM persons p 
        LEFT JOIN families f ON p.family_id = f.id 
        WHERE p.is_deacon = 1 AND p.is_active = 1 
        ORDER BY p.first_name ASC
    ");
    $data = $stmt->fetchAll();

} elseif ($report_type === 'birthdays') {
    $current_month = (int)date('m');
    $report_title = 'أعياد ميلاد المخدومين خلال هذا الشهر (' . date('F') . ')';
    $stmt = $pdo->prepare("
        SELECT p.*, f.family_name 
        FROM persons p 
        LEFT JOIN families f ON p.family_id = f.id 
        WHERE MONTH(p.date_of_birth) = ? AND p.is_active = 1 
        ORDER BY DAY(p.date_of_birth) ASC
    ");
    $stmt->execute([$current_month]);
    $data = $stmt->fetchAll();

} elseif ($report_type === 'special_cases') {
    $report_title = 'كشف الأسر ذات الحالات الخاصة والرعاية';
    $stmt = $pdo->query("
        SELECT f.*, 
               (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as members_cnt
        FROM families f 
        WHERE f.special_case IS NOT NULL AND f.special_case != 'عادية' AND f.is_active = 1
        ORDER BY f.family_name ASC
    ");
    $data = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Title & Print Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 print:hidden">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">التقارير والإحصائيات الكنسية</h1>
        <p class="text-xs text-slate-500 mt-1">إصدار وطباعة كشوف مدارس الأحد، الخدام، الشمامسة، ومتابعة الرعاية</p>
    </div>
    <div class="flex items-center space-x-2 space-x-reverse">
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-church-navy text-white font-bold text-sm hover:bg-church-blue transition shadow-sm">
            <i class="fas fa-print ml-2"></i>
            طباعة التقرير (Print)
        </button>
    </div>
</div>

<!-- Report Navigation Tabs -->
<div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200/80 mb-6 flex flex-wrap gap-2 print:hidden">
    <a href="reports.php?type=stage" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= ($report_type === 'stage') ? 'bg-church-navy text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
        المراحل التعليمية
    </a>
    <a href="reports.php?type=servants" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= ($report_type === 'servants') ? 'bg-church-navy text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
        كشف الخدام
    </a>
    <a href="reports.php?type=prep" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= ($report_type === 'prep') ? 'bg-church-navy text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
        كشف إعداد الخدام
    </a>
    <a href="reports.php?type=deacons" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= ($report_type === 'deacons') ? 'bg-church-navy text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
        كشف الشمامسة
    </a>
    <a href="reports.php?type=birthdays" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= ($report_type === 'birthdays') ? 'bg-church-navy text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
        أعياد ميلاد الشهر
    </a>
    <a href="reports.php?type=special_cases" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= ($report_type === 'special_cases') ? 'bg-church-navy text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
        حالات الرعاية الخاصة
    </a>
</div>

<!-- Printable Document Container -->
<div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 print:border-none print:shadow-none print:p-0">
    
    <!-- Printable Church Header -->
    <div class="text-center pb-6 border-b border-slate-200 mb-6">
        <h2 class="text-xl font-bold text-slate-900">كنيسة العائلة المقدسة</h2>
        <h3 class="text-base font-extrabold text-church-navy mt-1"><?= htmlspecialchars($report_title) ?></h3>
        <p class="text-xs text-slate-400 mt-1">تاريخ الإصدار: <?= date('Y/m/d') ?> • إجمالي النتائج: <?= count($data) ?> سجل</p>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse text-xs sm:text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 font-bold text-slate-700">
                    <th class="p-3">#</th>
                    <th class="p-3">الاسم بالكامل</th>
                    <?php if ($report_type !== 'special_cases'): ?>
                        <th class="p-3">العمر والميلاد</th>
                        <th class="p-3">المرحلة</th>
                        <th class="p-3">التليفون</th>
                        <th class="p-3">أب الاعتراف</th>
                    <?php else: ?>
                        <th class="p-3">كود الأسرة</th>
                        <th class="p-3">نوع الحالة</th>
                        <th class="p-3">العنوان</th>
                        <th class="p-3">التليفون</th>
                        <th class="p-3">الأفراد</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate-400">لا توجد بيانات مسجلة مطابقة لهذا التقرير.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($data as $i => $row): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-3 text-slate-400 font-mono"><?= $i + 1 ?></td>
                        
                        <?php if ($report_type !== 'special_cases'): ?>
                            <td class="p-3 font-bold text-slate-900">
                                <?= htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']) ?>
                            </td>
                            <td class="p-3 text-slate-700">
                                <?= format_date($row['date_of_birth']) ?> (<?= calc_age($row['date_of_birth']) ?> سنة)
                            </td>
                            <td class="p-3 text-slate-600 font-medium">
                                <?= get_stage_name($row['education_stage']) ?>
                            </td>
                            <td class="p-3 font-mono text-slate-700" dir="ltr">
                                <?= htmlspecialchars($row['phone'] ?: '-') ?>
                            </td>
                            <td class="p-3 text-slate-600">
                                <?= htmlspecialchars($row['confession_father'] ?: '-') ?>
                            </td>
                        <?php else: ?>
                            <td class="p-3 font-bold text-slate-900">
                                <?= htmlspecialchars($row['family_name']) ?>
                            </td>
                            <td class="p-3 font-mono text-church-blue font-bold">
                                <?= htmlspecialchars($row['family_code']) ?>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded font-semibold text-xs">
                                    <?= htmlspecialchars($row['special_case']) ?>
                                </span>
                            </td>
                            <td class="p-3 text-slate-600">
                                <?= htmlspecialchars($row['address'] ?: '-') ?>
                            </td>
                            <td class="p-3 font-mono" dir="ltr">
                                <?= htmlspecialchars($row['phone'] ?: '-') ?>
                            </td>
                            <td class="p-3 font-bold text-church-navy">
                                <?= $row['members_cnt'] ?> أفراد
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
