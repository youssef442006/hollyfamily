'use client';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';
import {
  HiHome, HiUsers, HiUserGroup, HiCog, HiSearch,
  HiDocumentReport, HiClipboardCheck, HiHeart, HiCalendar,
  HiLogout, HiMenu, HiX, HiDuplicate, HiBookOpen
} from 'react-icons/hi';
import { useState } from 'react';

const navItems = [
  { href: '/', label: 'الرئيسية', icon: HiHome },
  { href: '/families', label: 'الأسر', icon: HiUserGroup },
  { href: '/persons', label: 'الأفراد', icon: HiUsers },
  { href: '/services', label: 'الخدمات', icon: HiBookOpen },
  { href: '/confessions', label: 'الاعترافات', icon: HiClipboardCheck },
  { href: '/visits', label: 'الافتقاد', icon: HiHeart },
  { href: '/reports', label: 'التقارير', icon: HiDocumentReport },
  { href: '/duplicates', label: 'التكرارات', icon: HiDuplicate },
  { href: '/settings', label: 'الإعدادات', icon: HiCog },
];

const roleLabels: Record<string, string> = {
  admin: 'مدير النظام',
  priest: 'كاهن',
  service_admin: 'أمين خدمة',
  servant: 'خادم',
};

export default function Sidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);

  const initials = (user?.full_name || '؟')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w: string) => w[0])
    .join('');

  return (
    <>
      <button
        className="lg:hidden fixed top-4 right-4 z-50 bg-white p-2.5 rounded-xl shadow-lg text-church-primary border border-[#e8e0cd]"
        onClick={() => setMobileOpen(!mobileOpen)}
      >
        {mobileOpen ? <HiX size={22} /> : <HiMenu size={22} />}
      </button>

      <aside
        className={`fixed right-0 top-0 h-full w-64 z-40 transition-transform duration-300
          bg-gradient-to-b from-[#15264a] via-[#1e3766] to-[#233f75]
          shadow-[8px_0_30px_-10px_rgba(21,38,74,0.6)]
          border-r border-[#c9a44c]/25
          ${mobileOpen ? 'translate-x-0' : 'translate-x-full'} lg:translate-x-0`}
      >
        <div className="p-6 pb-5 border-b border-white/10 relative">
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-2xl bg-gradient-to-b from-[#e6d088] to-[#b0872b] flex items-center justify-center shadow-lg shrink-0">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                <g stroke="#14254a" strokeWidth="2.2" strokeLinecap="round">
                  <path d="M12 6v4M8 12h8M12 10v7M10 10L8 8M14 10l2-2" />
                </g>
                <circle cx="12" cy="12" r="2" fill="#14254a" />
              </svg>
            </div>
            <div>
              <h1 className="font-display text-lg font-bold text-white leading-tight">الكنيسة</h1>
              <p className="text-[11px] text-[#c9a44c] tracking-wide">نظام الإدارة</p>
            </div>
          </div>
          <div className="cross-divider mt-4 opacity-60" />
        </div>

        <nav className="p-3.5 space-y-1 max-h-[calc(100vh-230px)] overflow-y-auto">
          {navItems.map((item) => {
            const active = pathname === item.href || (item.href !== '/' && pathname.startsWith(item.href));
            return (
              <Link
                key={item.href}
                href={item.href}
                className={`sidebar-link ${active ? 'active' : ''}`}
                onClick={() => setMobileOpen(false)}
              >
                <item.icon size={20} className={active ? '' : 'text-[#8fa3cc]'} />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>

        <div className="absolute bottom-0 w-full p-4 border-t border-white/10 bg-[#15264a]/60 backdrop-blur">
          {user && (
            <div className="mb-3 px-1 flex items-center gap-3">
              <div className="w-9 h-9 rounded-full bg-gradient-to-b from-[#c9a44c] to-[#8a6d1f] flex items-center justify-center text-white text-sm font-bold shrink-0">
                {initials}
              </div>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-white truncate">{user.full_name}</p>
                <p className="text-[11px] text-[#aeb9d6]">{roleLabels[user.role] || user.role}</p>
              </div>
            </div>
          )}
          <button onClick={logout} className="sidebar-link w-full text-[#ffd9d4] hover:bg-[#a93226]/30 hover:text-white">
            <HiLogout size={20} />
            <span>تسجيل الخروج</span>
          </button>
        </div>
      </aside>

      {mobileOpen && (
        <div className="fixed inset-0 bg-[#1a2340]/70 backdrop-blur-sm z-30 lg:hidden" onClick={() => setMobileOpen(false)} />
      )}
    </>
  );
}