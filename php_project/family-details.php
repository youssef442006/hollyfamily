<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$family_id = (int)($_GET['id'] ?? 0);

if (!$family_id) {
    header("Location: families.php");
    exit;
}

// Fetch Family Details
$stmt = $pdo->prepare("SELECT * FROM families WHERE id = ?");
$stmt->execute([$family_id]);
$family = $stmt->fetch();

if (!$family) {
    die("الأسرة غير موجودة");
}

define('PAGE_TITLE', 'تفاصيل أسرة: ' . $family['family_name']);

// Handle Remove Member from Family (POST only + CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['remove_member']) || isset($_POST['delete_id']))) {
    require_csrf();
    $person_id = (int)($_POST['remove_member'] ?? $_POST['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM family_members WHERE family_id = ? AND person_id = ?");
    $stmt->execute([$family_id, $person_id]);
    $stmt2 = $pdo->prepare("UPDATE persons SET family_id = NULL WHERE id = ? AND family_id = ?");
    $stmt2->execute([$person_id, $family_id]);
    set_flash('success', 'تمت إزالة الفرد من الأسرة بنجاح.');
    header("Location: family-details.php?id=$family_id");
    exit;
}

// Handle Add Member to Family POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    require_csrf();
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $phone = trim($_POST['phone'] ?? '');
    $rel = $_POST['relationship_type'] ?? 'other';
    $is_head = ($rel === 'father' || isset($_POST['is_head'])) ? 1 : 0;
    $confession_father = trim($_POST['confession_father'] ?? '');
    $stage = $_POST['education_stage'] ?? null;
    $weekly_leave = !empty($_POST['weekly_leave']) ? implode(',', (array)$_POST['weekly_leave']) : null;

    if (!empty($first_name)) {
        // Insert person
        $stmt = $pdo->prepare("
            INSERT INTO persons (family_id, first_name, middle_name, last_name, gender, date_of_birth, phone, confession_father, education_stage, weekly_leave)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$family_id, $first_name, $middle_name, $last_name, $gender, $dob, $phone, $confession_father, $stage, $weekly_leave]);
        $person_id = $pdo->lastInsertId();

        // Link in family_members
        $stmt = $pdo->prepare("
            INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $is_spouse = ($rel === 'mother' || $rel === 'wife') ? 1 : 0;
        $is_child = ($rel === 'son' || $rel === 'daughter') ? 1 : 0;
        $stmt->execute([$family_id, $person_id, $rel, $is_head, $is_spouse, $is_child]);

        set_flash('success', 'تمت إضافة الفرد إلى الأسرة بنجاح.');
        header("Location: family-details.php?id=$family_id");
        exit;
    }
}

