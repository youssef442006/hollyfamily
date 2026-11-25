<?php
/**
 * Sidebar Template
 * القائمة الجانبية للشاشات والروابط - تصميم نحيف مع زر إخفاء وإظهار
 */
$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();
?>
<!-- Sidebar Backdrop for Mobile -->
<div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden"></div>

<!-- Sidebar Container (Slim: w-60) -->
<aside id="main-sidebar" class="fixed inset-y-0 right-0 z-50 w-60 navy-gradient text-white flex flex-col transition-all duration-300 transform translate-x-full lg:translate-x-0 lg:static lg:z-auto shadow-2xl flex-shrink-0">
    
    <!-- Church Branding & Toggle -->
    <div class="p-3.5 border-b border-white/10 flex items-center justify-between">
        <div class="flex items-center space-x-2.5 space-x-reverse">
            <div class="w-9 h-9 rounded-xl gold-gradient flex items-center justify-center shadow-md text-church-navy flex-shrink-0">
                <i class="fas fa-cross text-lg"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="text-xs font-bold tracking-tight text-white truncate">إدارة الكنيسة</h1>
                <p class="text-[9px] text-church-gold font-bold truncate">كنيسة العائلة المقدسة</p>
            </div>
        </div>
        <button onclick="toggleSidebarDesktop()" class="text-white/70 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition" title="طي القائمة">
            <i class="fas fa-times lg:hidden text-base"></i>
            <i class="fas fa-angles-right hidden lg:inline text-xs"></i>
        </button>
    </div>

    <!-- Navigation Menu (Slim compact layout) -->
    <nav class="flex-1 px-2.5 py-3 space-y-1 overflow-y-auto">
        
        <!-- الرئيسية -->
        <a href="index.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'index.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-chart-pie w-5 text-center text-sm <?= ($current_page === 'index.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">لوحة التحكم</span>
        </a>

        <!-- الأسر والعائلات -->
        <a href="families.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= (in_array($current_page, ['families.php', 'family-details.php', 'family-wizard.php'])) ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-people-roof w-5 text-center text-sm <?= (in_array($current_page, ['families.php', 'family-details.php', 'family-wizard.php'])) ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">سجل الأسر</span>
        </a>

        <!-- المخدومين والشعب -->
        <a href="persons.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= (in_array($current_page, ['persons.php', 'person-details.php'])) ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-users w-5 text-center text-sm <?= (in_array($current_page, ['persons.php', 'person-details.php'])) ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">المخدومين والشعب</span>
        </a>

        <!-- قطاعات ومدارس الأحد -->
        <?php if (can_access('services_manage')): ?>
        <a href="services.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'services.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-church w-5 text-center text-sm <?= ($current_page === 'services.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">مدارس الأحد والقطاعات</span>
        </a>
        <?php endif; ?>

        <!-- سجل الاعترافات -->
        <?php if (can_access('confessions')): ?>
        <a href="confessions.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'confessions.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-hands-praying w-5 text-center text-sm <?= ($current_page === 'confessions.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">سجل الاعترافات</span>
        </a>
        <?php endif; ?>

        <!-- سجل الافتقادات -->
        <?php if (can_access('visits')): ?>
        <a href="visits.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'visits.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-clipboard-user w-5 text-center text-sm <?= ($current_page === 'visits.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">سجل الافتقادات</span>
        </a>
        <?php endif; ?>

        <!-- كشف التكرار والدمج -->
        <?php if (can_access('duplicates')): ?>
        <a href="duplicates.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'duplicates.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-clone w-5 text-center text-sm <?= ($current_page === 'duplicates.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">كشف التكرار والدمج</span>
        </a>
        <?php endif; ?>

        <!-- التقارير والإحصائيات -->
        <?php if (can_access('reports')): ?>
        <a href="reports.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'reports.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-file-invoice w-5 text-center text-sm <?= ($current_page === 'reports.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">التقارير والإحصائيات</span>
        </a>
        <?php endif; ?>

        <!-- الإدارة والرقابة (Admin Only) -->
        <?php if (has_role(['admin'])): ?>
        <div class="pt-2 pb-1">
            <div class="border-t border-white/10"></div>
        </div>
        <a href="settings.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'settings.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-users-gear w-5 text-center text-sm <?= ($current_page === 'settings.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">المستخدمين والصلاحيات</span>
        </a>
        <a href="audit-log.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'audit-log.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-clock-rotate-left w-5 text-center text-sm <?= ($current_page === 'audit-log.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">سجل النشاطات</span>
        </a>
        <a href="backup.php" class="flex items-center px-3 py-2 text-xs font-semibold rounded-xl transition-all duration-150 <?= ($current_page === 'backup.php') ? 'bg-white/15 text-church-gold border-r-4 border-church-gold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fas fa-database w-5 text-center text-sm <?= ($current_page === 'backup.php') ? 'text-church-gold' : 'text-slate-400' ?>"></i>
            <span class="mr-2.5">النسخ الاحتياطي</span>
        </a>
        <?php endif; ?>

    </nav>

    <!-- User Profile Footer -->
    <div class="p-3 border-t border-white/10 bg-black/10">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2.5 space-x-reverse overflow-hidden">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-white text-xs flex-shrink-0 border border-white/30">
                    <?= mb_substr($user['full_name'] ?? 'م', 0, 1, 'UTF-8') ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($user['full_name'] ?? 'مستخدم') ?></p>
                    <span class="text-[10px] text-church-gold font-medium"><?= get_role_name($user['role'] ?? '') ?></span>
                </div>
            </div>
            <div class="flex items-center space-x-1 space-x-reverse flex-shrink-0">
                <a href="change-password.php" title="تغيير كلمة المرور" class="p-1.5 text-slate-300 hover:text-church-gold hover:bg-white/5 rounded-lg transition-colors">
                    <i class="fas fa-key text-sm"></i>
                </a>
                <a href="logout.php" title="تسجيل الخروج" class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-white/5 rounded-lg transition-colors">
                    <i class="fas fa-sign-out-alt text-base"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

