<?php
define('PAGE_TITLE', 'سجل الافتقاد والرعاية الرعوية');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

// خيارات الساعات بين ساعات اليوم بفاصل نصف ساعة
$time_options = [
    '08:00:00' => '08:00 صباحاً',
    '08:30:00' => '08:30 صباحاً',
    '09:00:00' => '09:00 صباحاً',
    '09:30:00' => '09:30 صباحاً',
    '10:00:00' => '10:00 صباحاً',
    '10:30:00' => '10:30 صباحاً',
    '11:00:00' => '11:00 صباحاً',
    '11:30:00' => '11:30 صباحاً',
    '12:00:00' => '12:00 ظهراً',
    '12:30:00' => '12:30 ظهراً',
    '13:00:00' => '01:00 ظهراً',
    '13:30:00' => '01:30 ظهراً',
    '14:00:00' => '02:00 عصراً',
    '14:30:00' => '02:30 عصراً',
    '15:00:00' => '03:00 عصراً',
    '15:30:00' => '03:30 عصراً',
    '16:00:00' => '04:00 عصراً',
    '16:30:00' => '04:30 عصراً',
    '17:00:00' => '05:00 مساءً',
    '17:30:00' => '05:30 مساءً',
    '18:00:00' => '06:00 مساءً',
    '18:30:00' => '06:30 مساءً',
    '19:00:00' => '07:00 مساءً',
    '19:30:00' => '07:30 مساءً',
    '20:00:00' => '08:00 مساءً',
    '20:30:00' => '08:30 مساءً',
    '21:00:00' => '09:00 مساءً',
    '21:30:00' => '09:30 مساءً',
    '22:00:00' => '10:00 مساءً',
    '22:30:00' => '10:30 مساءً',
    '23:00:00' => '11:00 مساءً',
    '23:30:00' => '11:30 مساءً',
];

// Handle Add / Edit Visit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $family_id = !empty($_POST['family_id']) ? (int)$_POST['family_id'] : null;
    $person_id = !empty($_POST['person_id']) ? (int)$_POST['person_id'] : null;
    $servant_id = !empty($_POST['servant_id']) ? (int)$_POST['servant_id'] : null;
    
    // تاريخ وساعة الافتقاد
    $visit_day = !empty($_POST['visit_day']) ? $_POST['visit_day'] : date('Y-m-d');
    $visit_time = !empty($_POST['visit_time']) ? $_POST['visit_time'] : '18:00:00';
    $visit_date = $visit_day . ' ' . $visit_time;
    
    $visit_type = $_POST['visit_type'] ?? 'home_visit';
    $status = $_POST['status'] ?? 'completed'; // completed (تمت) أو scheduled (مرتبة / مستقبلية)
    $notes = trim($_POST['notes'] ?? '');
    $next_visit_date = !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null;

    if (!$family_id && !$person_id) {
        set_flash('error', 'يرجى اختيار الأسرة أو الفرد المفتقد.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO visits (family_id, person_id, servant_id, visit_date, visit_type, status, notes, next_visit_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$family_id, $person_id, $servant_id, $visit_date, $visit_type, $status, $notes, $next_visit_date]);
        record_audit($pdo, 'create', 'visits', $pdo->lastInsertId());
        set_flash('success', 'تم تسجيل زيارة الافتقاد بنجاح.');
        header("Location: visits.php");
        exit;
    }
}

