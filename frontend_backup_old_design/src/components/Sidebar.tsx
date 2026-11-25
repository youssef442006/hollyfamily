'use client';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';
import {
  HiHome, HiUsers, HiUserGroup, HiCog, HiSearch,
  HiDocumentReport, HiClipboardCheck, HiHeart, HiCalendar,
  HiLogout, HiMenu, HiX, HiDuplicate
} from 'react-icons/hi';
import { useState } from 'react';

const navItems = [
  { href: '/', label: 'الرئيسية', icon: HiHome },
  { href: '/families', label: 'الأسر', icon: HiUserGroup },
  { href: '/persons', label: 'الأفراد', icon: HiUsers },
  { href: '/services', label: 'الخدمات', icon: HiCog },
  { href: '/confessions', label: 'الاعترافات', icon: HiClipboardCheck },
  { href: '/visits', label: 'الافتقاد', icon: HiHeart },
  { href: '/reports', label: 'التقارير', icon: HiDocumentReport },
  { href: '/duplicates', label: 'التكرارات', icon: HiDuplicate },
  { href: '/settings', label: 'الإعدادات', icon: HiCog },
];

export default function Sidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <>
      <button
        className="lg:hidden fixed top-4 right-4 z-50 bg-white p-2 rounded-lg shadow-md"
        onClick={() => setMobileOpen(!mobileOpen)}
      >
        {mobileOpen ? <HiX size={24} /> : <HiMenu size={24} />}
      </button>

      <aside className={`fixed right-0 top-0 h-full w-64 bg-white border-l border-gray-200 z-40 transition-transform duration-300 ${mobileOpen ? 'translate-x-0' : 'translate-x-full'} lg:translate-x-0`}>
        <div className="p-6 border-b border-gray-100">
          <h1 className="text-lg font-bold text-church-primary">نظام إدارة الكنيسة</h1>
          <p className="text-xs text-gray-500 mt-1">Coptic Church Management</p>
        </div>

        <nav className="p-4 space-y-1">
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={`sidebar-link ${pathname === item.href ? 'active' : ''}`}
              onClick={() => setMobileOpen(false)}
            >
              <item.icon size={20} />
              <span>{item.label}</span>
            </Link>
          ))}
        </nav>

        <div className="absolute bottom-0 w-full p-4 border-t border-gray-100">
          {user && (
            <div className="mb-3 px-2">
              <p className="text-sm font-medium text-gray-700">{user.full_name}</p>
              <p className="text-xs text-gray-500">{user.role === 'admin' ? 'مدير النظام' : user.role === 'priest' ? 'كاهن' : user.role === 'service_admin' ? 'أمين خدمة' : 'خادم'}</p>
            </div>
          )}
          <button onClick={logout} className="sidebar-link w-full text-red-500 hover:bg-red-50">
            <HiLogout size={20} />
            <span>تسجيل الخروج</span>
          </button>
        </div>
      </aside>

      {mobileOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden" onClick={() => setMobileOpen(false)} />
      )}
    </>
  );
}
