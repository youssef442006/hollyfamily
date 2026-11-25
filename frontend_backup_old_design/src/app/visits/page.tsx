'use client';
import AuthLayout from '@/components/AuthLayout';
import { visitsAPI, familiesAPI, personsAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { HiPlus, HiCheck, HiPhone } from 'react-icons/hi';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';

export default function VisitsPage() {
  const [visits, setVisits] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAdd, setShowAdd] = useState(false);
  const [filter, setFilter] = useState({ status: '', visit_type: '' });
  const [newVisit, setNewVisit] = useState({
    family_id: '', person_id: '', visit_date: '', visit_type: 'home_visit', notes: '', next_visit_date: ''
  });
  const [familySearch, setFamilySearch] = useState('');
  const [familyResults, setFamilyResults] = useState<any[]>([]);

  const loadData = async () => {
    try {
      const params: any = {};
      if (filter.status) params.status = filter.status;
      if (filter.visit_type) params.visit_type = filter.visit_type;
      const res = await visitsAPI.list(params);
      setVisits(res.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadData(); }, [filter]);

  useEffect(() => {
    if (!familySearch.trim()) { setFamilyResults([]); return; }
    const t = setTimeout(async () => {
      try {
        const res = await familiesAPI.list({ search: familySearch });
        setFamilyResults(res.data);
      } catch {}
    }, 300);
    return () => clearTimeout(t);
  }, [familySearch]);

  const addVisit = async () => {
    if (!newVisit.visit_date) { toast.error('أدخل تاريخ الزيارة'); return; }
    try {
      await visitsAPI.create({
        family_id: newVisit.family_id ? parseInt(newVisit.family_id) : null,
        person_id: newVisit.person_id ? parseInt(newVisit.person_id) : null,
        visit_date: newVisit.visit_date,
        visit_type: newVisit.visit_type,
        notes: newVisit.notes,
        next_visit_date: newVisit.next_visit_date || null,
      });
      toast.success('تم تسجيل الزيارة');
      setShowAdd(false);
      setNewVisit({ family_id: '', person_id: '', visit_date: '', visit_type: 'home_visit', notes: '', next_visit_date: '' });
      loadData();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ'));
    }
  };

  const completeVisit = async (id: number) => {
    try {
      await visitsAPI.update(id, { status: 'completed' });
      toast.success('تم تسجيل الإتمام');
      loadData();
    } catch {}
  };

  const statusBadge = (status: string) => {
    const map: any = { pending: 'badge-yellow', completed: 'badge-green', cancelled: 'badge-red' };
    const labels: any = { pending: 'معلق', completed: 'مكتمل', cancelled: 'ملغي' };
    return <span className={map[status] || 'badge-blue'}>{labels[status] || status}</span>;
  };

  const typeLabel = (type: string) => {
    const labels: any = { home_visit: 'زيارة منزلية', phone_call: 'اتصال', follow_up: 'متابعة', emergency_visit: 'زيارة طارئة' };
    return labels[type] || type;
  };

  return (
    <AuthLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">الافتقاد</h1>
          <p className="text-gray-500 mt-1">إدارة افتقاد وزيارات الكنيسة</p>
        </div>
        <button onClick={() => setShowAdd(true)} className="btn-primary flex items-center gap-2">
          <HiPlus size={20} /> إضافة زيارة
        </button>
      </div>

      <div className="card mb-6">
        <div className="flex gap-4">
          <select className="input-field" value={filter.status} onChange={e => setFilter({...filter, status: e.target.value})}>
            <option value="">جميع الحالات</option>
            <option value="pending">معلق</option>
            <option value="completed">مكتمل</option>
            <option value="cancelled">ملغي</option>
          </select>
          <select className="input-field" value={filter.visit_type} onChange={e => setFilter({...filter, visit_type: e.target.value})}>
            <option value="">جميع الأنواع</option>
            <option value="home_visit">زيارة منزلية</option>
            <option value="phone_call">اتصال</option>
            <option value="follow_up">متابعة</option>
            <option value="emergency_visit">زيارة طارئة</option>
          </select>
        </div>
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-10 text-gray-500">جاري التحميل...</div>
        ) : visits.length === 0 ? (
          <div className="text-center py-10 text-gray-500">لا توجد زيارات</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="table-header">
                  <th className="px-4 py-3 text-right">الأسرة / الشخص</th>
                  <th className="px-4 py-3 text-right">الخادم</th>
                  <th className="px-4 py-3 text-right">نوع الزيارة</th>
                  <th className="px-4 py-3 text-right">تاريخ الزيارة</th>
                  <th className="px-4 py-3 text-right">الزيارة القادمة</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {visits.map((v) => (
                  <tr key={v.id} className="border-b border-gray-100 hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium">{v.family_name || v.person_name || '-'}</td>
                    <td className="px-4 py-3">{v.servant_name || '-'}</td>
                    <td className="px-4 py-3">{typeLabel(v.visit_type)}</td>
                    <td className="px-4 py-3">{new Date(v.visit_date).toLocaleDateString('ar-EG')}</td>
                    <td className="px-4 py-3">{v.next_visit_date ? new Date(v.next_visit_date).toLocaleDateString('ar-EG') : '-'}</td>
                    <td className="px-4 py-3">{statusBadge(v.status)}</td>
                    <td className="px-4 py-3">
                      {v.status === 'pending' && (
                        <button onClick={() => completeVisit(v.id)} className="btn-success text-sm flex items-center gap-1">
                          <HiCheck size={14} /> تم
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {showAdd && (
        <div className="modal-overlay" onClick={() => setShowAdd(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">إضافة زيارة جديدة</h3>
            <div className="space-y-3">
              <div>
                <label className="label">الأسرة</label>
                <input className="input-field" placeholder="بحث عن أسرة..." value={familySearch} onChange={e => setFamilySearch(e.target.value)} />
                {familySearch.trim() && (
                  <div className="mt-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                    {familyResults.length === 0 ? (
                      <div className="text-sm text-gray-500 text-center py-2">لا توجد نتائج</div>
                    ) : (
                      familyResults.map(f => (
                        <div key={f.id} className={`p-2 rounded cursor-pointer hover:bg-blue-50 ${newVisit.family_id === String(f.id) ? 'bg-blue-100' : ''}`}
                          onClick={() => { setNewVisit({...newVisit, family_id: String(f.id)}); setFamilySearch(f.family_name); setFamilyResults([]); }}>
                          {f.family_name} - {f.phone}
                        </div>
                      ))
                    )}
                  </div>
                )}
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">نوع الزيارة</label>
                  <select className="input-field" value={newVisit.visit_type} onChange={e => setNewVisit({...newVisit, visit_type: e.target.value})}>
                    <option value="home_visit">زيارة منزلية</option>
                    <option value="phone_call">اتصال</option>
                    <option value="follow_up">متابعة</option>
                    <option value="emergency_visit">زيارة طارئة</option>
                  </select>
                </div>
                <div><label className="label">تاريخ الزيارة *</label><input type="datetime-local" className="input-field" value={newVisit.visit_date} onChange={e => setNewVisit({...newVisit, visit_date: e.target.value})} /></div>
              </div>
              <div><label className="label">الزيارة القادمة</label><input type="datetime-local" className="input-field" value={newVisit.next_visit_date} onChange={e => setNewVisit({...newVisit, next_visit_date: e.target.value})} /></div>
              <div><label className="label">ملاحظات</label><textarea className="input-field" value={newVisit.notes} onChange={e => setNewVisit({...newVisit, notes: e.target.value})} rows={2} /></div>
              <div className="flex gap-3 mt-4">
                <button onClick={addVisit} className="btn-primary">إضافة</button>
                <button onClick={() => setShowAdd(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AuthLayout>
  );
}
