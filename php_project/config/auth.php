<?php
/**
 * Authentication & Session Management
 * إدارة الجلسات وتسجيل الدخول والصلاحيات
 */

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * فحص هل المستخدم مسجل دخول أم لا
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * الحصول على بيانات المستخدم الحالي
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
        'email'     => $_SESSION['email'] ?? null,
    ];
}

/**
 * إجبار تسجيل الدخول والتحويل لصفحة الدخول إذا لم يكن مسجلاً
 * وإجبار تغيير كلمة المرور إذا كانت مؤقتة
 */
function require_login(): void {
    if (!is_logged_in()) {
        $current_url = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: login.php?redirect=$current_url");
        exit;
    }

    $current_script = basename($_SERVER['PHP_SELF'] ?? '');
    if (!empty($_SESSION['must_change_password']) && !in_array($current_script, ['change-password.php', 'logout.php'], true)) {
        header("Location: change-password.php?forced=1");
        exit;
    }
}

/**
 * فحص صلاحية الوصول لميزة أو قسم معين حسب رتبة المستخدم
 */
function can_access(string $feature): bool {
    if (!is_logged_in()) return false;
    $role = $_SESSION['role'] ?? 'servant';
    if ($role === 'admin') return true;

    $permissions = [
        'priest' => [
            'families_view', 'families_edit', 'families_delete',
            'persons_view', 'persons_edit', 'persons_delete',
            'services_manage', 'confessions', 'visits', 'duplicates', 'reports'
        ],
        'service_admin' => [
            'families_view', 'families_edit', 'families_delete',
            'persons_view', 'persons_edit', 'persons_delete',
            'services_manage', 'visits', 'duplicates', 'reports'
        ],
        'servant' => [
            'families_view', 'families_edit',
            'persons_view', 'persons_edit',
            'visits'
        ],
    ];

    return in_array($feature, $permissions[$role] ?? [], true);
}

/**
 * إجبار امتلاك صلاحية معينة للوصول للصفحة
 */
function require_permission(string $feature): void {
    require_login();
    if (!can_access($feature)) {
        http_response_code(403);
        die('<div dir="rtl" style="font-family:sans-serif;margin:50px auto;max-width:500px;padding:25px;border-radius:12px;background:#fff3cd;color:#856404;border:1px solid #ffeeba;text-align:center;">
            <h3>⛔ غير مصرح لك بالوصول</h3>
            <p>لا تملك الصلاحية الكافية لفتح هذه الصفحة.</p>
            <a href="index.php" style="display:inline-block;margin-top:15px;padding:8px 18px;background:#15264a;color:#fff;text-decoration:none;border-radius:6px;">العودة للرئيسية</a>
        </div>');
    }
}

/**
 * فحص امتلاك رتبة معينة
 */
function has_role(array|string $roles): bool {
    if (!is_logged_in()) return false;
    $user_role = $_SESSION['role'] ?? '';
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    return $user_role === $roles;
}

/**
 * إجبار رتبة معينة (مثل admin أو priest)
 */
function require_role(array|string $roles): void {
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        die('<div dir="rtl" style="font-family:sans-serif;margin:50px auto;max-width:500px;padding:25px;border-radius:12px;background:#fff3cd;color:#856404;border:1px solid #ffeeba;text-align:center;">
            <h3>⛔ غير مصرح لك بالوصول</h3>
            <p>لا تملك الصلاحية الكافية لفتح هذه الصفحة.</p>
            <a href="index.php" style="display:inline-block;margin-top:15px;padding:8px 18px;background:#15264a;color:#fff;text-decoration:none;border-radius:6px;">العودة للرئيسية</a>
        </div>');
    }
}

/**
 * توليد أو جلب توكن الحماية CSRF
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * حقل مخفي لتوكن CSRF في النماذج
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * التحقق من صحة توكن CSRF
 */
function validate_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * إجبار فحص توكن CSRF في الطلبات الحساسة
 */
function require_csrf(): void {
    if (!validate_csrf()) {
        http_response_code(403);
        die('<div dir="rtl" style="font-family:sans-serif;margin:50px auto;max-width:500px;padding:25px;border-radius:12px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;text-align:center;">
            <h3>⚠️ خطأ أمني (CSRF Token Mismatch)</h3>
            <p>انتهت صلاحية الجلسة أو الطلب غير مصرح به. يرجى تحديث الصفحة وإعادة المحاولة.</p>
            <a href="index.php" style="display:inline-block;margin-top:15px;padding:8px 18px;background:#15264a;color:#fff;text-decoration:none;border-radius:6px;">العودة للرئيسية</a>
        </div>');
    }
}
