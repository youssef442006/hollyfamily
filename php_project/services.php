<?php
define('PAGE_TITLE', 'قطاعات ومدارس الأحد والخدمات');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_permission('services_manage');
$user = current_user();

// Handle Add / Edit Service POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // 1. Assign Servant to Service with Role (أمين خدمة / أمين مساعد / خادم)
    if (isset($_POST['assign_servant'])) {
        $svc_id = (int)($_POST['service_id'] ?? 0);
        $person_id = (int)($_POST['person_id'] ?? 0);
        $role = $_POST['role'] ?? 'servant'; // service_admin, assistant_admin, servant

        if ($svc_id && $person_id) {
            $check = $pdo->prepare("SELECT id FROM service_members WHERE service_id = ? AND person_id = ? AND is_active = 1");
            $check->execute([$svc_id, $person_id]);
            if ($check->fetch()) {
                set_flash('error', 'هذا الشخص معين بالفعل في هذا الفصل / الخدمة.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO service_members (service_id, person_id, role, start_date, is_active) VALUES (?, ?, ?, CURDATE(), 1)");
                $stmt->execute([$svc_id, $person_id, $role]);
                $pdo->prepare("UPDATE persons SET is_servant = 1 WHERE id = ?")->execute([$person_id]);
                set_flash('success', 'تم تعيين الخادم وتحديد دوره في الفصل بنجاح.');
            }
        } else {
            set_flash('error', 'يرجى اختيار الخادم والدور.');
        }
        header("Location: services.php");
        exit;
    }

    // 2. Remove Servant from Service
    if (isset($_POST['remove_servant'])) {
        $mem_id = (int)($_POST['member_id'] ?? 0);
        if ($mem_id) {
            $pdo->prepare("UPDATE service_members SET is_active = 0, end_date = CURDATE() WHERE id = ?")->execute([$mem_id]);
            set_flash('success', 'تمت إزالة الخادم من الخدمة.');
        }
        header("Location: services.php");
        exit;
    }

    // 3. Save Service (Name, Description, Ages)
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $min_age = !empty($_POST['min_age']) ? (int)$_POST['min_age'] : null;
    $max_age = !empty($_POST['max_age']) ? (int)$_POST['max_age'] : null;
    $education_stages = trim($_POST['education_stages'] ?? '');

    if (empty($name)) {
        set_flash('error', 'يرجى إدخال اسم الخدمة.');
    } else {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, min_age = ?, max_age = ?, education_stages = ? WHERE id = ?");
            $stmt->execute([$name, $description, $min_age, $max_age, $education_stages, $id]);
            set_flash('success', 'تم تعديل الخدمة بنجاح.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (name, description, min_age, max_age, education_stages) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $min_age, $max_age, $education_stages]);
            set_flash('success', 'تمت إضافة الخدمة بنجاح.');
        }
        header("Location: services.php");
        exit;
    }
}

// Fetch all services with counts
$sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM service_assignments WHERE service_id = s.id AND status = 'active') AS students_count,
           (SELECT COUNT(*) FROM service_members WHERE service_id = s.id AND is_active = 1) AS servants_count
    FROM services s
    WHERE s.is_active = 1
    ORDER BY s.id ASC
";
$services = $pdo->query($sql)->fetchAll();

