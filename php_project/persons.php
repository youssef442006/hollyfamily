<?php
define('PAGE_TITLE', 'سجل المخدومين والشعب');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

// Handle Delete Person (POST only + CSRF protected + Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && has_role(['admin', 'priest'])) {
    require_csrf();
    $del_id = (int)$_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("UPDATE persons SET is_active = 0 WHERE id = ?");
        $stmt->execute([$del_id]);
        record_audit($pdo, 'delete', 'persons', $del_id);
        set_flash('success', 'تم حذف الفرد بنجاح.');
    } catch (Exception $e) {
        set_flash('error', 'حدث خطأ أثناء حذف الفرد: ' . $e->getMessage());
    }
    header("Location: persons.php");
    exit;
}

// Handle Add / Edit Person POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $family_id = !empty($_POST['family_id']) ? (int)$_POST['family_id'] : null;
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $marital_status = !empty($_POST['marital_status']) ? $_POST['marital_status'] : null;
    $education_stage = !empty($_POST['education_stage']) ? $_POST['education_stage'] : null;
    $qualification = trim($_POST['qualification'] ?? '') ?: null;
    $occupation = trim($_POST['occupation'] ?? '');
    $confession_father = trim($_POST['confession_father'] ?? '');
    $weekly_leave = !empty($_POST['weekly_leave']) ? implode(',', (array)$_POST['weekly_leave']) : null;
    $is_deacon = isset($_POST['is_deacon']) ? 1 : 0;
    $is_servant = isset($_POST['is_servant']) ? 1 : 0;
    $in_prep_center = isset($_POST['in_prep_center']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($first_name)) {
        set_flash('error', 'يرجى إدخال الاسم الأول على الأقل.');
    } else {
        if ($id) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE persons SET
                    family_id = ?, first_name = ?, middle_name = ?, last_name = ?, gender = ?,
                    date_of_birth = ?, phone = ?, whatsapp = ?, national_id = ?, marital_status = ?,
                    education_stage = ?, qualification = ?, occupation = ?, confession_father = ?, weekly_leave = ?, is_deacon = ?,
                    is_servant = ?, in_prep_center = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $family_id, $first_name, $middle_name, $last_name, $gender,
                $dob, $phone, $whatsapp, $national_id ?: null, $marital_status,
                $education_stage, $qualification, $occupation, $confession_father, $weekly_leave, $is_deacon,
                $is_servant, $in_prep_center, $notes, $id
            ]);
            record_audit($pdo, 'update', 'persons', $id);
            set_flash('success', 'تم تعديل بيانات الفرد بنجاح.');
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO persons (
                    family_id, first_name, middle_name, last_name, gender,
                    date_of_birth, phone, whatsapp, national_id, marital_status,
                    education_stage, qualification, occupation, confession_father, weekly_leave, is_deacon,
                    is_servant, in_prep_center, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $family_id, $first_name, $middle_name, $last_name, $gender,
                $dob, $phone, $whatsapp, $national_id ?: null, $marital_status,
                $education_stage, $qualification, $occupation, $confession_father, $weekly_leave, $is_deacon,
                $is_servant, $in_prep_center, $notes
            ]);
            $new_id = $pdo->lastInsertId();
            record_audit($pdo, 'create', 'persons', $new_id);
            set_flash('success', 'تمت إضافة الفرد بنجاح.');
        }
        header("Location: persons.php");
        exit;
    }
}

// Search and Filters
$search = trim($_GET['q'] ?? '');
$stage_filter = $_GET['stage'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';

$sql = "
    SELECT p.*, f.family_name, f.family_code
    FROM persons p
    LEFT JOIN families f ON p.family_id = f.id
    WHERE p.is_active = 1 AND p.is_archived = 0
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.first_name LIKE ? OR p.middle_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.national_id LIKE ? OR p.confession_father LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}

if (!empty($stage_filter)) {
    $sql .= " AND p.education_stage = ?";
    $params[] = $stage_filter;
}

if (!empty($gender_filter)) {
    $sql .= " AND p.gender = ?";
    $params[] = $gender_filter;
}

