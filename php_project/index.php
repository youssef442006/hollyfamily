<?php
define('PAGE_TITLE', 'لوحة التحكم الرئيسية');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

// 1. Key Statistics
$stats = [];
$stats['families'] = (int)$pdo->query("SELECT COUNT(*) FROM families WHERE is_active = 1")->fetchColumn();
$stats['persons']  = (int)$pdo->query("SELECT COUNT(*) FROM persons WHERE is_active = 1 AND is_archived = 0")->fetchColumn();
$stats['servants'] = (int)$pdo->query("SELECT COUNT(*) FROM persons WHERE (is_servant = 1 OR in_prep_center = 1) AND is_active = 1")->fetchColumn();
$stats['deacons']  = (int)$pdo->query("SELECT COUNT(*) FROM persons WHERE is_deacon = 1 AND is_active = 1")->fetchColumn();
$stats['services'] = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();
$stats['pending_visits'] = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE status IN ('pending', 'scheduled')")->fetchColumn();
$stats['overdue_confessions'] = can_access('confessions')
    ? (int)$pdo->query("SELECT COUNT(*) FROM confessions WHERE status = 'missed' OR (next_confession_date IS NOT NULL AND next_confession_date < NOW() AND status != 'completed')")->fetchColumn()
    : 0;

// 2. Birthdays This Month
$current_month = (int)date('m');
$stmt = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, date_of_birth, phone
    FROM persons
    WHERE MONTH(date_of_birth) = ? AND is_active = 1 AND is_deceased = 0
    ORDER BY DAY(date_of_birth) ASC
    LIMIT 6
");
$stmt->execute([$current_month]);
$birthdays = $stmt->fetchAll();

