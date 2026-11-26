<?php
define('PAGE_TITLE', 'تسجيل زواج وتكوين أسرة جديدة');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_permission('families_edit');
$user = current_user();

$person_id = (int)($_GET['person_id'] ?? $_POST['person_id'] ?? 0);
if (!$person_id) {
    header("Location: persons.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, f.family_name AS old_family_name, f.id AS old_family_id
    FROM persons p
    LEFT JOIN families f ON p.family_id = f.id
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$person_id]);
$person = $stmt->fetch();

if (!$person) {
    set_flash('error', 'لم يتم العثور على الفرد المطلوب.');
    header("Location: persons.php");
    exit;
}

$person_full_name = trim($person['first_name'] . ' ' . ($person['middle_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
$is_groom = ($person['gender'] === 'male');
$opposite_gender = $is_groom ? 'female' : 'male';

// قائمة المرشحين من الجنس الآخر داخل الكنيسة (غير المتوفين)
$stmt = $pdo->prepare("
    SELECT p.id, p.first_name, p.middle_name, p.last_name, p.phone, p.date_of_birth, p.marital_status, f.family_name
    FROM persons p
    LEFT JOIN families f ON p.family_id = f.id
    WHERE p.gender = ? AND p.id != ? AND p.is_active = 1 AND p.is_deceased = 0
    ORDER BY (p.marital_status = 'married') ASC, p.first_name ASC
");
$stmt->execute([$opposite_gender, $person_id]);
$candidates = $stmt->fetchAll();

// معالجة حفظ الزواج وتكوين الأسرة الجديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $partner_mode = $_POST['partner_mode'] ?? 'internal'; // internal | external
    $marriage_date = !empty($_POST['marriage_date']) ? $_POST['marriage_date'] : date('Y-m-d');
    $family_name_input = trim($_POST['family_name'] ?? '');

    $area_choice = trim($_POST['area_choice'] ?? '');
    $area_custom = trim($_POST['area_custom'] ?? '');
    $area = ($area_choice === 'other') ? $area_custom : $area_choice;
    $street = trim($_POST['street'] ?? '');
    $building_number = trim($_POST['building_number'] ?? '');
    $floor = trim($_POST['floor'] ?? '');
    $apartment_number = trim($_POST['apartment_number'] ?? '');
    $family_phone = trim($_POST['phone'] ?? '');

    $partner_id = null;
    $partner_full_name = '';

    try {
        $pdo->beginTransaction();

        if ($partner_mode === 'internal') {
            $partner_id = (int)($_POST['partner_person_id'] ?? 0);
            if (!$partner_id) {
                throw new Exception('يرجى اختيار شريك الحياة من القائمة.');
            }
            $pstmt = $pdo->prepare("SELECT * FROM persons WHERE id = ? AND is_active = 1");
            $pstmt->execute([$partner_id]);
            $partner_row = $pstmt->fetch();
            if (!$partner_row) {
                throw new Exception('الشخص المختار غير موجود.');
            }
            $partner_full_name = trim($partner_row['first_name'] . ' ' . ($partner_row['middle_name'] ?? '') . ' ' . ($partner_row['last_name'] ?? ''));
        } else {
            // شريك من خارج السيستم -> إضافته الآن
            $ext_name = trim($_POST['ext_name'] ?? '');
            if (empty($ext_name)) {
                throw new Exception('يرجى إدخال اسم شريك الحياة.');
            }
            $ext_phone = trim($_POST['ext_phone'] ?? '');
            $ext_dob = !empty($_POST['ext_dob']) ? $_POST['ext_dob'] : null;
            $ext_job = trim($_POST['ext_job'] ?? '');
            $ext_confession = trim($_POST['ext_confession'] ?? '');
            $ext_church = trim($_POST['ext_church'] ?? '');

            $ins = $pdo->prepare("
                INSERT INTO persons (first_name, last_name, gender, date_of_birth, phone, occupation, confession_father, confession_church, marital_status, education_stage, is_active)
                VALUES (?, '', ?, ?, ?, ?, ?, ?, 'married', 'graduate', 1)
            ");
            $ins->execute([$ext_name, $opposite_gender, $ext_dob, $ext_phone, $ext_job, $ext_confession, $ext_church]);
            $partner_id = (int)$pdo->lastInsertId();
            $partner_full_name = $ext_name;
        }

        $husband_id   = $is_groom ? $person_id : $partner_id;
        $wife_id      = $is_groom ? $partner_id : $person_id;
        $husband_name = $is_groom ? $person_full_name : $partner_full_name;

        // اسم الأسرة الجديدة يتولد تلقائياً من اسم الزوج إذا تُرك فارغاً
        $new_family_name = !empty($family_name_input) ? $family_name_input : ('أسرة ' . $husband_name);

        // كود تلقائي للأسرة
        $next_id = (int)$pdo->query("SELECT MAX(id) FROM families")->fetchColumn() + 1;
        $family_code = 'FAM-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        $full_address = trim($area . ($street ? '، ' . $street : ''));

        // 1. إنشاء الأسرة الجديدة
        $fstmt = $pdo->prepare("
            INSERT INTO families (family_code, family_name, area, street, address, building_number, floor, apartment_number, phone, special_case)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'عادية')
        ");
        $fstmt->execute([$family_code, $new_family_name, $area, $street, $full_address, $building_number, $floor, $apartment_number, $family_phone]);
        $new_family_id = (int)$pdo->lastInsertId();

        // 2. إنهاء العضوية الحالية في أسرة الأهل مع الإبقاء على السجل التاريخي (is_current = 0)
        $pdo->prepare("UPDATE family_members SET is_current = 0, end_date = ? WHERE person_id IN (?, ?) AND is_current = 1")
            ->execute([$marriage_date, $husband_id, $wife_id]);

        // 3. تحديث الأسرة الحالية والحالة الاجتماعية للزوجين
        $pdo->prepare("UPDATE persons SET family_id = ?, marital_status = 'married' WHERE id IN (?, ?)")
            ->execute([$new_family_id, $husband_id, $wife_id]);

        // 4. ربط الزوج والزوجة بالأسرة الجديدة
        $pdo->prepare("
            INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, start_date, is_current)
            VALUES (?, ?, 'husband', 1, 0, 0, ?, 1)
        ")->execute([$new_family_id, $husband_id, $marriage_date]);

        $pdo->prepare("
            INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, start_date, is_current)
            VALUES (?, ?, 'wife', 0, 1, 0, ?, 1)
        ")->execute([$new_family_id, $wife_id, $marriage_date]);

        // 5. تسجيل علاقة الزواج المباشرة
        $pdo->prepare("
            INSERT INTO person_relationships (person_id, related_person_id, relationship_type, start_date)
            VALUES (?, ?, 'husband', ?)
        ")->execute([$husband_id, $wife_id, $marriage_date]);

        $pdo->commit();
        record_audit($pdo, 'create', 'families', $new_family_id, null, "تكوين أسرة بالزواج: $new_family_name");
        set_flash('success', "مبارك! تم تسجيل الزواج وفتح ($new_family_name) بنجاح مع حفظ صلة القرابة بأسرة الأهل.");
        header("Location: family-details.php?id=$new_family_id");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<datalist id="confessionFathersList">
    <option value="أبونا سارافيم">
    <option value="أبونا جوزيف">
</datalist>
<datalist id="churchesList">
    <option value="كنيسة العائلة المقدسة">
</datalist>

<div class="w-full max-w-4xl mx-auto mb-12">
    <div class="mb-6">
        <a href="person-details.php?id=<?= $person_id ?>" class="text-xs text-slate-500 hover:text-church-navy font-semibold inline-flex items-center mb-1">
            <i class="fas fa-arrow-right ml-1"></i> العودة لملف <?= htmlspecialchars($person_full_name) ?>
        </a>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center">
            <i class="fas fa-rings-wedding ml-2.5 text-church-gold"></i>
            تسجيل زواج وتكوين أسرة جديدة
        </h1>
        <p class="text-xs text-slate-500 mt-1">
            يتم ربط الزوجين في أسرة جديدة مستقلة مع الحفاظ على سجلهم التاريخي وصلة قرابتهم بأسر أهلهم
        </p>
    </div>

    <form method="POST" action="marriage.php?person_id=<?= $person_id ?>" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="person_id" value="<?= $person_id ?>">

        <!-- 1. الطرف الأول -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
            <h2 class="text-sm font-bold text-church-navy mb-3 pb-2 border-b border-slate-100">
                1. <?= $is_groom ? 'العريس (من الكنيسة)' : 'العروس (من الكنيسة)' ?>
            </h2>
            <div class="flex flex-wrap items-center justify-between gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
                <div>
                    <p class="text-base font-extrabold text-slate-900"><?= htmlspecialchars($person_full_name) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <?= $person['old_family_name'] ? 'الأسرة الحالية: ' . htmlspecialchars($person['old_family_name']) : 'غير مرتبط بأسرة' ?>
                        <?= $person['phone'] ? ' • ' . htmlspecialchars($person['phone']) : '' ?>
                    </p>
                </div>
                <span class="px-3 py-1 bg-amber-100 text-amber-900 rounded-full text-xs font-bold">
                    <?= $is_groom ? '👨 الزوج' : '👰 الزوجة' ?>
                </span>
            </div>
        </div>

        <!-- 2. شريك الحياة -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
            <h2 class="text-sm font-bold text-church-navy mb-4 pb-2 border-b border-slate-100">
                2. اختيار <?= $is_groom ? 'العروس (الزوجة)' : 'العريس (الزوج)' ?>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                <label class="flex items-center p-3.5 rounded-2xl border-2 border-church-gold bg-amber-50/40 cursor-pointer" id="lblInternal">
                    <input type="radio" name="partner_mode" value="internal" checked onchange="togglePartnerMode('internal')" class="w-4 h-4 text-church-navy">
                    <div class="mr-3">
                        <p class="text-xs font-extrabold text-slate-900">مسجل/ة داخل الكنيسة</p>
                        <p class="text-[11px] text-slate-500">اختيار من قاعدة بيانات الشعب</p>
                    </div>
                </label>
                <label class="flex items-center p-3.5 rounded-2xl border-2 border-slate-200 bg-slate-50/60 cursor-pointer" id="lblExternal">
                    <input type="radio" name="partner_mode" value="external" onchange="togglePartnerMode('external')" class="w-4 h-4 text-church-navy">
                    <div class="mr-3">
                        <p class="text-xs font-extrabold text-slate-900">من خارج السيستم</p>
                        <p class="text-[11px] text-slate-500">إدخال بيانات شريك الحياة الآن</p>
                    </div>
                </label>
            </div>

            <!-- شريك من داخل السيستم -->
            <div id="internalPartnerBox" class="space-y-3">
                <input type="text" id="partnerSearch" oninput="filterPartners(this.value)" placeholder="🔍 ابحث بالاسم أو رقم الموبايل..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                <select name="partner_person_id" id="partnerSelect" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="">— اختر <?= $is_groom ? 'العروس' : 'العريس' ?> من القائمة —</option>
                    <?php foreach ($candidates as $cand): 
                        $cname = trim($cand['first_name'] . ' ' . ($cand['middle_name'] ?? '') . ' ' . ($cand['last_name'] ?? ''));
                    ?>
                        <option value="<?= $cand['id'] ?>">
                            <?= htmlspecialchars($cname) ?>
                            <?= $cand['family_name'] ? ' — (' . htmlspecialchars($cand['family_name']) . ')' : '' ?>
                            <?= $cand['phone'] ? ' [' . htmlspecialchars($cand['phone']) . ']' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- شريك من خارج السيستم -->
            <div id="externalPartnerBox" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم بالكامل *</label>
                    <input type="text" name="ext_name" id="extNameInput" placeholder="الاسم ثلاثي أو رباعي" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الموبايل</label>
                    <input type="text" name="ext_phone" placeholder="01..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                    <input type="date" name="ext_dob" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المهنة / العمل</label>
                    <input type="text" name="ext_job" placeholder="المهنة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">أب الاعتراف</label>
                    <input type="text" name="ext_confession" list="confessionFathersList" oninput="if(this.value.includes('سارافيم')||this.value.includes('جوزيف')) document.getElementById('extChurch').value='كنيسة العائلة المقدسة'" placeholder="أبونا سارافيم / أبونا جوزيف..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">كنيسة أب الاعتراف</label>
                    <input type="text" name="ext_church" id="extChurch" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>
        </div>

        <!-- 3. بيانات الأسرة والسكن الجديد -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
            <h2 class="text-sm font-bold text-church-navy mb-4 pb-2 border-b border-slate-100">
                3. بيانات الأسرة والسكن الجديد
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الأسرة (اختياري — يتولد من اسم الزوج)</label>
                    <input type="text" name="family_name" placeholder="يتولد تلقائياً: أسرة الزوج" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الإكليل / الزواج</label>
                    <input type="date" name="marriage_date" value="<?= date('Y-m-d') ?>" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تليفون المنزل الجديد</label>
                    <input type="text" name="phone" placeholder="01... أو أرضي" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المنطقة</label>
                    <select name="area_choice" onchange="document.getElementById('mCustomArea').classList.toggle('hidden', this.value !== 'other')" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                        <option value="عزبة البكري">عزبة البكري</option>
                        <option value="أرض الدلتا 1">أرض الدلتا 1</option>
                        <option value="أرض الدلتا 2">أرض الدلتا 2</option>
                        <option value="الخزف والصيني">الخزف والصيني</option>
                        <option value="بطن الجبل">بطن الجبل</option>
                        <option value="أرض أطلس">أرض أطلس</option>
                        <option value="other">منطقة أخرى...</option>
                    </select>
                </div>
                <div id="mCustomArea" class="hidden">
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم المنطقة المخصصة</label>
                    <input type="text" name="area_custom" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الشارع والعنوان</label>
                    <input type="text" name="street" placeholder="الشارع والعلامة المميزة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">عمارة / دور / شقة</label>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" name="building_number" placeholder="عمارة" class="p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-center">
                        <input type="text" name="floor" placeholder="دور" class="p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-center">
                        <input type="text" name="apartment_number" placeholder="شقة" class="p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-center">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 space-x-reverse">
            <a href="person-details.php?id=<?= $person_id ?>" class="px-5 py-2.5 bg-slate-200 text-slate-700 font-bold rounded-xl text-sm">إلغاء</a>
            <button type="submit" class="px-7 py-3 gold-gradient text-church-navy font-extrabold rounded-xl text-sm shadow-md hover:brightness-105 transition">
                <i class="fas fa-check-circle ml-1.5"></i>
                تأكيد الزواج وفتح الأسرة الجديدة
            </button>
        </div>
    </form>
</div>

<script>
    function togglePartnerMode(mode) {
        const intBox = document.getElementById('internalPartnerBox');
        const extBox = document.getElementById('externalPartnerBox');
        const lblInt = document.getElementById('lblInternal');
        const lblExt = document.getElementById('lblExternal');
        const extName = document.getElementById('extNameInput');

        if (mode === 'internal') {
            intBox.classList.remove('hidden');
            extBox.classList.add('hidden');
            extName.required = false;
            lblInt.className = 'flex items-center p-3.5 rounded-2xl border-2 border-church-gold bg-amber-50/40 cursor-pointer';
            lblExt.className = 'flex items-center p-3.5 rounded-2xl border-2 border-slate-200 bg-slate-50/60 cursor-pointer';
        } else {
            intBox.classList.add('hidden');
            extBox.classList.remove('hidden');
            extName.required = true;
            lblExt.className = 'flex items-center p-3.5 rounded-2xl border-2 border-church-gold bg-amber-50/40 cursor-pointer';
            lblInt.className = 'flex items-center p-3.5 rounded-2xl border-2 border-slate-200 bg-slate-50/60 cursor-pointer';
        }
    }

    function filterPartners(term) {
        term = term.trim().toLowerCase();
        const opts = document.getElementById('partnerSelect').options;
        for (let i = 1; i < opts.length; i++) {
            opts[i].style.display = opts[i].text.toLowerCase().includes(term) ? '' : 'none';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