if ($role_filter === 'deacon') {
    $sql .= " AND p.is_deacon = 1";
} elseif ($role_filter === 'servant') {
    $sql .= " AND p.is_servant = 1";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$persons = $stmt->fetchAll();

// Fetch Families for Select dropdown
$families = $pdo->query("SELECT id, family_name, family_code FROM families WHERE is_active = 1 ORDER BY family_name ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Title & Action Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">سجل المخدومين والشعب</h1>
        <p class="text-xs text-slate-500 mt-1">البحث في قاعدة بيانات أفراد الكنيسة، المراحل الدراسية، الخدام والشمامسة</p>
    </div>
    <div class="flex items-center space-x-2 space-x-reverse">
        <button onclick="openPersonModal()" class="inline-flex items-center px-4 py-2.5 rounded-xl gold-gradient text-church-navy font-bold text-sm shadow-md hover:brightness-105 transition">
            <i class="fas fa-user-plus ml-2"></i>
            إضافة فرد جديد
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 mb-6">
    <form method="GET" action="persons.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
        <div class="md:col-span-2 relative">
            <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fas fa-search text-sm"></i>
            </div>
            <input 
                type="text" 
                name="q" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="ابحث بالاسم، التليفون، الرقم القومي، أب الاعتراف..."
                class="w-full pr-10 pl-4 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-[#c9a44c]"
            >
        </div>

        <div>
            <select name="stage" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                <option value="">جميع المراحل</option>
                <option value="nursery" <?= $stage_filter === 'nursery' ? 'selected' : '' ?>>حضانة مبكرة</option>
                <option value="angels" <?= $stage_filter === 'angels' ? 'selected' : '' ?>>ملائكة</option>
                <option value="primary_1_2" <?= $stage_filter === 'primary_1_2' ? 'selected' : '' ?>>ابتدائي (1-2)</option>
                <option value="primary_3_4" <?= $stage_filter === 'primary_3_4' ? 'selected' : '' ?>>ابتدائي (3-4)</option>
                <option value="primary_5_6" <?= $stage_filter === 'primary_5_6' ? 'selected' : '' ?>>ابتدائي (5-6)</option>
                <option value="preparatory" <?= $stage_filter === 'preparatory' ? 'selected' : '' ?>>إعدادي</option>
                <option value="secondary" <?= $stage_filter === 'secondary' ? 'selected' : '' ?>>ثانوي</option>
                <option value="university" <?= $stage_filter === 'university' ? 'selected' : '' ?>>جامعة</option>
                <option value="graduate" <?= $stage_filter === 'graduate' ? 'selected' : '' ?>>خريجين</option>
            </select>
        </div>

        <div>
            <select name="role_filter" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                <option value="">الكل (شعب وخدام)</option>
                <option value="servant" <?= $role_filter === 'servant' ? 'selected' : '' ?>>خدام فقط</option>
                <option value="deacon" <?= $role_filter === 'deacon' ? 'selected' : '' ?>>شمامسة فقط</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2 bg-church-navy text-white rounded-xl text-xs font-bold hover:bg-church-blue">
                تطبيق
            </button>
            <?php if (!empty($search) || !empty($stage_filter) || !empty($role_filter) || !empty($gender_filter)): ?>
                <a href="persons.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200 flex items-center">
                    مسح
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Persons Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase">
                    <th class="p-4">الاسم بالكامل</th>
                    <th class="p-4">العمر والمرحلة</th>
                    <th class="p-4">الأسرة</th>
                    <th class="p-4">أب الاعتراف</th>
                    <th class="p-4">التليفون</th>
                    <th class="p-4">الرتب والخدمة</th>
                    <th class="p-4 text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($persons)): ?>
                <tr>
                    <td colspan="7" class="p-10 text-center text-slate-400">
                        <i class="fas fa-users-slash text-3xl mb-3 block text-slate-300"></i>
                        لم يتم العثور على أفراد مطابقين لشروط البحث.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($persons as $p): 
                        $age = calc_age($p['date_of_birth']);
                    ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4">
                            <div class="font-bold text-slate-900">
                                <a href="person-details.php?id=<?= $p['id'] ?>" class="hover:text-church-blue transition">
                                    <?= htmlspecialchars($p['first_name'] . ' ' . $p['middle_name'] . ' ' . $p['last_name']) ?>
                                </a>
                            </div>
                            <span class="text-xs text-slate-400 block mt-0.5">
                                <?= $p['gender'] === 'female' ? 'أنثى' : 'ذكر' ?> 
                                <?= $p['occupation'] ? '• ' . htmlspecialchars($p['occupation']) : '' ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="font-bold text-slate-800 text-xs"><?= $age !== null ? $age . ' سنة' : '-' ?></span>
                            <span class="text-xs text-slate-500 block mt-0.5"><?= get_stage_name($p['education_stage']) ?></span>
                        </td>
                        <td class="p-4 text-xs font-medium">
                            <?php if ($p['family_name']): ?>
                                <a href="family-details.php?id=<?= $p['family_id'] ?>" class="text-church-blue hover:underline">
                                    <?= htmlspecialchars($p['family_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-slate-400">بدون أسرة</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-xs text-slate-700 font-medium">
                            <?= htmlspecialchars($p['confession_father'] ?: 'غير محدد') ?>
                        </td>
                        <td class="p-4 font-mono text-xs" dir="ltr">
                            <?php if ($p['phone']): ?>
                                <a href="tel:<?= htmlspecialchars($p['phone']) ?>" class="text-slate-700 hover:text-church-blue">
                                    <?= htmlspecialchars($p['phone']) ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap gap-1">
                                <?php if ($p['is_deacon']): ?>
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded text-[11px] font-bold">شماس</span>
                                <?php endif; ?>
                                <?php if ($p['is_servant']): ?>
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[11px] font-bold">خادم</span>
                                <?php endif; ?>
                                <?php if (!empty($p['in_prep_center'])): ?>
                                    <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[11px] font-bold">إعداد خدام</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <div class="inline-flex items-center space-x-1.5 space-x-reverse">
                                <a href="person-details.php?id=<?= $p['id'] ?>" title="الملف الشامل" class="p-2 text-church-blue hover:bg-blue-50 rounded-lg">
                                    <i class="fas fa-id-card"></i>
                                </a>
                                <button onclick='editPerson(<?= json_encode($p) ?>)' title="تعديل" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (has_role(['admin', 'priest'])): ?>
                                <button onclick="postDelete('persons.php', <?= $p['id'] ?>, '<?= csrf_token() ?>', 'هل أنت متأكد من حذف هذا الفرد؟')" title="حذف" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Add / Edit Person -->
<div id="personModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="pModalTitle" class="text-lg font-bold text-slate-900">إضافة فرد جديد</h3>
            <button onclick="closePersonModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="persons.php" class="space-y-4 pt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="p_id" value="">

            <input type="hidden" name="last_name" id="p_last" value="">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الأول / بالكامل *</label>
                    <input type="text" name="first_name" id="p_first" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الأب (إن وجد)</label>
                    <input type="text" name="middle_name" id="p_mid" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الأسرة التابع لها</label>
                    <select name="family_id" id="p_family" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="">بدون أسرة (مستقل)</option>
                        <?php foreach ($families as $fam): ?>
                            <option value="<?= $fam['id'] ?>"><?= htmlspecialchars($fam['family_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">النوع *</label>
                    <select name="gender" id="p_gender" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                <input type="date" name="dob" id="p_dob" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الموبايل الأساسي</label>
                    <input type="text" name="phone" id="p_phone" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الواتساب</label>
                    <input type="text" name="whatsapp" id="p_wa" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المرحلة التعليمية</label>
                    <select name="education_stage" id="p_stage" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="">اختر المرحلة</option>
                        <option value="nursery">حضانة مبكرة</option>
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
                    <label class="block text-xs font-bold text-slate-700 mb-1">المهنة / العمل</label>
                    <input type="text" name="occupation" id="p_occ" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">المؤهل الدراسي / الكلية</label>
                <input type="text" name="qualification" id="p_qual" placeholder="مثال: بكالوريوس تجارة، ليسانس آداب، دبلوم..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">أب الاعتراف</label>
                <input type="text" name="confession_father" id="p_conf" placeholder="أبونا..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    <i class="fas fa-calendar-day ml-1 text-church-gold"></i>
                    الإجازة الأسبوعية (يمكنك اختيار أكثر من يوم):
                </label>
                <?= render_weekly_leave_picker('weekly_leave', []) ?>
            </div>

            <div class="flex items-center space-x-6 space-x-reverse pt-2">
                <label class="flex items-center space-x-2 space-x-reverse cursor-pointer">
                    <input type="checkbox" name="is_deacon" id="p_deacon" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                    <span class="text-xs font-bold text-slate-700">شماس</span>
                </label>
                <label class="flex items-center space-x-2 space-x-reverse cursor-pointer">
                    <input type="checkbox" name="is_servant" id="p_servant" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                    <span class="text-xs font-bold text-slate-700">خادم في الكنيسة</span>
                </label>
                <label class="flex items-center space-x-2 space-x-reverse cursor-pointer">
                    <input type="checkbox" name="in_prep_center" id="p_prep" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                    <span class="text-xs font-bold text-slate-700">في إعداد خدام</span>
                </label>
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-3 border-t border-slate-100">
                <button type="button" onclick="closePersonModal()" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
                    إلغاء
                </button>
                <button type="submit" class="px-5 py-2 bg-church-navy text-white rounded-xl text-sm font-bold hover:bg-church-blue">
                    حفظ البيانات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPersonModal() {
        document.getElementById('pModalTitle').textContent = 'إضافة فرد جديد';
        document.getElementById('p_id').value = '';
        document.getElementById('p_first').value = '';
        document.getElementById('p_mid').value = '';
        document.getElementById('p_last').value = '';
        document.getElementById('p_family').value = '';
        document.getElementById('p_gender').value = 'male';
        document.getElementById('p_dob').value = '';
        document.getElementById('p_phone').value = '';
        document.getElementById('p_wa').value = '';
        document.getElementById('p_stage').value = '';
        document.getElementById('p_occ').value = '';
        document.getElementById('p_conf').value = '';
        document.getElementById('p_deacon').checked = false;
        document.getElementById('p_servant').checked = false;
        document.querySelectorAll('input[name="weekly_leave[]"]').forEach(cb => cb.checked = false);
        document.getElementById('personModal').classList.remove('hidden');
    }

    function editPerson(p) {
        document.getElementById('pModalTitle').textContent = ('تعديل بيانات: ' + p.first_name + ' ' + (p.middle_name || '') + ' ' + (p.last_name || '')).trim();
        document.getElementById('p_id').value = p.id || '';
        document.getElementById('p_first').value = p.first_name || '';
        document.getElementById('p_mid').value = p.middle_name || '';
        document.getElementById('p_last').value = p.last_name || '';
        document.getElementById('p_family').value = p.family_id || '';
        document.getElementById('p_gender').value = p.gender || 'male';
        document.getElementById('p_dob').value = p.date_of_birth || '';
        document.getElementById('p_phone').value = p.phone || '';
        document.getElementById('p_wa').value = p.whatsapp || '';
        document.getElementById('p_stage').value = p.education_stage || '';
        document.getElementById('p_qual').value = p.qualification || '';
        document.getElementById('p_occ').value = p.occupation || '';
        document.getElementById('p_conf').value = p.confession_father || '';
        document.getElementById('p_deacon').checked = (p.is_deacon == 1);
        document.getElementById('p_servant').checked = (p.is_servant == 1);
        document.getElementById('p_prep').checked = (p.in_prep_center == 1);

        // Weekly leave days
        const leaves = (p.weekly_leave || '').split(',').map(s => s.trim());
        document.querySelectorAll('input[name="weekly_leave[]"]').forEach(cb => {
            cb.checked = leaves.includes(cb.value);
        });

        document.getElementById('personModal').classList.remove('hidden');
    }

    function closePersonModal() {
        document.getElementById('personModal').classList.add('hidden');
    }

    <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
        window.addEventListener('DOMContentLoaded', openPersonModal);
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