// Fetch Members
$stmt = $pdo->prepare("
    SELECT fm.*, p.first_name, p.middle_name, p.last_name, p.gender, p.date_of_birth, 
           p.phone, p.whatsapp, p.education_stage, p.confession_father, p.confession_church, p.is_deacon, p.is_servant, p.in_prep_center, p.occupation, p.care_situation
    FROM family_members fm
    JOIN persons p ON fm.person_id = p.id
    WHERE fm.family_id = ? AND fm.is_deleted = 0
    ORDER BY fm.is_head DESC, fm.is_spouse DESC, fm.id ASC
");
$stmt->execute([$family_id]);
$members = $stmt->fetchAll();

// Fetch Visits for this Family
$stmt = $pdo->prepare("
    SELECT v.*, p.first_name AS servant_first_name, p.middle_name AS servant_middle_name, p.last_name AS servant_last_name
    FROM visits v
    LEFT JOIN persons p ON v.servant_id = p.id
    WHERE v.family_id = ?
    ORDER BY v.visit_date DESC
");
$stmt->execute([$family_id]);
$visits = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Breadcrumb & Top Bar -->
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center space-x-2 space-x-reverse text-sm text-slate-500">
        <a href="families.php" class="hover:text-church-navy">سجل الأسر</a>
        <i class="fas fa-chevron-left text-xs text-slate-400"></i>
        <span class="font-bold text-slate-900"><?= htmlspecialchars($family['family_name']) ?></span>
    </div>
    <div class="flex items-center space-x-2 space-x-reverse">
        <button onclick="document.getElementById('addMemberModal').classList.remove('hidden')" class="px-4 py-2 bg-church-navy text-white rounded-xl text-sm font-semibold hover:bg-church-blue transition shadow-sm">
            <i class="fas fa-user-plus ml-1.5"></i> إضافة فرد للأسرة
        </button>
        <a href="visits.php?family_id=<?= $family['id'] ?>&action=new" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 transition shadow-sm">
            <i class="fas fa-person-walking ml-1.5"></i> تسجيل افتقاد للأسرة
        </a>
    </div>
</div>

<!-- Family Header Card -->
<div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 mb-8">
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
        <div>
            <?php if (!empty($family['special_case']) && $family['special_case'] !== 'عادية'): ?>
                <div class="mb-2">
                    <span class="text-xs font-bold px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-full">
                        <?= htmlspecialchars($family['special_case']) ?>
                    </span>
                </div>
            <?php endif; ?>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900"><?= htmlspecialchars($family['family_name']) ?></h1>
            <p class="text-slate-500 text-sm mt-1.5 flex items-center">
                <i class="fas fa-map-marker-alt ml-2 text-church-gold"></i>
                <?= htmlspecialchars($family['address'] ?: 'العنوان غير محدد') ?>
                <?php if ($family['building_number']): ?>
                    — عمارة <?= htmlspecialchars($family['building_number']) ?>، دور <?= htmlspecialchars($family['floor'] ?: '-') ?>، شقة <?= htmlspecialchars($family['apartment_number'] ?: '-') ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center space-x-3 space-x-reverse bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-church-navy flex items-center justify-center text-lg">
                <i class="fas fa-phone-flip"></i>
            </div>
            <div>
                <span class="text-[11px] font-bold text-slate-400 block uppercase">تليفون الأسرة</span>
                <span class="font-mono font-bold text-slate-800 text-sm" dir="ltr"><?= htmlspecialchars($family['phone'] ?: 'غير متوفر') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Family Members Section -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center space-x-3 space-x-reverse">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900">أفراد الأسرة (<?= count($members) ?>)</h2>
                <p class="text-xs text-slate-500">بيانات كل فرد من أفراد الأسرة، السن، الخدمة والاعتراف</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50/70 border-b border-slate-200/80 text-xs font-bold text-slate-500">
                    <th class="p-4">الصلة</th>
                    <th class="p-4">الاسم بالكامل</th>
                    <th class="p-4">العمر والمرحلة</th>
                    <th class="p-4">أب الاعتراف</th>
                    <th class="p-4">التليفون</th>
                    <th class="p-4">الرتب والخدمة</th>
                    <th class="p-4 text-center">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($members)): ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-400">لا يوجد أفراد مسجلين في هذه الأسرة بعد.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): 
                        $age = calc_age($m['date_of_birth']);
                    ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold <?= $m['is_head'] ? 'bg-amber-100 text-amber-900 font-extrabold' : 'bg-slate-100 text-slate-700' ?>">
                                <?= get_relationship_name($m['relationship_type']) ?>
                            </span>
                        </td>
                        <td class="p-4 font-bold text-slate-900">
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <a href="person-details.php?id=<?= $m['person_id'] ?>" class="hover:text-church-blue font-bold">
                                    <?= htmlspecialchars(trim($m['first_name'] . ' ' . ($m['middle_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                                </a>
                                <?php if (!empty($m['is_deacon'])): ?>
                                    <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[10px] font-bold">شماس</span>
                                <?php endif; ?>
                                <?php if (!empty($m['is_servant'])): ?>
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[10px] font-bold">خادم</span>
                                <?php endif; ?>
                                <?php if (!empty($m['in_prep_center'])): ?>
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded text-[10px] font-bold">إعداد خدام</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($m['occupation']): ?>
                                <span class="text-xs text-slate-400 font-normal block mt-0.5"><?= htmlspecialchars($m['occupation']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4">
                            <span class="font-bold text-slate-800 text-xs"><?= $age !== null ? $age . ' سنة' : '-' ?></span>
                            <span class="text-xs text-slate-500 block mt-0.5"><?= get_stage_name($m['education_stage']) ?></span>
                        </td>
                        <td class="p-4 text-slate-700 text-xs font-medium">
                            <?= htmlspecialchars($m['confession_father'] ?: 'غير محدد') ?>
                        </td>
                        <td class="p-4 font-mono text-xs" dir="ltr">
                            <?= htmlspecialchars($m['phone'] ?: '-') ?>
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap gap-1">
                                <?php if (!empty($m['care_situation']) && $m['care_situation'] !== 'normal'): ?>
                                    <span class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 rounded text-[11px] font-bold">
                                        <?= get_care_label($m['care_situation']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($m['is_deacon']): ?>
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded text-[11px] font-bold">شماس</span>
                                <?php endif; ?>
                                <?php if ($m['is_servant']): ?>
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[11px] font-bold">خادم</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <div class="inline-flex items-center space-x-1 space-x-reverse">
                                <a href="person-details.php?id=<?= $m['person_id'] ?>" title="الملف الكامل" class="p-2 text-church-blue hover:bg-blue-50 rounded-lg">
                                    <i class="fas fa-id-card"></i>
                                </a>
                                <button onclick="postDelete('family-details.php?id=<?= $family_id ?>', <?= $m['person_id'] ?>, '<?= csrf_token() ?>', 'إزالة الفرد', 'هل أنت متأكد من إزالة هذا الفرد من الأسرة؟')" title="إزالة من الأسرة" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition">
                                    <i class="fas fa-user-minus"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Visits History for this Family -->
<div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80 mb-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-slate-900 text-lg">سجل افتقادات الأسرة</h3>
        <a href="visits.php?family_id=<?= $family_id ?>&action=new" class="text-xs font-bold text-church-blue hover:underline">
            + إضافة زيارة جديدة
        </a>
    </div>
    <div class="divide-y divide-slate-100">
        <?php if (empty($visits)): ?>
            <p class="text-slate-400 text-sm py-4 text-center">لا توجد زيارات افتقاد مسجلة لهذه الأسرة حتى الآن.</p>
        <?php else: ?>
            <?php foreach ($visits as $v): ?>
            <div class="py-3.5 flex items-center justify-between text-sm">
                <div>
                    <span class="font-bold text-slate-800"><?= format_date($v['visit_date']) ?></span>
                    <span class="text-xs text-slate-500 mr-2">الخادم: <?= htmlspecialchars(trim(($v['servant_first_name'] ?? '') . ' ' . ($v['servant_middle_name'] ?? '') . ' ' . ($v['servant_last_name'] ?? '')) ?: 'غير محدد') ?></span>
                    <?php if ($v['notes']): ?>
                        <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars($v['notes']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $v['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= $v['status'] === 'completed' ? 'منتهية' : 'معلقة' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add Member to Family -->
<div id="addMemberModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">إضافة فرد جديد لأسرة <?= htmlspecialchars($family['family_name']) ?></h3>
            <button onclick="document.getElementById('addMemberModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="family-details.php?id=<?= $family_id ?>" class="space-y-4 pt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="add_member" value="1">

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الأول *</label>
                    <input type="text" name="first_name" required placeholder="الاسم" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الأب</label>
                    <input type="text" name="middle_name" placeholder="الأب" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اللقب</label>
                    <input type="text" name="last_name" placeholder="اللقب" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">صلة القرابة *</label>
                    <select name="relationship_type" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="father">أب / عائل الأسرة</option>
                        <option value="mother">أم / زوجة</option>
                        <option value="son" selected>ابن</option>
                        <option value="daughter">ابنة</option>
                        <option value="grandfather">جد</option>
                        <option value="grandmother">جدة</option>
                        <option value="relative">قريب</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">النوع</label>
                    <select name="gender" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                    <input type="date" name="dob" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم التليفون</label>
                    <input type="text" name="phone" placeholder="01..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المرحلة التعليمية</label>
                    <select name="education_stage" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="">اختر المرحلة</option>
                        <option value="nursery">حضانة</option>
                        <option value="angels">ملائكة</option>
                        <option value="primary_1_2">ابتدائي (1-2)</option>
                        <option value="primary_3_4">ابتدائي (3-4)</option>
                        <option value="primary_5_6">ابتدائي (5-6)</option>
                        <option value="preparatory">إعدادي</option>
                        <option value="secondary">ثانوي</option>
                        <option value="university">جامعة</option>
                        <option value="graduate">خريج</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">أب الاعتراف</label>
                    <input type="text" name="confession_father" placeholder="أبونا..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    <i class="fas fa-calendar-day ml-1 text-church-gold"></i>
                    الإجازة الأسبوعية (يمكنك اختيار أكثر من يوم):
                </label>
                <?= render_weekly_leave_picker('weekly_leave', []) ?>
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('addMemberModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
                    إلغاء
                </button>
                <button type="submit" class="px-5 py-2 bg-church-navy text-white rounded-xl text-sm font-bold hover:bg-church-blue">
                    إضافة الفرد
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
