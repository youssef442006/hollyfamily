<?php
define('PAGE_TITLE', 'كشف التكرار ودمج السجلات');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_role(['admin', 'priest', 'service_admin']);
$user = current_user();

// Handle Merge POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge'])) {
    require_csrf();
    $primary_id = (int)$_POST['primary_id'];
    $duplicate_id = (int)$_POST['duplicate_id'];

    if ($primary_id && $duplicate_id && $primary_id !== $duplicate_id) {
        try {
            $pdo->beginTransaction();

            // Reassign confessions
            $pdo->prepare("UPDATE confessions SET person_id = ? WHERE person_id = ?")->execute([$primary_id, $duplicate_id]);

            // Reassign visits (as subject)
            $pdo->prepare("UPDATE visits SET person_id = ? WHERE person_id = ?")->execute([$primary_id, $duplicate_id]);

            // Reassign services
            $pdo->prepare("UPDATE service_assignments SET person_id = ? WHERE person_id = ?")->execute([$primary_id, $duplicate_id]);

            // Archive or delete duplicate person
            $pdo->prepare("UPDATE persons SET is_archived = 1, is_active = 0, notes = CONCAT(IFNULL(notes,''), ' [تم الدمج مع الفرد رقم ', ?, ']') WHERE id = ?")
                ->execute([$primary_id, $duplicate_id]);

            $pdo->commit();
            record_audit($pdo, 'merge_persons', 'persons', $primary_id, "Duplicate #$duplicate_id merged into #$primary_id");
            set_flash('success', "تم دمج السجل #$duplicate_id بنجاح في السجل الرئيسي #$primary_id.");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'حدث خطأ أثناء الدمج: ' . $e->getMessage());
        }
        header("Location: duplicates.php");
        exit;
    }
}

