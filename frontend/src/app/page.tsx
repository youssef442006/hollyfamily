'use client';
import AuthLayout from '@/components/AuthLayout';
import { dashboardAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { HiUsers, HiUserGroup, HiHeart, HiClipboardCheck, HiHome, HiSparkles, HiUserRemove, HiBookOpen, HiBadgeCheck } from 'react-icons/hi';

interface DashboardStats {
  total_families: number;
  total_persons: number;
  total_children: number;
  total_servants: number;
  total_services: number;
  total_beneficiaries: number;
  persons_without_service: number;
  families_needing_visit: number;
  upcoming_confessions: number;
  service_stats: Array<{service_id: number; service_name: string; servants: number; beneficiaries: number}>;
}

const iconGradients: Record<string, string> = {
  blue: 'from-[#2b4c8e] to-[#3a5f9f]',
  green: 'from-[#1e7d4f] to-[#2a9d65]',
  pink: 'from-[#b03a7a] to-[#d4599c]',
  purple: 'from-[#6a3fa0] to-[#8b5fc0]',
  yellow: 'from-[#b0872b] to-[#c9a44c]',
  indigo: 'from-[#3a45a0] to-[#5866c9]',
  orange: 'from-[#c2571b] to-[#e07b35]',
  red: 'from-[#b03a2e] to-[#d45548]',
  teal: 'from-[#127a72] to-[#1ba196]',
};

export default function DashboardPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    dashboardAPI.get()
      .then(res => setStats(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <AuthLayout><div className="flex flex-col items-center gap-4 py-24"><div className="w-12 h-12 rounded-full border-4 border-[#c9a44c]/30 border-t-[#c9a44c] animate-spin" /><span className="text-gray-500">جاري التحميل...</span></div></AuthLayout>;
  if (!stats) return <AuthLayout><div className="text-center py-20 text-red-500">خطأ في تحميل البيانات</div></AuthLayout>;

  const statCards = [
    { label: 'الأسر', value: stats.total_families, icon: HiHome, grad: 'blue' },
    { label: 'الأفراد', value: stats.total_persons, icon: HiUsers, grad: 'green' },
    { label: 'الأطفال', value: stats.total_children, icon: HiSparkles, grad: 'pink' },
    { label: 'الخدام', value: stats.total_servants, icon: HiBadgeCheck, grad: 'purple' },
    { label: 'الخدمات', value: stats.total_services, icon: HiBookOpen, grad: 'yellow' },
    { label: 'المخدومين', value: stats.total_beneficiaries, icon: HiUserGroup, grad: 'indigo' },
    { label: 'بدون خدمة', value: stats.persons_without_service, icon: HiUserRemove, grad: 'orange' },
    { label: 'أسر تحتاج افتقاد', value: stats.families_needing_visit, icon: HiHeart, grad: 'red' },
    { label: 'اعترافات قادمة', value: stats.upcoming_confessions, icon: HiClipboardCheck, grad: 'teal' },
  ];

  return (
    <AuthLayout>
      {/* Hero banner */}
      <div className="header-band rounded-3xl p-8 lg:p-10 mb-8 shadow-[0_20px_50px_-20px_rgba(30,55,102,0.6)]">
        <p className="text-[#c9a44c] font-semibold text-sm tracking-wide mb-2">+ سنة بركة</p>
        <h1 className="text-3xl lg:text-4xl font-bold text-white font-display">الرئيسية</h1>
        <p className="text-white/70 mt-2">مرحباً بك في نظام إدارة الكنيسة القبطية</p>
        <div className="cross-divider mt-5 max-w-sm !justify-start">
          <span className="text-[#e6d088] text-sm font-semibold whitespace-nowrap">نظرة عامة على الخدمة</span>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-5 mb-8">
        {statCards.map((card, i) => {
          const inner = (
            <div className="stat-card flex items-center gap-4 h-full">
              <div className={`w-14 h-14 rounded-2xl bg-gradient-to-b ${iconGradients[card.grad]} text-white flex items-center justify-center shadow-lg shrink-0`}>
                <card.icon size={26} />
              </div>
              <div className="min-w-0">
                <p className="text-sm text-[#6b7280]">{card.label}</p>
                <p className="font-display text-3xl font-bold text-church-dark mt-0.5">{card.value}</p>
              </div>
            </div>
          );
          return (
            <div key={i} className={card.label === 'بدون خدمة' ? 'cursor-pointer' : ''}>
              {card.label === 'بدون خدمة' ? (
                <Link href="/reports?tab=withoutService" className="block h-full">{inner}</Link>
              ) : inner}
            </div>
          );
        })}
      </div>

      <div className="card">
        <div className="flex items-center justify-between mb-5">
          <h2 className="text-xl font-bold text-church-dark flex items-center gap-2">
            <span className="text-[#c9a44c]">✦</span>
            إحصائيات الخدمات
          </h2>
          <div className="cross-divider !justify-end flex-1 max-w-md hidden sm:flex" />
        </div>
        <div className="overflow-x-auto rounded-xl border border-[#eee7d7]">
          <table className="w-full">
            <thead>
              <tr className="table-header">
                <th className="text-right">الخدمة</th>
                <th className="text-right">الخدام</th>
                <th className="text-right">المخدومين</th>
              </tr>
            </thead>
            <tbody>
              {stats.service_stats.map((svc) => (
                <tr key={svc.service_id} className="hover:bg-[#faf7ef]">
                  <td className="px-4 py-3.5 font-semibold text-church-primary">{svc.service_name}</td>
                  <td className="px-4 py-3.5"><span className="badge badge-blue">{svc.servants}</span></td>
                  <td className="px-4 py-3.5"><span className="badge badge-green">{svc.beneficiaries}</span></td>
                </tr>
              ))}
              {stats.service_stats.length === 0 && (
                <tr><td colSpan={3} className="px-4 py-8 text-center text-gray-400">لا توجد خدمات بعد</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AuthLayout>
  );
}