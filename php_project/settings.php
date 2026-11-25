<?php
define('PAGE_TITLE', 'إدارة المستخدمين والصلاحيات');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role(['admin']);
$user = current_user();

// Handle Add / Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    require_csrf();
    $u_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'servant';

    if (empty($username) || empty($full_name)) {
        set_flash('error', 'يرجى إدخال اسم المستخدم والاسم بالكامل.');
    } else {
        if ($u_id) {
            // Update existing user (name, email, role)
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$full_name, $email ?: null, $role, $u_id]);
            record_audit($pdo, 'update_user', 'users', $u_id, null, "تحديث المستخدم: $full_name ($role)");
            set_flash('success', 'تم تحديث بيانات وصلاحية المستخدم بنجاح.');
        } else {
            // Insert new user: كلمة المرور المؤقتة تلقائياً "1" ويجب تغييرها عند الدخول
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                set_flash('error', 'اسم الدخول مسجل مسبقاً، يرجى اختيار اسم آخر.');
            } else {
                $temp_hashed = password_hash('1', PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, role, hashed_password, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 1, 1)");
                $stmt->execute([$username, $full_name, $email ?: null, $role, $temp_hashed]);
                $new_uid = $pdo->lastInsertId();
                record_audit($pdo, 'create_user', 'users', $new_uid, null, "إضافة مستخدم جديد: $full_name ($role)");
                set_flash('success', "تمت إضافة المستخدم ($full_name) بنجاح. كلمة المرور المؤقتة هي (1) وسيُجبر على تغييرها عند أول دخول.");
            }
        }
        header("Location: settings.php");
        exit;
    }
}

// Handle Reset Password to Temporary '1'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_id'])) {
    require_csrf();
    $rid = (int)$_POST['reset_password_id'];
    if ($rid !== $user['id']) {
        $temp_hashed = password_hash('1', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET hashed_password = ?, must_change_password = 1 WHERE id = ?")->execute([$temp_hashed, $rid]);
        record_audit($pdo, 'reset_password', 'users', $rid, null, 'إعادة تعيين كلمة المرور إلى المؤقتة (1)');
        set_flash('success', 'تمت إعادة تعيين كلمة المرور إلى (1) وسيُطلب من المستخدم تغييرها فور دخوله.');
    } else {
        set_flash('error', 'يمكنك تغيير كلمة مرورك من صفحة "تغيير كلمة المرور" في القائمة.');
    }
    header("Location: settings.php");
    exit;
}

// Handle Toggle User Active (POST only + CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    require_csrf();
    $tid = (int)$_POST['toggle_user_id'];
    if ($tid !== $user['id']) {
        $pdo->prepare("UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id = ?")->execute([$tid]);
        record_audit($pdo, 'toggle_user_status', 'users', $tid, null, 'تغيير حالة تفعيل/إيقاف المستخدم');
        set_flash('success', 'تم تغيير حالة المستخدم.');
    } else {
        set_flash('error', 'لا يمكنك إيقاف حسابك الحالي.');
    }
    header("Location: settings.php");
    exit;
}

// Handle Clear Data (مع الحفاظ على الخدمات والمستخدمين والكهنة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_data'])) {
    require_csrf();
    try {
        $pdo->beginTransaction();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DELETE FROM service_assignments;");
        $pdo->exec("DELETE FROM servant_assignments;");
        $pdo->exec("DELETE FROM service_members;");
        $pdo->exec("DELETE FROM confessions;");
        $pdo->exec("DELETE FROM visits;");
        $pdo->exec("DELETE FROM person_relationships;");
        $pdo->exec("DELETE FROM family_members;");
        $pdo->exec("DELETE FROM persons;");
        $pdo->exec("DELETE FROM families;");
        $pdo->exec("UPDATE users SET person_id = NULL;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $pdo->commit();
        record_audit($pdo, 'clear_data', 'system', null, null, 'تفريغ بيانات الأسر والأفراد والافتقادات');
        set_flash('success', 'تم تفريغ بيانات الأسر والأفراد والافتقادات بنجاح، وتم الحفاظ الكامل على الخدمات والمستخدمين دون أي مساس!');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'حدث خطأ أثناء تفريغ البيانات: ' . $e->getMessage());
    }
    header("Location: settings.php");
    exit;
}

