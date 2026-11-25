'use client';
import AuthLayout from '@/components/AuthLayout';
import { familiesAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { HiPlus, HiSearch, HiTrash } from 'react-icons/hi';
import { translate, specialCases } from '@/lib/translations';

export default function FamiliesPage() {
  const [families, setFamilies] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  const loadFamilies = async () => {
    setLoading(true);
    try {
      const res = await familiesAPI.list({ search: search || undefined });
      setFamilies(res.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadFamilies(); }, [search]);

  const deleteFamily = async (id: number, name: string) => {
    if (!window.confirm(`هل أنت متأكد من حذف الأسرة "${name}"؟`)) return;
    try {
      await familiesAPI.delete(id);
      toast.success('تم حذف الأسرة');
      loadFamilies();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  return (
    <AuthLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">الأسر</h1>
          <p className="text-gray-500 mt-1">إدارة أسر الكنيسة</p>
        </div>
        <Link href="/families/new" className="btn-primary flex items-center gap-2">
          <HiPlus size={20} />
          إضافة أسرة
        </Link>
      </div>

      <div className="card mb-6">
        <div className="relative">
          <HiSearch className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
          <input
            type="text"
            className="input-field pr-10"
            placeholder="بحث بالاسم أو الهاتف..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-10 text-gray-500">جاري التحميل...</div>
        ) : families.length === 0 ? (
          <div className="text-center py-10 text-gray-500">لا توجد أسر</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="table-header">
                  <th className="px-4 py-3 text-right">كود الأسرة</th>
                  <th className="px-4 py-3 text-right">اسم الأسرة</th>
                  <th className="px-4 py-3 text-right">عدد الأفراد</th>
                  <th className="px-4 py-3 text-right">الهاتف</th>
                  <th className="px-4 py-3 text-right">العنوان</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {families.map((f) => (
                  <tr key={f.id} className="border-b border-gray-100 hover:bg-gray-50 cursor-pointer">
                    <td className="px-4 py-3 font-mono text-sm">{f.family_code}</td>
                    <td className="px-4 py-3">
                      <Link href={`/families/${f.id}`} className="text-church-primary hover:underline font-medium">
                        {f.family_name}
                      </Link>
                      {f.special_case && (
                        <span className="badge-red block w-fit mt-1 text-xs">{translate(f.special_case, specialCases)}</span>
                      )}
                    </td>
                    <td className="px-4 py-3">{f.member_count || 0}</td>
                    <td className="px-4 py-3">{f.phone || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{f.address || '-'}</td>
                    <td className="px-4 py-3">
                      <span className={f.is_active ? 'badge-green' : 'badge-red'}>
                        {f.is_active ? 'نشطة' : 'غير نشطة'}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <button onClick={() => deleteFamily(f.id, f.family_name)} className="text-red-500 hover:text-red-700" title="حذف">
                        <HiTrash size={18} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AuthLayout>
  );
}
