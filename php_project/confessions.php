<?php
define('PAGE_TITLE', 'سجل ومتابعة الاعترافات');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_permission('confessions');
$user = current_user();

// Handle Add / Edit Confession POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $person_id = (int)($_POST['person_id'] ?? 0);
    $priest_id = !empty($_POST['priest_id']) ? (int)$_POST['priest_id'] : null;
    $confession_date = !empty($_POST['confession_date']) ? $_POST['confession_date'] : date('Y-m-d H:i:s');
    $next_date = !empty($_POST['next_confession_date']) ? $_POST['next_confession_date'] : null;
    $status = $_POST['status'] ?? 'completed';
    $notes = trim($_POST['notes'] ?? '');

    if (!$person_id) {
        set_flash('error', 'يرجى اختيار المخدوم.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO confessions (person_id, priest_id, confession_date, next_confession_date, status, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$person_id, $priest_id, $confession_date, $next_date, $status, $notes]);
        record_audit($pdo, 'create', 'confessions', $pdo->lastInsertId());
        set_flash('success', 'تم تسجيل جلسة الاعتراف بنجاح.');
        header("Location: confessions.php");
        exit;
    }
}

// Fetch Priests for filter/modal
$priests = $pdo->query("SELECT * FROM priests WHERE is_active = 1 ORDER BY display_name ASC")->fetchAll();

// Fetch Persons for dropdown (مع أب الاعتراف المسجل لاختياره تلقائياً)
$persons_list = $pdo->query("SELECT id, first_name, middle_name, last_name, phone, confession_father, confession_church FROM persons WHERE is_active = 1 ORDER BY first_name ASC")->fetchAll();

