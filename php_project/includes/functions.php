<?php
/**
 * Helper Functions
 * دوال مساعدة عامة للنظام
 */

function sanitize(string|null $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * حساب العمر من تاريخ الميلاد
 */
function calc_age(?string $dob): ?int {
    if (empty($dob)) return null;
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * تنسيق التاريخ للعرض بالعربية
 */
function format_date(?string $date_str): string {
    if (empty($date_str)) return '-';
    try {
        $d = new DateTime($date_str);
        return $d->format('Y/m/d');
    } catch (Exception $e) {
        return $date_str;
    }
}

/**
 * تنسيق التاريخ والوقت
 */
function format_datetime(?string $datetime_str): string {
    if (empty($datetime_str)) return '-';
    try {
        $d = new DateTime($datetime_str);
        return $d->format('Y/m/d h:i A');
    } catch (Exception $e) {
        return $datetime_str;
    }
}

/**
 * أسماء المراحل التعليمية بالعربية
 */
function get_stage_name(?string $stage): string {
    $stages = [
        'nursery'        => 'حضانة',
        'angels'         => 'ملايكة',
        'primary_1_2'    => 'أولى وثانية ابتدائي',
        'primary_3_4'    => 'ثالثة ورابعة ابتدائي',
        'primary_5_6'    => 'خامسة وسادسة ابتدائي',
        'preparatory'    => 'إعدادي',
        'secondary'      => 'ثانوي',
        'university'     => 'جامعيين',
        'graduate'       => 'خريجين',
        'mens_gathering' => 'اجتماع الرجال'
    ];
    return $stages[$stage] ?? ($stage ?: '-');
}

/**
 * أسماء صلات القرابة بالعربية
 */
function get_relationship_name(?string $rel): string {
    $rels = [
        'father'        => 'أب / عائل الأسرة',
        'mother'        => 'أم / زوجة',
        'husband'       => 'زوج',
        'wife'          => 'زوجة',
        'son'           => 'ابن',
        'daughter'      => 'ابنة',
        'grandfather'   => 'جد',
        'grandmother'   => 'جدة',
        'grandson'      => 'حفيد',
        'granddaughter' => 'حفيدة',
        'relative'      => 'قريب',
        'other'         => 'أخرى'
    ];
    return $rels[$rel] ?? ($rel ?: '-');
}

/**
 * مسميات الحالات الخاصة / الرعاية بالعربية
 */
function get_care_label(?string $care): string {
    $cares = [
        'normal'        => 'عادي',
        'deceased'      => 'متوفي',
        'widowed'       => 'أرمل / أرملة',
        'special_needs' => 'ذوي احتياجات خاصة',
        'abroad'        => 'مسافر بالخارج',
        'sick'          => 'مرض مزمن / طريح فراش',
        'chronic_disease' => 'مرض مزمن',
    ];
    return $cares[$care] ?? ($care ?: 'عادي');
}

/**
 * أيام الأسبوع للإجازة
 */
function get_week_days(): array {
    return [
        'friday'    => 'الجمعة',
        'saturday'  => 'السبت',
        'sunday'    => 'الأحد',
        'monday'    => 'الاثنين',
        'tuesday'   => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday'  => 'الخميس',
    ];
}

/**
 * تنسيق وعرض أيام الإجازة الأسبوعية
 */
function format_weekly_leave(?string $leave_str): string {
    if (empty($leave_str)) return '-';
    $days = get_week_days();
    $selected = array_filter(array_map('trim', explode(',', $leave_str)));
    $labels = [];
    foreach ($selected as $s) {
        $labels[] = $days[$s] ?? $s;
    }
    return !empty($labels) ? implode('، ', $labels) : '-';
}

/**
 * توليد واجهة اختيار متعدد لأيام الإجازة الأسبوعية
 */
function render_weekly_leave_picker(string $input_name, array|string|null $selected_days = []): string {
    if (is_string($selected_days)) {
        $selected_days = array_filter(array_map('trim', explode(',', $selected_days)));
    } elseif ($selected_days === null) {
        $selected_days = [];
    }
    
    $days = get_week_days();
    $html = '<div class="flex flex-wrap gap-1.5 mt-1">';
    foreach ($days as $val => $label) {
        $checked = in_array($val, $selected_days) ? 'checked' : '';
        $html .= '
        <label class="cursor-pointer select-none">
            <input type="checkbox" name="' . htmlspecialchars($input_name) . '[]" value="' . $val . '" ' . $checked . ' class="hidden peer">
            <span class="inline-block px-2.5 py-1 text-xs rounded-lg border border-slate-300 text-slate-700 bg-white peer-checked:bg-church-navy peer-checked:text-white peer-checked:border-church-navy font-semibold transition hover:bg-slate-50">
                ' . $label . '
            </span>
        </label>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * تسمية الرتب والمستخدمين
 */
function get_role_name(?string $role): string {
    $roles = [
        'admin'         => 'مدير النظام',
        'priest'        => 'أب كاهن',
        'service_admin' => 'أمين خدمة',
        'servant'       => 'خادم'
    ];
    return $roles[$role] ?? ($role ?: 'مستخدم');
}

/**
 * شارة الرتبة مع الألوان
 */
function get_role_badge(?string $role): string {
    switch ($role) {
        case 'admin':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">مدير النظام</span>';
        case 'priest':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">أب كاهن</span>';
        case 'service_admin':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">أمين خدمة</span>';
        case 'servant':
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">خادم</span>';
    }
}

/**
 * حفظ رسالة تنبيه للمستخدم (Flash Message)
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * إظهار رسالة التنبيه
 */
function display_flash(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['flash'])) return '';
    
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    
    $is_success = ($f['type'] === 'success');
    $bg = $is_success ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-red-50 border-red-500 text-red-800';
    $icon = $is_success ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    return '
    <div class="mb-5 p-4 rounded-xl border-r-4 shadow-sm flex items-center justify-between ' . $bg . '" role="alert">
        <div class="flex items-center space-x-3 space-x-reverse">
            <i class="fas ' . $icon . ' text-xl"></i>
            <span class="font-medium">' . htmlspecialchars($f['message']) . '</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>';
}

/**
 * تسجيل حركة في جدول التدقيق (Audit Log)
 */
function record_audit(PDO $pdo, string $action, string $table, ?int $record_id = null, ?string $old_val = null, ?string $new_val = null): void {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $table, $record_id, $old_val, $new_val]);
    } catch (Exception $e) {
        // Ignored to avoid breaking main transaction
    }
}