// Fetch Servants / Members grouped by Service
$stmt_mem = $pdo->query("
    SELECT sm.*, p.first_name, p.middle_name, p.last_name, p.phone
    FROM service_members sm
    JOIN persons p ON sm.person_id = p.id
    WHERE sm.is_active = 1
    ORDER BY FIELD(sm.role, 'service_admin', 'assistant_admin', 'servant', 'supervisor'), sm.id ASC
");
$members_by_service = [];
while ($m = $stmt_mem->fetch()) {
    $members_by_service[$m['service_id']][] = $m;
}

// Fetch candidate persons for assignment dropdown (فقط الخدام أو إعداد خدام)
$all_persons_for_servant = $pdo->query("
    SELECT id, first_name, middle_name, last_name, phone, is_servant, in_prep_center 
    FROM persons 
    WHERE is_active = 1 AND (is_servant = 1 OR in_prep_center = 1)
    ORDER BY is_servant DESC, first_name ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">قطاعات ومدارس الأحد والخدمات</h1>
        <p class="text-xs text-slate-500 mt-1">إدارة فصول مدارس الأحد، الاجتماعات النوعية، الخدام والمخدومين</p>
    </div>
    <div class="flex items-center space-x-2 space-x-reverse">
        <button onclick="openServiceModal()" class="inline-flex items-center px-4 py-2.5 rounded-xl gold-gradient text-church-navy font-bold text-sm shadow-md hover:brightness-105 transition">
            <i class="fas fa-plus ml-2"></i>
            إضافة قطاع / خدمة جديدة
        </button>
    </div>
</div>

<!-- Services Grid Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <?php foreach ($services as $srv): 
        $srv_members = $members_by_service[$srv['id']] ?? [];
        $service_admin = null;
        $assistant_admin = null;
        foreach ($srv_members as $sm) {
            if ($sm['role'] === 'service_admin' && !$service_admin) {
                $service_admin = trim($sm['first_name'] . ' ' . ($sm['middle_name'] ?? '') . ' ' . ($sm['last_name'] ?? ''));
            } elseif ($sm['role'] === 'assistant_admin' && !$assistant_admin) {
                $assistant_admin = trim($sm['first_name'] . ' ' . ($sm['middle_name'] ?? '') . ' ' . ($sm['last_name'] ?? ''));
            }
        }
    ?>
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80 hover:shadow-md transition flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-church-gold flex items-center justify-center text-xl shadow-inner">
                    <i class="fas fa-church"></i>
                </div>
                <button onclick='editService(<?= json_encode($srv) ?>)' class="p-2 text-slate-400 hover:text-church-blue rounded-lg transition" title="تعديل بيانات الخدمة">
                    <i class="fas fa-edit"></i>
                </button>
            </div>

            <h3 class="text-lg font-bold text-slate-900 mb-1.5"><?= htmlspecialchars($srv['name']) ?></h3>
            <p class="text-xs text-slate-500 line-clamp-2 mb-4 leading-relaxed"><?= htmlspecialchars($srv['description'] ?: 'لا يوجد وصف مضاف') ?></p>

            <!-- Leaders Info -->
            <div class="mb-4 space-y-1.5 bg-slate-50/80 p-3 rounded-2xl border border-slate-100 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500"><i class="fas fa-crown text-amber-500 ml-1"></i> أمين الخدمة:</span>
                    <span class="font-bold <?= $service_admin ? 'text-slate-800' : 'text-slate-400 font-normal' ?>">
                        <?= $service_admin ? htmlspecialchars($service_admin) : 'لم يُعيّن بعد' ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500"><i class="fas fa-star text-blue-500 ml-1"></i> أمين مساعد:</span>
                    <span class="font-bold <?= $assistant_admin ? 'text-slate-800' : 'text-slate-400 font-normal' ?>">
                        <?= $assistant_admin ? htmlspecialchars($assistant_admin) : 'لم يُعيّن' ?>
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500 mb-4 bg-slate-50 p-3 rounded-xl border border-slate-100">
                <div>
                    <span class="block text-slate-400 text-[10px]">المخدومين</span>
                    <span class="font-extrabold text-slate-800 text-sm"><?= $srv['students_count'] ?> مخدوم</span>
                </div>
                <div class="border-r border-slate-200 pr-4">
                    <span class="block text-slate-400 text-[10px]">إجمالي الخدام</span>
                    <span class="font-extrabold text-emerald-700 text-sm"><?= count($srv_members) ?> خادم</span>
                </div>
                <div>
                    <button onclick='openServantsModal(<?= $srv['id'] ?>, <?= json_encode($srv['name']) ?>, <?= json_encode($srv_members) ?>)' class="px-3 py-1.5 bg-church-navy text-white text-xs font-bold rounded-xl hover:bg-church-blue transition">
                        <i class="fas fa-user-shield ml-1"></i> إدارة الخدام
                    </button>
                </div>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
            <a href="persons.php?stage=<?= explode(',', $srv['education_stages'] ?? '')[0] ?? '' ?>" class="text-xs font-bold text-church-blue hover:underline">
                عرض المخدومين في هذا القطاع <i class="fas fa-chevron-left text-[10px] mr-1"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Manage Servants for Service (أمين خدمة / أمين مساعد / خادم) -->
<div id="servantsModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="servantsModalTitle" class="text-lg font-bold text-slate-900">إدارة خدام الخدمة</h3>
                <p class="text-xs text-slate-500">تعيين أمين خدمة، أمين مساعد، وخدام للفصل</p>
            </div>
            <button onclick="closeServantsModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Current Servants List -->
        <div class="my-4">
            <h4 class="text-xs font-bold text-slate-700 mb-2">الخدام المعينون حالياً:</h4>
            <div id="servantsListContainer" class="space-y-2 max-h-48 overflow-y-auto">
                <!-- Dynamically populated -->
            </div>
        </div>

        <!-- Assign New Servant Form -->
        <div class="border-t border-slate-100 pt-4 mt-4">
            <h4 class="text-xs font-bold text-slate-800 mb-3"><i class="fas fa-user-plus text-church-gold ml-1"></i> تعيين خادم جديد بالفصل:</h4>
            <form method="POST" action="services.php" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="assign_servant" value="1">
                <input type="hidden" name="service_id" id="assign_service_id" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اختر الخادم / الشخص *</label>
                    <select name="person_id" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                        <option value="">— اختر من قائمة الأفراد —</option>
                        <?php foreach ($all_persons_for_servant as $ap): ?>
                            <?php 
                            $ap_name = trim($ap['first_name'] . ' ' . ($ap['middle_name'] ?? '') . ' ' . ($ap['last_name'] ?? ''));
                            $role_label = ($ap['is_servant'] && $ap['in_prep_center']) ? ' (خادم + إعداد خدام)' : ($ap['in_prep_center'] ? ' (إعداد خدام)' : ' (خادم)');
                            ?>
                            <option value="<?= $ap['id'] ?>">
                                <?= htmlspecialchars($ap_name . $role_label) ?>
                                <?= $ap['phone'] ? ' - ' . $ap['phone'] : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الصفة / الدور بالخدمة *</label>
                    <select name="role" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold">
                        <option value="service_admin">👑 أمين خدمة (مسؤول أول عن الفصل)</option>
                        <option value="assistant_admin">⭐ أمين مساعد</option>
                        <option value="servant" selected>👥 خادم بالفصل</option>
                        <option value="supervisor">🔍 مسؤول متابعة / مشرف</option>
                    </select>
                </div>

                <div class="flex items-center justify-end space-x-2 space-x-reverse pt-2">
                    <button type="button" onclick="closeServantsModal()" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200">
                        إغلاق
                    </button>
                    <button type="submit" class="px-5 py-2 gold-gradient text-church-navy font-bold rounded-xl text-xs shadow hover:brightness-105">
                        <i class="fas fa-check ml-1"></i> حفظ وتعيين الخادم
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Service -->
<div id="serviceModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="srvModalTitle" class="text-lg font-bold text-slate-900">إضافة خدمة جديدة</h3>
            <button onclick="closeServiceModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="services.php" class="space-y-4 pt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="srv_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">اسم الخدمة *</label>
                <input type="text" name="name" id="srv_name" required placeholder="مثال: مدارس أحد ثانوي بنين" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">وصف الخدمة</label>
                <textarea name="description" id="srv_desc" rows="2" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">السن الأدنى</label>
                    <input type="number" name="min_age" id="srv_min" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">السن الأقصى</label>
                    <input type="number" name="max_age" id="srv_max" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">المراحل التعليمية المرتبطة (مفصولة بفاصلة)</label>
                <input type="text" name="education_stages" id="srv_stages" placeholder="secondary,preparatory" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono">
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-3 border-t border-slate-100">
                <button type="button" onclick="closeServiceModal()" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
                    إلغاء
                </button>
                <button type="submit" class="px-5 py-2 bg-church-navy text-white rounded-xl text-sm font-bold hover:bg-church-blue">
                    حفظ الخدمة
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const roleLabels = {
        'service_admin': '👑 أمين خدمة',
        'assistant_admin': '⭐ أمين مساعد',
        'servant': '👥 خادم',
        'supervisor': 'مسؤول متابعة'
    };
    const roleColors = {
        'service_admin': 'bg-amber-100 text-amber-800 border-amber-200',
        'assistant_admin': 'bg-blue-100 text-blue-800 border-blue-200',
        'servant': 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'supervisor': 'bg-purple-100 text-purple-800 border-purple-200'
    };

    function openServantsModal(serviceId, serviceName, members) {
        document.getElementById('servantsModalTitle').textContent = 'خدام خدمة: ' + serviceName;
        document.getElementById('assign_service_id').value = serviceId;
        const container = document.getElementById('servantsListContainer');
        container.innerHTML = '';

        if (!members || members.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">لا يوجد خدام معينون في هذا الفصل حتى الآن. يمكنك تعيين خادم بالأسفل.</div>';
        } else {
            members.forEach(m => {
                const name = (m.first_name + ' ' + (m.middle_name || '') + ' ' + (m.last_name || '')).trim();
                const roleBadge = roleLabels[m.role] || m.role;
                const badgeClass = roleColors[m.role] || 'bg-slate-100 text-slate-700 border-slate-200';
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-xs';
                div.innerHTML = `
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <span class="font-bold text-slate-800">${name}</span>
                        <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold ${badgeClass}">${roleBadge}</span>
                        ${m.phone ? `<span class="text-slate-400 text-[10px]">${m.phone}</span>` : ''}
                    </div>
                    <form method="POST" action="services.php" onsubmit="return confirm('هل أنت متأكد من إزالة الخادم من هذه الخدمة؟')">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="remove_servant" value="1">
                        <input type="hidden" name="member_id" value="${m.id}">
                        <button type="submit" class="p-1 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition" title="إزالة">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                `;
                container.appendChild(div);
            });
        }

        document.getElementById('servantsModal').classList.remove('hidden');
    }

    function closeServantsModal() {
        document.getElementById('servantsModal').classList.add('hidden');
    }

    function openServiceModal() {
        document.getElementById('srvModalTitle').textContent = 'إضافة خدمة جديدة';
        document.getElementById('srv_id').value = '';
        document.getElementById('srv_name').value = '';
        document.getElementById('srv_desc').value = '';
        document.getElementById('srv_min').value = '';
        document.getElementById('srv_max').value = '';
        document.getElementById('srv_stages').value = '';
        document.getElementById('serviceModal').classList.remove('hidden');
    }

    function editService(s) {
        document.getElementById('srvModalTitle').textContent = 'تعديل خدمة: ' + s.name;
        document.getElementById('srv_id').value = s.id || '';
        document.getElementById('srv_name').value = s.name || '';
        document.getElementById('srv_desc').value = s.description || '';
        document.getElementById('srv_min').value = s.min_age || '';
        document.getElementById('srv_max').value = s.max_age || '';
        document.getElementById('srv_stages').value = s.education_stages || '';
        document.getElementById('serviceModal').classList.remove('hidden');
    }

    function closeServiceModal() {
        document.getElementById('serviceModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