// Filter
$priest_filter = $_GET['priest_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "
    SELECT c.*, p.first_name, p.middle_name, p.last_name, p.phone, pr.display_name AS priest_name
    FROM confessions c
    JOIN persons p ON c.person_id = p.id
    LEFT JOIN priests pr ON c.priest_id = pr.id
    WHERE 1=1
";
$params = [];

if (!empty($priest_filter)) {
    $sql .= " AND c.priest_id = ?";
    $params[] = $priest_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY c.confession_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$confessions = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">سجل ومتابعة جلسات الاعتراف</h1>
        <p class="text-xs text-slate-500 mt-1">متابعة انتظام المخدومين في سر الاعتراف، المواعيد الدورية والكهنة</p>
    </div>
    <div>
        <button onclick="openConfessionModal()" class="inline-flex items-center px-4 py-2.5 rounded-xl gold-gradient text-church-navy font-bold text-sm shadow-md hover:brightness-105 transition">
            <i class="fas fa-plus-circle ml-2"></i>
            تسجيل جلسة اعتراف جديدة
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 mb-6 flex flex-wrap gap-3 items-center">
    <form method="GET" action="confessions.php" class="flex flex-wrap gap-3 w-full sm:w-auto flex-1">
        <select name="priest_id" class="p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
            <option value="">جميع الآباء الكهنة</option>
            <?php foreach ($priests as $pr): ?>
                <option value="<?= $pr['id'] ?>" <?= $priest_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['display_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
            <option value="">جميع الحالات</option>
            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>تم الاعتراف</option>
            <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>موعد قادم</option>
            <option value="missed" <?= $status_filter === 'missed' ? 'selected' : '' ?>>متأخر عن الموعد</option>
        </select>

        <button type="submit" class="px-4 py-2 bg-church-navy text-white rounded-xl text-xs font-bold hover:bg-church-blue">
            تطبيق
        </button>

        <?php if (!empty($priest_filter) || !empty($status_filter)): ?>
            <a href="confessions.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200">
                إلغاء الفلتر
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Confessions Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase">
                    <th class="p-4">اسم المعترف</th>
                    <th class="p-4">أب الاعتراف</th>
                    <th class="p-4">تاريخ الجلسة</th>
                    <th class="p-4">الموعد القادم</th>
                    <th class="p-4">الحالة</th>
                    <th class="p-4">الملاحظات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($confessions)): ?>
                <tr>
                    <td colspan="6" class="p-10 text-center text-slate-400">
                        <i class="fas fa-hands-praying text-3xl mb-3 block text-slate-300"></i>
                        لا توجد جلسات اعتراف مسجلة مطابقة للبحث.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($confessions as $c): ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4 font-bold text-slate-900">
                            <a href="person-details.php?id=<?= $c['person_id'] ?>" class="hover:text-church-blue">
                                <?= htmlspecialchars($c['first_name'] . ' ' . $c['middle_name'] . ' ' . $c['last_name']) ?>
                            </a>
                            <span class="block text-xs font-mono text-slate-400 mt-0.5" dir="ltr"><?= htmlspecialchars($c['phone'] ?: '') ?></span>
                        </td>
                        <td class="p-4 text-slate-700 font-medium text-xs">
                            <?= htmlspecialchars($c['priest_name'] ?: 'غير محدد') ?>
                        </td>
                        <td class="p-4 font-bold text-slate-800 text-xs">
                            <?= format_datetime($c['confession_date']) ?>
                        </td>
                        <td class="p-4 text-slate-500 text-xs">
                            <?= format_date($c['next_confession_date']) ?>
                        </td>
                        <td class="p-4">
                            <?php if ($c['status'] === 'completed'): ?>
                                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">تم بنجاح</span>
                            <?php elseif ($c['status'] === 'missed'): ?>
                                <span class="px-2.5 py-0.5 bg-rose-100 text-rose-800 rounded-full text-xs font-bold">متأخر</span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-bold">قادم</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-xs text-slate-500">
                            <?= htmlspecialchars($c['notes'] ?: '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Confession -->
<div id="confessionModal" class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">تسجيل جلسة اعتراف</h3>
            <button onclick="document.getElementById('confessionModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <form method="POST" action="confessions.php" class="space-y-4 pt-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">المخدوم المعترف *</label>
                <input type="text" id="conf_person_search" placeholder="🔍 اكتب اسم أو رقم هاتف لتصفية القائمة..." oninput="filterPersonOptions(this.value)" class="w-full mb-1.5 p-2 bg-slate-100 border border-slate-200 rounded-lg text-xs focus:bg-white focus:border-church-blue outline-none transition">
                <select name="person_id" id="conf_person_id" onchange="onConfessionPersonChange(this)" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    <option value="">اختر المخدوم</option>
                    <?php foreach ($persons_list as $pl): ?>
                        <?php $pname = trim($pl['first_name'] . ' ' . ($pl['middle_name'] ?? '') . ' ' . ($pl['last_name'] ?? '')); ?>
                        <option value="<?= $pl['id'] ?>" 
                                data-confession="<?= htmlspecialchars($pl['confession_father'] ?? '') ?>"
                                data-church="<?= htmlspecialchars($pl['confession_church'] ?? '') ?>"
                                <?= (isset($_GET['person_id']) && $_GET['person_id'] == $pl['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pname) ?> <?= $pl['phone'] ? '('.$pl['phone'].')' : '' ?> <?= $pl['confession_father'] ? '— أب الاعتراف: ' . htmlspecialchars($pl['confession_father']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">أب الاعتراف (يتحدد تلقائياً حسب المخدوم أو اختر)</label>
                <select name="priest_id" id="conf_priest_id" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-church-navy">
                    <option value="">اختر الأب الكاهن</option>
                    <?php foreach ($priests as $pr): ?>
                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ الاعتراف</label>
                    <input type="datetime-local" name="confession_date" value="<?= date('Y-m-d\TH:i') ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الموعد القادم المقترح</label>
                    <input type="date" name="next_confession_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الحالة</label>
                <select name="status" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    <option value="completed">تم الاعتراف</option>
                    <option value="upcoming">موعد قادم</option>
                    <option value="missed">متأخر</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">ملاحظات روحية سرية</label>
                <textarea name="notes" rows="2" placeholder="ملاحظات سرية خاصة بجلسة الاعتراف..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 space-x-reverse pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('confessionModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200">
                    إلغاء
                </button>
                <button type="submit" class="px-5 py-2 bg-church-navy text-white rounded-xl text-sm font-bold hover:bg-church-blue">
                    تسجيل الجلسة
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openConfessionModal() {
        document.getElementById('confessionModal').classList.remove('hidden');
        onConfessionPersonChange(document.getElementById('conf_person_id'));
    }

    function onConfessionPersonChange(select) {
        if (!select) return;
        const opt = select.options[select.selectedIndex];
        if (!opt) return;
        const father = (opt.getAttribute('data-confession') || '').trim();
        const priestSelect = document.getElementById('conf_priest_id');
        if (!priestSelect) return;

        if (!father) {
            return;
        }

        for (let i = 0; i < priestSelect.options.length; i++) {
            const txt = priestSelect.options[i].text.trim();
            if (txt && (txt.includes(father) || father.includes(txt) || 
                (father.includes('سارافيم') && txt.includes('سارافيم')) ||
                (father.includes('سيرافيم') && txt.includes('سارافيم')) ||
                (father.includes('جوزيف') && txt.includes('جوزيف')))) {
                priestSelect.selectedIndex = i;
                break;
            }
        }
    }

    function filterPersonOptions(term) {
        term = term.trim().toLowerCase();
        const select = document.getElementById('conf_person_id');
        const options = select.options;
        for (let i = 1; i < options.length; i++) {
            const txt = options[i].text.toLowerCase();
            options[i].style.display = txt.includes(term) ? '' : 'none';
        }
    }

    <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
        window.addEventListener('DOMContentLoaded', openConfessionModal);
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