// Fetch Users List
$users = $pdo->query("SELECT * FROM users ORDER BY FIELD(role, 'admin', 'priest', 'service_admin', 'servant'), id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">إدارة المستخدمين والصلاحيات</h1>
        <p class="text-xs text-slate-500 mt-1">التحكم في حسابات الكنيسة، توزيع الصلاحيات، وإعادة تعيين كلمات المرور المؤقتة</p>
    </div>
    <button onclick="openUserModal()" class="inline-flex items-center px-4 py-2.5 rounded-xl gold-gradient text-church-navy font-bold text-sm shadow-md hover:brightness-105 transition">
        <i class="fas fa-user-plus ml-2"></i>
        إضافة مستخدم جديد
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Users Table (2 cols) -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900 flex items-center text-church-navy">
                <i class="fas fa-users-gear ml-2 text-church-gold"></i>
                حسابات المستخدمين المسجلة (<?= count($users) ?>)
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-bold text-slate-600">
                        <th class="p-3">المستخدم</th>
                        <th class="p-3">الرتبة والصلاحية</th>
                        <th class="p-3">كلمة المرور</th>
                        <th class="p-3">الحالة</th>
                        <th class="p-3 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-3">
                            <p class="font-bold text-slate-900"><?= htmlspecialchars($u['full_name']) ?></p>
                            <span class="font-mono text-[11px] text-slate-400">@<?= htmlspecialchars($u['username']) ?></span>
                        </td>
                        <td class="p-3">
                            <?= get_role_badge($u['role']) ?>
                        </td>
                        <td class="p-3 text-xs">
                            <?php if (!empty($u['must_change_password'])): ?>
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-md font-bold text-[11px]">مؤقتة (بانتظار التغيير)</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md font-semibold text-[11px]">مُعيّنة شخصياً</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3">
                            <?php if ($u['is_active']): ?>
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[11px] font-bold">نشط</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded-full text-[11px] font-bold">موقوف</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-center">
                            <div class="inline-flex items-center space-x-1 space-x-reverse">
                                <button onclick='editUser(<?= json_encode($u) ?>)' class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="تعديل الاسم أو الصلاحية">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($u['id'] !== $user['id']): ?>
                                <form method="POST" action="settings.php" class="inline" onsubmit="return confirm('هل تريد إعادة تعيين كلمة مرور هذا المستخدم إلى (1) وإجباره على تغييرها؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reset_password_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="إعادة تعيين كلمة المرور إلى 1">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </form>
                                <form method="POST" action="settings.php" class="inline" onsubmit="return confirm('تغيير حالة الحساب؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="toggle_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="p-1.5 <?= $u['is_active'] ? 'text-rose-500 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50' ?> rounded-lg" title="<?= $u['is_active'] ? 'إيقاف الحساب' : 'تفعيل الحساب' ?>">
                                        <i class="fas <?= $u['is_active'] ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Permissions Guide Card (1 col) -->
    <div class="lg:col-span-1 bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <h2 class="text-base font-bold text-slate-900 pb-3 border-b border-slate-100 flex items-center text-church-navy">
            <i class="fas fa-shield-halved ml-2 text-church-gold"></i>
            دليل الصلاحيات لكل رتبة
        </h2>

        <div class="space-y-3 text-xs">
            <div class="p-3 bg-red-50/70 rounded-2xl border border-red-100">
                <p class="font-extrabold text-red-900 mb-1">👑 مدير النظام</p>
                <p class="text-red-800/80 leading-relaxed">صلاحية كاملة على كافة الشاشات + إدارة المستخدمين والصلاحيات + الاطلاع على سجل النشاطات.</p>
            </div>
            <div class="p-3 bg-amber-50/70 rounded-2xl border border-amber-100">
                <p class="font-extrabold text-amber-900 mb-1">✝️ أب كاهن</p>
                <p class="text-amber-800/80 leading-relaxed">كافة البيانات الرعوية: الأسر، المخدومين، مدارس الأحد، <strong>سجل الاعترافات السري</strong>، الافتقاد، التكرار، والتقارير.</p>
            </div>
            <div class="p-3 bg-blue-50/70 rounded-2xl border border-blue-100">
                <p class="font-extrabold text-blue-900 mb-1">⭐ أمين خدمة</p>
                <p class="text-blue-800/80 leading-relaxed">إدارة الأسر والمخدومين، تنظيم الفصول وتعيين الخدام، الافتقاد، التكرار، والتقارير (بدون الاعترافات السرية).</p>
            </div>
            <div class="p-3 bg-emerald-50/70 rounded-2xl border border-emerald-100">
                <p class="font-extrabold text-emerald-900 mb-1">📝 مدخل بيانات / خادم</p>
                <p class="text-emerald-800/80 leading-relaxed">إضافة وتعديل بيانات الأسر والمخدومين وتسجيل الافتقادات فقط (بدون حذف أو اعترافات أو تقارير أو إدارة).</p>
            </div>
        </div>
    </div>

</div>

<!-- Data Management Section (Clear Data Safely) -->
<div class="bg-white rounded-3xl p-6 shadow-sm border border-red-200/80 mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 space-x-reverse text-red-600 mb-1">
                <i class="fas fa-triangle-exclamation text-lg"></i>
                <h3 class="font-bold text-base">تفريغ بيانات الأسر والأفراد (إعادة التهيئة)</h3>
            </div>
            <p class="text-xs text-slate-500 leading-relaxed max-w-2xl">
                يقوم هذا الإجراء بمسح بيانات الأسر والمخدومين وسجلات الافتقاد والاعترافات.
                <strong class="text-emerald-700">ملاحظة أمان: قطاعات ومدارس الأحد (الخدمات) وحسابات المستخدمين والكهنة تظل محفوظة بالكامل ولن يتم حذفها إطلاقاً.</strong>
            </p>
        </div>
        <form method="POST" action="settings.php" id="clearDataForm" onsubmit="return confirmClearData(event)">
            <?= csrf_field() ?>
            <input type="hidden" name="clear_data" value="1">
            <button type="submit" class="px-5 py-2.5 bg-red-600 text-white font-bold text-xs rounded-xl hover:bg-red-700 transition shadow-sm flex items-center space-x-2 space-x-reverse whitespace-nowrap">
                <i class="fas fa-trash-can ml-1.5"></i>
                تفريغ البيانات (مع الحفاظ على الخدمات)
            </button>
        </form>
    </div>
</div>

<script>
    function confirmClearData(e) {
        e.preventDefault();
        Swal.fire({
            title: 'هل أنت متأكد من تفريغ البيانات؟',
            html: '<p class="text-sm text-slate-600">سيتم مسح بيانات الأسر والمخدومين المسجلين فقط.<br><strong class="text-emerald-600">الخدمات ومدارس الأحد ستظل محفوظة ولن تُحذف!</strong></p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#15264a',
            confirmButtonText: 'نعم، قم بالتفريغ',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('clearDataForm').submit();
            }
        });
        return false;
    }
