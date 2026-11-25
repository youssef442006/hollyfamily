<?php
define('PAGE_TITLE', 'سجل الأسر والعائلات');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

// Handle Delete Family (POST only + CSRF protected + Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && has_role(['admin', 'priest'])) {
    require_csrf();
    $del_id = (int)$_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("UPDATE families SET is_active = 0 WHERE id = ?");
        $stmt->execute([$del_id]);
        record_audit($pdo, 'delete', 'families', $del_id);
        set_flash('success', 'تم حذف الأسرة بنجاح.');
    } catch (Exception $e) {
        set_flash('error', 'حدث خطأ أثناء حذف الأسرة: ' . $e->getMessage());
    }
    header("Location: families.php");
    exit;
}

// Handle Add / Edit Family POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $family_code = trim($_POST['family_code'] ?? '');
        $family_name = trim($_POST['family_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $building_number = trim($_POST['building_number'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $apartment_number = trim($_POST['apartment_number'] ?? '');
        $special_case = trim($_POST['special_case'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Auto generate code if empty
        if (empty($family_code)) {
            $stmt = $pdo->query("SELECT MAX(id) FROM families");
            $max_id = (int)$stmt->fetchColumn() + 1;
            $family_code = 'FAM-' . str_pad($max_id, 3, '0', STR_PAD_LEFT);
        }

        if (empty($family_name)) {
            set_flash('error', 'يرجى إدخال اسم الأسرة.');
        } else {
            if ($id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE families 
                    SET family_code = ?, family_name = ?, phone = ?, address = ?, street = ?, 
                        building_number = ?, floor = ?, apartment_number = ?, special_case = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$family_code, $family_name, $phone, $address, $street, $building_number, $floor, $apartment_number, $special_case, $notes, $id]);
                record_audit($pdo, 'update', 'families', $id);
                set_flash('success', 'تم تحديث بيانات الأسرة بنجاح.');
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO families (family_code, family_name, phone, address, street, building_number, floor, apartment_number, special_case, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$family_code, $family_name, $phone, $address, $street, $building_number, $floor, $apartment_number, $special_case, $notes]);
                $new_id = $pdo->lastInsertId();
                record_audit($pdo, 'create', 'families', $new_id);
                set_flash('success', 'تمت إضافة الأسرة الجديدة بنجاح.');
            }
            header("Location: families.php");
            exit;
        }
    }
}

// Search and Filter
$search = trim($_GET['q'] ?? '');
$sql = "
    SELECT f.*, 
           (SELECT COUNT(*) FROM family_members WHERE family_id = f.id AND is_deleted = 0) AS members_count,
           (SELECT CONCAT(p.first_name, ' ', IFNULL(p.last_name, '')) 
            FROM family_members fm 
            JOIN persons p ON fm.person_id = p.id 
            WHERE fm.family_id = f.id AND fm.is_head = 1 AND fm.is_deleted = 0 LIMIT 1) AS head_name
    FROM families f
    WHERE f.is_active = 1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (f.family_name LIKE ? OR f.family_code LIKE ? OR f.phone LIKE ? OR f.address LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
}

$sql .= " ORDER BY f.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$families = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Page Title & Actions -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">سجل الأسر والعائلات</h1>
        <p class="text-xs text-slate-500 mt-1">إدارة بيانات الأسر، أفراد كل عائلة، ومتابعة الافتقاد والعناوين</p>
    </div>
    <div class="flex items-center space-x-2.5 space-x-reverse">
        <a href="family-wizard.php" class="inline-flex items-center px-5 py-2.5 rounded-xl gold-gradient text-church-navy font-extrabold text-sm shadow-md hover:brightness-105 transition">
            <i class="fas fa-plus-circle ml-2 text-base"></i>
            إضافة أسرة جديدة
        </a>
    </div>
</div>

