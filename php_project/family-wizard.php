<?php
define('PAGE_TITLE', 'معالج إضافة أسرة كاملة');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

// 0. AJAX Real-time Duplicate Check
if (isset($_GET['action']) && $_GET['action'] === 'check_duplicate') {
    header('Content-Type: application/json; charset=utf-8');
    $name = trim($_GET['name'] ?? '');
    $phone = trim($_GET['phone'] ?? '');
    $results = [];

    if (!empty($phone) && strlen($phone) >= 6) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.first_name, p.middle_name, p.last_name, p.phone, f.id AS family_id, f.family_name, 'تطابق في رقم الهاتف' AS match_reason
            FROM persons p
            LEFT JOIN families f ON p.family_id = f.id
            WHERE (p.phone LIKE ? OR f.phone LIKE ?) AND p.is_active = 1
            LIMIT 5
        ");
        $stmt->execute(["%$phone%", "%$phone%"]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (!empty($name) && mb_strlen($name, 'UTF-8') >= 3) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.first_name, p.middle_name, p.last_name, p.phone, f.id AS family_id, f.family_name, 'اسم شخص مطابق أو مشابه' AS match_reason
            FROM persons p
            LEFT JOIN families f ON p.family_id = f.id
            WHERE (p.first_name LIKE ? OR CONCAT(p.first_name, ' ', IFNULL(p.middle_name, '')) LIKE ?) AND p.is_active = 1
            LIMIT 5
        ");
        $stmt->execute(["%$name%", "%$name%"]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $unique = [];
    $filtered = [];
    foreach ($results as $r) {
        if (!isset($unique[$r['id']])) {
            $unique[$r['id']] = true;
            $filtered[] = [
                'id' => $r['id'],
                'full_name' => trim($r['first_name'] . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                'phone' => $r['phone'] ?? '',
                'family_name' => $r['family_name'] ?? '',
                'family_id' => $r['family_id'] ?? 0,
                'match_reason' => $r['match_reason']
            ];
        }
    }

    echo json_encode(['found' => count($filtered) > 0, 'matches' => $filtered]);
    exit;
}

// Fetch Services for assignments
$services_list = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY min_age ASC, id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $father_first = trim($_POST['father_first'] ?? '');
    $family_name = trim($_POST['family_name'] ?? '');

    // 1. اسم الأسرة: لو اتساب فاضي يتاخد تلقائياً من اسم عائل الأسرة (الأب)
    if (empty($family_name)) {
        $family_name = !empty($father_first) ? 'أسرة ' . $father_first : 'أسرة جديدة';
    }

    // 2. كود الأسرة: يتولد تلقائياً في السيرفر دون إدخال المستخدم
    $stmt = $pdo->query("SELECT MAX(id) FROM families");
    $next_id = (int)$stmt->fetchColumn() + 1;
    $family_code = 'FAM-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

    // 3. المنطقة والعنوان
    $area_choice = trim($_POST['area_choice'] ?? '');
    $area_custom = trim($_POST['area_custom'] ?? '');
    $area = ($area_choice === 'other') ? $area_custom : $area_choice;
    $street = trim($_POST['street'] ?? '');
    $building_number = trim($_POST['building_number'] ?? '');
    $floor = trim($_POST['floor'] ?? '');
    $apartment_number = trim($_POST['apartment_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $special_case = trim($_POST['special_case'] ?? 'عادية');

    // 4. بيانات الأب
    $father_care = trim($_POST['father_care'] ?? 'normal');
    $father_is_deceased = ($father_care === 'deceased') ? 1 : 0;
    $father_dob = !empty($_POST['father_dob']) ? $_POST['father_dob'] : null;

    if ($father_is_deceased) {
        $father_phone = null;
        $father_job = null;
        $father_confession = null;
        $father_confession_church = null;
        $father_leave = null;
        $father_service_id = null;
        $father_is_deacon = 0;
        $father_is_servant = 0;
        $father_in_prep_center = 0;
    } else {
        $father_phone = trim($_POST['father_phone'] ?? '');
        $father_job = trim($_POST['father_job'] ?? '');
        $father_confession = trim($_POST['father_confession'] ?? '');
        $father_confession_church = trim($_POST['father_confession_church'] ?? '');
        $father_leave = !empty($_POST['father_weekly_leave']) ? implode(',', (array)$_POST['father_weekly_leave']) : null;
        $father_service_id = !empty($_POST['father_service_id']) ? (int)$_POST['father_service_id'] : null;
        $father_is_deacon = isset($_POST['father_is_deacon']) ? 1 : 0;
        $father_is_servant = isset($_POST['father_is_servant']) ? 1 : 0;
        $father_in_prep_center = isset($_POST['father_in_prep_center']) ? 1 : 0;
    }

    // 5. بيانات الأم
    $mother_first = trim($_POST['mother_first'] ?? '');
    $mother_care = trim($_POST['mother_care'] ?? 'normal');
    $mother_is_deceased = ($mother_care === 'deceased') ? 1 : 0;
    $mother_dob = !empty($_POST['mother_dob']) ? $_POST['mother_dob'] : null;

    if ($mother_is_deceased) {
        $mother_phone = null;
        $mother_job = null;
        $mother_confession = null;
        $mother_confession_church = null;
        $mother_leave = null;
        $mother_service_id = null;
        $mother_is_servant = 0;
        $mother_in_prep_center = 0;
    } else {
        $mother_phone = trim($_POST['mother_phone'] ?? '');
        $mother_job = trim($_POST['mother_job'] ?? '');
        $mother_confession = trim($_POST['mother_confession'] ?? '');
        $mother_confession_church = trim($_POST['mother_confession_church'] ?? '');
        $mother_leave = !empty($_POST['mother_weekly_leave']) ? implode(',', (array)$_POST['mother_weekly_leave']) : null;
        $mother_service_id = !empty($_POST['mother_service_id']) ? (int)$_POST['mother_service_id'] : null;
        $mother_is_servant = isset($_POST['mother_is_servant']) ? 1 : 0;
        $mother_in_prep_center = isset($_POST['mother_in_prep_center']) ? 1 : 0;
    }

    if (empty($father_first)) {
        set_flash('error', 'يرجى إدخال اسم عائل الأسرة (الأب) على الأقل.');
    } else {
        try {
            $pdo->beginTransaction();

            // 1. حفظ بيانات الأسرة
            $full_address = trim($area . ($street ? '، ' . $street : ''));
            $stmt = $pdo->prepare("
                INSERT INTO families (family_code, family_name, area, street, address, building_number, floor, apartment_number, phone, special_case)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$family_code, $family_name, $area, $street, $full_address, $building_number, $floor, $apartment_number, $phone, $special_case]);
            $family_id = $pdo->lastInsertId();

            // 2. حفظ الأب (عائل الأسرة) - last_name يترك فارغاً حتى لا يتكرر اسم الأسرة
            $stmt = $pdo->prepare("
                INSERT INTO persons (family_id, first_name, last_name, gender, date_of_birth, phone, occupation, confession_father, confession_church, care_situation, weekly_leave, education_stage, marital_status, is_deceased, is_deacon, is_servant, in_prep_center)
                VALUES (?, ?, '', 'male', ?, ?, ?, ?, ?, ?, ?, 'graduate', 'married', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $family_id,
                $father_first,
                $father_dob,
                $father_phone,
                $father_job,
                $father_confession,
                $father_confession_church,
                $father_care,
                $father_leave,
                $father_is_deceased,
                $father_is_deacon,
                $father_is_servant,
                $father_in_prep_center
            ]);
            $father_id = $pdo->lastInsertId();

            // ربط الأب كعائل للأسرة
            $pdo->prepare("
                INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, is_current)
                VALUES (?, ?, 'father', 1, 0, 0, 1)
            ")->execute([$family_id, $father_id]);

            // تعيين خدمة الأب
            if ($father_service_id && !$father_is_deceased) {
                if ($father_is_servant || $father_in_prep_center) {
                    $pdo->prepare("INSERT INTO service_members (service_id, person_id, role, is_active) VALUES (?, ?, 'servant', 1)")
                        ->execute([$father_service_id, $father_id]);
                } else {
                    $pdo->prepare("INSERT INTO service_assignments (person_id, service_id, status) VALUES (?, ?, 'active')")
                        ->execute([$father_id, $father_service_id]);
                }
            }

            // 3. حفظ الأم (إن وجدت)
            $mother_id = null;
            if (!empty($mother_first)) {
                $stmt = $pdo->prepare("
                    INSERT INTO persons (family_id, first_name, last_name, gender, date_of_birth, phone, occupation, confession_father, confession_church, care_situation, weekly_leave, education_stage, marital_status, is_deceased, is_deacon, is_servant, in_prep_center)
                    VALUES (?, ?, '', 'female', ?, ?, ?, ?, ?, ?, ?, 'graduate', 'married', ?, 0, ?, ?)
                ");
                $stmt->execute([
                    $family_id,
                    $mother_first,
                    $mother_dob,
                    $mother_phone,
                    $mother_job,
                    $mother_confession,
                    $mother_confession_church,
                    $mother_care,
                    $mother_leave,
                    $mother_is_deceased,
                    $mother_is_servant,
                    $mother_in_prep_center
                ]);
                $mother_id = $pdo->lastInsertId();

                // ربط الأم كزوجة
                $pdo->prepare("
                    INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, is_current)
                    VALUES (?, ?, 'mother', 0, 1, 0, 1)
                ")->execute([$family_id, $mother_id]);

                // قرابة الأب والزوجة
                $pdo->prepare("
                    INSERT INTO person_relationships (person_id, related_person_id, relationship_type)
                    VALUES (?, ?, 'husband')
                ")->execute([$father_id, $mother_id]);

                // تعيين خدمة الأم
                if ($mother_service_id && !$mother_is_deceased) {
                    if ($mother_is_servant || $mother_in_prep_center) {
                        $pdo->prepare("INSERT INTO service_members (service_id, person_id, role, is_active) VALUES (?, ?, 'servant', 1)")
                            ->execute([$mother_service_id, $mother_id]);
                    } else {
                        $pdo->prepare("INSERT INTO service_assignments (person_id, service_id, status) VALUES (?, ?, 'active')")
                            ->execute([$mother_id, $mother_service_id]);
                    }
                }
            }

            // 4. حفظ الأبناء
            $child_names = $_POST['child_name'] ?? [];
            $child_genders = $_POST['child_gender'] ?? [];
            $child_dobs = $_POST['child_dob'] ?? [];
            $child_stages = $_POST['child_stage'] ?? [];
            $child_services = $_POST['child_service_id'] ?? [];
            $child_cares = $_POST['child_care'] ?? [];
            $child_confessions = $_POST['child_confession'] ?? [];
            $child_confession_churches = $_POST['child_confession_church'] ?? [];
            $child_is_deacons = $_POST['child_is_deacon'] ?? [];
            $child_is_servants = $_POST['child_is_servant'] ?? [];
            $child_in_preps = $_POST['child_in_prep'] ?? [];

            for ($i = 0; $i < count($child_names); $i++) {
                $c_name = trim($child_names[$i]);
                if (empty($c_name)) continue;

                $c_gender = $child_genders[$i] ?? 'male';
                $c_dob = !empty($child_dobs[$i]) ? $child_dobs[$i] : null;
                $c_stage = !empty($child_stages[$i]) ? $child_stages[$i] : null;
                $c_care = !empty($child_cares[$i]) ? $child_cares[$i] : 'normal';
                $c_confession = trim($child_confessions[$i] ?? '');
                $c_church = trim($child_confession_churches[$i] ?? '');
                $c_deacon = !empty($child_is_deacons[$i]) ? 1 : 0;
                $c_servant = !empty($child_is_servants[$i]) ? 1 : 0;
                $c_prep = !empty($child_in_preps[$i]) ? 1 : 0;
                $rel_type = ($c_gender === 'female') ? 'daughter' : 'son';

                $stmt = $pdo->prepare("
                    INSERT INTO persons (family_id, first_name, middle_name, last_name, gender, date_of_birth, education_stage, care_situation, confession_father, confession_church, is_deacon, is_servant, in_prep_center)
                    VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $family_id,
                    $c_name,
                    $father_first,
                    $c_gender,
                    $c_dob,
                    $c_stage,
                    $c_care,
                    $c_confession,
                    $c_church,
                    $c_deacon,
                    $c_servant,
                    $c_prep
                ]);
                $child_id = $pdo->lastInsertId();

                // ربط الابن بالأسرة
                $pdo->prepare("
                    INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, is_current)
                    VALUES (?, ?, ?, 0, 0, 1, 1)
                ")->execute([$family_id, $child_id, $rel_type]);

                // صلة القرابة بالأب
                $pdo->prepare("
                    INSERT INTO person_relationships (person_id, related_person_id, relationship_type)
                    VALUES (?, ?, 'father')
                ")->execute([$child_id, $father_id]);

                // صلة القرابة بالأم
                if ($mother_id) {
                    $pdo->prepare("
                        INSERT INTO person_relationships (person_id, related_person_id, relationship_type)
                        VALUES (?, ?, 'mother')
                    ")->execute([$child_id, $mother_id]);
                }

                // تعيين خدمة الابن
                if (!empty($child_services[$i])) {
                    $c_svc_id = (int)$child_services[$i];
                    if ($c_servant || $c_prep) {
                        $pdo->prepare("INSERT INTO service_members (service_id, person_id, role, is_active) VALUES (?, ?, 'servant', 1)")
                            ->execute([$c_svc_id, $child_id]);
                    } else {
                        $pdo->prepare("INSERT INTO service_assignments (person_id, service_id, status) VALUES (?, ?, 'active')")
                            ->execute([$child_id, $c_svc_id]);
                    }
                }
            }

            // 5. حفظ الأجداد (اختياري)
            if (!empty($_POST['has_grandfather']) && !empty($_POST['grandfather_name'])) {
                $gf_name = trim($_POST['grandfather_name']);
                $gf_dob = !empty($_POST['grandfather_dob']) ? $_POST['grandfather_dob'] : null;
                $gf_phone = trim($_POST['grandfather_phone'] ?? '');
                $gf_confession = trim($_POST['grandfather_confession'] ?? '');
                $gf_church = trim($_POST['grandfather_confession_church'] ?? '');
                $gf_care = trim($_POST['grandfather_care'] ?? 'normal');

                $stmt = $pdo->prepare("
                    INSERT INTO persons (family_id, first_name, last_name, gender, date_of_birth, phone, confession_father, confession_church, care_situation, education_stage)
                    VALUES (?, ?, '', 'male', ?, ?, ?, ?, ?, 'graduate')
                ");
                $stmt->execute([$family_id, $gf_name, $gf_dob, $gf_phone, $gf_confession, $gf_church, $gf_care]);
                $gf_id = $pdo->lastInsertId();

                $pdo->prepare("
                    INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, is_current)
                    VALUES (?, ?, 'grandfather', 0, 0, 0, 1)
                ")->execute([$family_id, $gf_id]);
            }

            // الجدة
            if (!empty($_POST['has_grandmother']) && !empty($_POST['grandmother_name'])) {
                $gm_name = trim($_POST['grandmother_name']);
                $gm_dob = !empty($_POST['grandmother_dob']) ? $_POST['grandmother_dob'] : null;
                $gm_phone = trim($_POST['grandmother_phone'] ?? '');
                $gm_confession = trim($_POST['grandmother_confession'] ?? '');
                $gm_church = trim($_POST['grandmother_confession_church'] ?? '');
                $gm_care = trim($_POST['grandmother_care'] ?? 'normal');

                $stmt = $pdo->prepare("
                    INSERT INTO persons (family_id, first_name, last_name, gender, date_of_birth, phone, confession_father, confession_church, care_situation, education_stage)
                    VALUES (?, ?, '', 'female', ?, ?, ?, ?, ?, 'graduate')
                ");
                $stmt->execute([$family_id, $gm_name, $gm_dob, $gm_phone, $gm_confession, $gm_church, $gm_care]);
                $gm_id = $pdo->lastInsertId();

                $pdo->prepare("
                    INSERT INTO family_members (family_id, person_id, relationship_type, is_head, is_spouse, is_child, is_current)
                    VALUES (?, ?, 'grandmother', 0, 0, 0, 1)
                ")->execute([$family_id, $gm_id]);
            }

            $pdo->commit();
            record_audit($pdo, 'create', 'families', (int)$family_id, null, "إضافة أسرة: $family_name");
            set_flash('success', "تم تسجيل أسرة ($family_name) بنجاح!");
            header("Location: family-details.php?id=$family_id");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'حدث خطأ أثناء حفظ بيانات الأسرة: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Datalist for Professions / Jobs -->
<datalist id="jobsList">
    <option value="مهندس">
    <option value="طبيب / صيدلي">
    <option value="محاسب">
    <option value="مدرس / معلم">
    <option value="محامي">
    <option value="موظف حكومي">
    <option value="موظف قطاع خاص">
    <option value="أعمال حرة / تاجر">
    <option value="فني / صنايعي">
    <option value="سائق">
    <option value="ربة منزل">
    <option value="طالب">
    <option value="بالمعاش">
    <option value="بدون عمل">
</datalist>

<!-- Datalist for Confession Fathers (أبونا سارافيم وأبونا جوزيف فقط) -->
<datalist id="confessionFathersList">
    <option value="أبونا سارافيم">
    <option value="أبونا جوزيف">
</datalist>

<!-- Datalist for Churches -->
<datalist id="churchesList">
    <option value="كنيسة العائلة المقدسة">
    <option value="كنيسة الشهيد مارجرجس">
    <option value="كنيسة السيدة العذراء مريم">
    <option value="كنيسة رئيس الملائكة ميخائيل">
    <option value="كنيسة الأنبا شنودة">
</datalist>

<div class="w-full mb-12">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="families.php" class="text-xs text-slate-500 hover:text-church-navy font-semibold inline-flex items-center mb-1">
                <i class="fas fa-arrow-right ml-1"></i> العودة لسجل الأسر
            </a>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">معالج إضافة أسرة كاملة</h1>
            <p class="text-xs text-slate-500 mt-0.5">تسجيل بيانات السكن، الأب، الأم، الأبناء والأجداد بطريقة ذكية وسريعة</p>
        </div>
    </div>

    <!-- صندوق تنبيه التكرار الفوري -->
    <div id="duplicateAlertBox" class="hidden mb-6 p-4 bg-amber-50 border-2 border-amber-300 rounded-3xl text-amber-900 text-xs shadow-sm">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-amber-600 text-lg ml-3 mt-0.5"></i>
            <div class="flex-1">
                <p class="font-extrabold text-sm mb-1 text-amber-900">تنبيه احتمالية وجود أسرة أو فرد مسجل مسبقاً بنفس الاسم أو الهاتف!</p>
                <div id="duplicateMatchesList" class="space-y-1.5 mt-2"></div>
            </div>
        </div>
    </div>

    <form method="POST" action="family-wizard.php" id="familyWizardForm" class="space-y-6">
        <?= csrf_field() ?>
        
        <!-- 1. بيانات الأسرة والسكن -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80">
            <h2 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center text-church-navy">
                <i class="fas fa-home ml-2 text-church-gold"></i>
                1. بيانات الأسرة والسكن
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        اسم الأسرة 
                        <span class="text-slate-400 font-normal">(اختياري — يتولد تلقائياً من اسم الأب إذا ترك فارغاً)</span>
                    </label>
                    <input type="text" name="family_name" id="familyNameInput" placeholder="سيتم تسميتها تلقائياً من اسم عائل الأسرة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#c9a44c]">
                    <p id="familyNameHint" class="text-[11px] text-[#c9a44c] font-semibold mt-1 hidden">
                        <i class="fas fa-magic ml-1"></i> اسم الأسرة المقترح: <span id="suggestedFamilyNameText"></span>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تليفون الأسرة / المنزل (الأرضي أو الأساسي)</label>
                    <input type="text" name="phone" id="familyPhoneInput" oninput="checkDuplicateNow()" placeholder="مثال: 0244745327 أو رقم موبايل" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المنطقة *</label>
                    <select name="area_choice" id="areaChoice" onchange="toggleCustomArea(this.value)" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800">
                        <option value="عزبة البكري">عزبة البكري</option>
                        <option value="أرض الدلتا 1">أرض الدلتا 1</option>
                        <option value="أرض الدلتا 2">أرض الدلتا 2</option>
                        <option value="الخزف والصيني">الخزف والصيني</option>
                        <option value="بطن الجبل">بطن الجبل</option>
                        <option value="أرض أطلس">أرض أطلس</option>
                        <option value="other">منطقة أخرى (كتابة يدوية)...</option>
                    </select>
                </div>
            </div>

            <!-- الشارع والعمارة والحالة الخاصة -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4">
                <div id="customAreaContainer" class="hidden">
                    <label class="block text-xs font-bold text-slate-700 mb-1">اكتب اسم المنطقة المخصصة</label>
                    <input type="text" name="area_custom" id="customAreaInput" placeholder="اكتب المنطقة هنا" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div id="streetContainer">
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الشارع والمتفرع منه والعلامة المميزة</label>
                    <input type="text" name="street" placeholder="مثال: شارع الترعة متفرع من شارع المحطة بجوار صيدلية..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">عمارة / دور / شقة</label>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" name="building_number" placeholder="رقم العمارة" class="p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-center">
                        <input type="text" name="floor" placeholder="الدور" class="p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-center">
                        <input type="text" name="apartment_number" placeholder="رقم الشقة" class="p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-center">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">حالة خاصة للأسرة (لتقارير الافتقاد والدعم)</label>
                    <select name="special_case" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="عادية">عادية</option>
                        <option value="تحتاج دعم مادي">تحتاج دعم مادي</option>
                        <option value="تحتاج رعاية صحية">تحتاج رعاية صحية</option>
                        <option value="دعم مادي + رعاية صحية">دعم مادي ورعاية صحية</option>
                        <option value="أسرة بها أيتام">أسرة بها أيتام</option>
                        <option value="أحد الأفراد مسجون">أحد الأفراد مسجون</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 2. عائل الأسرة (الأب / الزوج) -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h2 class="text-base font-bold text-slate-900 flex items-center text-church-navy">
                    <i class="fas fa-user-tie ml-2 text-church-gold"></i>
                    2. عائل الأسرة (الأب / الزوج)
                </h2>
                <div class="w-48">
                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">الحالة الحياتية / الرعاية للأب</label>
                    <select name="father_care" id="fatherCare" onchange="onFatherCareChange(this.value)" class="w-full p-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-church-navy">
                        <option value="normal">عادي (سليم ومقيم بالمنزل)</option>
                        <option value="deceased">متوفي</option>
                        <option value="widowed">أرمل</option>
                        <option value="special_needs">ذوي احتياجات خاصة</option>
                        <option value="abroad">مسافر بالخارج</option>
                        <option value="sick">مرض مزمن / طريح فراش</option>
                    </select>
                </div>
            </div>

            <!-- تنبيه إذا كان الأب متوفي -->
            <div id="fatherDeceasedNote" class="hidden p-3.5 bg-slate-100 rounded-2xl border border-slate-200/80 text-xs text-slate-600 font-bold mb-4">
                <i class="fas fa-cross ml-2 text-church-navy"></i>
                الأب متنيح في فردوس النعيم — تم إخفاء بيانات الاتصال والاعتراف والخدمة تلقائياً، ويكتفى بالاسم وتاريخ الميلاد.
            </div>

            <!-- صف الاسم وتاريخ الميلاد -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الأول / بالكامل *</label>
                    <input type="text" name="father_first" id="fatherFirstInput" oninput="onFatherNameInput(this.value)" required placeholder="مثال: عاطف رزق الله اندراوس" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                    <input type="date" name="father_dob" id="fatherDob" onchange="onParentDobChange('father', this.value)" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    <span id="fatherAgeText" class="text-[11px] text-slate-400 font-medium block mt-0.5"></span>
                </div>
                <div id="fatherJobWrapper">
                    <label class="block text-xs font-bold text-slate-700 mb-1">المهنة / العمل (اختر أو اكتب)</label>
                    <input type="text" name="father_job" list="jobsList" placeholder="اختر أو اكتب المهنة..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <!-- الحقول النشطة للأب (تخفى في حالة المتوفي) -->
            <div id="fatherActiveFields" class="space-y-4">
                <!-- صفات الأب الكنسية: شماس، خادم، إعداد خدام -->
                <div class="flex flex-wrap items-center gap-6 p-3 bg-slate-50 rounded-2xl border border-slate-200/60">
                    <span class="text-xs font-bold text-slate-700">الصفة الكنسية للأب:</span>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="father_is_deacon" value="1" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                        <span class="mr-2 text-xs font-bold text-slate-800">شماس</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="father_is_servant" value="1" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                        <span class="mr-2 text-xs font-bold text-slate-800">خادم</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="father_in_prep_center" value="1" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                        <span class="mr-2 text-xs font-bold text-slate-800">في إعداد خدام</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الموبايل</label>
                        <input type="text" name="father_phone" id="fatherPhoneInput" oninput="checkDuplicateNow()" placeholder="01..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أب الاعتراف</label>
                        <input type="text" name="father_confession" id="fatherConfession" list="confessionFathersList" oninput="syncConfessionChurch(this.value, 'fatherConfessionChurch')" placeholder="أبونا سارافيم / أبونا جوزيف..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">كنيسة أب الاعتراف</label>
                        <input type="text" name="father_confession_church" id="fatherConfessionChurch" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الخدمة / الاجتماع المقترح</label>
                        <select name="father_service_id" id="fatherServiceId" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                            <option value="">بدون خدمة محددة</option>
                            <?php foreach ($services_list as $svc): ?>
                                <option value="<?= $svc['id'] ?>" data-name="<?= htmlspecialchars($svc['name']) ?>" <?= str_contains($svc['name'], 'الرجال') || str_contains($svc['name'], 'موسى') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($svc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- أيام الإجازة الأسبوعية للأب -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/60">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        <i class="fas fa-calendar-day ml-1 text-church-gold"></i>
                        الإجازة الأسبوعية للأب (اختيار أكثر من يوم):
                    </label>
                    <?= render_weekly_leave_picker('father_weekly_leave', ['friday']) ?>
                </div>
            </div>
        </div>

        <!-- 3. الزوجة (الأم) -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h2 class="text-base font-bold text-slate-900 flex items-center text-church-navy">
                    <i class="fas fa-person-dress ml-2 text-church-gold"></i>
                    3. الزوجة (الأم)
                </h2>
                <div class="w-48">
                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">الحالة الحياتية / الرعاية للأم</label>
                    <select name="mother_care" id="motherCare" onchange="onMotherCareChange(this.value)" class="w-full p-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-church-navy">
                        <option value="normal">عادية (سليمة ومقيمة بالمنزل)</option>
                        <option value="deceased">متوفية</option>
                        <option value="special_needs">ذوي احتياجات خاصة</option>
                        <option value="abroad">مسافرة بالخارج</option>
                        <option value="sick">مرض مزمن / طريحة فراش</option>
                    </select>
                </div>
            </div>

            <!-- تنبيه إذا كانت الأم متوفية -->
            <div id="motherDeceasedNote" class="hidden p-3.5 bg-slate-100 rounded-2xl border border-slate-200/80 text-xs text-slate-600 font-bold mb-4">
                <i class="fas fa-cross ml-2 text-church-navy"></i>
                الأم متنيحة في فردوس النعيم — تم إخفاء بيانات الاتصال والاعتراف والخدمة تلقائياً، ويكتفى بالاسم وتاريخ الميلاد.
            </div>

            <!-- صف الاسم وتاريخ الميلاد -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الأول / بالكامل</label>
                    <input type="text" name="mother_first" placeholder="اسم الأم" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                    <input type="date" name="mother_dob" id="motherDob" onchange="onParentDobChange('mother', this.value)" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    <span id="motherAgeText" class="text-[11px] text-slate-400 font-medium block mt-0.5"></span>
                </div>
                <div id="motherJobWrapper">
                    <label class="block text-xs font-bold text-slate-700 mb-1">المهنة / العمل (اختر أو اكتب)</label>
                    <input type="text" name="mother_job" list="jobsList" placeholder="معلمة، ربة منزل..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <!-- الحقول النشطة للأم (تخفى في حالة المتوفية) -->
            <div id="motherActiveFields" class="space-y-4">
                <!-- صفات الأم الكنسية: خادمة، إعداد خدام -->
                <div class="flex flex-wrap items-center gap-6 p-3 bg-slate-50 rounded-2xl border border-slate-200/60">
                    <span class="text-xs font-bold text-slate-700">الصفة الكنسية للأم:</span>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="mother_is_servant" value="1" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                        <span class="mr-2 text-xs font-bold text-slate-800">خادمة</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="mother_in_prep_center" value="1" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                        <span class="mr-2 text-xs font-bold text-slate-800">في إعداد خدام</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الموبايل</label>
                        <input type="text" name="mother_phone" placeholder="01..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أب الاعتراف</label>
                        <input type="text" name="mother_confession" id="motherConfession" list="confessionFathersList" oninput="syncConfessionChurch(this.value, 'motherConfessionChurch')" placeholder="أبونا سارافيم / أبونا جوزيف..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">كنيسة أب الاعتراف</label>
                        <input type="text" name="mother_confession_church" id="motherConfessionChurch" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الخدمة / الاجتماع المقترح</label>
                        <select name="mother_service_id" id="motherServiceId" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                            <option value="">بدون خدمة محددة</option>
                            <?php foreach ($services_list as $svc): ?>
                                <option value="<?= $svc['id'] ?>" data-name="<?= htmlspecialchars($svc['name']) ?>" <?= str_contains($svc['name'], 'السيدات') || str_contains($svc['name'], 'المرأة') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($svc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- أيام الإجازة الأسبوعية للأم -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/60">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        <i class="fas fa-calendar-day ml-1 text-church-gold"></i>
                        الإجازة الأسبوعية للأم:
                    </label>
                    <?= render_weekly_leave_picker('mother_weekly_leave', ['friday', 'sunday']) ?>
                </div>
            </div>
        </div>

        <!-- 4. الأبناء -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center text-church-navy">
                        <i class="fas fa-children ml-2 text-church-gold"></i>
                        4. الأبناء (بنين وبنات)
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">اقتراح المرحلة الدراسية وفصل مدارس الأحد يتم آلياً حسب تاريخ الميلاد</p>
                </div>
                <button type="button" onclick="addChildRow()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-church-navy text-xs font-bold rounded-xl transition flex items-center">
                    <i class="fas fa-plus ml-1.5 text-church-gold"></i> إضافة ابن / ابنة أخرى
                </button>
            </div>

            <div id="childrenContainer" class="space-y-4">
                <!-- الابن الأول (الافتراضي) -->
                <div class="child-row p-4 sm:p-5 bg-slate-50 rounded-2xl border border-slate-200/60 relative">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 items-end mb-3">
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1">الاسم الأول للابن/الابنة *</label>
                            <input type="text" name="child_name[]" placeholder="اسم الطفل الأول" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold">
                        </div>
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1">النوع</label>
                            <select name="child_gender[]" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                                <option value="male">ذكر (ابن)</option>
                                <option value="female">أنثى (ابنة)</option>
                            </select>
                        </div>
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1">تاريخ الميلاد</label>
                            <input type="date" name="child_dob[]" onchange="onChildDobChange(this)" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                            <span class="child-age-text text-[11px] text-slate-400 font-medium block mt-0.5"></span>
                        </div>
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1">الحالة الخاصة بالابن</label>
                            <select name="child_care[]" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold">
                                <option value="normal">عادي (لا توجد)</option>
                                <option value="special_needs">ذوي احتياجات خاصة</option>
                            </select>
                        </div>
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1">المرحلة الدراسية</label>
                            <select name="child_stage[]" class="child-stage-select w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                                <option value="">تلقائي حسب السن</option>
                                <option value="nursery">حضانة مبكرة</option>
                                <option value="angels">ملائكة</option>
                                <option value="primary_1_2">ابتدائي (أولى وتانية)</option>
                                <option value="primary_3_4">ابتدائي (تالتة ورابعة)</option>
                                <option value="primary_5_6">ابتدائي (خامسة وسادسة)</option>
                                <option value="preparatory">إعدادي</option>
                                <option value="secondary">ثانوي</option>
                                <option value="university">جامعيين</option>
                                <option value="graduate">خريجين</option>
                            </select>
                        </div>
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1">الخدمة / فصل مدارس الأحد</label>
                            <select name="child_service_id[]" class="child-service-select w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold">
                                <option value="">بدون خدمة محددة</option>
                                <?php foreach ($services_list as $svc): ?>
                                    <option value="<?= $svc['id'] ?>" data-name="<?= htmlspecialchars($svc['name']) ?>">
                                        <?= htmlspecialchars($svc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- صفات الابن الكنسية: شماس، خادم، إعداد خدام -->
                    <div class="flex flex-wrap items-center gap-6 py-2 border-t border-slate-200/60 mb-2">
                        <span class="text-[11px] font-bold text-slate-600">الصفة الكنسية:</span>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="hidden" name="child_is_deacon[]" value="0">
                            <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="w-3.5 h-3.5 rounded text-church-navy focus:ring-church-gold">
                            <span class="mr-1.5 text-xs font-bold text-slate-700">شماس</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="hidden" name="child_is_servant[]" value="0">
                            <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="w-3.5 h-3.5 rounded text-church-navy focus:ring-church-gold">
                            <span class="mr-1.5 text-xs font-bold text-slate-700">خادم</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="hidden" name="child_in_prep[]" value="0">
                            <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="w-3.5 h-3.5 rounded text-church-navy focus:ring-church-gold">
                            <span class="mr-1.5 text-xs font-bold text-slate-700">في إعداد خدام</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/40">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">أب الاعتراف (اختياري)</label>
                            <input type="text" name="child_confession[]" list="confessionFathersList" oninput="syncConfessionChurch(this.value, this.parentElement.nextElementSibling.querySelector('input'))" placeholder="أبونا سارافيم / أبونا جوزيف..." class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">كنيسة أب الاعتراف</label>
                            <input type="text" name="child_confession_church[]" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. الأجداد (الساكنين مع الأسرة - اختياري) -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center text-church-navy">
                        <i class="fas fa-heart ml-2 text-church-gold"></i>
                        5. الأجداد (الساكنون مع الأسرة)
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">تسجيل الجد أو الجدة المقيمين مع الأسرة بنفس كود الأسرة واسم الأب</p>
                </div>
            </div>

            <!-- الجد -->
            <div class="mt-4 p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/60">
                <label class="inline-flex items-center cursor-pointer mb-3">
                    <input type="checkbox" name="has_grandfather" id="hasGrandfather" onchange="toggleGrandparent('grandfather', this.checked)" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                    <span class="mr-2 text-xs font-bold text-slate-800">يوجد جد ساكن مع الأسرة (أبو الأب أو أبو الأم)</span>
                </label>

                <div id="grandfatherFields" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 hidden pt-3 border-t border-slate-200/60">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">اسم الجد بالكامل</label>
                        <input type="text" name="grandfather_name" placeholder="اسم الجد" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                        <input type="date" name="grandfather_dob" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">الحالة الخاصة / الرعاية</label>
                        <select name="grandfather_care" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                            <option value="normal">عادي / مسن</option>
                            <option value="special_needs">ذوي احتياجات خاصة</option>
                            <option value="sick">مرض مزمن / طريح فراش</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">أب الاعتراف</label>
                        <input type="text" name="grandfather_confession" list="confessionFathersList" oninput="syncConfessionChurch(this.value, 'gfChurch')" placeholder="أبونا سارافيم..." class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">كنيسة أب الاعتراف</label>
                        <input type="text" name="grandfather_confession_church" id="gfChurch" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                </div>
            </div>

            <!-- الجدة -->
            <div class="mt-4 p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/60">
                <label class="inline-flex items-center cursor-pointer mb-3">
                    <input type="checkbox" name="has_grandmother" id="hasGrandmother" onchange="toggleGrandparent('grandmother', this.checked)" class="w-4 h-4 rounded text-church-navy focus:ring-church-gold">
                    <span class="mr-2 text-xs font-bold text-slate-800">توجد جدة ساكنة مع الأسرة (أم الأب أو أم الأم)</span>
                </label>

                <div id="grandmotherFields" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 hidden pt-3 border-t border-slate-200/60">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">اسم الجدة بالكامل</label>
                        <input type="text" name="grandmother_name" placeholder="اسم الجدة" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">تاريخ الميلاد</label>
                        <input type="date" name="grandmother_dob" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">الحالة الخاصة / الرعاية</label>
                        <select name="grandmother_care" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                            <option value="normal">عادية / مسنة</option>
                            <option value="special_needs">ذوي احتياجات خاصة</option>
                            <option value="sick">مرض مزمن / طريحة فراش</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">أب الاعتراف</label>
                        <input type="text" name="grandmother_confession" list="confessionFathersList" oninput="syncConfessionChurch(this.value, 'gmChurch')" placeholder="أبونا سارافيم..." class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">كنيسة أب الاعتراف</label>
                        <input type="text" name="grandmother_confession_church" id="gmChurch" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    </div>
                </div>
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex items-center justify-end space-x-3 space-x-reverse pt-2">
            <a href="families.php" class="px-6 py-3 bg-slate-200 text-slate-700 font-bold rounded-2xl text-sm hover:bg-slate-300 transition">
                إلغاء
            </a>
            <button type="submit" class="px-8 py-3.5 gold-gradient text-church-navy font-extrabold rounded-2xl text-base shadow-lg shadow-church-gold/20 hover:brightness-105 transition flex items-center">
                <i class="fas fa-check-circle ml-2"></i>
                حفظ بيانات الأسرة بالكامل
            </button>
        </div>

    </form>
</div>

<script>
    // 0. كشف التكرار الفوري أثناء الإدخال
    let duplicateTimeout = null;
    function checkDuplicateNow() {
        clearTimeout(duplicateTimeout);
        duplicateTimeout = setTimeout(() => {
            const name = (document.getElementById('fatherFirstInput')?.value || '').trim();
            const phone = (document.getElementById('fatherPhoneInput')?.value || '').trim();
            const famPhone = (document.getElementById('familyPhoneInput')?.value || '').trim();
            const activePhone = phone || famPhone;

            if (name.length < 3 && activePhone.length < 6) {
                document.getElementById('duplicateAlertBox').classList.add('hidden');
                return;
            }

            fetch(`family-wizard.php?action=check_duplicate&name=${encodeURIComponent(name)}&phone=${encodeURIComponent(activePhone)}`)
                .then(r => r.json())
                .then(data => {
                    const alertBox = document.getElementById('duplicateAlertBox');
                    const list = document.getElementById('duplicateMatchesList');
                    list.innerHTML = '';

                    if (data.found && data.matches.length > 0) {
                        data.matches.forEach(m => {
                            const p = document.createElement('div');
                            p.className = 'flex items-center justify-between p-2 bg-amber-100/70 rounded-xl text-xs font-semibold';
                            p.innerHTML = `
                                <div>
                                    <span class="text-amber-950 font-bold">${m.full_name}</span>
                                    <span class="text-amber-800 mr-2">(${m.match_reason})</span>
                                    ${m.family_name ? `<span class="text-slate-600 mr-2">الأسرة: ${m.family_name}</span>` : ''}
                                    ${m.phone ? `<span class="text-slate-600 mr-2">هاتف: ${m.phone}</span>` : ''}
                                </div>
                                ${m.family_id ? `<a href="family-details.php?id=${m.family_id}" target="_blank" class="px-2.5 py-1 bg-amber-200 hover:bg-amber-300 text-amber-900 rounded-lg text-[11px] font-bold">عرض الأسرة <i class="fas fa-external-link-alt mr-1"></i></a>` : ''}
                            `;
                            list.appendChild(p);
                        });
                        alertBox.classList.remove('hidden');
                    } else {
                        alertBox.classList.add('hidden');
                    }
                })
                .catch(() => {});
        }, 350);
    }

    // 1. مزامنة اسم الأسرة التلقائي من اسم الأب
    function onFatherNameInput(name) {
        const familyNameInput = document.getElementById('familyNameInput');
        const hint = document.getElementById('familyNameHint');
        const hintText = document.getElementById('suggestedFamilyNameText');
        const trimmed = name.trim();

        if (trimmed.length > 0) {
            const suggested = 'أسرة ' + trimmed;
            hintText.textContent = suggested;
            hint.classList.remove('hidden');
            if (!familyNameInput.dataset.userEdited) {
                familyNameInput.placeholder = suggested;
            }
        } else {
            hint.classList.add('hidden');
            familyNameInput.placeholder = 'سيتم تسميتها تلقائياً من اسم عائل الأسرة';
        }
        checkDuplicateNow();
    }

    document.getElementById('familyNameInput').addEventListener('input', function() {
        this.dataset.userEdited = this.value.trim().length > 0 ? '1' : '';
    });

    // 2. التحكم في حقل المنطقة المخصصة
    function toggleCustomArea(val) {
        const customContainer = document.getElementById('customAreaContainer');
        const customInput = document.getElementById('customAreaInput');
        if (val === 'other') {
            customContainer.classList.remove('hidden');
            customInput.required = true;
            customInput.focus();
        } else {
            customContainer.classList.add('hidden');
            customInput.required = false;
            customInput.value = '';
        }
    }

    // 3. مزامنة كنيسة أب الاعتراف تلقائياً عند اختيار كاهن الكنيسة
    function syncConfessionChurch(priestName, churchTarget) {
        let inputEl = null;
        if (typeof churchTarget === 'string') {
            inputEl = document.getElementById(churchTarget);
        } else if (churchTarget instanceof HTMLElement) {
            inputEl = churchTarget;
        }
        if (!inputEl) return;

        const pName = (priestName || '').trim();
        if (pName.includes('سارافيم') || pName.includes('جوزيف')) {
            inputEl.value = 'كنيسة العائلة المقدسة';
        }
    }

    // 4. حالة الأب (متوفي يخفي التفاصيل الزائدة)
    function onFatherCareChange(careVal) {
        const activeFields = document.getElementById('fatherActiveFields');
        const note = document.getElementById('fatherDeceasedNote');
        const jobWrapper = document.getElementById('fatherJobWrapper');

        if (careVal === 'deceased') {
            activeFields.classList.add('hidden');
            note.classList.remove('hidden');
            jobWrapper.classList.add('hidden');
        } else {
            activeFields.classList.remove('hidden');
            note.classList.add('hidden');
            jobWrapper.classList.remove('hidden');
        }
    }

    // 5. حالة الأم (متوفية تخفي التفاصيل الزائدة)
    function onMotherCareChange(careVal) {
        const activeFields = document.getElementById('motherActiveFields');
        const note = document.getElementById('motherDeceasedNote');
        const jobWrapper = document.getElementById('motherJobWrapper');

        if (careVal === 'deceased') {
            activeFields.classList.add('hidden');
            note.classList.remove('hidden');
            jobWrapper.classList.add('hidden');
        } else {
            activeFields.classList.remove('hidden');
            note.classList.add('hidden');
            jobWrapper.classList.remove('hidden');
        }
    }

    // 6. حساب السن واقتراح الاجتماع للوالدين
    function calculateAge(dobString) {
        if (!dobString) return null;
        const dob = new Date(dobString);
        if (isNaN(dob.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        return age >= 0 ? age : null;
    }

    function onParentDobChange(who, dobVal) {
        const age = calculateAge(dobVal);
        const ageSpan = document.getElementById(who + 'AgeText');
        if (age !== null) {
            ageSpan.textContent = `(السن التقريبي: ${age} سنة)`;
        } else {
            ageSpan.textContent = '';
        }
    }

    // 7. حساب السن واقتراح الخدمة والمرحلة للأبناء
    function onChildDobChange(dobInput) {
        const row = dobInput.closest('.child-row');
        const age = calculateAge(dobInput.value);
        const ageSpan = row.querySelector('.child-age-text');
        const stageSelect = row.querySelector('.child-stage-select');
        const serviceSelect = row.querySelector('.child-service-select');

        if (age === null) {
            ageSpan.textContent = '';
            return;
        }

        ageSpan.textContent = `(السن التقريبي: ${age} سنة)`;

        let suggestedStage = '';
        let serviceKeywords = [];

        if (age < 4) {
            suggestedStage = 'nursery';
            serviceKeywords = ['حضانة', 'ملائكة'];
        } else if (age >= 4 && age <= 5) {
            suggestedStage = 'angels';
            serviceKeywords = ['ملائكة'];
        } else if (age >= 6 && age <= 7) {
            suggestedStage = 'primary_1_2';
            serviceKeywords = ['أولى', 'تانية', 'ابتدائي'];
        } else if (age >= 8 && age <= 9) {
            suggestedStage = 'primary_3_4';
            serviceKeywords = ['تالتة', 'رابعة', 'ابتدائي'];
        } else if (age >= 10 && age <= 11) {
            suggestedStage = 'primary_5_6';
            serviceKeywords = ['خامسة', 'سادسة', 'ابتدائي'];
        } else if (age >= 12 && age <= 14) {
            suggestedStage = 'preparatory';
            serviceKeywords = ['إعدادي'];
        } else if (age >= 15 && age <= 17) {
            suggestedStage = 'secondary';
            serviceKeywords = ['ثانوي'];
        } else if (age >= 18 && age <= 22) {
            suggestedStage = 'university';
            serviceKeywords = ['جامعي', 'شباب'];
        } else {
            suggestedStage = 'graduate';
            serviceKeywords = ['خريج', 'شباب'];
        }

        if (stageSelect && suggestedStage) {
            stageSelect.value = suggestedStage;
        }

        if (serviceSelect && serviceKeywords.length > 0) {
            for (let i = 0; i < serviceSelect.options.length; i++) {
                const optText = serviceSelect.options[i].text.toLowerCase();
                const matches = serviceKeywords.some(kw => optText.includes(kw));
                if (matches) {
                    serviceSelect.selectedIndex = i;
                    break;
                }
            }
        }
    }

    // 8. إظهار / إخفاء الأجداد
    function toggleGrandparent(who, isChecked) {
        const fields = document.getElementById(who + 'Fields');
        if (isChecked) {
            fields.classList.remove('hidden');
        } else {
            fields.classList.add('hidden');
        }
    }

    // 9. إضافة صف طفل ديناميكي
    const serviceOptionsHtml = `
        <option value="">بدون خدمة محددة</option>
        <?php foreach ($services_list as $svc): ?>
            <option value="<?= $svc['id'] ?>" data-name="<?= htmlspecialchars($svc['name']) ?>"><?= htmlspecialchars($svc['name']) ?></option>
        <?php endforeach; ?>
    `;

    function addChildRow() {
        const container = document.getElementById('childrenContainer');
        const div = document.createElement('div');
        div.className = 'child-row p-4 sm:p-5 bg-slate-50 rounded-2xl border border-slate-200/60 relative';
        div.innerHTML = `
            <button type="button" onclick="this.parentElement.remove()" class="absolute top-3 left-3 text-rose-500 hover:text-rose-700 p-1" title="حذف الابن">
                <i class="fas fa-trash-alt text-sm"></i>
            </button>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 items-end mb-3">
                <div class="xl:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">الاسم الأول للابن/الابنة *</label>
                    <input type="text" name="child_name[]" required placeholder="اسم الطفل الأول" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold">
                </div>
                <div class="xl:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">النوع</label>
                    <select name="child_gender[]" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                        <option value="male">ذكر (ابن)</option>
                        <option value="female">أنثى (ابنة)</option>
                    </select>
                </div>
                <div class="xl:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">تاريخ الميلاد</label>
                    <input type="date" name="child_dob[]" onchange="onChildDobChange(this)" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                    <span class="child-age-text text-[11px] text-slate-400 font-medium block mt-0.5"></span>
                </div>
                <div class="xl:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">الحالة الخاصة بالابن</label>
                    <select name="child_care[]" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold">
                        <option value="normal">عادي (لا توجد)</option>
                        <option value="special_needs">ذوي احتياجات خاصة</option>
                    </select>
                </div>
                <div class="xl:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">المرحلة الدراسية</label>
                    <select name="child_stage[]" class="child-stage-select w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                        <option value="">تلقائي حسب السن</option>
                        <option value="nursery">حضانة مبكرة</option>
                        <option value="angels">ملائكة</option>
                        <option value="primary_1_2">ابتدائي (أولى وتانية)</option>
                        <option value="primary_3_4">ابتدائي (تالتة ورابعة)</option>
                        <option value="primary_5_6">ابتدائي (خامسة وسادسة)</option>
                        <option value="preparatory">إعدادي</option>
                        <option value="secondary">ثانوي</option>
                        <option value="university">جامعيين</option>
                        <option value="graduate">خريجين</option>
                    </select>
                </div>
                <div class="xl:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">الخدمة / فصل مدارس الأحد</label>
                    <select name="child_service_id[]" class="child-service-select w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold">
                        ${serviceOptionsHtml}
                    </select>
                </div>
            </div>

            <!-- صفات الابن الكنسية: شماس، خادم، إعداد خدام -->
            <div class="flex flex-wrap items-center gap-6 py-2 border-t border-slate-200/60 mb-2">
                <span class="text-[11px] font-bold text-slate-600">الصفة الكنسية:</span>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="hidden" name="child_is_deacon[]" value="0">
                    <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="w-3.5 h-3.5 rounded text-church-navy focus:ring-church-gold">
                    <span class="mr-1.5 text-xs font-bold text-slate-700">شماس</span>
                </label>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="hidden" name="child_is_servant[]" value="0">
                    <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="w-3.5 h-3.5 rounded text-church-navy focus:ring-church-gold">
                    <span class="mr-1.5 text-xs font-bold text-slate-700">خادم</span>
                </label>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="hidden" name="child_in_prep[]" value="0">
                    <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="w-3.5 h-3.5 rounded text-church-navy focus:ring-church-gold">
                    <span class="mr-1.5 text-xs font-bold text-slate-700">في إعداد خدام</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/40">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">أب الاعتراف (اختياري)</label>
                    <input type="text" name="child_confession[]" list="confessionFathersList" oninput="syncConfessionChurch(this.value, this.parentElement.nextElementSibling.querySelector('input'))" placeholder="أبونا سارافيم / أبونا جوزيف..." class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">كنيسة أب الاعتراف</label>
                    <input type="text" name="child_confession_church[]" list="churchesList" placeholder="كنيسة العائلة المقدسة" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
                </div>
            </div>
        `;
        container.appendChild(div);
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
