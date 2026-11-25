<?php
define('PAGE_TITLE', 'تغيير كلمة المرور');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$is_forced = !empty($_SESSION['must_change_password']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current_pw = $_POST['current_password'] ?? '';
    $new_pw     = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT hashed_password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $db_user = $stmt->fetch();

    if (!$is_forced && (!$db_user || !password_verify($current_pw, $db_user['hashed_password']))) {
        $error = 'كلمة المرور الحالية غير صحيحة.';
    } elseif (strlen($new_pw) < 4) {
        $error = 'يجب أن تتكون كلمة المرور الجديدة من 4 أحرف أو أرقام على الأقل.';
    } elseif ($new_pw === '1') {
        $error = 'لا يمكن استخدام كلمة المرور المؤقتة (1) ككلمة مرور دائمة. اختر كلمة مرور أخرى.';
    } elseif ($new_pw !== $confirm_pw) {
        $error = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
    } else {
        $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET hashed_password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?")
            ->execute([$hashed, $user['id']]);
        $_SESSION['must_change_password'] = false;

        record_audit($pdo, 'change_password', 'users', $user['id'], null, 'تغيير كلمة المرور: ' . $user['full_name']);
        set_flash('success', 'تم تحديث كلمة المرور بنجاح!');
        header("Location: index.php");
        exit;
    }
}

if ($is_forced):
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعيين كلمة مرور جديدة | نظام إدارة الكنيسة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php $css_ver = @filemtime(__DIR__ . '/assets/css/tailwind.min.css') ?: time(); ?>
    <link rel="stylesheet" href="assets/css/tailwind.min.css?v=<?= $css_ver ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        html, body { font-family: 'Cairo', sans-serif; overflow-x: hidden; max-width: 100%; background: linear-gradient(135deg, #0f1b36 0%, #15264a 55%, #1e3766 100%); }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 border border-slate-100">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-amber-50 text-[#c9a44c] flex items-center justify-center border border-amber-200">
                <i class="fas fa-key text-2xl"></i>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900">تعيين كلمة مرور جديدة</h1>
            <p class="text-xs text-slate-500 mt-1">أهلاً بك يا <span class="font-bold text-church-navy"><?= htmlspecialchars($user['full_name']) ?></span></p>
        </div>

        <div class="mb-5 p-3.5 bg-amber-50 border border-amber-200 text-amber-900 text-xs rounded-2xl leading-relaxed">
            <i class="fas fa-shield-alt ml-1.5 text-amber-600"></i>
            لأمان حسابك، لأنك تستخدم كلمة المرور المؤقتة، يرجى تعيين كلمة مرور خاصة بك للمتابعة إلى النظام.
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-bold rounded-xl">
                <i class="fas fa-circle-exclamation ml-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="change-password.php?forced=1" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور الجديدة *</label>
                <input type="password" name="new_password" required minlength="4" autofocus placeholder="4 أحرف أو أرقام على الأقل" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">تأكيد كلمة المرور الجديدة *</label>
                <input type="password" name="confirm_password" required minlength="4" placeholder="أعد كتابة كلمة المرور الجديدة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>
            <button type="submit" class="w-full py-3 gold-gradient text-church-navy font-extrabold rounded-xl text-sm shadow-md hover:brightness-105 transition">
                حفظ كلمة المرور والدخول للنظام
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="logout.php" class="text-xs text-slate-400 hover:text-rose-500 font-semibold">
                <i class="fas fa-sign-out-alt ml-1"></i> تسجيل الخروج
            </a>
        </div>
    </div>
</body>
</html>
<?php
    exit;
endif;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">تغيير كلمة المرور</h1>
        <p class="text-xs text-slate-500 mt-1">تحديث كلمة المرور الخاصة بحسابك (<?= htmlspecialchars($user['full_name']) ?>)</p>
    </div>

    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80">
        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3.5 bg-red-50 border border-red-200 text-red-700 text-xs font-bold rounded-xl flex items-center">
                <i class="fas fa-circle-exclamation ml-2 text-sm"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="change-password.php" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور الحالية *</label>
                <input type="password" name="current_password" required placeholder="أدخل كلمة المرور الحالية" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور الجديدة *</label>
                <input type="password" name="new_password" required minlength="4" placeholder="4 أحرف أو أرقام على الأقل" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">تأكيد كلمة المرور الجديدة *</label>
                <input type="password" name="confirm_password" required minlength="4" placeholder="أعد كتابة كلمة المرور الجديدة" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            </div>
            <div class="pt-2 flex items-center justify-end space-x-2 space-x-reverse">
                <a href="index.php" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">إلغاء</a>
                <button type="submit" class="px-6 py-2.5 gold-gradient text-church-navy font-extrabold rounded-xl text-xs shadow-md hover:brightness-105 transition">
                    <i class="fas fa-check ml-1.5"></i> حفظ كلمة المرور الجديدة
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
