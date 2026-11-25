'use client';
import AuthLayout from '@/components/AuthLayout';
import { servicesAPI, personsAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { HiArrowRight, HiPlus, HiPencil, HiTrash } from 'react-icons/hi';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { translate, serviceRoles, educationStages } from '@/lib/translations';
import { useAuth } from '@/contexts/AuthContext';

export default function ServiceDetailPage() {
  const params = useParams();
  const { isEditor, isModerator } = useAuth();
  const router = useRouter();
  const [service, setService] = useState<any>(null);
  const [members, setMembers] = useState<any[]>([]);
  const [assignments, setAssignments] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAssignServant, setShowAssignServant] = useState(false);
  const [showAssignBeneficiary, setShowAssignBeneficiary] = useState(false);
  const [showEdit, setShowEdit] = useState(false);
  const [editData, setEditData] = useState({ name: '', description: '', min_age: '', max_age: '' });
  const [personSearch, setPersonSearch] = useState('');
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [servantRole, setServantRole] = useState('servant');

  const loadData = async () => {
    try {
      const id = Number(params.id);
      const [svcRes, memRes, assignRes] = await Promise.all([
        servicesAPI.get(id),
        servicesAPI.getMembers(id),
        servicesAPI.getAssignments(id),
      ]);
      setService(svcRes.data);
      setMembers(memRes.data);
      setAssignments(assignRes.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadData(); }, [params.id]);

  useEffect(() => {
    if (!personSearch.trim()) { setSearchResults([]); return; }
    const t = setTimeout(() => { searchPersons(); }, 400);
    return () => clearTimeout(t);
  }, [personSearch]);

  const searchPersons = async () => {
    if (!personSearch) return;
    try {
      const res = await personsAPI.list({ search: personSearch });
      setSearchResults(res.data);
    } catch {}
  };

  const openEdit = () => {
    setEditData({
      name: service.name, description: service.description || '',
      min_age: service.min_age ?? '', max_age: service.max_age ?? '',
    });
    setShowEdit(true);
  };

  const saveEdit = async () => {
    try {
      await servicesAPI.update(Number(params.id), {
        name: editData.name,
        description: editData.description,
        min_age: editData.min_age !== '' ? parseInt(editData.min_age) : null,
        max_age: editData.max_age !== '' ? parseInt(editData.max_age) : null,
      });
      toast.success('تم حفظ التعديلات');
      setShowEdit(false);
      loadData();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحفظ'));
    }
  };

  const deleteService = async () => {
    if (!window.confirm(`هل أنت متأكد من حذف الخدمة "${service.name}"؟`)) return;
    try {
      await servicesAPI.deleteService(Number(params.id));
      toast.success('تم حذف الخدمة');
      router.push('/services');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  const assignServant = async (personId: number) => {
    try {
      await servicesAPI.addMember(Number(params.id), { person_id: personId, service_id: Number(params.id), role: servantRole });
      toast.success('تم تعيين الخادم بنجاح');
      setShowAssignServant(false);
      setPersonSearch('');
      setSearchResults([]);
      setServantRole('servant');
      loadData();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ'));
    }
  };

  const assignBeneficiary = async (personId: number) => {
    try {
      await servicesAPI.createAssignment(Number(params.id), { person_id: personId, service_id: Number(params.id), status: 'active' });
      toast.success('تمت إضافة المخدوم بنجاح');
      setShowAssignBeneficiary(false);
      setPersonSearch('');
      setSearchResults([]);
      loadData();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الإضافة'));
    }
  };

  if (loading) return <AuthLayout><div className="text-center py-20">جاري التحميل...</div></AuthLayout>;
  if (!service) return <AuthLayout><div className="text-center py-20">الخدمة غير موجودة</div></AuthLayout>;

  return (
    <AuthLayout>
      <button onClick={() => router.back()} className="flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
        <HiArrowRight size={20} /> رجوع
      </button>

      <div className="card mb-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">{service.name}</h1>
            <p className="text-gray-500 mt-1">{service.description}</p>
          </div>
          <div className="flex items-center gap-3">
            <span className={service.is_active ? 'badge-green' : 'badge-red'}>
              {service.is_active ? 'نشطة' : 'غير نشطة'}
            </span>
            {isEditor && (
              <button onClick={openEdit} className="btn-secondary flex items-center gap-1 text-sm">
                <HiPencil size={16} /> تعديل
              </button>
            )}
            {isModerator && (
              <button onClick={deleteService} className="btn-danger flex items-center gap-1 text-sm">
                <HiTrash size={16} /> حذف
              </button>
            )}
          </div>
        </div>
        <div className="grid grid-cols-3 gap-4 mt-4 pt-4 border-t">
          <div className="text-center"><p className="text-2xl font-bold text-church-primary">{members.length}</p><p className="text-sm text-gray-500">خدام</p></div>
          <div className="text-center"><p className="text-2xl font-bold text-green-600">{assignments.length}</p><p className="text-sm text-gray-500">مخدومين</p></div>
          <div className="text-center"><p className="text-2xl font-bold text-gray-600">{service.min_age || 0} - {service.max_age || '؟'}</p><p className="text-sm text-gray-500">العمر</p></div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold">المخدومين</h2>
            {isEditor && (
              <button onClick={() => setShowAssignBeneficiary(true)} className="btn-primary flex items-center gap-2 text-sm">
                <HiPlus size={16} /> إضافة مخدوم
              </button>
            )}
          </div>
          <div className="space-y-2">
            {assignments.length === 0 ? (
              <p className="text-gray-500 text-center py-4">لا يوجد مخدومين</p>
            ) : (
              assignments.map((a) => (
                <div key={a.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                  <div>
                    <span>{a.person?.first_name || ''} {a.person?.middle_name || ''} {a.person?.last_name || ''}</span>
                    <span className="block text-xs text-gray-500 mt-0.5">
                      {translate(a.person?.education_stage, educationStages)} - {a.person?.date_of_birth ? `${Math.floor((Date.now() - new Date(a.person.date_of_birth).getTime()) / (365.25 * 24 * 60 * 60 * 1000))} سنة` : 'بدون عمر'}
                    </span>
                  </div>
                  <span className="badge-green">{a.status}</span>
                </div>
              ))
            )}
          </div>
        </div>

        <div className="card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold">الخدام</h2>
            {isEditor && (
              <button onClick={() => setShowAssignServant(true)} className="btn-primary flex items-center gap-2 text-sm">
                <HiPlus size={16} /> تعيين خادم
              </button>
            )}
          </div>
          <div className="space-y-2">
            {members.length === 0 ? (
              <p className="text-gray-500 text-center py-4">لا يوجد خدام</p>
            ) : (
              members.map((m) => (
                <div key={m.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                  <div>
                    <span>{m.person?.first_name || ''} {m.person?.middle_name || ''} {m.person?.last_name || ''}</span>
                    <span className="block text-xs text-gray-500 mt-0.5">
                      {translate(m.person?.education_stage, educationStages)} - {m.person?.date_of_birth ? `${Math.floor((Date.now() - new Date(m.person.date_of_birth).getTime()) / (365.25 * 24 * 60 * 60 * 1000))} سنة` : 'بدون عمر'}
                    </span>
                  </div>
                  <span className="badge-blue">{translate(m.role, serviceRoles)}</span>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {showEdit && (
        <div className="modal-overlay" onClick={() => setShowEdit(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">تعديل الخدمة</h3>
            <div className="space-y-3">
              <div><label className="label">اسم الخدمة *</label><input className="input-field" value={editData.name} onChange={e => setEditData({...editData, name: e.target.value})} /></div>
              <div><label className="label">الوصف</label><input className="input-field" value={editData.description} onChange={e => setEditData({...editData, description: e.target.value})} /></div>
              <div className="grid grid-cols-2 gap-3">
                <div><label className="label">الحد الأدنى للعمر</label><input type="number" className="input-field" value={editData.min_age} onChange={e => setEditData({...editData, min_age: e.target.value})} /></div>
                <div><label className="label">الحد الأقصى للعمر</label><input type="number" className="input-field" value={editData.max_age} onChange={e => setEditData({...editData, max_age: e.target.value})} /></div>
              </div>
              <div className="flex gap-3 mt-4">
                <button onClick={saveEdit} className="btn-primary">حفظ</button>
                <button onClick={() => setShowEdit(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {showAssignServant && (
        <div className="modal-overlay" onClick={() => setShowAssignServant(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">تعيين خادم (من الأفراد المسجلين)</h3>
            <div className="flex gap-2 mb-4">
              <input className="input-field flex-1" placeholder="بحث عن شخص بالاسم..." value={personSearch} onChange={e => setPersonSearch(e.target.value)} />
              <button onClick={searchPersons} className="btn-primary">بحث</button>
            </div>
            <div className="mb-4">
              <label className="label">دور الخادم</label>
              <select className="input-field" value={servantRole} onChange={e => setServantRole(e.target.value)}>
                <option value="service_admin">أمين خدمة</option>
                <option value="assistant_admin">أمين مساعد</option>
                <option value="servant">خادم</option>
              </select>
            </div>
            <div className="space-y-2 max-h-60 overflow-y-auto">
              {searchResults.map((p) => (
                <div key={p.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                  <span>{p.first_name} {p.middle_name || ''} {p.last_name || ''}</span>
                  <button onClick={() => assignServant(p.id)} className="btn-success text-sm">تعيين</button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    {showAssignBeneficiary && (
        <div className="modal-overlay" onClick={() => setShowAssignBeneficiary(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">إضافة مخدوم (من الأفراد المسجلين)</h3>
            <div className="flex gap-2 mb-4">
              <input className="input-field flex-1" placeholder="بحث عن شخص بالاسم..." value={personSearch} onChange={e => setPersonSearch(e.target.value)} />
              <button onClick={searchPersons} className="btn-primary">بحث</button>
            </div>
            <div className="space-y-2 max-h-60 overflow-y-auto">
              {searchResults.length === 0 ? (
                <p className="text-gray-500 text-center py-4">ابحث عن الشخص لإضافته</p>
              ) : (
                searchResults.map((p) => (
                  <div key={p.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                    <span>{p.first_name} {p.middle_name || ''} {p.last_name || ''}</span>
                    <button onClick={() => assignBeneficiary(p.id)} className="btn-success text-sm">إضافة</button>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}
    </AuthLayout>
  );
}