// 3. Recent & Scheduled Visits (5)
$recent_visits = $pdo->query("
    SELECT v.*, 
           f.family_name,
           p.first_name, p.middle_name, p.last_name,
           s.first_name AS servant_first, s.middle_name AS servant_middle
    FROM visits v
    LEFT JOIN families f ON v.family_id = f.id
    LEFT JOIN persons p ON v.person_id = p.id
    LEFT JOIN persons s ON v.servant_id = s.id
    ORDER BY v.visit_date DESC
    LIMIT 5
")->fetchAll();

// 4. Recent Confessions (5) - فقط لمن له صلاحية الاعترافات
$recent_confessions = [];
if (can_access('confessions')) {
    $recent_confessions = $pdo->query("
        SELECT c.*, p.first_name, p.middle_name, p.last_name, pr.display_name AS priest_name
        FROM confessions c
        JOIN persons p ON c.person_id = p.id
        LEFT JOIN priests pr ON c.priest_id = pr.id
        ORDER BY c.confession_date DESC
        LIMIT 5
    ")->fetchAll();
}

// 5. Services Overview (للمستخدمين بدون صلاحية الاعترافات أو للجميع)
$services_overview = $pdo->query("
    SELECT s.id, s.name,
           (SELECT COUNT(*) FROM service_assignments sa WHERE sa.service_id = s.id AND sa.status = 'active') AS students_cnt,
           (SELECT COUNT(*) FROM service_members sm WHERE sm.service_id = s.id AND sm.is_active = 1) AS servants_cnt
    FROM services s
    WHERE s.is_active = 1
    ORDER BY s.min_age ASC, s.id ASC
    LIMIT 6
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- 1. Hero Welcome + Instant Search -->
<div class="navy-gradient rounded-3xl p-6 sm:p-8 text-white shadow-xl mb-6 relative overflow-hidden">
    <div class="absolute -top-10 -left-10 w-44 h-44 bg-church-gold/10 rounded-full blur-2xl pointer-events-none"></div>
    <div class="absolute -bottom-12 right-12 w-36 h-36 bg-white/5 rounded-full blur-xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 border border-white/15 text-church-gold text-[11px] font-bold mb-3">
                <i class="fas fa-cross ml-1.5"></i>
                كنيسة العائلة المقدسة • <?= get_role_name($user['role']) ?>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                سلام ونعمة، <?= htmlspecialchars($user['full_name']) ?> ✨
            </h1>
            <p class="text-slate-300 text-xs sm:text-sm mt-1.5">
                لوحة المتابعة الرعوية السريعة — ابحث عن أي مخدوم أو ابدأ العمل مباشرة
            </p>
        </div>

        <!-- Quick Global Search -->
        <form action="persons.php" method="GET" class="w-full lg:w-96">
            <div class="relative">
                <input type="text" name="q" placeholder="بحث سريع بالاسم أو رقم الموبايل..." class="w-full pr-11 pl-20 py-3.5 rounded-2xl bg-white/10 border border-white/20 text-white placeholder-slate-300 text-sm focus:bg-white focus:text-slate-900 focus:placeholder-slate-400 focus:outline-none transition shadow-inner">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-church-gold">
                    <i class="fas fa-search"></i>
                </div>
                <button type="submit" class="absolute inset-y-1.5 left-1.5 px-4 rounded-xl gold-gradient text-church-navy font-extrabold text-xs hover:brightness-105 transition">
                    بحث
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Quick Action Tiles (اختصارات العمل اليومي) -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
    <a href="family-wizard.php" class="group bg-white hover:bg-amber-50/40 rounded-2xl p-4 shadow-sm border border-slate-200/80 hover:border-church-gold/60 transition flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-2xl gold-gradient text-church-navy flex items-center justify-center text-lg shadow-sm mb-2.5 group-hover:scale-105 transition">
            <i class="fas fa-house-chimney-medical"></i>
        </div>
        <span class="text-xs font-extrabold text-slate-900">إضافة أسرة جديدة</span>
        <span class="text-[10px] text-slate-400 mt-0.5">معالج تسجيل الأسرة</span>
    </a>

    <a href="persons.php" class="group bg-white hover:bg-blue-50/40 rounded-2xl p-4 shadow-sm border border-slate-200/80 hover:border-blue-300 transition flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-2xl bg-blue-50 text-church-navy flex items-center justify-center text-lg mb-2.5 group-hover:scale-105 transition">
            <i class="fas fa-users"></i>
        </div>
        <span class="text-xs font-extrabold text-slate-900">سجل المخدومين</span>
        <span class="text-[10px] text-slate-400 mt-0.5">عرض وتعديل الشعب</span>
    </a>

    <?php if (can_access('visits')): ?>
    <a href="visits.php?action=new" class="group bg-white hover:bg-emerald-50/40 rounded-2xl p-4 shadow-sm border border-slate-200/80 hover:border-emerald-300 transition flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg mb-2.5 group-hover:scale-105 transition">
            <i class="fas fa-person-walking"></i>
        </div>
        <span class="text-xs font-extrabold text-slate-900">تسجيل افتقاد</span>
        <span class="text-[10px] text-slate-400 mt-0.5">زيارة تمت أو قادمة</span>
    </a>
    <?php endif; ?>

    <?php if (can_access('confessions')): ?>
    <a href="confessions.php?action=new" class="group bg-white hover:bg-amber-50/40 rounded-2xl p-4 shadow-sm border border-slate-200/80 hover:border-amber-300 transition flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg mb-2.5 group-hover:scale-105 transition">
            <i class="fas fa-hands-praying"></i>
        </div>
        <span class="text-xs font-extrabold text-slate-900">تسجيل اعتراف</span>
        <span class="text-[10px] text-slate-400 mt-0.5">سر الاعتراف</span>
    </a>
    <?php endif; ?>

    <?php if (can_access('services_manage')): ?>
    <a href="services.php" class="group bg-white hover:bg-indigo-50/40 rounded-2xl p-4 shadow-sm border border-slate-200/80 hover:border-indigo-300 transition flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-lg mb-2.5 group-hover:scale-105 transition">
            <i class="fas fa-church"></i>
        </div>
        <span class="text-xs font-extrabold text-slate-900">مدارس الأحد</span>
        <span class="text-[10px] text-slate-400 mt-0.5">الفصول والخدام</span>
    </a>
    <?php endif; ?>
</div>

<!-- 3. Clean KPI Summary Strip -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <a href="families.php" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 hover:shadow-md transition flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-500">الأسر المسجلة</p>
            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($stats['families']) ?></h3>
        </div>
        <div class="w-11 h-11 rounded-xl bg-blue-50 text-church-navy flex items-center justify-center text-lg">
            <i class="fas fa-people-roof"></i>
        </div>
    </a>

    <a href="persons.php" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 hover:shadow-md transition flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-500">إجمالي الشعب</p>
            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($stats['persons']) ?></h3>
        </div>
        <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
            <i class="fas fa-users"></i>
        </div>
    </a>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-500">الخدام وإعداد خدام</p>
            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($stats['servants']) ?></h3>
            <span class="text-[10px] text-amber-700 font-bold"><?= number_format($stats['deacons']) ?> شماس</span>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
            <i class="fas fa-user-check"></i>
        </div>
    </div>

    <a href="visits.php" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 hover:shadow-md transition flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-500">زيارات قادمة / متابعة</p>
            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($stats['pending_visits'] + $stats['overdue_confessions']) ?></h3>
            <span class="text-[10px] text-rose-600 font-bold"><?= $stats['pending_visits'] ?> زيارة مجدولة</span>
        </div>
        <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
            <i class="fas fa-calendar-check"></i>
        </div>
    </a>
</div>

<!-- 4. Actionable Widgets Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Birthdays This Month -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-sm flex items-center">
                <i class="fas fa-cake-candles ml-2 text-church-gold"></i>
                أعياد ميلاد هذا الشهر
            </h3>
            <?php if (can_access('reports')): ?>
            <a href="reports.php?type=birthdays" class="text-[11px] font-bold text-church-blue hover:underline">عرض الكل</a>
            <?php endif; ?>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($birthdays)): ?>
                <div class="p-8 text-center text-slate-400 text-xs">لا توجد أعياد ميلاد مسجلة هذا الشهر.</div>
            <?php else: ?>
                <?php foreach ($birthdays as $b): 
                    $b_name = trim($b['first_name'] . ' ' . ($b['middle_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
                    $b_age = calc_age($b['date_of_birth']);
                ?>
                <div class="p-3.5 hover:bg-slate-50/70 transition flex items-center justify-between">
                    <div>
                        <a href="person-details.php?id=<?= $b['id'] ?>" class="text-xs font-bold text-slate-900 hover:text-church-blue">
                            <?= htmlspecialchars($b_name) ?>
                        </a>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= date('d / m', strtotime($b['date_of_birth'])) ?> • <?= $b_age !== null ? $b_age . ' سنة' : '' ?></p>
                    </div>
                    <?php if (!empty($b['phone'])): 
                        $wa = preg_replace('/[^0-9]/', '', $b['phone']);
                        if (str_starts_with($wa, '01')) $wa = '20' . substr($wa, 1);
                    ?>
                    <a href="https://wa.me/<?= $wa ?>" target="_blank" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg text-[11px] font-bold hover:bg-emerald-100">
                        <i class="fab fa-whatsapp"></i> تهنئة
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent & Scheduled Visits -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-sm flex items-center">
                <i class="fas fa-clipboard-user ml-2 text-indigo-600"></i>
                أحدث الافتقادات والمواعيد
            </h3>
            <a href="visits.php" class="text-[11px] font-bold text-church-blue hover:underline">عرض الكل</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($recent_visits)): ?>
                <div class="p-8 text-center text-slate-400 text-xs">لا توجد زيارات افتقاد مسجلة حالياً.</div>
            <?php else: ?>
                <?php foreach ($recent_visits as $v): 
                    $p_name = trim(($v['first_name'] ?? '') . ' ' . ($v['middle_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                    $s_name = trim(($v['servant_first'] ?? '') . ' ' . ($v['servant_middle'] ?? ''));
                ?>
                <div class="p-3.5 hover:bg-slate-50/70 transition flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-900">
                            <?= htmlspecialchars($v['family_name'] ?: $p_name) ?>
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">الخادم: <?= htmlspecialchars($s_name ?: 'الخدمة العامة') ?></p>
                    </div>
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 block"><?= format_date($v['visit_date']) ?></span>
                        <?php if ($v['status'] === 'completed'): ?>
                            <span class="inline-block mt-0.5 px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-bold">تمت</span>
                        <?php else: ?>
                            <span class="inline-block mt-0.5 px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-[10px] font-bold">مرتبة / قادمة</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Third Column: Confessions (للكهنة ومدير النظام) OR Services Overview (لأمناء الخدمة والخدام) -->
    <?php if (can_access('confessions')): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-sm flex items-center">
                <i class="fas fa-hands-praying ml-2 text-church-gold"></i>
                أحدث جلسات الاعتراف
            </h3>
            <a href="confessions.php" class="text-[11px] font-bold text-church-blue hover:underline">عرض الكل</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($recent_confessions)): ?>
                <div class="p-8 text-center text-slate-400 text-xs">لا توجد جلسات اعتراف مسجلة حالياً.</div>
            <?php else: ?>
                <?php foreach ($recent_confessions as $c): 
                    $c_name = trim($c['first_name'] . ' ' . ($c['middle_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                ?>
                <div class="p-3.5 hover:bg-slate-50/70 transition flex items-center justify-between">
                    <div>
                        <a href="person-details.php?id=<?= $c['person_id'] ?>" class="text-xs font-bold text-slate-900 hover:text-church-blue">
                            <?= htmlspecialchars($c_name) ?>
                        </a>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($c['priest_name'] ?: 'غير محدد') ?></p>
                    </div>
                    <span class="text-[11px] text-slate-500 font-semibold"><?= format_date($c['confession_date']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-sm flex items-center">
                <i class="fas fa-church ml-2 text-church-gold"></i>
                نظرة على فصول مدارس الأحد
            </h3>
            <?php if (can_access('services_manage')): ?>
            <a href="services.php" class="text-[11px] font-bold text-church-blue hover:underline">إدارة الخدمات</a>
            <?php endif; ?>
        </div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($services_overview as $srv): ?>
            <div class="p-3.5 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($srv['name']) ?></span>
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="px-2 py-0.5 bg-blue-50 text-church-navy rounded-md font-bold"><?= $srv['students_cnt'] ?> مخدوم</span>
                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-md font-bold"><?= $srv['servants_cnt'] ?> خادم</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