// 1. Find duplicates by National ID
$dup_nids = $pdo->query("
    SELECT national_id, COUNT(*) as cnt 
    FROM persons 
    WHERE national_id IS NOT NULL AND national_id != '' AND is_active = 1 AND is_archived = 0 
    GROUP BY national_id 
    HAVING cnt > 1
")->fetchAll();

// 2. Find duplicates by Phone
$dup_phones = $pdo->query("
    SELECT phone, COUNT(*) as cnt 
    FROM persons 
    WHERE phone IS NOT NULL AND phone != '' AND is_active = 1 AND is_archived = 0 
    GROUP BY phone 
    HAVING cnt > 1
")->fetchAll();

// 3. Find duplicates by Full Compound Name (الاسم الأول واسم الأب معاً)
$dup_names = $pdo->query("
    SELECT TRIM(CONCAT(first_name, ' ', IFNULL(middle_name, ''))) AS compound_name, COUNT(*) as cnt 
    FROM persons 
    WHERE is_active = 1 AND is_archived = 0 AND first_name != ''
    GROUP BY compound_name 
    HAVING cnt > 1
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">كشف التكرار ودمج السجلات</h1>
    <p class="text-xs text-slate-500 mt-1">فحص التكرارات في قاعدة البيانات بالرقم القومي والتليفون والاسم ودمج السجلات تلقائياً</p>
</div>

<!-- Duplicate Detection Overview -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80">
        <span class="text-xs font-bold text-slate-500">تكرار بالرقم القومي</span>
        <h3 class="text-2xl font-extrabold text-rose-600 mt-1"><?= count($dup_nids) ?> حالات</h3>
        <p class="text-[11px] text-slate-400 mt-1">تطابق تام في الرقم القومي</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80">
        <span class="text-xs font-bold text-slate-500">تكرار برقم التليفون</span>
        <h3 class="text-2xl font-extrabold text-amber-600 mt-1"><?= count($dup_phones) ?> حالات</h3>
        <p class="text-[11px] text-slate-400 mt-1">نفس رقم الموبايل مسجل لأكثر من فرد</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80">
        <span class="text-xs font-bold text-slate-500">تكرار بالاسم الثنائي</span>
        <h3 class="text-2xl font-extrabold text-church-navy mt-1"><?= count($dup_names) ?> حالات</h3>
        <p class="text-[11px] text-slate-400 mt-1">تطابق الاسم الأول واسم العائلة</p>
    </div>
</div>

<?php if (empty($dup_nids) && empty($dup_phones) && empty($dup_names)): ?>
<div class="bg-white rounded-3xl p-12 text-center shadow-sm border border-slate-200/80">
    <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center text-2xl mb-4">
        <i class="fas fa-check-double"></i>
    </div>
    <h3 class="text-lg font-bold text-slate-900">قاعدة البيانات سليمة تماماً!</h3>
    <p class="text-sm text-slate-500 mt-1">لم يتم العثور على أي سجلات مكررة في النظام حالياً.</p>
</div>
<?php else: ?>

<!-- Duplicate Name Items List -->
<div class="space-y-6">
    <?php foreach ($dup_names as $dn): 
        $stmt = $pdo->prepare("
            SELECT p.*, f.family_name 
            FROM persons p 
            LEFT JOIN families f ON p.family_id = f.id 
            WHERE TRIM(CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''))) = ? AND p.is_active = 1 AND p.is_archived = 0
        ");
        $stmt->execute([$dn['compound_name']]);
        $matches = $stmt->fetchAll();
        if (count($matches) < 2) continue;
    ?>
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base">
                تشابه في الاسم: <span class="text-church-blue"><?= htmlspecialchars($dn['compound_name']) ?></span>
                (<?= count($matches) ?> سجلات)
            </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($matches as $idx => $m): ?>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/70 text-xs space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="font-mono font-bold text-slate-400">سجل #<?= $m['id'] ?></span>
                    <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($m['first_name'] . ' ' . $m['middle_name'] . ' ' . $m['last_name']) ?></span>
                </div>
                <p class="text-slate-500">الأسرة: <?= htmlspecialchars($m['family_name'] ?: 'بدون أسرة') ?></p>
                <p class="text-slate-500">الميلاد: <?= format_date($m['date_of_birth']) ?> (<?= calc_age($m['date_of_birth']) ?> سنة)</p>
                <p class="text-slate-500">التليفون: <?= htmlspecialchars($m['phone'] ?: '-') ?></p>
                <p class="text-slate-500">أب الاعتراف: <?= htmlspecialchars($m['confession_father'] ?: '-') ?></p>
                
                <div class="pt-2 flex justify-end">
                    <a href="person-details.php?id=<?= $m['id'] ?>" class="text-church-blue font-bold hover:underline">
                        عرض الملف الكامل
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Merge Action Form -->
        <form method="POST" action="duplicates.php" class="mt-4 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-amber-50/50 p-3 rounded-xl">
            <?= csrf_field() ?>
            <input type="hidden" name="merge" value="1">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-bold text-slate-700">دمج السجل:</span>
                <select name="duplicate_id" class="p-1.5 bg-white border border-slate-300 rounded-lg font-mono text-xs">
                    <?php foreach ($matches as $m): ?>
                        <option value="<?= $m['id'] ?>">#<?= $m['id'] ?> (<?= htmlspecialchars(trim($m['first_name'] . ' ' . ($m['middle_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <span class="font-bold text-slate-700">في السجل الرئيسي:</span>
                <select name="primary_id" class="p-1.5 bg-white border border-slate-300 rounded-lg font-mono text-xs">
                    <?php foreach ($matches as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $m === $matches[0] ? 'selected' : '' ?>>#<?= $m['id'] ?> (الرئيسي)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" onclick="return confirm('هل أنت متأكد من دمج السجلين ونقل كل البيانات؟')" class="px-4 py-2 bg-church-navy text-white text-xs font-bold rounded-lg hover:bg-church-blue transition">
                تنفيذ الدمج
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
