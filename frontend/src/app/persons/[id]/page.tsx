'use client';
import AuthLayout from '@/components/AuthLayout';
import OccupationInput from '@/components/OccupationInput';
import { personsAPI, servicesAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { HiArrowRight, HiPencil, HiTrash } from 'react-icons/hi';
import { translate, relationshipTypes, educationStages, schoolYears, maritalStatuses, genders, confessionStatuses, visitTypes, visitStatuses, careSituations, kinshipTypes, weeklyLeaves } from '@/lib/translations';
import { useAuth } from '@/contexts/AuthContext';

export default function PersonDetailPage() {
  const params = useParams();
  const router = useRouter();
  const { isEditor, isModerator } = useAuth();
  const [profile, setProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [showEdit, setShowEdit] = useState(false);
  const [editData, setEditData] = useState<any>({});
  const [services, setServices] = useState<any[]>([]);
  const [newServiceId, setNewServiceId] = useState('');
  const [changingService, setChangingService] = useState(false);
  const [showCreateFamily, setShowCreateFamily] = useState(false);
  const [cfRole, setCfRole] = useState('');
  const [cfFamilyName, setCfFamilyName] = useState('');
  const [cfEndOld, setCfEndOld] = useState(true);
  const [cfSpouseMode, setCfSpouseMode] = useState<'none' | 'existing' | 'new'>('none');
  const [cfSpouseSearch, setCfSpouseSearch] = useState('');
  const [cfSpouseResults, setCfSpouseResults] = useState<any[]>([]);
  const [cfSpouseId, setCfSpouseId] = useState<number | null>(null);
  const [cfSpouseNew, setCfSpouseNew] = useState({ first_name: '', middle_name: '', last_name: '', gender: 'female', date_of_birth: '', phone: '', care_situation: 'normal', occupation: '' });
  const [showAddRel, setShowAddRel] = useState(false);
  const [relSearch, setRelSearch] = useState('');
  const [relResults, setRelResults] = useState<any[]>([]);
  const [relType, setRelType] = useState('father');

  useEffect(() => {
const loadProfile = async () => {
        try {
          const res = await personsAPI.getProfile(Number(params.id));
          setProfile(res.data);
          const active = res.data.service_assignments?.find((sa: any) => sa.status === 'active');
          setNewServiceId(active ? String(active.service_id) : '');
        } catch {}
        setLoading(false);
      };
    loadProfile();
    servicesAPI.list().then(res => setServices(res.data)).catch(() => {});
  }, [params.id]);

  const openEdit = () => {
    const p = profile.person;
    setEditData({
      first_name: p.first_name, middle_name: p.middle_name || '', last_name: p.last_name || '',
      gender: p.gender, date_of_birth: p.date_of_birth || '',
      is_deceased: p.is_deceased || false, date_of_death: p.date_of_death || '',
      phone: p.phone || '', whatsapp: p.whatsapp || '', second_phone: p.second_phone || '',
      occupation: p.occupation || '', weekly_leave: p.weekly_leave || '',
      marital_status: p.marital_status || '', care_situation: p.care_situation || 'normal',
      education_stage: p.education_stage || '', school_year: p.school_year || '',
      confession_father: p.confession_father || '', confession_church: p.confession_church || '',
      is_deacon: p.is_deacon || false, is_servant: p.is_servant || false, in_prep_center: p.in_prep_center || false,
    });
    setShowEdit(true);
  };

  const saveEdit = async () => {
    try {
      const payload: any = { ...editData };
      for (const k of ['date_of_birth', 'date_of_death', 'phone', 'marital_status', 'occupation', 'education_stage', 'school_year', 'confession_father', 'confession_church', 'second_phone']) {
        if (payload[k] === '') payload[k] = null;
      }
      await personsAPI.update(Number(params.id), payload);
      await personsAPI.changeService(Number(params.id), newServiceId ? Number(newServiceId) : undefined);
      toast.success('تم حفظ التعديلات');
      setShowEdit(false);
      reloadProfile();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحفظ'));
    }
  };

  const reloadProfile = async () => {
    try {
      const res = await personsAPI.getProfile(Number(params.id));
      setProfile(res.data);
      const active = res.data.service_assignments?.find((sa: any) => sa.status === 'active');
      setNewServiceId(active ? String(active.service_id) : '');
    } catch {}
  };

  const changeService = async () => {    setChangingService(true);
    try {
      const res = await personsAPI.changeService(Number(params.id), newServiceId ? Number(newServiceId) : undefined);
      toast.success(res.data.message || 'تم التحديث');
      reloadProfile();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في تغيير الخدمة'));
    }
    setChangingService(false);
  };

  const deletePerson = async () => {
    if (!window.confirm('هل أنت متأكد من حذف هذا الشخص؟')) return;
    try {
      await personsAPI.deletePerson(Number(params.id));
      toast.success('تم حذف الشخص');
      router.push('/persons');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  useEffect(() => {
    if (cfSpouseMode !== 'existing' || !cfSpouseSearch.trim()) { setCfSpouseResults([]); return; }
    const t = setTimeout(async () => {
      try {
        const res = await personsAPI.list({ search: cfSpouseSearch });
        setCfSpouseResults(res.data.filter((p: any) => p.id !== Number(params.id)));
      } catch {}
    }, 300);
    return () => clearTimeout(t);
  }, [cfSpouseSearch, cfSpouseMode]);

  useEffect(() => {
    if (!relSearch.trim()) { setRelResults([]); return; }
    const t = setTimeout(async () => {
      try {
        const res = await personsAPI.list({ search: relSearch });
        setRelResults(res.data.filter((p: any) => p.id !== Number(params.id)));
      } catch {}
    }, 300);
    return () => clearTimeout(t);
  }, [relSearch]);

  const submitCreateFamily = async () => {
    if (!window.confirm('سيتم إنشاء أسرة جديدة لهذا الشخص. هل تريد المتابعة؟')) return;
    try {
      const payload: any = {
        family_name: cfFamilyName || undefined,
        role: cfRole || undefined,
        end_old_membership: cfEndOld,
      };
      if (cfSpouseMode === 'existing' && cfSpouseId) payload.spouse_person_id = cfSpouseId;
      if (cfSpouseMode === 'new' && cfSpouseNew.first_name.trim()) {
        payload.spouse_new = {
          ...cfSpouseNew,
          date_of_birth: cfSpouseNew.date_of_birth || null,
          phone: cfSpouseNew.phone.trim() || null,
          occupation: cfSpouseNew.occupation.trim() || null,
        };
      }
      const res = await personsAPI.createFamily(Number(params.id), payload);
      toast.success(res.data.message || 'تم إنشاء الأسرة');
      setShowCreateFamily(false);
      router.push(`/families/${res.data.family_id}`);
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في إنشاء الأسرة'));
    }
  };

  const addRelationship = async (relatedId: number) => {
    try {
      await personsAPI.createRelationship(Number(params.id), { related_person_id: relatedId, relationship_type: relType });
      toast.success('تمت إضافة الصلة');
      setShowAddRel(false);
      setRelSearch('');
      setRelResults([]);
      reloadProfile();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في إضافة الصلة'));
    }
  };

  const removeRelationship = async (relId: number) => {
    if (!window.confirm('هل تريد حذف هذه الصلة؟')) return;
    try {
      await personsAPI.deleteRelationship(Number(params.id), relId);
      toast.success('تم حذف الصلة');
      reloadProfile();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  if (loading) return <AuthLayout><div className="text-center py-20">جاري التحميل...</div></AuthLayout>;
  if (!profile) return <AuthLayout><div className="text-center py-20">الشخص غير موجود</div></AuthLayout>;

  const person = profile.person;
  const age = person.date_of_birth
    ? Math.floor((Date.now() - new Date(person.date_of_birth).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
    : '-';

  return (
    <AuthLayout>
      <button onClick={() => router.back()} className="flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
        <HiArrowRight size={20} /> رجوع
      </button>

      <div className="card mb-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">
              {person.first_name} {person.middle_name || ''} {person.last_name || ''}
            </h1>
            <p className="text-gray-500 mt-1">{translate(person.gender, genders)} - عمر {age} سنة</p>
          </div>
          <span className={person.is_active ? 'badge-green' : 'badge-red'}>
            {person.is_active ? 'نشط' : 'غير نشط'}
          </span>
          <div className="flex gap-2">
            <Link href={`/persons/${params.id}/tree`} className="btn-secondary flex items-center gap-1 text-sm">
              شجرة العائلة
            </Link>
            {isEditor && (
              <button onClick={() => { setCfRole(person.gender === 'male' ? 'husband' : 'wife'); setCfSpouseNew({ ...cfSpouseNew, gender: person.gender === 'male' ? 'female' : 'male' }); setShowCreateFamily(true); }} className="btn-primary flex items-center gap-1 text-sm">
                إنشاء أسرة جديدة
              </button>
            )}
            {isEditor && (
              <button onClick={openEdit} className="btn-secondary flex items-center gap-1 text-sm">
                <HiPencil size={16} /> تعديل
              </button>
            )}
            {isModerator && (
              <button onClick={deletePerson} className="btn-danger flex items-center gap-1 text-sm">
                <HiTrash size={16} /> حذف
              </button>
            )}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card">
          <h2 className="text-lg font-bold mb-4">البيانات الشخصية</h2>
          <div className="space-y-3">
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">الاسم</span><span>{person.first_name} {person.middle_name || ''} {person.last_name || ''}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">تاريخ الميلاد</span><span>{person.date_of_birth || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">العمر</span><span>{age}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">المرحلة الدراسية</span><span>{translate(person.education_stage, educationStages)}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">السنة بالظبط</span><span>{translate(person.school_year, schoolYears)}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">أب الاعتراف</span><span>{person.confession_father || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">كنيسة أب الاعتراف</span><span>{person.confession_church || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">الحالة الاجتماعية</span><span>{translate(person.marital_status, maritalStatuses)}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">حالة الخدمة</span><span>{translate(person.care_situation, careSituations)}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">المهنة</span><span>{person.occupation || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">الهاتف</span><span>{person.phone || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">تليفون تاني</span><span>{person.second_phone || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">واتساب</span><span>{person.whatsapp || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">البريد الإلكتروني</span><span>{person.email || '-'}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">الإجازة الأسبوعية</span><span>{translate(person.weekly_leave, weeklyLeaves)}</span></div>
            <div className="flex justify-between border-b pb-2"><span className="text-gray-500">الخدمة الكنسية</span>
              <span>[{person.is_deacon ? 'شماس' : ''}{person.is_servant ? (person.is_deacon ? '، ' : '') + 'خادم' : ''}{person.in_prep_center ? (person.is_deacon || person.is_servant ? '، ' : '') + 'مركز إعداد الخدام' : ''}{(person.is_deacon || person.is_servant || person.in_prep_center) ? '' : '—'}]</span>
            </div>
          </div>
        </div>

        <div className="space-y-6">
          <div className="card">
            <h2 className="text-lg font-bold mb-4">العائلة</h2>
            {profile.current_family ? (
              <div className="mb-3">
                <p className="text-sm text-gray-500 mb-1">الأسرة الحالية</p>
                <Link href={`/families/${profile.current_family.family_id}`} className="text-church-primary hover:underline font-medium">
                  {profile.current_family.family_name || profile.family_memberships.find((fm: any) => fm.is_current && fm.family_id === profile.current_family.family_id)?.family_name || `أسرة #${profile.current_family.family_id}`}
                </Link>
              </div>
            ) : (
              <p className="text-gray-500 mb-3">لا توجد أسرة حالية</p>
            )}
            {profile.family_memberships.length > 0 ? (
              <div className="space-y-2">
                {profile.family_memberships.map((fm: any) => (
                  <div key={fm.id} className="flex justify-between items-center bg-gray-50 p-3 rounded-lg">
                    <div>
                      <Link href={`/families/${fm.family_id}`} className="text-church-primary hover:underline text-sm">
                        {fm.family_name || `أسرة #${fm.family_id}`}
                      </Link>
                      <span className="text-xs text-gray-500 mr-2">{translate(fm.relationship_type, relationshipTypes)}{fm.is_head ? ' - رئيس الأسرة' : ''}</span>
                      {!fm.is_current && <span className="text-xs text-gray-400 mr-1">({fm.start_date || ''} ← {fm.end_date || ''})</span>}
                    </div>
                    <span className={fm.is_current ? 'badge-green' : 'badge-yellow'}>{fm.is_current ? 'حالية' : 'سابقة'}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500">لا توجد عائلة مرتبطة</p>
            )}
          </div>

          <div className="card">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold">صلات القرابة</h2>
              {isEditor && (
                <button onClick={() => setShowAddRel(true)} className="btn-secondary text-sm">إضافة صلة</button>
              )}
            </div>
            {profile.relationships && profile.relationships.length > 0 ? (
              <div className="space-y-2">
                {profile.relationships.map((r: any) => (
                  <div key={`${r.direction}-${r.id}`} className="flex justify-between items-center bg-gray-50 p-3 rounded-lg">
                    <div>
                      <span className="badge-blue ml-2">{translate(r.label, kinshipTypes)}</span>
                      <Link href={`/persons/${r.other_person_id}`} className="text-church-primary hover:underline text-sm">
                        {r.other_person_name}
                      </Link>
                    </div>
                    {r.direction === 'outgoing' && (
                      <button onClick={() => removeRelationship(r.id)} className="text-red-500 hover:text-red-700 text-xs">حذف</button>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500">لا توجد صلات مسجلة</p>
            )}
          </div>

          <div className="card">
            <h2 className="text-lg font-bold mb-4">الخدمات</h2>
            {profile.service_assignments.length > 0 ? (
              <div className="space-y-2 mb-4">
                {profile.service_assignments.map((sa: any) => (
                  <div key={sa.id} className="flex justify-between bg-gray-50 p-3 rounded-lg">
                    <span>{services.find((s: any) => s.id === sa.service_id)?.name || `خدمة #${sa.service_id}`}</span>
                    <span className={sa.status === 'active' ? 'badge-green' : 'badge-yellow'}>{sa.status === 'active' ? 'نشطة' : sa.status}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500 mb-4">لم يتم تعيين لأي خدمة</p>
            )}
            {isEditor && (
              <div className="border-t pt-3">
                <label className="label">تغيير الخدمة التابع لها</label>
                <div className="flex gap-2">
                  <select
                    className="input-field"
                    value={newServiceId}
                    onChange={e => setNewServiceId(e.target.value)}
                  >
                    <option value="">— إزالة من الخدمة —</option>
                    {services.map((s: any) => (
                      <option key={s.id} value={String(s.id)}>{s.name}</option>
                    ))}
                  </select>
                  <button onClick={changeService} className="btn-primary whitespace-nowrap" disabled={changingService}>
                    {changingService ? 'جاري...' : 'حفظ'}
                  </button>
                </div>
              </div>
            )}
          </div>

          <div className="card">
            <h2 className="text-lg font-bold mb-4">الاعترافات</h2>
            {profile.confessions.length > 0 ? (
              <div className="space-y-2">
                {profile.confessions.map((c: any) => (
                  <div key={c.id} className="flex justify-between bg-gray-50 p-3 rounded-lg">
                    <span>{new Date(c.confession_date).toLocaleDateString('ar-EG')}</span>
                    <span className={c.status === 'completed' ? 'badge-green' : c.status === 'upcoming' ? 'badge-blue' : 'badge-yellow'}>{translate(c.status, confessionStatuses)}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500">لا توجد اعترافات مسجلة</p>
            )}
          </div>

          <div className="card">
            <h2 className="text-lg font-bold mb-4">الافتقادات</h2>
            {profile.visits.length > 0 ? (
              <div className="space-y-2">
                {profile.visits.map((v: any) => (
                  <div key={v.id} className="flex justify-between bg-gray-50 p-3 rounded-lg">
                    <span>{new Date(v.visit_date).toLocaleDateString('ar-EG')}</span>
                    <span>{translate(v.visit_type, visitTypes)} - {translate(v.status, visitStatuses)}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500">لا توجد افتقادات</p>
            )}
          </div>
        </div>
      </div>
    {showEdit && (
        <div className="modal-overlay" onClick={() => setShowEdit(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">تعديل بيانات الشخص</h3>
            <div className="space-y-3">
              <div className="grid grid-cols-3 gap-3">
                <div><label className="label">الاسم الأول *</label><input className="input-field" value={editData.first_name} onChange={e => setEditData({...editData, first_name: e.target.value})} /></div>
                <div><label className="label">الاسم الأوسط</label><input className="input-field" value={editData.middle_name} onChange={e => setEditData({...editData, middle_name: e.target.value})} /></div>
                <div><label className="label">الاسم الأخير</label><input className="input-field" value={editData.last_name} onChange={e => setEditData({...editData, last_name: e.target.value})} /></div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">النوع</label>
                  <select className="input-field" value={editData.gender} onChange={e => setEditData({...editData, gender: e.target.value})}>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                  </select>
                </div>
                <div>
                  <label className="label">الحالة الاجتماعية</label>
                  <select className="input-field" value={editData.marital_status} onChange={e => setEditData({...editData, marital_status: e.target.value})}>
                    <option value="">—</option>
                    <option value="single">أعزب</option>
                    <option value="married">متزوج</option>
                    <option value="widowed">أرمل</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">المرحلة الدراسية (الاجتماع)</label>
                  <select className="input-field" value={editData.education_stage} onChange={e => setEditData({...editData, education_stage: e.target.value})}>
                    <option value="">—</option>
                    {Object.entries(educationStages).map(([k, v]) => (
                      <option key={k} value={k}>{v}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="label">السنة بالظبط (سنة كام)</label>
                  <select className="input-field" value={editData.school_year} onChange={e => setEditData({...editData, school_year: e.target.value})}>
                    <option value="">—</option>
                    {Object.entries(schoolYears).map(([k, v]) => (
                      <option key={k} value={k}>{v}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="label">أب الاعتراف</label>
                <input className="input-field" list="confession-fathers" value={editData.confession_father} onChange={e => setEditData({...editData, confession_father: e.target.value})} placeholder="ابونا سيرافيم / ابونا جوزيف / اكتب أي حد تاني" />
                <datalist id="confession-fathers">
                  <option value="ابونا سيرافيم" />
                  <option value="ابونا جوزيف" />
                </datalist>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">كنيسة أب الاعتراف</label>
                  <input className="input-field" value={editData.confession_church} onChange={e => setEditData({...editData, confession_church: e.target.value})} placeholder="بتتعدلي لوحدها لو الأب سيرافيم أو جوزيف" />
                </div>
                <div>
                  <label className="label">الإجازة الأسبوعية</label>
                  <select className="input-field" value={editData.weekly_leave} onChange={e => setEditData({...editData, weekly_leave: e.target.value})}>
                    <option value="">— اختر —</option>
                    {Object.entries(weeklyLeaves).map(([k, label]) => (
                      <option key={k} value={k}>{label}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="flex flex-wrap gap-4">
                {editData.gender === 'male' && <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={editData.is_deacon} onChange={e => setEditData({...editData, is_deacon: e.target.checked})} /> شماس</label>}
                <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={editData.is_servant} onChange={e => {
                  const next = { ...editData, is_servant: e.target.checked };
                  if (next.is_servant) next.in_prep_center = false;
                  setEditData(next);
                }} /> خادم</label>
                <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={editData.in_prep_center} onChange={e => {
                  const next = { ...editData, in_prep_center: e.target.checked };
                  if (next.in_prep_center) next.is_servant = false;
                  setEditData(next);
                }} /> مركز إعداد الخدام</label>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div><label className="label">الهاتف</label><input className="input-field" value={editData.phone} onChange={e => setEditData({...editData, phone: e.target.value})} /></div>
                <div><label className="label">تليفون تاني</label><input className="input-field" value={editData.second_phone} onChange={e => setEditData({...editData, second_phone: e.target.value})} /></div>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div><label className="label">واتساب</label><input className="input-field" value={editData.whatsapp} onChange={e => setEditData({...editData, whatsapp: e.target.value})} /></div>
                <div><label className="label">المهنة</label><input className="input-field" value={editData.occupation} onChange={e => setEditData({...editData, occupation: e.target.value})} /></div>
                <div></div>
              </div>
              <div>
                <label className="label">حالة الخدمة</label>
                <select className="input-field" value={editData.care_situation} onChange={e => setEditData({...editData, care_situation: e.target.value})}>
                  {Object.entries(careSituations).map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
              </div>
              {editData.care_situation === 'deceased' ? (
                <div>
                  <label className="label">تاريخ الوفاة (اختياري)</label>
                  <input type="date" className="input-field" value={editData.date_of_death} onChange={e => setEditData({...editData, date_of_death: e.target.value})} />
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-3">
                  <div><label className="label">تاريخ الميلاد</label><input type="date" className="input-field" value={editData.date_of_birth} onChange={e => setEditData({...editData, date_of_birth: e.target.value})} /></div>
                  <div><label className="label">الهاتف</label><input className="input-field" value={editData.phone} onChange={e => setEditData({...editData, phone: e.target.value})} /></div>
                </div>
              )}
              <div>
                <label className="label">الخدمة التابع لها</label>
                <select className="input-field" value={newServiceId} onChange={e => setNewServiceId(e.target.value)}>
                  <option value="">— بدون خدمة —</option>
                  {services.map((s: any) => (
                    <option key={s.id} value={String(s.id)}>{s.name}</option>
                  ))}
                </select>
              </div>
              <div className="flex gap-3 mt-4">
                <button onClick={saveEdit} className="btn-primary">حفظ</button>
                <button onClick={() => setShowEdit(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}
      {showCreateFamily && (
        <div className="modal-overlay" onClick={() => setShowCreateFamily(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-1">إنشاء أسرة جديدة من {person.first_name}</h3>
            <p className="text-sm text-gray-500 mb-4">سيُحتفظ بكل بيانات الشخص وسجله — مجرد انتقال لأسرة جديدة</p>
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">الدور في الأسرة الجديدة</label>
                  <select className="input-field" value={cfRole} onChange={e => setCfRole(e.target.value)}>
                    <option value="husband">زوج</option>
                    <option value="wife">زوجة</option>
                    <option value="father">أب</option>
                    <option value="mother">أم</option>
                    <option value="son">ابن</option>
                    <option value="daughter">ابنة</option>
                  </select>
                </div>
                <div>
                  <label className="label">اسم الأسرة الجديدة (اختياري)</label>
                  <input className="input-field" value={cfFamilyName} onChange={e => setCfFamilyName(e.target.value)} placeholder={`افتراضي: ${person.first_name} ${person.middle_name || ''}`.trim()} />
                </div>
              </div>
              <div>
                <label className="label">الزوجة / الزوج</label>
                <select className="input-field" value={cfSpouseMode} onChange={e => { setCfSpouseMode(e.target.value as any); setCfSpouseId(null); }}>
                  <option value="none">بدون (لاحقاً)</option>
                  <option value="existing">اختيار شخص موجود</option>
                  <option value="new">إضافة زوجة جديدة</option>
                </select>
              </div>
              {cfSpouseMode === 'existing' && (
                <div>
                  <label className="label">بحث بالاسم</label>
                  <input className="input-field" placeholder="اكتب للبحث..." value={cfSpouseSearch} onChange={e => setCfSpouseSearch(e.target.value)} />
                  <div className="space-y-2 max-h-40 overflow-y-auto mt-2">
                    {cfSpouseSearch.trim() && cfSpouseResults.length === 0 && (
                      <div className="text-sm text-gray-500 text-center py-2">لا توجد نتائج</div>
                    )}
                    {cfSpouseResults.map((p: any) => (
                      <div key={p.id} className={`flex items-center justify-between p-2 rounded-lg cursor-pointer ${cfSpouseId === p.id ? 'bg-church-light' : 'bg-gray-50'}`} onClick={() => setCfSpouseId(p.id)}>
                        <span className="text-sm">{p.first_name} {p.middle_name || ''} {p.last_name || ''}</span>
                        {cfSpouseId === p.id && <span className="badge-green">مختار</span>}
                      </div>
                    ))}
                  </div>
                </div>
              )}
              {cfSpouseMode === 'new' && (
                <div className="space-y-3 bg-gray-50 p-3 rounded-lg">
                  <p className="text-sm font-bold">بيانات الزوجة الجديدة (كاملة كأي فرد)</p>
                  <div className="grid grid-cols-3 gap-3">
                    <div><label className="label">الاسم الأول *</label><input className="input-field" value={cfSpouseNew.first_name} onChange={e => setCfSpouseNew({ ...cfSpouseNew, first_name: e.target.value })} /></div>
                    <div><label className="label">الأوسط</label><input className="input-field" value={cfSpouseNew.middle_name} onChange={e => setCfSpouseNew({ ...cfSpouseNew, middle_name: e.target.value })} /></div>
                    <div><label className="label">الأخير</label><input className="input-field" value={cfSpouseNew.last_name} onChange={e => setCfSpouseNew({ ...cfSpouseNew, last_name: e.target.value })} /></div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="label">النوع</label>
                      <select className="input-field" value={cfSpouseNew.gender} onChange={e => setCfSpouseNew({ ...cfSpouseNew, gender: e.target.value })}>
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                      </select>
                    </div>
                    <div>
                      <label className="label">تاريخ الميلاد</label>
                      <input type="date" className="input-field" value={cfSpouseNew.date_of_birth} onChange={e => setCfSpouseNew({ ...cfSpouseNew, date_of_birth: e.target.value })} />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="label">الهاتف</label>
                      <input className="input-field" value={cfSpouseNew.phone} onChange={e => setCfSpouseNew({ ...cfSpouseNew, phone: e.target.value })} />
                    </div>
                    <div>
                      <label className="label">حالة الخدمة</label>
                      <select className="input-field" value={cfSpouseNew.care_situation} onChange={e => setCfSpouseNew({ ...cfSpouseNew, care_situation: e.target.value })}>
                        {Object.entries(careSituations).map(([k, v]) => (
                          <option key={k} value={k}>{v}</option>
                        ))}
                      </select>
                    </div>
                  </div>
                  <OccupationInput value={cfSpouseNew.occupation} onChange={v => setCfSpouseNew({ ...cfSpouseNew, occupation: v })} />
                </div>
              )}
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" checked={cfEndOld} onChange={e => setCfEndOld(e.target.checked)} />
                إنهاء العضوية في الأسرة الحالية (تصبح سابقة)
              </label>
              <div className="flex gap-3 mt-4">
                <button onClick={submitCreateFamily} className="btn-primary">إنشاء الأسرة</button>
                <button onClick={() => setShowCreateFamily(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}
      {showAddRel && (
        <div className="modal-overlay" onClick={() => setShowAddRel(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">إضافة صلة قرابة</h3>
            <div className="mb-3">
              <label className="label">نوع الصلة (من وجهة نظر {person.first_name})</label>
              <select className="input-field" value={relType} onChange={e => setRelType(e.target.value)}>
                {Object.entries(kinshipTypes).map(([k, v]) => (
                  <option key={k} value={k}>{v}</option>
                ))}
              </select>
            </div>
            <label className="label">بحث عن الشخص</label>
            <input className="input-field" placeholder="اكتب للبحث..." value={relSearch} onChange={e => setRelSearch(e.target.value)} />
            <div className="space-y-2 max-h-60 overflow-y-auto mt-2">
              {relSearch.trim() && relResults.length === 0 && (
                <div className="text-sm text-gray-500 text-center py-2">لا توجد نتائج</div>
              )}
              {relResults.map((p: any) => (
                <div key={p.id} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                  <span className="text-sm">{p.first_name} {p.middle_name || ''} {p.last_name || ''} - {p.phone || 'بدون هاتف'}</span>
                  <button onClick={() => addRelationship(p.id)} className="btn-success text-sm">إضافة</button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </AuthLayout>
  );
}
