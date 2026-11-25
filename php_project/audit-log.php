<?php
define('PAGE_TITLE', 'سجل النشاطات والرقابة');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

// متاح فقط لمدير النظام (يوسف عاطف)
require_role(['admin']);
$user = current_user();

$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';

$sql = "
    SELECT a.*, u.full_name, u.username, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($user_filter)) {
    $sql .= " AND a.user_id = ?";
    $params[] = (int)$user_filter;
}
if (!empty($action_filter)) {
    $sql .= " AND a.action = ?";
    $params[] = $action_filter;
}

$sql .= " ORDER BY a.timestamp DESC, a.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch users for filter
$users_list = $pdo->query("SELECT id, full_name FROM users ORDER BY id ASC")->fetchAll();

// Action label mapping
$action_labels = [
    'login'              => ['تسجيل دخول', 'bg-blue-50 text-blue-700 border-blue-200', 'fa-right-to-bracket'],
    'change_password'    => ['تغيير كلمة المرور', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-key'],
    'create_user'        => ['إضافة مستخدم', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-user-plus'],
    'update_user'        => ['تعديل مستخدم', 'bg-indigo-50 text-indigo-700 border-indigo-200', 'fa-user-pen'],
    'reset_password'     => ['إعادة تعيين كلمة مرور', 'bg-amber-50 text-amber-800 border-amber-200', 'fa-rotate'],
    'toggle_user_status' => ['تغيير حالة مستخدم', 'bg-slate-100 text-slate-700 border-slate-200', 'fa-power-off'],
    'create'             => ['إضافة سجل', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-plus-circle'],
    'update'             => ['تعديل بيانات', 'bg-indigo-50 text-indigo-700 border-indigo-200', 'fa-pen'],
    'delete'             => ['حذف سجل', 'bg-rose-50 text-rose-700 border-rose-200', 'fa-trash'],
    'merge_persons'      => ['دمج سجلات مكررة', 'bg-purple-50 text-purple-700 border-purple-200', 'fa-code-merge'],
    'clear_data'         => ['تفريغ بيانات', 'bg-red-100 text-red-800 border-red-200', 'fa-triangle-exclamation'],
];

$table_labels = [
    'users'       => 'المستخدمين',
    'families'    => 'الأسر',
    'persons'     => 'المخدومين والشعب',
    'visits'      => 'الافتقاد والزيارات',
    'confessions' => 'الاعترافات',
    'services'    => 'الخدمات ومدارس الأحد',
    'system'      => 'النظام',
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center">
            <i class="fas fa-clock-rotate-left ml-2.5 text-church-gold"></i>
            سجل النشاطات والرقابة
        </h1>
        <p class="text-xs text-slate-500 mt-1">متابعة جميع حركات الدخول، الإضافة، التعديل، والحذف في النظام (متاح لمدير النظام فقط)</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 mb-6">
    <form method="GET" action="audit-log.php" class="flex flex-wrap items-center gap-3">
        <select name="user_id" class="p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
            <option value="">جميع المستخدمين</option>
            <?php foreach ($users_list as $ul): ?>
                <option value="<?= $ul['id'] ?>" <?= $user_filter == $ul['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ul['full_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="action" class="p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
            <option value="">جميع النشاطات</option>
            <?php foreach ($action_labels as $ak => $av): ?>
                <option value="<?= $ak ?>" <?= $action_filter === $ak ? 'selected' : '' ?>><?= $av[0] ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="px-4 py-2 bg-church-navy text-white rounded-xl text-xs font-bold hover:bg-church-blue">
            تصفية
        </button>

        <?php if (!empty($user_filter) || !empty($action_filter)): ?>
            <a href="audit-log.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200">
                عرض الكل
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Logs Table -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse text-xs sm:text-sm">
            <thead>
                <tr class="bg-slate-50/90 border-b border-slate-200/80 text-xs font-bold text-slate-600">
                    <th class="p-4">التوقيت</th>
                    <th class="p-4">المستخدم</th>
                    <th class="p-4">النشاط / الحدث</th>
                    <th class="p-4">القسم</th>
                    <th class="p-4">التفاصيل</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" class="p-10 text-center text-slate-400">
                        <i class="fas fa-history text-3xl mb-3 block text-slate-300"></i>
                        لا توجد نشاطات مسجلة مطابقة للبحث.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $act = $action_labels[$log['action']] ?? [$log['action'], 'bg-slate-100 text-slate-700 border-slate-200', 'fa-circle-info'];
                        $tbl = $table_labels[$log['table_name']] ?? $log['table_name'];
                    ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4 font-mono text-xs text-slate-500 whitespace-nowrap" dir="ltr">
                            <?= date('Y-m-d h:i A', strtotime($log['timestamp'])) ?>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <span class="font-bold text-slate-900"><?= htmlspecialchars($log['full_name'] ?: 'النظام') ?></span>
                                <?php if (!empty($log['role'])): ?>
                                    <span class="text-[10px] text-slate-400">(<?= get_role_name($log['role']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold border <?= $act[1] ?>">
                                <i class="fas <?= $act[2] ?> ml-1.5"></i>
                                <?= htmlspecialchars($act[0]) ?>
                            </span>
                        </td>
                        <td class="p-4 text-xs font-semibold text-slate-700">
                            <?= htmlspecialchars($tbl) ?>
                            <?php if ($log['record_id']): ?>
                                <span class="text-slate-400 font-mono text-[11px]">#<?= $log['record_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-xs text-slate-600 max-w-md">
                            <?= htmlspecialchars($log['new_value'] ?: ($log['old_value'] ?: '-')) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