</script>

<!-- Modal: Add / Edit User -->
<div id="userModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="uModalTitle" class="text-lg font-bold text-slate-900">إضافة مستخدم جديد</h3>
            <button onclick="document.getElementById('userModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="settings.php" class="space-y-4 pt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="save_user" value="1">
            <input type="hidden" name="user_id" id="u_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">اسم الدخول (Username) *</label>
                <input type="text" name="username" id="u_username" required placeholder="مثال: mina_adel" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الاسم بالكامل *</label>
                <input type="text" name="full_name" id="u_fullname" required placeholder="مثال: مينا عادل" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني (اختياري)</label>
                <input type="email" name="email" id="u_email" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الرتبة والصلاحية *</label>
                <select name="role" id="u_role" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="servant">مدخل بيانات / خادم (إدخال بيانات الأسر والافتقاد فقط)</option>
                    <option value="service_admin">أمين خدمة (الكل ما عدا الاعترافات والإعدادات)</option>
                    <option value="priest">أب كاهن (كافة البيانات الرعوية والاعترافات)</option>
                    <option value="admin">مدير النظام (صلاحية كاملة)</option>
                </select>
            </div>

            <div id="tempPwNotice" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900">
                <i class="fas fa-key ml-1 text-amber-600"></i>
                سيتم إنشاء الحساب تلقائياً بكلمة مرور مؤقتة: <strong class="font-mono text-sm">1</strong>، وسيُجبر المستخدم على تغييرها فور أول تسجيل دخول.
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('userModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
                    إلغاء
                </button>
                <button type="submit" class="px-5 py-2 bg-church-navy text-white rounded-xl text-sm font-bold hover:bg-church-blue">
                    حفظ الحساب
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUserModal() {
        document.getElementById('uModalTitle').textContent = 'إضافة مستخدم جديد';
        document.getElementById('u_id').value = '';
        document.getElementById('u_username').value = '';
        document.getElementById('u_username').readOnly = false;
        document.getElementById('u_fullname').value = '';
        document.getElementById('u_email').value = '';
        document.getElementById('u_role').value = 'servant';
        document.getElementById('tempPwNotice').classList.remove('hidden');
        document.getElementById('userModal').classList.remove('hidden');
    }

    function editUser(u) {
        document.getElementById('uModalTitle').textContent = 'تعديل مستخدم: ' + u.full_name;
        document.getElementById('u_id').value = u.id || '';
        document.getElementById('u_username').value = u.username || '';
        document.getElementById('u_username').readOnly = true;
        document.getElementById('u_fullname').value = u.full_name || '';
        document.getElementById('u_email').value = u.email || '';
        document.getElementById('u_role').value = u.role || 'servant';
        document.getElementById('tempPwNotice').classList.add('hidden');
        document.getElementById('userModal').classList.remove('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