<!-- Main Content Area Wrapper -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    <!-- Top Bar Header with Toggle Button -->
    <header class="bg-white border-b border-slate-200 py-2.5 px-4 sm:px-6 flex items-center justify-between shadow-sm z-10">
        <div class="flex items-center space-x-3 space-x-reverse">
            <!-- زر طي / إظهار القائمة الجانبية -->
            <button type="button" onclick="toggleSidebarDesktop()" class="p-2 text-slate-600 hover:text-church-navy hover:bg-slate-100 rounded-xl transition flex items-center gap-1.5 text-xs font-bold" title="إخفاء / إظهار القائمة الجانبية">
                <i class="fas fa-bars text-base text-church-navy"></i>
                <span class="hidden sm:inline text-slate-700">القائمة</span>
            </button>
            <span class="text-xs font-bold text-church-navy hidden md:inline">
                <i class="fas fa-church ml-1 text-church-gold"></i>
                كنيسة العائلة المقدسة
            </span>
        </div>
        <div class="flex items-center space-x-3 space-x-reverse">
            <div class="text-left hidden sm:block">
                <p class="text-[10px] text-slate-400 font-semibold">اليوم</p>
                <p class="text-xs text-church-navy font-bold"><?= date('d F Y') ?></p>
            </div>
            <div class="w-8 h-8 rounded-full gold-gradient flex items-center justify-center text-church-navy text-xs font-bold shadow-sm">
                +
            </div>
        </div>
    </header>

    <!-- Page Content Scroll Area -->
    <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
        <?= display_flash() ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('main-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (sidebar) sidebar.classList.toggle('translate-x-full');
    if (backdrop) backdrop.classList.toggle('hidden');
}

function toggleSidebarDesktop() {
    const sidebar = document.getElementById('main-sidebar');
    if (!sidebar) return;
    if (window.innerWidth < 1024) {
        toggleSidebar();
    } else {
        sidebar.classList.toggle('lg:hidden');
        const isHidden = sidebar.classList.contains('lg:hidden');
        localStorage.setItem('sidebar_hidden', isHidden ? '1' : '0');
    }
}

// Restore sidebar preference
if (localStorage.getItem('sidebar_hidden') === '1' && window.innerWidth >= 1024) {
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('main-sidebar');
        if (sidebar) sidebar.classList.add('lg:hidden');
    });
}
</script>
