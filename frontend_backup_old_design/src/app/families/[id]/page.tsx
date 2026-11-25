'use client';
import AuthLayout from '@/components/AuthLayout';
import { familiesAPI, personsAPI, servicesAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { HiPlus, HiArrowRight, HiPencil, HiTrash } from 'react-icons/hi';
import { translate, relationshipTypes, educationStages, schoolYears, genders, careSituations, specialCases, suggestedAreas } from '@/lib/translations';
import { useAuth } from '@/contexts/AuthContext';

export default function FamilyDetailPage() {
  const params = useParams();
  const router = useRouter();
  const { isEditor, isModerator } = useAuth();
  const [family, setFamily] = useState<any>(null);
  const [members, setMembers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddMember, setShowAddMember] = useState(false);
  const [showEdit, setShowEdit] = useState(false);
  const [editData, setEditData] = useState({ family_name: '', phone: '', address: '', street: '', branch_from: '', area: '', landmark: '', building_number: '', floor: '', apartment_number: '', gps_location: '', special_case: '', notes: '' });
const [newMember, setNewMember] = useState({
    first_name: '', middle_name: '', last_name: '', gender: 'male',
    date_of_birth: '', phone: '', education_stage: '', school_year: '', care_situation: 'normal', relationship_type: 'son', confession_father: '', service_id: ''
  });
  const [services, setServices] = useState<any[]>([]);
  const [showAddExisting, setShowAddExisting] = useState(false);
  const [existingSearch, setExistingSearch] = useState('');
  const [existingResults, setExistingResults] = useState<any[]>([]);
  const [existingRole, setExistingRole] = useState('son');

  const currentMembers = members.filter((m: any) => m.is_current !== false);
  const historicalMembers = members.filter((m: any) => m.is_current === false);

  const loadFamily = async () => {
    try {
      const id = Number(params.id);
      const [famRes, memRes] = await Promise.all([
        familiesAPI.get(id),
        familiesAPI.getMembers(id),
      ]);
      setFamily(famRes.data);
      setMembers(memRes.data);
    } catch { toast.error('خطأ في تحميل البيانات'); }
    setLoading(false);
  };

  useEffect(() => { loadFamily(); }, [params.id]);

  useEffect(() => {
    const loadServices = async () => {
      try { setServices((await servicesAPI.list()).data); } catch {}
    };
    loadServices();
  }, []);

  useEffect(() => {
    if (!existingSearch.trim()) { setExistingResults([]); return; }
    const t = setTimeout(async () => {
      try {
        const res = await personsAPI.list({ search: existingSearch });
        setExistingResults(res.data);
      } catch {}
    }, 300);
    return () => clearTimeout(t);
  }, [existingSearch]);

  const openEdit = () => {
    setEditData({ family_name: family.family_name, phone: family.phone || '', address: family.address || '', street: family.street || '', branch_from: family.branch_from || '', area: family.area || '', landmark: family.landmark || '', building_number: family.building_number || '', floor: family.floor || '', apartment_number: family.apartment_number || '', gps_location: family.gps_location || '', special_case: family.special_case || '', notes: family.notes || '' });
    setShowEdit(true);
  };

  const saveEdit = async () => {
    try {
      await familiesAPI.update(Number(params.id), editData);
      toast.success('تم حفظ التعديلات');
      setShowEdit(false);
      loadFamily();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحفظ'));
    }
  };

  const deleteFamily = async () => {
    if (!window.confirm('هل أنت متأكد من حذف هذه الأسرة؟')) return;
    try {
      await familiesAPI.delete(Number(params.id));
      toast.success('تم حذف الأسرة');
      router.push('/families');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  const removeMember = async (personId: number, name: string) => {
    if (!window.confirm(`هل تريد إنهاء عضوية "${name}" في الأسرة؟ (سيُحتفظ به كعضو سابق)`)) return;
    try {
      await familiesAPI.removeMember(Number(params.id), personId);
      toast.success('تم إنهاء العضوية مع الاحتفاظ بالسجل');
      loadFamily();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  const addExistingMember = async (personId: number) => {
    try {
      await familiesAPI.addMember(Number(params.id), {
        person_id: personId,
        relationship_type: existingRole,
        is_child: existingRole === 'son' || existingRole === 'daughter',
        is_head: existingRole === 'father' || existingRole === 'husband',
        is_spouse: existingRole === 'mother' || existingRole === 'wife',
      });
      toast.success('تمت إضافة الفرد للأسرة');
      setShowAddExisting(false);
      setExistingSearch('');
      setExistingResults([]);
      loadFamily();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الإضافة'));
    }
  };

  const addMember = async () => {
    try {
      if (!newMember.first_name.trim()) { toast.error('من فضلك اكتب الاسم الأول'); return; }
      const personRes = await personsAPI.create({
        first_name: newMember.first_name,
        middle_name: newMember.middle_name,
        last_name: newMember.last_name,
        gender: newMember.gender,
        date_of_birth: newMember.date_of_birth || null,
        phone: newMember.phone || null,
        care_situation: newMember.care_situation || 'normal',
        education_stage: newMember.education_stage || null,
        school_year: newMember.school_year || null,
        confession_father: newMember.confession_father || null,
        family_id: Number(params.id),
      });
      const personId = personRes.data.id;
      await familiesAPI.addMember(Number(params.id), {
        person_id: personId,
        relationship_type: newMember.relationship_type,
        is_child: newMember.relationship_type === 'son' || newMember.relationship_type === 'daughter',
        is_head: newMember.relationship_type === 'father',
        is_spouse: newMember.relationship_type === 'mother' || newMember.relationship_type === 'wife',
      });
      if (newMember.service_id) {
        await servicesAPI.createAssignment(Number(newMember.service_id), { person_id: personId });
      }
      toast.success('تمت إضافة الفرد بنجاح');
      setShowAddMember(false);
      setNewMember({ first_name: '', middle_name: '', last_name: '', gender: 'male', date_of_birth: '', phone: '', education_stage: '', school_year: '', care_situation: 'normal', relationship_type: 'son', confession_father: '', service_id: '' });
      loadFamily();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الإضافة'));
    }
  };

  if (loading) return <AuthLayout><div className="text-center py-20">جاري التحميل...</div></AuthLayout>;
  if (!family) return <AuthLayout><div className="text-center py-20">الأسرة غير موجودة</div></AuthLayout>;

  return (
    <AuthLayout>
      <div className="mb-6">
        <button onClick={() => router.back()} className="flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
          <HiArrowRight size={20} />
          رجوع
        </button>
        <div className="card">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-800">{family.family_name}</h1>
              <p className="text-gray-500 mt-1">كود: {family.family_code}</p>
              {family.special_case && (
                <span className="badge-red inline-block mt-2">{translate(family.special_case, specialCases)}</span>
              )}
            </div>
            <div className="flex items-center gap-3">
              <div className="text-left">
                <p className="text-sm text-gray-500">{family.phone}</p>
                {family.area && <p className="text-sm text-gray-500">{family.area}{family.street ? ` - ${family.street}` : ''}{family.building_number ? ` - ${family.building_number}` : ''}</p>}
                {(family.landmark || family.branch_from || family.apartment_number) && (
                  <p className="text-sm text-gray-500">{family.landmark || ''}{family.branch_from ? ` - متفرع من: ${family.branch_from}` : ''}{family.apartment_number ? ` - شقة ${family.apartment_number}` : ''}</p>
                )}
                {family.gps_location && (
                  <p className="text-xs text-gray-400">
                    <a href={`https://www.google.com/maps?q=${family.gps_location}`} target="_blank" rel="noreferrer" className="text-blue-600 underline">موقع على الخريطة</a>
                  </p>
                )}
                <p className="text-sm text-gray-500">{family.address}</p>
              </div>
              {isEditor && (
                <button onClick={openEdit} className="btn-secondary flex items-center gap-1 text-sm">
                  <HiPencil size={16} /> تعديل
                </button>
              )}
              {isModerator && (
                <button onClick={deleteFamily} className="btn-danger flex items-center gap-1 text-sm">
                  <HiTrash size={16} /> حذف
                </button>
              )}
            </div>
          </div>
        </div>
      </div>

      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-bold text-gray-800">أفراد الأسرة ({currentMembers.length})</h2>
        <div className="flex gap-2">
          <Link href={`/families/${params.id}/tree`} className="btn-secondary flex items-center gap-2">
            شجرة العائلة
          </Link>
          {isEditor && (
            <>
              <button onClick={() => setShowAddExisting(true)} className="btn-secondary flex items-center gap-2">
                <HiPlus size={18} />
                إضافة شخص موجود
              </button>
              <button onClick={() => setShowAddMember(true)} className="btn-primary flex items-center gap-2">
                <HiPlus size={18} />
                إضافة فرد جديد
              </button>
            </>
          )}
        </div>
      </div>

      <div className="card">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="table-header">
                <th className="px-4 py-3 text-right">الاسم</th>
                <th className="px-4 py-3 text-right">صلة القرابة</th>
                <th className="px-4 py-3 text-right">العمر</th>
                <th className="px-4 py-3 text-right">المرحلة</th>
                <th className="px-4 py-3 text-right">السنة</th>
                <th className="px-4 py-3 text-right">أب الاعتراف</th>
                <th className="px-4 py-3 text-right">حالة الخدمة</th>
                <th className="px-4 py-3 text-right">الهاتف</th>
                <th className="px-4 py-3 text-right">إجراءات</th>
              </tr>
            </thead>
<tbody>
                {currentMembers.map((m) => {
                const p = m.person;
                const age = p?.date_of_birth
                  ? Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
                  : '-';
                return (
                  <tr key={m.id} className="border-b border-gray-100 hover:bg-gray-50">
                    <td className="px-4 py-3">
                      {p ? (
                        <Link href={`/persons/${p.id}`} className="text-church-primary hover:underline font-medium">
                          {p.first_name} {p.middle_name || ''} {p.last_name || ''}
                        </Link>
                      ) : '-'}
                    </td>
                    <td className="px-4 py-3">
                      {isEditor ? (
                        <select
                          className="input-field py-1"
                          value={m.relationship_type}
                          onChange={async (e) => {
                            const newRole = e.target.value;
                            try {
                              await familiesAPI.updateMember(Number(params.id), p.id, { relationship_type: newRole });
                              toast.success('تم تحديث صلة القرابة');
                              loadFamily();
                            } catch (err: any) {
                              toast.error(errorMessage(err, 'خطأ في التحديث'));
                            }
                          }}
                        >
                          {Object.entries(relationshipTypes).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                          ))}
                        </select>
                      ) : (
                        translate(m.relationship_type, relationshipTypes)
                      )}
                    </td>
                    <td className="px-4 py-3">{age}</td>
                    <td className="px-4 py-3">{translate(p?.education_stage, educationStages)}</td>
                    <td className="px-4 py-3">{translate(p?.school_year, schoolYears)}</td>
                    <td className="px-4 py-3">{p?.confession_father || '-'}</td>
                    <td className="px-4 py-3">{translate(p?.care_situation, careSituations)}</td>
                    <td className="px-4 py-3">{p?.phone || '-'}</td>
                    <td className="px-4 py-3">
                      {p && isModerator && (
                        <button
                          onClick={() => removeMember(p.id, `${p.first_name} ${p.last_name || ''}`.trim())}
                          className="text-red-500 hover:text-red-700"
                          title="حذف من الأسرة"
                        >
                          <HiTrash size={18} />
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {historicalMembers.length > 0 && (
        <div className="card mt-6">
          <h2 className="text-lg font-bold text-gray-800 mb-4">أعضاء سابقون ({historicalMembers.length})</h2>
          <div className="space-y-2">
            {historicalMembers.map((m: any) => {
              const p = m.person;
              return (
                <div key={m.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg opacity-80">
                  <div>
                    {p ? (
                      <Link href={`/persons/${p.id}`} className="text-church-primary hover:underline font-medium">
                        {p.first_name} {p.middle_name || ''} {p.last_name || ''}
                      </Link>
                    ) : '-'}
                    <span className="text-sm text-gray-500 mr-2">{translate(m.relationship_type, relationshipTypes)}</span>
                  </div>
                  <span className="text-sm text-gray-500">{m.start_date || ''} ← {m.end_date || ''}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {showEdit && (
        <div className="modal-overlay" onClick={() => setShowEdit(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">تعديل بيانات الأسرة</h3>
            <div className="space-y-3">
              <div>
                <label className="label">اسم الأسرة</label>
                <input className="input-field" value={editData.family_name} onChange={e => setEditData({...editData, family_name: e.target.value})} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">الهاتف</label>
                  <input className="input-field" value={editData.phone} onChange={e => setEditData({...editData, phone: e.target.value})} />
                </div>
                <div>
                  <label className="label">العنوان (نص حر)</label>
                  <input className="input-field" value={editData.address} onChange={e => setEditData({...editData, address: e.target.value})} />
                </div>
                <div className="col-span-2">
                  <label className="label">المنطقة</label>
                  <input className="input-field" list="mf-areas" value={editData.area} onChange={e => setEditData({...editData, area: e.target.value})} placeholder="اختر أو اكتب" />
                  <datalist id="mf-areas">
                    {suggestedAreas.map(a => <option key={a} value={a} />)}
                  </datalist>
                </div>
                <div><label className="label">اسم الشارع</label><input className="input-field" value={editData.street} onChange={e => setEditData({...editData, street: e.target.value})} /></div>
                <div><label className="label">متفرع من</label><input className="input-field" value={editData.branch_from} onChange={e => setEditData({...editData, branch_from: e.target.value})} /></div>
                <div className="col-span-2"><label className="label">أقرب مكان مميز</label><input className="input-field" value={editData.landmark} onChange={e => setEditData({...editData, landmark: e.target.value})} /></div>
                <div><label className="label">رقم المنزل</label><input className="input-field" value={editData.building_number} onChange={e => setEditData({...editData, building_number: e.target.value})} /></div>
                <div><label className="label">الدور</label><input className="input-field" value={editData.floor} onChange={e => setEditData({...editData, floor: e.target.value})} /></div>
                <div className="col-span-2"><label className="label">رقم الشقة</label><input className="input-field" value={editData.apartment_number} onChange={e => setEditData({...editData, apartment_number: e.target.value})} /></div>
                <div className="col-span-2"><label className="label">إحداثيات GPS</label><input className="input-field" value={editData.gps_location} onChange={e => setEditData({...editData, gps_location: e.target.value})} placeholder="مثال: 30.123456,31.123456" /></div>
              </div>
              <div>
                <label className="label">حالة خاصة</label>
                <select className="input-field" value={editData.special_case} onChange={e => setEditData({...editData, special_case: e.target.value})}>
                  {Object.entries(specialCases).map(([k, v]) => (
                    <option key={k} value={k}>{v || 'بدون حالة خاصة'}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">ملاحظات</label>
                <textarea className="input-field" rows={2} value={editData.notes} onChange={e => setEditData({...editData, notes: e.target.value})} />
              </div>
              <div className="flex gap-3 mt-4">
                <button onClick={saveEdit} className="btn-primary">حفظ</button>
                <button onClick={() => setShowEdit(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {showAddMember && (
        <div className="modal-overlay" onClick={() => setShowAddMember(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">إضافة فرد جديد</h3>
            <div className="space-y-3">
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="label">الاسم الأول</label>
                  <input className="input-field" value={newMember.first_name} onChange={e => setNewMember({...newMember, first_name: e.target.value})} />
                </div>
                <div>
                  <label className="label">الاسم الأوسط</label>
                  <input className="input-field" value={newMember.middle_name} onChange={e => setNewMember({...newMember, middle_name: e.target.value})} />
                </div>
                <div>
                  <label className="label">الاسم الأخير</label>
                  <input className="input-field" value={newMember.last_name} onChange={e => setNewMember({...newMember, last_name: e.target.value})} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">النوع</label>
                  <select className="input-field" value={newMember.gender} onChange={e => setNewMember({...newMember, gender: e.target.value})}>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                  </select>
                </div>
                <div>
                  <label className="label">صلة القرابة</label>
                  <select className="input-field" value={newMember.relationship_type} onChange={e => setNewMember({...newMember, relationship_type: e.target.value})}>
                    <option value="father">أب</option>
                    <option value="mother">أم</option>
                    <option value="son">ابن</option>
                    <option value="daughter">ابنة</option>
                    <option value="grandfather">جد</option>
                    <option value="grandmother">جدة</option>
                    <option value="relative">قريب</option>
                    <option value="other">أخرى</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">تاريخ الميلاد</label>
                  <input type="date" className="input-field" value={newMember.date_of_birth} onChange={e => setNewMember({...newMember, date_of_birth: e.target.value})} />
                </div>
                <div>
                  <label className="label">الهاتف</label>
                  <input className="input-field" value={newMember.phone} onChange={e => setNewMember({...newMember, phone: e.target.value})} />
                </div>
              </div>
              <div>
                <label className="label">حالة الخدمة</label>
                <select className="input-field" value={newMember.care_situation} onChange={e => setNewMember({...newMember, care_situation: e.target.value})}>
                  {Object.entries(careSituations).map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">المرحلة الدراسية</label>
                  <select className="input-field" value={newMember.education_stage} onChange={e => setNewMember({...newMember, education_stage: e.target.value, school_year: ''})}>
                    <option value="">بدون</option>
                    {Object.entries(educationStages).filter(([k]) => k !== 'university').map(([k, v]) => (
                      <option key={k} value={k}>{v}</option>
                    ))}
                    <option value="university">جامعة</option>
                    <option value="graduate">خريج</option>
                  </select>
                </div>
                <div>
                  <label className="label">سنة كام بالظبط؟</label>
                  <select className="input-field" value={newMember.school_year} onChange={e => setNewMember({...newMember, school_year: e.target.value})}>
                    <option value="">بدون</option>
                    {Object.entries(schoolYears).map(([k, v]) => (
                      <option key={k} value={k}>{v}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="label">الاجتماع / الخدمة</label>
                <select className="input-field" value={newMember.service_id} onChange={e => setNewMember({...newMember, service_id: e.target.value})}>
                  <option value="">بدون اجتماع</option>
                  {services.map((s: any) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">أب الاعتراف</label>
                <input className="input-field" list="confession-fathers-family" value={newMember.confession_father} onChange={e => setNewMember({...newMember, confession_father: e.target.value})} placeholder="ابونا سيرافيم / ابونا جوزيف / اكتب أي حد تاني" />
              </div>
              <div className="flex gap-3 mt-4">
                <button onClick={addMember} className="btn-primary">إضافة</button>
                <button onClick={() => setShowAddMember(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {showAddExisting && (
        <div className="modal-overlay" onClick={() => setShowAddExisting(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-1">إضافة شخص موجود للأسرة</h3>
            <p className="text-sm text-gray-500 mb-4">ابحث عن الشخص المسجل مسبقاً — لن يتم إنشاء سجل جديد</p>
            <div className="mb-4">
              <label className="label">بحث بالاسم (أو الهاتف)</label>
              <input className="input-field" placeholder="اكتب للبحث..." value={existingSearch} onChange={e => setExistingSearch(e.target.value)} />
            </div>
            <div className="mb-4">
              <label className="label">الدور في الأسرة</label>
              <select className="input-field" value={existingRole} onChange={e => setExistingRole(e.target.value)}>
                <option value="father">أب</option>
                <option value="mother">أم</option>
                <option value="husband">زوج</option>
                <option value="wife">زوجة</option>
                <option value="son">ابن</option>
                <option value="daughter">ابنة</option>
                <option value="grandfather">جد</option>
                <option value="grandmother">جدة</option>
                <option value="relative">قريب</option>
                <option value="other">أخرى</option>
              </select>
            </div>
            <div className="space-y-2 max-h-60 overflow-y-auto">
              {existingSearch.trim() && existingResults.length === 0 && (
                <div className="text-sm text-gray-500 text-center py-2">لا توجد نتائج</div>
              )}
              {existingResults.map((p: any) => (
                <div key={p.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                  <span>{p.first_name} {p.middle_name || ''} {p.last_name || ''} - {p.phone || 'بدون هاتف'}</span>
                  <button onClick={() => addExistingMember(p.id)} className="btn-success text-sm">إضافة</button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
      <datalist id="confession-fathers-family">
        <option value="ابونا سيرافيم" />
        <option value="ابونا جوزيف" />
      </datalist>
    </AuthLayout>
  );
}
