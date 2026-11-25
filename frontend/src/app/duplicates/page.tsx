'use client';
import AuthLayout from '@/components/AuthLayout';
import { personsAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { HiDuplicate, HiRefresh } from 'react-icons/hi';
import { useAuth } from '@/contexts/AuthContext';

export default function DuplicatesPage() {
  const { isAdmin } = useAuth();
  const [groups, setGroups] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [merging, setMerging] = useState(false);

  const loadGroups = async () => {
    setLoading(true);
    try {
      const res = await personsAPI.listDuplicates();
      setGroups(res.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadGroups(); }, []);

  const merge = async (primaryId: number, group: any) => {
    const duplicateIds = group.person_ids.filter((id: number) => id !== primaryId);
    if (duplicateIds.length === 0) return;
    await doMerge(primaryId, duplicateIds[0]);
  };

  const doMerge = async (primaryId: number, duplicateId: number) => {
    if (!window.confirm(`سيدمج الشخص المكرر في الشخص الرئيسي. هل تريد المتابعة؟`)) return;
    setMerging(true);
    try {
      await personsAPI.merge(primaryId, duplicateId);
      toast.success('تم الدمج بنجاح');
      loadGroups();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الدمج (متاح للمدير فقط)'));
    }
    setMerging(false);
  };

  return (
    <AuthLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">التكرارات المحتملة</h1>
          <p className="text-gray-500 mt-1">أشخاص متطابقون في الاسم أو الهاتف (يحتاجون دمجاً)</p>
        </div>
        <button onClick={loadGroups} className="btn-secondary flex items-center gap-2">
          <HiRefresh size={18} /> تحديث
        </button>
      </div>

      {!isAdmin && (
        <div className="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg mb-6">
          الدمج متاح لمدير النظام فقط. يمكنك عرض التكرارات.
        </div>
      )}

      {loading ? (
        <div className="text-center py-20 text-gray-500">جاري فحص البيانات...</div>
      ) : groups.length === 0 ? (
        <div className="card text-center py-16">
          <HiDuplicate size={48} className="mx-auto text-gray-300 mb-3" />
          <p className="text-gray-500">لا توجد تكرارات حالياً — مبروك!</p>
        </div>
      ) : (
        <div className="space-y-4">
          {groups.map((group, gi) => (
            <div key={gi} className="card">
              <div className="flex items-center justify-between mb-3">
                <h3 className="font-bold">
                  {group.type === 'name' ? `اسم مطابق: ${group.label}` : `هاتف مطابق: ${group.label}`}
                </h3>
                <span className="badge-yellow">{group.persons.length} سجلات</span>
              </div>
              <div className="space-y-2">
                {group.persons.map((p: any, pi: number) => (
                  <div key={p.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                    <div className="flex items-center gap-3">
                      <span className="w-6 text-center text-gray-400">{pi + 1}</span>
                      <span className="font-medium">{p.full_name_display}</span>
                    </div>
                    <div className="flex items-center gap-4 text-sm text-gray-500">
                      <span>{p.date_of_birth || 'بدون ميلاد'}</span>
                      <span>{p.phone || 'بدون هاتف'}</span>
                      {isAdmin && (
                        <button
                          onClick={() => merge(p.id, group)}
                          className="btn-secondary text-xs"
                          disabled={merging}
                        >
                          اجعل هذا سجلاً رئيسياً
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </AuthLayout>
  );
}