'use client';
import AuthLayout from '@/components/AuthLayout';
import { confessionsAPI, priestsAPI, personsAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { HiPlus, HiCheck } from 'react-icons/hi';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';

export default function ConfessionsPage() {
  const [confessions, setConfessions] = useState<any[]>([]);
  const [priests, setPriests] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAdd, setShowAdd] = useState(false);
  const [filter, setFilter] = useState({ status: '', priest_id: '' });
  const [personSearch, setPersonSearch] = useState('');
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [selectedPerson, setSelectedPerson] = useState<any>(null);
  const [newConfession, setNewConfession] = useState({ priest_id: '', confession_date: '', next_confession_date: '', notes: '' });

  const loadData = async () => {
    try {
      const params: any = {};
      if (filter.status) params.status = filter.status;
      if (filter.priest_id) params.priest_id = filter.priest_id;
      const [cRes, pRes] = await Promise.all([
        confessionsAPI.list(params),
        priestsAPI.list(),
      ]);
      setConfessions(cRes.data);
      setPriests(pRes.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadData(); }, [filter]);

  const searchPersons = async () => {
    if (!personSearch) return;
    try {
      const res = await personsAPI.list({ search: personSearch });
      setSearchResults(res.data);
    } catch {}
  };

  const addConfession = async () => {
    if (!selectedPerson || !newConfession.confession_date) {
      toast.error('اختر الشخص وتاريخ الاعتراف');
      return;
    }
    try {
      await confessionsAPI.create({
        person_id: selectedPerson.id,
        priest_id: newConfession.priest_id ? parseInt(newConfession.priest_id) : null,
        confession_date: newConfession.confession_date,
        next_confession_date: newConfession.next_confession_date || null,
        notes: newConfession.notes,
      });
      toast.success('تم تسجيل الاعتراف');
      setShowAdd(false);
      setSelectedPerson(null);
      setPersonSearch('');
      setNewConfession({ priest_id: '', confession_date: '', next_confession_date: '', notes: '' });
      loadData();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ'));
    }
  };

  const completeConfession = async (id: number) => {
    try {
      await confessionsAPI.complete(id);
      toast.success('تم تسجيل الإتمام');
      loadData();
    } catch {}
  };

  const statusBadge = (status: string) => {
    const map: any = {
      upcoming: 'badge-blue',
      completed: 'badge-green',
      missed: 'badge-red',
      cancelled: 'badge-yellow',
    };
    const labels: any = { upcoming: 'قادم', completed: 'مكتمل', missed: 'فائت', cancelled: 'ملغي' };
    return <span className={map[status] || 'badge-blue'}>{labels[status] || status}</span>;
  };

  return (
    <AuthLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">الاعترافات</h1>
          <p className="text-gray-500 mt-1">إدارة اعترافات الكنيسة</p>
        </div>
        <button onClick={() => setShowAdd(true)} className="btn-primary flex items-center gap-2">
          <HiPlus size={20} /> إضافة اعتراف
        </button>
      </div>

      <div className="card mb-6">
        <div className="flex gap-4">
          <select className="input-field" value={filter.status} onChange={e => setFilter({...filter, status: e.target.value})}>
            <option value="">جميع الحالات</option>
            <option value="upcoming">قادم</option>
            <option value="completed">مكتمل</option>
            <option value="missed">فائت</option>
            <option value="cancelled">ملغي</option>
          </select>
          <select className="input-field" value={filter.priest_id} onChange={e => setFilter({...filter, priest_id: e.target.value})}>
            <option value="">جميع الكهنة</option>
            {priests.map(p => <option key={p.id} value={p.id}>{p.display_name}</option>)}
          </select>
        </div>
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-10 text-gray-500">جاري التحميل...</div>
        ) : confessions.length === 0 ? (
          <div className="text-center py-10 text-gray-500">لا توجد اعترافات</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="table-header">
                  <th className="px-4 py-3 text-right">الشخص</th>
                  <th className="px-4 py-3 text-right">الكاهن</th>
                  <th className="px-4 py-3 text-right">تاريخ الاعتراف</th>
                  <th className="px-4 py-3 text-right">الاعتراف القادم</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {confessions.map((c) => (
                  <tr key={c.id} className="border-b border-gray-100 hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium">{c.person_name || '-'}</td>
                    <td className="px-4 py-3">{c.priest_name || '-'}</td>
                    <td className="px-4 py-3">{new Date(c.confession_date).toLocaleDateString('ar-EG')}</td>
                    <td className="px-4 py-3">{c.next_confession_date ? new Date(c.next_confession_date).toLocaleDateString('ar-EG') : '-'}</td>
                    <td className="px-4 py-3">{statusBadge(c.status)}</td>
                    <td className="px-4 py-3">
                      {c.status === 'upcoming' && (
                        <button onClick={() => completeConfession(c.id)} className="btn-success text-sm flex items-center gap-1">
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
            <h3 className="text-lg font-bold mb-4">إضافة اعتراف جديد</h3>
            <div className="space-y-3">
              <div>
                <label className="label">الشخص *</label>
                <div className="flex gap-2">
                  <input className="input-field flex-1" placeholder="بحث عن شخص..." value={personSearch} onChange={e => setPersonSearch(e.target.value)} />
                  <button onClick={searchPersons} className="btn-primary">بحث</button>
                </div>
                {searchResults.length > 0 && (
                  <div className="mt-2 space-y-1 max-h-40 overflow-y-auto border rounded-lg p-2">
                    {searchResults.map(p => (
                      <div key={p.id} className={`p-2 rounded cursor-pointer hover:bg-blue-50 ${selectedPerson?.id === p.id ? 'bg-blue-100' : ''}`}
                        onClick={() => { setSelectedPerson(p); setPersonSearch(p.full_name_display || p.first_name); setSearchResults([]); }}>
                        {p.first_name} {p.last_name || ''}
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div>
                <label className="label">الكاهن</label>
                <select className="input-field" value={newConfession.priest_id} onChange={e => setNewConfession({...newConfession, priest_id: e.target.value})}>
                  <option value="">اختر الكاهن</option>
                  {priests.map(p => <option key={p.id} value={p.id}>{p.display_name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div><label className="label">تاريخ الاعتراف *</label><input type="datetime-local" className="input-field" value={newConfession.confession_date} onChange={e => setNewConfession({...newConfession, confession_date: e.target.value})} /></div>
                <div><label className="label">الاعتراف القادم</label><input type="datetime-local" className="input-field" value={newConfession.next_confession_date} onChange={e => setNewConfession({...newConfession, next_confession_date: e.target.value})} /></div>
              </div>
              <div><label className="label">ملاحظات</label><textarea className="input-field" value={newConfession.notes} onChange={e => setNewConfession({...newConfession, notes: e.target.value})} rows={2} /></div>
              <div className="flex gap-3 mt-4">
                <button onClick={addConfession} className="btn-primary">إضافة</button>
                <button onClick={() => setShowAdd(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AuthLayout>
  );
}
