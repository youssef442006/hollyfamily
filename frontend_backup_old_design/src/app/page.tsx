'use client';
import AuthLayout from '@/components/AuthLayout';
import { dashboardAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { HiUsers, HiUserGroup, HiCog, HiHeart, HiClipboardCheck, HiHome, HiSparkles, HiUserRemove } from 'react-icons/hi';

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

export default function DashboardPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    dashboardAPI.get()
      .then(res => setStats(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <AuthLayout><div className="text-center py-20">جاري التحميل...</div></AuthLayout>;
  if (!stats) return <AuthLayout><div className="text-center py-20">خطأ في تحميل البيانات</div></AuthLayout>;

  const statCards = [
    { label: 'الأسر', value: stats.total_families, icon: HiHome, color: 'bg-blue-500' },
    { label: 'الأفراد', value: stats.total_persons, icon: HiUsers, color: 'bg-green-500' },
    { label: 'الأطفال', value: stats.total_children, icon: HiSparkles, color: 'bg-pink-500' },
    { label: 'الخدام', value: stats.total_servants, icon: HiCog, color: 'bg-purple-500' },
    { label: 'الخدمات', value: stats.total_services, icon: HiCog, color: 'bg-yellow-500' },
    { label: 'المخدومين', value: stats.total_beneficiaries, icon: HiUserGroup, color: 'bg-indigo-500' },
    { label: 'بدون خدمة', value: stats.persons_without_service, icon: HiUserRemove, color: 'bg-orange-500' },
    { label: 'أسر تحتاج افتقاد', value: stats.families_needing_visit, icon: HiHeart, color: 'bg-red-500' },
    { label: 'اعترافات قادمة', value: stats.upcoming_confessions, icon: HiClipboardCheck, color: 'bg-teal-500' },
  ];

  return (
    <AuthLayout>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-800">الرئيسية</h1>
        <p className="text-gray-500 mt-1">مرحباً بك في نظام إدارة الكنيسة</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {statCards.map((card, i) => (
          <div key={i} className={card.label === 'بدون خدمة' ? 'cursor-pointer' : ''}>
            {card.label === 'بدون خدمة' ? (
              <Link href="/reports?tab=withoutService" className="stat-card block">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500">{card.label}</p>
                    <p className="text-2xl font-bold text-gray-800 mt-1">{card.value}</p>
                  </div>
                  <div className={`${card.color} p-3 rounded-lg text-white`}>
                    <card.icon size={24} />
                  </div>
                </div>
              </Link>
            ) : (
              <div className="stat-card">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500">{card.label}</p>
                    <p className="text-2xl font-bold text-gray-800 mt-1">{card.value}</p>
                  </div>
                  <div className={`${card.color} p-3 rounded-lg text-white`}>
                    <card.icon size={24} />
                  </div>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="card">
        <h2 className="text-lg font-bold text-gray-800 mb-4">إحصائيات الخدمات</h2>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="table-header">
                <th className="px-4 py-3 text-right">الخدمة</th>
                <th className="px-4 py-3 text-right">الخدام</th>
                <th className="px-4 py-3 text-right">المخدومين</th>
              </tr>
            </thead>
            <tbody>
              {stats.service_stats.map((svc) => (
                <tr key={svc.service_id} className="border-b border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium">{svc.service_name}</td>
                  <td className="px-4 py-3">{svc.servants}</td>
                  <td className="px-4 py-3">{svc.beneficiaries}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AuthLayout>
  );
}
