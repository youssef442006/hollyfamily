'use client';
import AuthLayout from '@/components/AuthLayout';
import { personsAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { HiSearch, HiTrash, HiSparkles } from 'react-icons/hi';
import { translate, educationStages, schoolYears, genders } from '@/lib/translations';

export default function PersonsPage() {
  const [persons, setPersons] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [promoting, setPromoting] = useState(false);

  const loadPersons = async () => {
    setLoading(true);
    try {
      const res = await personsAPI.list({ search: search || undefined });
      setPersons(res.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadPersons(); }, [search]);

  const deletePerson = async (id: number, name: string) => {
    if (!window.confirm(`هل أنت متأكد من حذف "${name}"؟`)) return;
    try {
      await personsAPI.deletePerson(id);
      toast.success('تم حذف الشخص');
      loadPersons();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  const promoteStages = async () => {
    if (!window.confirm('سيتم ترقية الجميع سنة واحدة (النيروز): خامسة←سادسة تبقى في نفس الاجتماع، سادسة←أولى إعدادي وتنتقل لاجتماع إعدادي، وهكذا. الكبار (جامعيين/خريجين) لن يتغيروا. متابعة؟')) return;
    setPromoting(true);
    try {
      const res = await personsAPI.promoteStages();
      const d = res.data;
      toast.success(`تم نقل المراحل: ترقية ${d.promoted} شخص، انتقال ${d.moved} لاجتماع جديد`);
      loadPersons();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في نقل المراحل'));
    }
    setPromoting(false);
  };

  return (
    <AuthLayout>
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">الأفراد</h1>
            <p className="text-gray-500 mt-1">إدارة أفراد الكنيسة</p>
          </div>
          <button onClick={promoteStages} disabled={promoting} className="btn-primary flex items-center gap-2">
            <HiSparkles size={20} />
            {promoting ? 'جاري النقل...' : 'نقل المراحل في النيروز'}
          </button>
        </div>
      </div>

      <div className="card mb-6">
        <div className="relative">
          <HiSearch className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
          <input type="text" className="input-field pr-10" placeholder="بحث بالاسم أو الهاتف..." value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-10 text-gray-500">جاري التحميل...</div>
        ) : persons.length === 0 ? (
          <div className="text-center py-10 text-gray-500">لا يوجد أفراد</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="table-header">
                  <th className="px-4 py-3 text-right">الاسم</th>
                  <th className="px-4 py-3 text-right">النوع</th>
                  <th className="px-4 py-3 text-right">تاريخ الميلاد</th>
                  <th className="px-4 py-3 text-right">العمر</th>
                  <th className="px-4 py-3 text-right">المرحلة الدراسية</th>
                  <th className="px-4 py-3 text-right">الهاتف</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {persons.map((p) => {
                  const age = p.date_of_birth
                    ? Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
                    : '-';
                  return (
                    <tr key={p.id} className="border-b border-gray-100 hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <Link href={`/persons/${p.id}`} className="text-church-primary hover:underline font-medium">
                          {p.full_name_display || `${p.first_name} ${p.middle_name || ''} ${p.last_name || ''}`}
                        </Link>
                      </td>
                      <td className="px-4 py-3">{translate(p.gender, genders)}</td>
                      <td className="px-4 py-3">{p.date_of_birth || '-'}</td>
                      <td className="px-4 py-3">{age}</td>
                      <td className="px-4 py-3">
                        {p.school_year ? translate(p.school_year, schoolYears) : translate(p.education_stage, educationStages)}
                      </td>
                      <td className="px-4 py-3">{p.phone || '-'}</td>
                      <td className="px-4 py-3">
                        <span className={p.is_active ? 'badge-green' : 'badge-red'}>
                          {p.is_active ? 'نشط' : 'غير نشط'}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <button onClick={() => deletePerson(p.id, p.full_name_display || p.first_name)} className="text-red-500 hover:text-red-700" title="حذف">
                          <HiTrash size={18} />
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AuthLayout>
  );
}
