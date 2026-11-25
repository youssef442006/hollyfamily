<?php
/**
 * Login Page
 * صفحة تسجيل الدخول بتصميم الكنيسة المخصص
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to index
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
$raw_redirect = $_GET['redirect'] ?? 'index.php';
// Validate redirect to prevent Open Redirect attacks (only allow local relative paths)
$redirect = 'index.php';
if (!empty($raw_redirect) && !preg_match('#^(\w+:)?//#i', $raw_redirect) && !str_starts_with($raw_redirect, '\\')) {
    $redirect = $raw_redirect;
}

// Fetch Active Users for Quick Selection Dropdown
$users_list = [];
try {
    $users_list = $pdo->query("SELECT username, full_name, role FROM users WHERE is_active = 1 ORDER BY FIELD(role, 'admin', 'priest', 'service_admin', 'servant'), id ASC")->fetchAll();
} catch (Exception $e) {
    $users_list = [];
}

$role_labels = [
    'admin' => 'مدير النظام (أدمن)',
    'priest' => 'كاهن',
    'service_admin' => 'أمين خدمة',
    'servant' => 'خادم',
];

$role_icons = [
    'admin' => '👑',
    'priest' => '✝️',
    'service_admin' => '⭐',
    'servant' => '👤',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['hashed_password'])) {
            if (!$user['is_active']) {
                $error = 'هذا الحساب موقوف حالياً، يرجى مراجعة إدارة الكنيسة';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['must_change_password'] = !empty($user['must_change_password']);

                // Update updated_at & record audit
                $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$user['id']]);
                record_audit($pdo, 'login', 'users', $user['id'], null, 'تسجيل دخول: ' . $user['full_name']);

                if ($_SESSION['must_change_password']) {
                    header("Location: change-password.php?forced=1");
                } else {
                    header("Location: " . $redirect);
                }
                exit;
            }
        } else {
            $error = 'خطأ في اسم المستخدم أو كلمة المرور';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | نظام إدارة الكنيسة</title>
    
    <!-- Cairo Arabic Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Local Precompiled Tailwind CSS (Ultra Fast) -->
    <?php $css_ver = @filemtime(__DIR__ . '/assets/css/tailwind.min.css') ?: time(); ?>
    <link rel="stylesheet" href="assets/css/tailwind.min.css?v=<?= $css_ver ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        html, body {
            font-family: 'Cairo', sans-serif;
            overflow-x: hidden;
            max-width: 100%;
        }
        .btn-gold {
            background: linear-gradient(135deg, #d8b75e 0%, #c9a44c 50%, #ad8225 100%);
            color: #14254a;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .btn-gold:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -5px rgba(201, 164, 76, 0.4);
        }
        .cross-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(201,164,76,0.6), transparent);
        }
    </style>
</head>
<body class="h-full min-h-screen w-full flex items-center justify-center p-4 relative overflow-x-hidden bg-[#15264a]">

    <!-- Background Ornaments and Glows (contained cleanly so mobile never overflows) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 bg-gradient-to-br from-[#15264a] via-[#1e3766] to-[#233f75]"></div>
        <div class="absolute -top-32 -left-32 w-80 sm:w-[480px] h-80 sm:h-[480px] rounded-full bg-[#c9a44c]/10 blur-3xl"></div>
        <div class="absolute -bottom-40 -right-24 w-80 sm:w-[520px] h-80 sm:h-[520px] rounded-full bg-[#c9a44c]/10 blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md z-10 my-auto">
        
        <!-- Main Card -->
        <div class="bg-white/95 backdrop-blur-md rounded-3xl p-6 sm:p-8 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.5)] border border-[#c9a44c]/30">
            
            <!-- Church Emblem -->
            <div class="text-center mb-7">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-b from-[#e6d088] to-[#b0872b] flex items-center justify-center shadow-xl text-church-navy">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none">
                        <g stroke="#14254a" stroke-width="2" stroke-linecap="round">
                            <path d="M12 5v4M8 11h8M12 9v8M10 9L8 7M14 9l2-2" />
                        </g>
                        <circle cx="12" cy="11" r="2" fill="#14254a" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-church-dark">نظام إدارة الكنيسة</h1>
                <p class="text-xs tracking-widest text-[#c9a44c] font-bold uppercase mt-1">Coptic Church Management</p>
                <div class="cross-divider mt-4"></div>
            </div>

            <!-- Error Notification -->
            <?php if (!empty($error)): ?>
            <div class="mb-5 p-3.5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl flex items-center space-x-2 space-x-reverse">
                <i class="fas fa-circle-exclamation text-base"></i>
                <span class="font-semibold"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php?redirect=<?= urlencode($redirect) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 mr-1">اختر المستخدم</label>

                    <!-- Dropdown Selection -->
                    <div class="relative">
                        <select
                            id="userSelect"
                            name="username"
                            required
                            class="w-full pr-3 pl-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#c9a44c] focus:border-transparent transition-all"
                        >
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?= htmlspecialchars($u['username']) ?>" <?= ($_POST['username'] ?? '') === $u['username'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 mr-1">كلمة المرور</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-lock text-sm"></i>
                        </div>
                        <input
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="أدخل كلمة المرور الخاصة بك"
                            class="w-full pr-10 pl-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#c9a44c] focus:border-transparent transition-all"
                        >
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="btn-gold w-full py-3 rounded-xl text-base shadow-lg shadow-[#c9a44c]/20 flex items-center justify-center space-x-2 space-x-reverse"
                    >
                        <span>تسجيل الدخول</span>
                        <i class="fas fa-arrow-left text-sm mr-1"></i>
                    </button>
                </div>
            </form>

            <p class="mt-5 text-center text-[11px] text-slate-400 font-medium">
                <i class="fas fa-shield-alt ml-1 text-[#c9a44c]"></i>
                كنيسة العائلة المقدسة — نظام محمي بصلاحيات المستخدمين
            </p>

        </div>

        <!-- Footer Blessing -->
        <p class="text-center text-white/60 text-xs mt-6 tracking-wide font-medium">
            + سنة بركة • نظام إدارة حي الكنيسة القبطية
        </p>

    </div>

</body>
</html>