<!-- Search Bar -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 mb-6">
    <form method="GET" action="families.php" class="flex gap-3">
        <div class="relative flex-1">
            <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fas fa-search text-sm"></i>
            </div>
            <input 
                type="text" 
                name="q" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="ابحث باسم الأسرة، كود الأسرة، التليفون، أو العنوان..."
                class="w-full pr-10 pl-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#c9a44c]"
            >
        </div>
        <button type="submit" class="px-5 py-2.5 bg-church-navy text-white rounded-xl text-sm font-semibold hover:bg-church-blue transition">
            بحث
        </button>
        <?php if (!empty($search)): ?>
            <a href="families.php" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200 transition flex items-center">
                إلغاء الفلتر
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Families Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                    <th class="p-4">اسم الأسرة</th>
                    <th class="p-4">عائل الأسرة</th>
                    <th class="p-4 text-center">عدد الأفراد</th>
                    <th class="p-4">العنوان</th>
                    <th class="p-4">التليفون</th>
                    <th class="p-4 text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($families)): ?>
                <tr>
                    <td colspan="6" class="p-10 text-center text-slate-400 font-medium">
                        <i class="fas fa-folder-open text-3xl mb-3 block text-slate-300"></i>
                        لم يتم العثور على أسر مسجلة مطابقة للبحث.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($families as $f): ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4 font-bold text-slate-900">
                            <a href="family-details.php?id=<?= $f['id'] ?>" class="hover:text-church-blue transition">
                                <?= htmlspecialchars($f['family_name']) ?>
                            </a>
                            <?php if (!empty($f['special_case']) && $f['special_case'] !== 'عادية'): ?>
                                <span class="inline-block mr-2 px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[11px] font-semibold">
                                    <?= htmlspecialchars($f['special_case']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-slate-700 font-medium">
                            <?= htmlspecialchars($f['head_name'] ?: 'غير محدد') ?>
                        </td>
                        <td class="p-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-church-navy">
                                <?= $f['members_count'] ?> أفراد
                            </span>
                        </td>
                        <td class="p-4 text-slate-600 text-xs">
                            <?= htmlspecialchars($f['address'] ?: ($f['street'] ?: '-')) ?>
                        </td>
                        <td class="p-4 font-mono text-slate-700 text-xs" dir="ltr">
                            <?= htmlspecialchars($f['phone'] ?: '-') ?>
                        </td>
                        <td class="p-4 text-center">
                            <div class="inline-flex items-center space-x-1.5 space-x-reverse">
                                <a href="family-details.php?id=<?= $f['id'] ?>" title="عرض التفاصيل والأفراد" class="p-2 text-church-blue hover:bg-blue-50 rounded-lg transition">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick='editFamily(<?= json_encode($f) ?>)' title="تعديل الأسرة" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (has_role(['admin', 'priest'])): ?>
                                <button onclick="postDelete('families.php', <?= $f['id'] ?>, '<?= csrf_token() ?>', 'هل أنت متأكد من حذف هذه الأسرة؟')" title="حذف" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition">
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

<!-- Modal for Quick Add / Edit Family -->
<div id="familyModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="modalTitle" class="text-lg font-bold text-slate-900">إضافة أسرة جديدة</h3>
            <button onclick="closeFamilyModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="families.php" class="space-y-4 pt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="fam_id" value="">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">كود الأسرة (تلقائي)</label>
                    <input type="text" name="family_code" id="fam_code" placeholder="FAM-001" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الأسرة *</label>
                    <input type="text" name="family_name" id="fam_name" required placeholder="أسرة..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم التليفون</label>
                    <input type="text" name="phone" id="fam_phone" placeholder="01..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">حالة خاصة</label>
                    <input type="text" name="special_case" id="fam_special" placeholder="عادية / تحتاج رعاية" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">المنطقة / الشارع</label>
                <input type="text" name="address" id="fam_address" placeholder="شبرا، شارع..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم العمارة</label>
                    <input type="text" name="building_number" id="fam_bld" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الدور</label>
                    <input type="text" name="floor" id="fam_floor" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الشقة</label>
                    <input type="text" name="apartment_number" id="fam_apt" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">ملاحظات إضافية</label>
                <textarea name="notes" id="fam_notes" rows="2" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-2">
                <button type="button" onclick="closeFamilyModal()" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
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
    function openFamilyModal() {
        document.getElementById('modalTitle').textContent = 'إضافة أسرة جديدة';
        document.getElementById('fam_id').value = '';
        document.getElementById('fam_code').value = '';
        document.getElementById('fam_name').value = '';
        document.getElementById('fam_phone').value = '';
        document.getElementById('fam_address').value = '';
        document.getElementById('fam_bld').value = '';
        document.getElementById('fam_floor').value = '';
        document.getElementById('fam_apt').value = '';
        document.getElementById('fam_special').value = '';
        document.getElementById('fam_notes').value = '';
        document.getElementById('familyModal').classList.remove('hidden');
    }

    function editFamily(data) {
        document.getElementById('modalTitle').textContent = 'تعديل بيانات الأسرة: ' + data.family_name;
        document.getElementById('fam_id').value = data.id || '';
        document.getElementById('fam_code').value = data.family_code || '';
        document.getElementById('fam_name').value = data.family_name || '';
        document.getElementById('fam_phone').value = data.phone || '';
        document.getElementById('fam_address').value = data.address || '';
        document.getElementById('fam_bld').value = data.building_number || '';
        document.getElementById('fam_floor').value = data.floor || '';
        document.getElementById('fam_apt').value = data.apartment_number || '';
        document.getElementById('fam_special').value = data.special_case || '';
        document.getElementById('fam_notes').value = data.notes || '';
        document.getElementById('familyModal').classList.remove('hidden');
    }

    function closeFamilyModal() {
        document.getElementById('familyModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
