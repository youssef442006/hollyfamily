<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$person_id = (int)($_GET['id'] ?? 0);

if (!$person_id) {
    header("Location: persons.php");
    exit;
}

// Fetch Person with Family Info
$stmt = $pdo->prepare("
    SELECT p.*, f.family_name, f.family_code, f.address AS family_address
    FROM persons p
    LEFT JOIN families f ON p.family_id = f.id
    WHERE p.id = ?
");
$stmt->execute([$person_id]);
$person = $stmt->fetch();

if (!$person) {
    die("الفرد غير موجود");
}

$full_name = trim($person['first_name'] . ' ' . ($person['middle_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
define('PAGE_TITLE', 'الملف الشخصي: ' . $full_name);
$age = calc_age($person['date_of_birth']);

// Fetch Confession History
$stmt = $pdo->prepare("
    SELECT c.*, pr.display_name AS priest_name
    FROM confessions c
    LEFT JOIN priests pr ON c.priest_id = pr.id
    WHERE c.person_id = ?
    ORDER BY c.confession_date DESC
");
$stmt->execute([$person_id]);
$confessions = $stmt->fetchAll();

// Fetch Visits History
$stmt = $pdo->prepare("
    SELECT v.*, p.first_name AS servant_first_name, p.middle_name AS servant_middle_name, p.last_name AS servant_last_name
    FROM visits v
    LEFT JOIN persons p ON v.servant_id = p.id
    WHERE v.person_id = ?
    ORDER BY v.visit_date DESC
");
$stmt->execute([$person_id]);
$visits = $stmt->fetchAll();

// Fetch Service Assignments
$stmt = $pdo->prepare("
    SELECT sa.*, s.name AS service_name
    FROM service_assignments sa
    JOIN services s ON sa.service_id = s.id
    WHERE sa.person_id = ?
");
$stmt->execute([$person_id]);
$services = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Breadcrumb -->
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center space-x-2 space-x-reverse text-sm text-slate-500">
        <a href="persons.php" class="hover:text-church-navy">سجل المخدومين</a>
        <i class="fas fa-chevron-left text-xs text-slate-400"></i>
        <span class="font-bold text-slate-900"><?= htmlspecialchars($full_name) ?></span>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if (can_access('families_edit') && empty($person['is_deceased'])): ?>
        <a href="marriage.php?person_id=<?= $person_id ?>" class="px-3.5 py-2 gold-gradient text-church-navy rounded-xl text-xs font-extrabold hover:brightness-105 transition shadow-sm">
            <i class="fas fa-rings-wedding ml-1.5"></i> تسجيل زواج / فتح أسرة
        </a>
        <?php endif; ?>
        <?php if (can_access('confessions')): ?>
        <a href="confessions.php?person_id=<?= $person_id ?>&action=new" class="px-3.5 py-2 bg-amber-600 text-white rounded-xl text-xs font-bold hover:bg-amber-700 transition">
            <i class="fas fa-hands-praying ml-1.5"></i> تسجيل اعتراف
        </a>
        <?php endif; ?>
        <a href="visits.php?person_id=<?= $person_id ?>&action=new" class="px-3.5 py-2 bg-church-navy text-white rounded-xl text-xs font-bold hover:bg-church-blue transition">
            <i class="fas fa-person-walking ml-1.5"></i> تسجيل افتقاد
        </a>
    </div>
</div>

<!-- Main Profile Header Card -->
<div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 mb-8">
    <div class="flex flex-col sm:flex-row items-start justify-between gap-6">
        <div class="flex items-start space-x-4 space-x-reverse">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-church-navy flex items-center justify-center text-2xl font-bold border border-slate-200">
                <?= mb_substr($person['first_name'], 0, 1, 'UTF-8') ?>
            </div>
            <div>
                <div class="flex items-center space-x-2 space-x-reverse mb-1">
                    <h1 class="text-2xl font-extrabold text-slate-900">
                        <?= htmlspecialchars($full_name) ?>
                    </h1>
                    <?php if ($person['is_deacon']): ?>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-bold">شماس</span>
                    <?php endif; ?>
                    <?php if ($person['is_servant']): ?>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">خادم</span>
                    <?php endif; ?>
                    <?php if (!empty($person['in_prep_center'])): ?>
                        <span class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs font-bold">إعداد خدام</span>
                    <?php endif; ?>
                </div>

                <p class="text-xs text-slate-500 font-medium">
                    <?= $person['gender'] === 'female' ? 'أنثى' : 'ذكر' ?> • 
                    <?= $age !== null ? $age . ' سنة (' . format_date($person['date_of_birth']) . ')' : 'تاريخ الميلاد غير محدد' ?> • 
                    <?= htmlspecialchars($person['occupation'] ?: 'المهنة غير مسجلة') ?>
                </p>

                <?php if ($person['family_name']): ?>
                <p class="text-xs text-slate-600 mt-2 font-semibold">
                    الأسرة: 
                    <a href="family-details.php?id=<?= $person['family_id'] ?>" class="text-church-blue hover:underline">
                        <?= htmlspecialchars($person['family_name']) ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Contacts -->
        <div class="flex flex-wrap gap-2">
            <?php if ($person['phone']): ?>
            <a href="tel:<?= htmlspecialchars($person['phone']) ?>" class="inline-flex items-center px-3.5 py-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-mono font-bold" dir="ltr">
                <i class="fas fa-phone mr-2 text-church-blue"></i>
                <?= htmlspecialchars($person['phone']) ?>
            </a>
            <?php endif; ?>

            <?php if ($person['whatsapp'] || $person['phone']): 
                $wa = preg_replace('/[^0-9]/', '', $person['whatsapp'] ?: $person['phone']);
                if (str_starts_with($wa, '01')) $wa = '20' . substr($wa, 1);
            ?>
            <a href="https://wa.me/<?= $wa ?>" target="_blank" class="inline-flex items-center px-3.5 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold">
                <i class="fab fa-whatsapp ml-1.5 text-base text-emerald-600"></i>
                واتساب
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 2 Column Details: Spiritual & Personal Details -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    
    <!-- Spiritual Profile -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
        <h3 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center text-church-navy">
            <i class="fas fa-cross ml-2 text-church-gold"></i>
            البيانات الروحية والكنسية
        </h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">أب الاعتراف:</dt>
                <dd class="font-bold text-slate-800"><?= htmlspecialchars($person['confession_father'] ?: 'غير محدد') ?></dd>
            </div>
            <?php if (!empty($person['confession_church'])): ?>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">كنيسة أب الاعتراف:</dt>
                <dd class="font-bold text-slate-800"><?= htmlspecialchars($person['confession_church']) ?></dd>
            </div>
            <?php endif; ?>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">المرحلة الكنسية / التعليمية:</dt>
                <dd class="font-semibold text-slate-800"><?= get_stage_name($person['education_stage']) ?></dd>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">رتبة الشمامسة:</dt>
                <dd class="font-semibold text-slate-800"><?= $person['is_deacon'] ? 'شماس مرسوم' : 'لا يوجد' ?></dd>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">الصفة في الخدمة:</dt>
                <dd class="font-semibold text-slate-800">
                    <?php
                    $roles = [];
                    if ($person['is_servant']) $roles[] = 'خادم في الكنيسة';
                    if (!empty($person['in_prep_center'])) $roles[] = 'في إعداد خدام';
                    if (empty($roles)) $roles[] = 'مخدوم';
                    echo implode(' + ', $roles);
                    ?>
                </dd>
            </div>
            <?php if (!empty($person['care_situation']) && $person['care_situation'] !== 'normal'): ?>
            <div class="flex justify-between py-1">
                <dt class="text-slate-500 text-xs">حالة الرعاية الخاصة:</dt>
                <dd class="font-bold text-amber-700"><?= htmlspecialchars($person['care_situation']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Personal & Civil Profile -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
        <h3 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center text-church-navy">
            <i class="fas fa-id-card ml-2 text-church-gold"></i>
            البيانات المدنية والشخصية
        </h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">الرقم القومي:</dt>
                <dd class="font-mono font-bold text-slate-800" dir="ltr"><?= htmlspecialchars($person['national_id'] ?: 'غير مسجل') ?></dd>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">الحالة الاجتماعية:</dt>
                <dd class="font-semibold text-slate-800"><?= htmlspecialchars($person['marital_status'] ?: 'غير محدد') ?></dd>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">المهنة / الدراسة:</dt>
                <dd class="font-semibold text-slate-800"><?= htmlspecialchars($person['occupation'] ?: '-') ?></dd>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <dt class="text-slate-500 text-xs">الإجازة الأسبوعية:</dt>
                <dd class="font-bold text-church-navy"><?= format_weekly_leave($person['weekly_leave']) ?></dd>
            </div>
            <div class="flex justify-between py-1">
                <dt class="text-slate-500 text-xs">العنوان المسجل:</dt>
                <dd class="font-semibold text-slate-800"><?= htmlspecialchars($person['address'] ?: ($person['family_address'] ?: '-')) ?></dd>
            </div>
        </dl>
    </div>

</div>

<!-- Confessions Log Section -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-900 text-base flex items-center">
            <i class="fas fa-hands-praying ml-2 text-church-gold"></i>
            سجل جلسات الاعتراف (<?= count($confessions) ?>)
        </h3>
        <a href="confessions.php?person_id=<?= $person_id ?>&action=new" class="text-xs font-bold text-church-blue hover:underline">
            + إضافة جلسة اعتراف
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50/70 border-b border-slate-200/80 text-xs font-bold text-slate-500">
                    <th class="p-3.5">تاريخ الاعتراف</th>
                    <th class="p-3.5">أب الاعتراف</th>
                    <th class="p-3.5">الموعد القادم</th>
                    <th class="p-3.5">الحالة</th>
                    <th class="p-3.5">ملاحظات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($confessions)): ?>
                <tr>
                    <td colspan="5" class="p-6 text-center text-slate-400">لا توجد جلسات اعتراف مسجلة لهذا الشخص.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($confessions as $c): ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="p-3.5 font-bold text-slate-800"><?= format_date($c['confession_date']) ?></td>
                        <td class="p-3.5 font-medium text-slate-700"><?= htmlspecialchars($c['priest_name'] ?: 'غير محدد') ?></td>
                        <td class="p-3.5 text-xs text-slate-500"><?= format_date($c['next_confession_date']) ?></td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $c['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                <?= $c['status'] === 'completed' ? 'تم' : 'متأخر' ?>
                            </span>
                        </td>
                        <td class="p-3.5 text-xs text-slate-500"><?= htmlspecialchars($c['notes'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Visits Log Section -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-900 text-base flex items-center">
            <i class="fas fa-clipboard-user ml-2 text-church-gold"></i>
            سجل افتقادات ومتابعات هذا الفرد (<?= count($visits) ?>)
        </h3>
        <a href="visits.php?person_id=<?= $person_id ?>&action=new" class="text-xs font-bold text-church-blue hover:underline">
            + تسجيل افتقاد
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50/70 border-b border-slate-200/80 text-xs font-bold text-slate-500">
                    <th class="p-3.5">تاريخ الافتقاد</th>
                    <th class="p-3.5">الخادم المفتقد</th>
                    <th class="p-3.5">نوع الافتقاد</th>
                    <th class="p-3.5">الحالة</th>
                    <th class="p-3.5">الملاحظات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($visits)): ?>
                <tr>
                    <td colspan="5" class="p-6 text-center text-slate-400">لا توجد زيارات افتقاد مسجلة.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($visits as $v): ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="p-3.5 font-bold text-slate-800"><?= format_date($v['visit_date']) ?></td>
                        <td class="p-3.5 text-slate-700"><?= htmlspecialchars(trim(($v['servant_first_name'] ?? '') . ' ' . ($v['servant_middle_name'] ?? '') . ' ' . ($v['servant_last_name'] ?? '')) ?: 'غير محدد') ?></td>
                        <td class="p-3.5 text-xs text-slate-600"><?= htmlspecialchars($v['visit_type']) ?></td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $v['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= $v['status'] === 'completed' ? 'تمت الزيارة' : 'مرتبة / مستقبلية' ?>
                            </span>
                        </td>
                        <td class="p-3.5 text-xs text-slate-500"><?= htmlspecialchars($v['notes'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