// Fetch Servants (فقط من هم خدام أو في إعداد خدام حسب التشيك بوكس)
$servants = $pdo->query("
    SELECT id, first_name, middle_name, last_name, is_servant, in_prep_center 
    FROM persons 
    WHERE is_active = 1 AND (is_servant = 1 OR in_prep_center = 1) 
    ORDER BY first_name ASC
")->fetchAll();

// Fetch Families for dropdown
$families = $pdo->query("SELECT id, family_name, family_code FROM families WHERE is_active = 1 ORDER BY family_name ASC")->fetchAll();

// Fetch Persons for dropdown
$persons = $pdo->query("SELECT id, first_name, middle_name, last_name, phone FROM persons WHERE is_active = 1 ORDER BY first_name ASC")->fetchAll();

// Tab & Filter
$tab = $_GET['tab'] ?? 'log';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// الأسر المتأخرة عن الافتقاد (أكثر من 60 يوم بدون زيارة مكتملة وليس لها موعد قادم)
$overdue_families = $pdo->query("
    SELECT f.id, f.family_name, f.area, f.phone,
           (SELECT p.phone FROM family_members fm JOIN persons p ON fm.person_id = p.id WHERE fm.family_id = f.id AND fm.is_head = 1 AND fm.is_deleted = 0 LIMIT 1) AS head_phone,
           MAX(CASE WHEN v.status = 'completed' THEN v.visit_date END) AS last_visit_date,
           SUM(CASE WHEN v.status IN ('scheduled', 'pending') THEN 1 ELSE 0 END) AS scheduled_cnt
    FROM families f
    LEFT JOIN visits v ON f.id = v.family_id
    WHERE f.is_active = 1
    GROUP BY f.id
    HAVING scheduled_cnt = 0 AND (last_visit_date IS NULL OR last_visit_date < DATE_SUB(NOW(), INTERVAL 60 DAY))
    ORDER BY last_visit_date ASC, f.id ASC
")->fetchAll();

$sql = "
    SELECT v.*, 
           f.family_name, f.family_code,
           p.first_name, p.middle_name, p.last_name,
           s.first_name AS servant_first, s.middle_name AS servant_middle, s.last_name AS servant_last
    FROM visits v
    LEFT JOIN families f ON v.family_id = f.id
    LEFT JOIN persons p ON v.person_id = p.id
    LEFT JOIN persons s ON v.servant_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($type_filter)) {
    $sql .= " AND v.visit_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY v.visit_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">سجل الافتقاد والرعاية الرعوية</h1>
        <p class="text-xs text-slate-500 mt-1">متابعة الزيارات المنجزة والمواعيد المستقبلية للخدام والمفتقدين</p>
    </div>
    <div>
        <button onclick="openVisitModal()" class="inline-flex items-center px-4 py-2.5 rounded-xl gold-gradient text-church-navy font-bold text-sm shadow-md hover:brightness-105 transition">
            <i class="fas fa-plus-circle ml-2"></i>
            تسجيل زيارة افتقاد جديدة
        </button>
    </div>
</div>

<!-- Tabs: سجل الزيارات | المتأخرين عن الافتقاد (+60 يوم) -->
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="visits.php?tab=log" class="px-4 py-2 rounded-xl text-xs font-extrabold transition <?= $tab !== 'overdue' ? 'bg-church-navy text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
        <i class="fas fa-list ml-1.5"></i> سجل الافتقادات (<?= count($visits) ?>)
    </a>
    <a href="visits.php?tab=overdue" class="px-4 py-2 rounded-xl text-xs font-extrabold transition flex items-center gap-2 <?= $tab === 'overdue' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-rose-700 border border-rose-200 hover:bg-rose-50' ?>">
        <i class="fas fa-bell"></i>
        <span>أسر متأخرة عن الافتقاد (+60 يوم)</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] <?= $tab === 'overdue' ? 'bg-white text-rose-700' : 'bg-rose-100 text-rose-800' ?>"><?= count($overdue_families) ?></span>
    </a>
</div>

<?php if ($tab === 'overdue'): ?>
<!-- Overdue Families Table + Ready WhatsApp -->
<div class="bg-white rounded-2xl shadow-sm border border-rose-200/80 overflow-hidden mb-6">
    <div class="p-4 bg-rose-50/70 border-b border-rose-100 flex items-center justify-between">
        <p class="text-xs font-bold text-rose-900">
            <i class="fas fa-circle-info ml-1.5 text-rose-600"></i>
            هذه الأسر لم تتم زيارتها منذ أكثر من 60 يوماً (أو لم تُزر من قبل) ولا يوجد لها موعد قادم مجدول.
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-bold text-slate-600">
                    <th class="p-4">الأسرة</th>
                    <th class="p-4">المنطقة</th>
                    <th class="p-4">آخر زيارة</th>
                    <th class="p-4 text-center">إجراء سريع</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($overdue_families)): ?>
                <tr>
                    <td colspan="4" class="p-10 text-center text-emerald-700 font-bold text-xs">
                        <i class="fas fa-check-circle text-2xl mb-2 block text-emerald-500"></i>
                        ممتاز! لا توجد أسر متأخرة عن الافتقاد حالياً.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($overdue_families as $of): 
                        $raw_phone = $of['head_phone'] ?: $of['phone'];
                        $wa = preg_replace('/[^0-9]/', '', (string)$raw_phone);
                        if (str_starts_with($wa, '01')) $wa = '20' . substr($wa, 1);
                        $wa_msg = urlencode("سلام ونعمة يا " . $of['family_name'] . "، بنسأل عليكم ونطمن على أخباركم من كنيسة العائلة المقدسة، نتمنى تكونوا بخير دايماً ❤️");
                    ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4 font-bold text-slate-900">
                            <a href="family-details.php?id=<?= $of['id'] ?>" class="text-church-blue hover:underline">
                                <?= htmlspecialchars($of['family_name']) ?>
                            </a>
                        </td>
                        <td class="p-4 text-xs text-slate-600"><?= htmlspecialchars($of['area'] ?: '-') ?></td>
                        <td class="p-4 text-xs">
                            <?php if ($of['last_visit_date']): ?>
                                <span class="px-2.5 py-1 bg-rose-100 text-rose-800 rounded-full font-bold">منذ <?= floor((time() - strtotime($of['last_visit_date'])) / 86400) ?> يوم</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full font-bold">لم تُزر من قبل</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-center">
                            <div class="inline-flex flex-wrap items-center justify-center gap-2">
                                <?php if (!empty($wa)): ?>
                                <a href="https://wa.me/<?= $wa ?>?text=<?= $wa_msg ?>" target="_blank" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-xl text-xs font-bold inline-flex items-center">
                                    <i class="fab fa-whatsapp ml-1.5 text-sm"></i> رسالة افتقاد
                                </a>
                                <?php endif; ?>
                                <a href="visits.php?family_id=<?= $of['id'] ?>&action=new" class="px-3 py-1.5 bg-church-navy text-white hover:bg-church-blue rounded-xl text-xs font-bold inline-flex items-center">
                                    <i class="fas fa-plus ml-1"></i> تسجيل زيارة
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>

<!-- Filters Bar -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 mb-6 flex flex-wrap gap-3 items-center">
    <form method="GET" action="visits.php" class="flex flex-wrap gap-3 w-full sm:w-auto flex-1">
        <select name="type" class="p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
            <option value="">جميع أنواع الافتقاد</option>
            <option value="home_visit" <?= $type_filter === 'home_visit' ? 'selected' : '' ?>>زيارة منزلية</option>
            <option value="phone_call" <?= $type_filter === 'phone_call' ? 'selected' : '' ?>>مكالمة هاتفية</option>
            <option value="emergency_visit" <?= $type_filter === 'emergency_visit' ? 'selected' : '' ?>>افتقاد طارئ / رعاية خاصة</option>
            <option value="follow_up" <?= $type_filter === 'follow_up' ? 'selected' : '' ?>>متابعة عامة</option>
        </select>

        <select name="status" class="p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
            <option value="">جميع الحالات</option>
            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>تمت الزيارة</option>
            <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>مرتبة / مستقبلية</option>
        </select>

        <button type="submit" class="px-4 py-2 bg-church-navy text-white rounded-xl text-xs font-bold hover:bg-church-blue">
            تطبيق الفلتر
        </button>

        <?php if (!empty($type_filter) || !empty($status_filter)): ?>
            <a href="visits.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200">
                مسح الفلتر
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Visits Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase">
                    <th class="p-4">المفتقد (الأسرة / الفرد)</th>
                    <th class="p-4">الخادم القائم بالافتقاد</th>
                    <th class="p-4">تاريخ ووقت الافتقاد</th>
                    <th class="p-4">النوع</th>
                    <th class="p-4">الحالة</th>
                    <th class="p-4">الملاحظات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($visits)): ?>
                <tr>
                    <td colspan="6" class="p-10 text-center text-slate-400">
                        <i class="fas fa-clipboard-user text-3xl mb-3 block text-slate-300"></i>
                        لا توجد زيارات افتقاد مسجلة مطابقة للبحث.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($visits as $v): ?>
                    <?php 
                    $person_name = trim(($v['first_name'] ?? '') . ' ' . ($v['middle_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                    $servant_name = trim(($v['servant_first'] ?? '') . ' ' . ($v['servant_middle'] ?? '') . ' ' . ($v['servant_last'] ?? ''));
                    ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4 font-bold text-slate-900">
                            <?php if ($v['family_name']): ?>
                                <a href="family-details.php?id=<?= $v['family_id'] ?>" class="text-church-blue hover:underline">
                                    <?= htmlspecialchars($v['family_name']) ?>
                                </a>
                            <?php else: ?>
                                <a href="person-details.php?id=<?= $v['person_id'] ?>" class="hover:text-church-blue">
                                    <?= htmlspecialchars($person_name) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-slate-700 font-semibold text-xs">
                            <?= htmlspecialchars($servant_name ?: 'الخدمة العامة') ?>
                        </td>
                        <td class="p-4 font-bold text-slate-800 text-xs">
                            <?= format_datetime($v['visit_date']) ?>
                        </td>
                        <td class="p-4 text-xs text-slate-600 font-medium">
                            <?php
                            $types = [
                                'home_visit'      => 'زيارة منزلية',
                                'phone_call'      => 'مكالمة هاتفية',
                                'emergency_visit' => 'افتقاد طارئ',
                                'follow_up'       => 'متابعة'
                            ];
                            echo $types[$v['visit_type']] ?? $v['visit_type'];
                            ?>
                        </td>
                        <td class="p-4">
                            <?php if ($v['status'] === 'completed'): ?>
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold inline-flex items-center">
                                    <i class="fas fa-check-circle ml-1 text-emerald-600"></i> تمت الزيارة
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-bold inline-flex items-center">
                                    <i class="fas fa-calendar-alt ml-1 text-blue-600"></i> مرتبة / مستقبلية
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-xs text-slate-500 max-w-xs truncate">
                            <?= htmlspecialchars($v['notes'] ?: '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Add Visit -->
<div id="visitModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">تسجيل زيارة افتقاد جديدة</h3>
            <button onclick="document.getElementById('visitModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="visits.php" class="space-y-4 pt-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الأسرة المفتقدة</label>
                <select name="family_id" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    <option value="">اختر الأسرة (أو حدد مخدوم بالأسفل)</option>
                    <?php foreach ($families as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (isset($_GET['family_id']) && $_GET['family_id'] == $f['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['family_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">أو مخدوم بعينه</label>
                <select name="person_id" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    <option value="">اختر المخدوم</option>
                    <?php foreach ($persons as $p): ?>
                        <?php $p_full = trim($p['first_name'] . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? '')); ?>
                        <option value="<?= $p['id'] ?>" <?= (isset($_GET['person_id']) && $_GET['person_id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p_full) ?> <?= $p['phone'] ? '('.$p['phone'].')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الخادم القائم بالافتقاد *</label>
                <select name="servant_id" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-church-navy">
                    <option value="">اختر الخادم من سجل خدام الكنيسة</option>
                    <?php foreach ($servants as $s): ?>
                        <?php 
                        $sname = trim($s['first_name'] . ' ' . ($s['middle_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                        $role_info = ($s['is_servant'] && $s['in_prep_center']) ? ' (خادم + إعداد خدام)' : ($s['in_prep_center'] ? ' (إعداد خدام)' : ' (خادم)');
                        ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($sname . $role_info) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">نوع الافتقاد</label>
                    <select name="visit_type" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="home_visit">زيارة منزلية</option>
                        <option value="phone_call">مكالمة هاتفية</option>
                        <option value="emergency_visit">افتقاد طارئ</option>
                        <option value="follow_up">متابعة</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">حالة الزيارة *</label>
                    <select name="status" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-church-navy">
                        <option value="completed">تمت الزيارة (حصلت بالفعل)</option>
                        <option value="scheduled">مرتبة / مستقبلية (موعد قادم)</option>
                    </select>
                </div>
            </div>

            <!-- تاريخ وساعة الافتقاد بخيارات كل نصف ساعة -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الافتقاد</label>
                    <input type="date" name="visit_day" value="<?= date('Y-m-d') ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الساعة (فواصل نصف ساعة)</label>
                    <select name="visit_time" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-church-navy">
                        <?php foreach ($time_options as $t_val => $t_label): ?>
                            <option value="<?= $t_val ?>" <?= $t_val === '18:00:00' ? 'selected' : '' ?>><?= $t_label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">موعد المتابعة القادمة (اختياري)</label>
                <input type="date" name="next_visit_date" value="<?= date('Y-m-d', strtotime('+60 days')) ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">ملاحظات وتقرير الافتقاد</label>
                <textarea name="notes" rows="2" placeholder="أحوال الأسرة، طلبات الصلاة، الاحتياجات..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('visitModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
                    إلغاء
                </button>
                <button type="submit" class="px-5 py-2 bg-church-navy text-white rounded-xl text-sm font-bold hover:bg-church-blue">
                    حفظ الزيارة
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openVisitModal() {
        document.getElementById('visitModal').classList.remove('hidden');
    }

    <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
        window.addEventListener('DOMContentLoaded', openVisitModal);
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
