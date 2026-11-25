'use client';
import AuthLayout from '@/components/AuthLayout';
import OccupationInput from '@/components/OccupationInput';
import { familiesAPI, personsAPI, servicesAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import { HiArrowRight, HiCheck } from 'react-icons/hi';
import { schoolYears, educationStages, translate, suggestedAreas, weeklyLeaves, confessionChurches } from '@/lib/translations';

interface ParentForm {
  first_name: string;
  middle_name: string;
  last_name: string;
  gender: string;
  date_of_birth: string;
  is_deceased: boolean;
  date_of_death: string;
  phone: string;
  second_phone: string;
  weekly_leave: string;
  confession_church: string;
  is_deacon: boolean;
  is_servant: boolean;
  in_prep_center: boolean;
  care_situation: string;
  occupation: string;
  confession_father: string;
  school_year: string;
}

interface ChildForm {
  first_name: string;
  gender: string;
  date_of_birth: string;
  phone: string;
  second_phone: string;
  weekly_leave: string;
  confession_church: string;
  is_deacon: boolean;
  is_servant: boolean;
  in_prep_center: boolean;
  confession_father: string;
  school_year: string;
}

const EMPTY_PERSON: ParentForm = {
  first_name: '', middle_name: '', last_name: '', gender: 'male', date_of_birth: '',
  is_deceased: false, date_of_death: '', phone: '', second_phone: '', weekly_leave: '',
  confession_church: '', is_deacon: false, is_servant: false, in_prep_center: false,
  care_situation: 'normal', occupation: '', confession_father: '', school_year: '',
};

const EMPTY_CHILD: ChildForm = {
  first_name: '', gender: 'male', date_of_birth: '', phone: '', second_phone: '',
  weekly_leave: '', confession_church: '', is_deacon: false, is_servant: false,
  in_prep_center: false, confession_father: '', school_year: '',
};

const HOLY_FAMILY_FATHERS = ['ابونا سيرافيم', 'ابونا جوزيف'];

export default function NewFamilyPage() {
  const router = useRouter();
  const [step, setStep] = useState(1);
  const [services, setServices] = useState<any[]>([]);
  const [familyData, setFamilyData] = useState({ family_name: '', phone: '', address: '', street: '', branch_from: '', area: '', landmark: '', building_number: '', floor: '', apartment_number: '', gps_location: '', special_case: '', notes: '' });
  const [fatherData, setFatherData] = useState<ParentForm>({ ...EMPTY_PERSON });
  const [motherData, setMotherData] = useState<ParentForm>({ ...EMPTY_PERSON, gender: 'female' });
  const [grandfatherData, setGrandfatherData] = useState<ParentForm>({ ...EMPTY_PERSON });
  const [grandmotherData, setGrandmotherData] = useState<ParentForm>({ ...EMPTY_PERSON, gender: 'female' });
  const [children, setChildren] = useState<ChildForm[]>([]);
  const [suggestions, setSuggestions] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [childForm, setChildForm] = useState<ChildForm>({ ...EMPTY_CHILD });
  const [dupWarnings, setDupWarnings] = useState<any[]>([]);
  const [checkingDups, setCheckingDups] = useState(false);
  const [liveHints, setLiveHints] = useState<Record<string, string>>({});

  const CARE_LABELS: Record<string, string> = {
    normal: 'عادي',
    deceased: 'متوفي/متوفاة',
    widowed: 'أرمل/أرملة',
    abroad: 'مسافر بالخارج',
    special_needs: 'ذو احتياجات خاصة',
  };

  const SPECIAL_CASE_LABELS: Record<string, string> = {
    '': 'بدون حالة خاصة',
    financial_need: 'تحتاج دعم مادي',
    medical_need: 'تحتاج رعاية صحية',
    financial_medical: 'دعم مادي + رعاية صحية',
    prisoner: 'أحد الأفراد مسجون',
    orphaned: 'أسرة بها أيتام',
  };

  const careLabel = (v: string) => CARE_LABELS[v] || v || 'عادي';
  const caseLabel = (v: string) => SPECIAL_CASE_LABELS[v] || v || 'بدون حالة خاصة';

  const resolveOccupation = (p: ParentForm) => p.occupation.trim() || null;

  const [fatherSugg, setFatherSugg] = useState({ suggested_id: '', reason: '', school_year: '' });
  const [motherSugg, setMotherSugg] = useState({ suggested_id: '', reason: '', school_year: '' });
  const [grandfatherSugg, setGrandfatherSugg] = useState({ suggested_id: '', reason: '', school_year: '' });
  const [grandmotherSugg, setGrandmotherSugg] = useState({ suggested_id: '', reason: '', school_year: '' });

  useEffect(() => {
    servicesAPI.list().then(res => setServices(res.data)).catch(() => {});
  }, []);

  useEffect(() => {
    if (step === 4) computeSuggestions();
  }, [step, services]);

  useEffect(() => {
    const timer = setTimeout(async () => {
      const hints: Record<string, string> = {};
      const digitsOnly = (v: string) => (v || '').replace(/\D/g, '');
      const famPhoneDigits = digitsOnly(familyData.phone);
      const fatherPhoneDigits = digitsOnly(fatherData.phone);
      const motherPhoneDigits = digitsOnly(motherData.phone);

      if (familyData.family_name.trim().length >= 2) {
        try {
          const res = await familiesAPI.checkDuplicate({ family_name: familyData.family_name });
          if (res.data?.length) {
            hints.family_name = `الأسرة "${res.data[0].family_name}" موجودة بالفعل (كود ${res.data[0].family_code})`;
          }
        } catch {}
      }
      const phoneChecks: [string, string][] = [];
      if (famPhoneDigits) phoneChecks.push(['family_phone', famPhoneDigits]);
      if (fatherPhoneDigits) phoneChecks.push(['father_phone', fatherPhoneDigits]);
      if (motherPhoneDigits) phoneChecks.push(['mother_phone', motherPhoneDigits]);
      for (const child of children) {
        const d = digitsOnly(child.phone);
        if (d) phoneChecks.push([`child_${child.first_name}`, d]);
      }
      for (const [key, digits] of phoneChecks) {
        try {
          const res = await personsAPI.checkPhone({ phone: digits });
          if (res.data?.exists) {
            hints[key] = `الرقم مسجل بالفعل على: ${res.data.owner_name} (${res.data.owner_type === 'person' ? 'شخص' : 'أسرة'})`;
          }
        } catch {}
      }
      const fullFather = [fatherData.first_name, fatherData.middle_name, fatherData.last_name].filter(Boolean).join(' ');
      if (fatherData.first_name && fatherData.last_name && fullFather.length >= 3) {
        try {
          const res = await personsAPI.checkDuplicate({ first_name: fatherData.first_name, last_name: fatherData.last_name, gender: 'male', date_of_birth: null });
          if (res.data?.is_duplicate && (res.data.potential_matches || []).length > 0) {
            hints.father_name = `اسم قد يكون مكرراً: ${res.data.potential_matches[0].full_name_display || ''}`;
          }
        } catch {}
      }
      const fullMother = [motherData.first_name, motherData.middle_name, motherData.last_name].filter(Boolean).join(' ');
      if (motherData.first_name && motherData.last_name && fullMother.length >= 3) {
        try {
          const res = await personsAPI.checkDuplicate({ first_name: motherData.first_name, last_name: motherData.last_name, gender: 'female', date_of_birth: null });
          if (res.data?.is_duplicate && (res.data.potential_matches || []).length > 0) {
            hints.mother_name = `اسم قد يكون مكرراً: ${res.data.potential_matches[0].full_name_display || ''}`;
          }
        } catch {}
      }
      setLiveHints(hints);
    }, 500);
    return () => clearTimeout(timer);
  }, [familyData, fatherData, motherData, children]);

  const parentMiddle = (p: ParentForm) => `${p.first_name} ${p.middle_name || ''}`.trim();

  const childSurname = (fatherData.first_name ? parentMiddle(fatherData)
    : motherData.first_name ? parentMiddle(motherData)
    : '');

  const autoFamilyName = (fatherData.first_name ? parentMiddle(fatherData)
    : motherData.first_name ? parentMiddle(motherData)
    : '');

  const addChild = () => {
    if (!childForm.first_name.trim()) { toast.error('أدخل الاسم الأول للطفل'); return; }
    setChildren([...children, childForm]);
    setChildForm({ ...EMPTY_CHILD });
  };

  const removeChild = (index: number) => {
    setChildren(children.filter((_, i) => i !== index));
  };

  const calcAge = (dob: string) => {
    if (!dob) return null;
    const d = new Date(dob);
    return Math.floor((Date.now() - d.getTime()) / (365.25 * 24 * 60 * 60 * 1000));
  };

  const setConfessionFather = (p: ParentForm, setP: (v: ParentForm) => void, v: string) => {
    const next = { ...p, confession_father: v };
    if (HOLY_FAMILY_FATHERS.includes(v.trim())) next.confession_church = 'كنيسة العائلة المقدسة';
    setP(next);
  };

  const setChildConfessionFather = (v: string) => {
    const next = { ...childForm, confession_father: v };
    if (HOLY_FAMILY_FATHERS.includes(v.trim())) next.confession_church = 'كنيسة العائلة المقدسة';
    setChildForm(next);
  };

  const togglePersonFlag = (p: ParentForm, setP: (v: ParentForm) => void, key: 'is_deacon' | 'is_servant' | 'in_prep_center') => {
    const next = { ...p, [key]: !p[key] };
    if (key === 'is_servant' && next.is_servant) next.in_prep_center = false;
    if (key === 'in_prep_center' && next.in_prep_center) next.is_servant = false;
    setP(next);
  };

  const toggleChildFlag = (key: 'is_deacon' | 'is_servant' | 'in_prep_center') => {
    const next = { ...childForm, [key]: !childForm[key] };
    if (key === 'is_servant' && next.is_servant) next.in_prep_center = false;
    if (key === 'in_prep_center' && next.in_prep_center) next.is_servant = false;
    setChildForm(next);
  };

  const getGpsLocation = () => {
    if (!('geolocation' in navigator)) { toast.error('متصفحك لا يدعم تحديد الموقع — اكتب الإحداثيات يدوياً'); return; }
    toast.loading('جاري تحديد الموقع...');
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        toast.dismiss();
        const coords = `${pos.coords.latitude.toFixed(6)},${pos.coords.longitude.toFixed(6)}`;
        setFamilyData((prev) => ({ ...prev, gps_location: coords }));
        toast.success('تم تحديد الموقع');
      },
      (err) => {
        toast.dismiss();
        if (err.code === err.PERMISSION_DENIED) toast.error('تم رفض إذن الموقع — افتح الخريطة وانسخ الإحداثيات يدوياً');
        else if (err.code === err.TIMEOUT) toast.error('انتهت مهلة التحديد — جرب مرة أخرى أو اكتب الإحداثيات يدوياً');
        else toast.error('تعذر تحديد الموقع — اكتب الإحداثيات يدوياً (مثال: 30.123456,31.123456)');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  };

  const openMapAt = (v: string) => {
    window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(v)}`, '_blank');
  };

  const handleCareChange = (who: 'father' | 'mother', v: string) => {
    if (who === 'father') {
      setFatherData({ ...fatherData, care_situation: v, is_deceased: v === 'deceased' });
      if (v === 'deceased' && motherData.care_situation === 'normal') {
        setMotherData({ ...motherData, care_situation: 'widowed' });
        toast('تم تحويل حالة الأم إلى أرملة تلقائياً');
      } else if (v !== 'deceased' && motherData.care_situation === 'widowed') {
        setMotherData({ ...motherData, care_situation: 'normal' });
      }
    } else {
      setMotherData({ ...motherData, care_situation: v, is_deceased: v === 'deceased' });
      if (v === 'deceased' && fatherData.care_situation === 'normal') {
        setFatherData({ ...fatherData, care_situation: 'widowed' });
        toast('تم تحويل حالة الأب إلى أرمل تلقائياً');
      } else if (v !== 'deceased' && fatherData.care_situation === 'widowed') {
        setFatherData({ ...fatherData, care_situation: 'normal' });
      }
    }
  };

  const yearByAge = (age: number | null): string => {
    if (age === null) return '';
    if (age < 3) return 'nursery';
    if (age < 5) return 'angels';
    if (age <= 6) return 'grade_1';
    if (age <= 7) return 'grade_2';
    if (age <= 8) return 'grade_3';
    if (age <= 9) return 'grade_4';
    if (age <= 10) return 'grade_5';
    if (age <= 11) return 'grade_6';
    if (age <= 12) return 'prep_1';
    if (age <= 13) return 'prep_2';
    if (age <= 14) return 'prep_3';
    if (age <= 15) return 'sec_1';
    if (age <= 16) return 'sec_2';
    if (age <= 17) return 'sec_3';
    if (age <= 18) return 'uni_1';
    if (age <= 19) return 'uni_2';
    if (age <= 20) return 'uni_3';
    if (age <= 21) return 'uni_4';
    if (age <= 22) return 'uni_5';
    if (age <= 25) return 'graduate';
    return 'graduate';
  };

  const suggestForAdult = (dob: string, gender: string) => {
    const age = calcAge(dob);
    if (age === null) return { suggested_id: '', reason: 'أدخل تاريخ الميلاد أولاً', school_year: '' };
    const candidates = services.filter((s: any) =>
      s.min_age !== null && s.max_age !== null && age >= s.min_age && age < s.max_age &&
      (!s.gender_restriction || s.gender_restriction === gender)
    );
    if (candidates.length === 0) return { suggested_id: '', reason: 'لا توجد خدمة مناسبة تلقائياً، اختر يدوياً', school_year: yearByAge(age) };
    let best = candidates[0];
    if (gender === 'male') {
      const mens = candidates.find((s: any) => s.name.includes('الرجال') || s.name.includes('موسى'));
      if (mens) best = mens;
    } else {
      const womens = candidates.find((s: any) => s.gender_restriction === 'female' || s.name.includes('الأمهات'));
      if (womens) best = womens;
    }
    return { suggested_id: String(best.id), reason: `العمر ${age} سنة يناسب: ${best.name}`, school_year: yearByAge(age) };
  };

  const computeSuggestions = () => {
    if (fatherData.first_name && fatherData.care_situation !== 'deceased') {
      const sg = suggestForAdult(fatherData.date_of_birth, 'male');
      setFatherSugg(sg);
      setFatherData({ ...fatherData, school_year: fatherData.school_year || sg.school_year });
    } else {
      setFatherSugg({ suggested_id: '', reason: '', school_year: '' });
    }
    if (motherData.first_name && motherData.care_situation !== 'deceased') {
      const sg = suggestForAdult(motherData.date_of_birth, 'female');
      setMotherSugg(sg);
      setMotherData({ ...motherData, school_year: motherData.school_year || sg.school_year });
    } else {
      setMotherSugg({ suggested_id: '', reason: '', school_year: '' });
    }
    const ageCalcs = children.map(child => {
      const age = calcAge(child.date_of_birth);
      let suggested: any = null;
      if (age !== null) {
        suggested = services.find((s: any) => s.min_age !== null && s.max_age !== null && age >= s.min_age && age < s.max_age) || null;
      }
      return {
        child_name: `${child.first_name} ${childSurname}`.trim(),
        age,
        suggested_id: suggested ? String(suggested.id) : '',
        reason: suggested ? `العمر ${age} سنة يناسب: ${suggested.name}` : (age === null ? 'أدخل تاريخ الميلاد أولاً' : 'لا توجد خدمة مناسبة تلقائياً، اختر يدوياً'),
        school_year: child.school_year || yearByAge(age),
      };
    });
    setSuggestions(ageCalcs);
    setChildren(children.map((child, i) => ({ ...child, school_year: child.school_year || ageCalcs[i].school_year })));
    if (grandfatherData.first_name && grandfatherData.care_situation !== 'deceased') {
      const sg = suggestForAdult(grandfatherData.date_of_birth, 'male');
      setGrandfatherSugg(sg);
      setGrandfatherData({ ...grandfatherData, school_year: grandfatherData.school_year || sg.school_year });
    } else {
      setGrandfatherSugg({ suggested_id: '', reason: '', school_year: '' });
    }
    if (grandmotherData.first_name && grandmotherData.care_situation !== 'deceased') {
      const sg = suggestForAdult(grandmotherData.date_of_birth, 'female');
      setGrandmotherSugg(sg);
      setGrandmotherData({ ...grandmotherData, school_year: grandmotherData.school_year || sg.school_year });
    } else {
      setGrandmotherSugg({ suggested_id: '', reason: '', school_year: '' });
    }
  };

  const YEAR_LABELS: Array<[string, string]> = Object.entries(schoolYears);
  const STAGE_LABELS: Array<[string, string]> = Object.entries(educationStages);

  const personPayload = (p: ParentForm) => ({
    first_name: p.first_name,
    middle_name: p.middle_name || null,
    last_name: p.last_name || null,
    gender: p.gender,
    date_of_birth: p.is_deceased ? null : (p.date_of_birth || null),
    is_deceased: p.is_deceased,
    date_of_death: (p.is_deceased && p.date_of_death) ? p.date_of_death : null,
    phone: p.is_deceased ? null : (p.phone || null),
    second_phone: p.second_phone || null,
    weekly_leave: p.weekly_leave || null,
    confession_father: p.confession_father || null,
    confession_church: p.confession_church || null,
    school_year: p.school_year || null,
    care_situation: p.care_situation,
    is_deacon: p.is_deacon,
    is_servant: p.is_servant,
    in_prep_center: p.in_prep_center,
  });

  const childPayload = (c: ChildForm) => ({
    date_of_birth: c.date_of_birth || null,
    phone: c.phone || null,
    second_phone: c.second_phone || null,
    weekly_leave: c.weekly_leave || null,
    confession_father: c.confession_father || null,
    confession_church: c.confession_church || null,
    school_year: c.school_year || null,
    is_deacon: c.is_deacon,
    is_servant: c.is_servant,
    in_prep_center: c.in_prep_center,
  });

  const buildPayload = () => {
      const familyName = familyData.family_name.trim() || autoFamilyName || 'أسرة بدون اسم';
      return {
        family: { ...familyData, family_name: familyName },
        father: fatherData.first_name ? { ...personPayload(fatherData), marital_status: 'married', occupation: resolveOccupation(fatherData), suggested_service_id: fatherSugg.suggested_id ? Number(fatherSugg.suggested_id) : null } : null,
        mother: motherData.first_name ? { ...personPayload(motherData), marital_status: 'married', occupation: resolveOccupation(motherData), suggested_service_id: motherSugg.suggested_id ? Number(motherSugg.suggested_id) : null } : null,
        grandfather: grandfatherData.first_name ? { ...personPayload(grandfatherData), marital_status: 'widowed', occupation: resolveOccupation(grandfatherData), suggested_service_id: grandfatherSugg.suggested_id ? Number(grandfatherSugg.suggested_id) : null } : null,
        grandmother: grandmotherData.first_name ? { ...personPayload(grandmotherData), marital_status: 'widowed', occupation: resolveOccupation(grandmotherData), suggested_service_id: grandmotherSugg.suggested_id ? Number(grandmotherSugg.suggested_id) : null } : null,
        children: children.map((child, i) => ({
          ...childPayload(child),
          first_name: child.first_name,
          gender: child.gender,
          middle_name: fatherData.first_name || '',
          last_name: fatherData.middle_name || null,
          suggested_service_id: suggestions[i]?.suggested_id || null,
        })),
      };
    };

  const checkDuplicates = async (): Promise<any[]> => {
    const checks: any[] = [];
    if (fatherData.first_name) checks.push({ label: `الأب: ${fatherData.first_name} ${fatherData.last_name || ''}`.trim(), first_name: fatherData.first_name, last_name: fatherData.last_name || '' });
    if (motherData.first_name) checks.push({ label: `الأم: ${motherData.first_name} ${motherData.last_name || ''}`.trim(), first_name: motherData.first_name, last_name: motherData.last_name || '' });
    for (const child of children) {
      checks.push({ label: `الطفل: ${child.first_name} ${childSurname}`.trim(), first_name: child.first_name, last_name: fatherData.middle_name || '' });
    }
    const warnings: any[] = [];
    for (const c of checks) {
      try {
        const res = await personsAPI.checkDuplicate({ first_name: c.first_name, last_name: c.last_name || null, gender: 'male', date_of_birth: null });
        if (res.data.is_duplicate && res.data.potential_matches.length > 0) {
          warnings.push({ label: c.label, matches: res.data.potential_matches });
        }
      } catch {}
    }
    try {
      const famRes = await familiesAPI.checkDuplicate({
        family_name: familyData.family_name,
        father_first_name: fatherData.first_name,
        father_middle_name: fatherData.middle_name,
        father_last_name: fatherData.last_name,
        phone: familyData.phone,
      });
      const matches = famRes.data;
      if (matches && matches.length > 0) {
        warnings.push({ label: `الأسرة: ${familyData.family_name || 'اسم الأسرة'}`, familyMatches: matches });
      }
    } catch {}
    return warnings;
  };

  const saveFamily = async (skipDuplicateCheck = false) => {
    setLoading(true);
    try {
      if (!skipDuplicateCheck) {
        setCheckingDups(true);
        const warnings = await checkDuplicates();
        setCheckingDups(false);
        if (warnings.length > 0) {
          setDupWarnings(warnings);
          setLoading(false);
          return;
        }
      }
      const res = await familiesAPI.createComplete(buildPayload());
      toast.success('تم إنشاء الأسرة بنجاح');
      router.push(`/families/${res.data.family_id}`);
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحفظ، لم يتم حفظ أي بيانات'));
    }
    setLoading(false);
  };

  const steps = ['بيانات الأسرة', 'الأب والأم', 'الأطفال', 'اقتراحات الخدمات', 'المراجعة'];

  const handleCareChangeGP = (p: ParentForm, setP: (v: ParentForm) => void, v: string) => {
    setP({ ...p, care_situation: v, is_deceased: v === 'deceased' });
  };

  const renderGi = (title: string, p: ParentForm, setP: (v: ParentForm) => void) => (
    <div>
      <h3 className="text-lg font-bold mb-3">{title}</h3>
      <div className="grid grid-cols-3 gap-3">
        <div><label className="label">الاسم الأول *</label><input className="input-field" value={p.first_name} onChange={e => setP({...p, first_name: e.target.value})} /></div>
        <div><label className="label">الاسم الأوسط</label><input className="input-field" value={p.middle_name} onChange={e => setP({...p, middle_name: e.target.value})} /></div>
        <div><label className="label">الاسم الأخير</label><input className="input-field" value={p.last_name} onChange={e => setP({...p, last_name: e.target.value})} /></div>
      </div>
      <div className="grid grid-cols-3 gap-3 mt-3">
        <div>
          <label className="label">حالة الخدمة</label>
          <select className="input-field" value={p.care_situation} onChange={e => handleCareChangeGP(p, setP, e.target.value)}>
            {Object.entries(CARE_LABELS).map(([k, label]) => (
              <option key={k} value={k}>{label}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label">تاريخ الميلاد</label>
          <input type="date" className="input-field" value={p.date_of_birth} onChange={e => setP({...p, date_of_birth: e.target.value})} />
        </div>
        <div>
          <label className="label">الهاتف</label>
          <input className="input-field" value={p.phone} onChange={e => setP({...p, phone: e.target.value})} />
        </div>
      </div>
      {p.care_situation === 'deceased' && (
        <div className="mt-3">
          <label className="label">تاريخ الوفاة (اختياري)</label>
          <input type="date" className="input-field" value={p.date_of_death} onChange={e => setP({...p, date_of_death: e.target.value})} />
        </div>
      )}
      <div className="mt-3">
        <label className="label">أب الاعتراف</label>
        <input className="input-field" list="confession-fathers-wizard" value={p.confession_father} onChange={e => setConfessionFather(p, setP, e.target.value)} placeholder="ابونا سيرافيم / ابونا جوزيف / اكتب أي حد تاني" />
      </div>
      <div className="mt-3">
        <label className="label">كنيسة أب الاعتراف</label>
        <input className="input-field" list="confession-churches-wizard" value={p.confession_church} onChange={e => setP({...p, confession_church: e.target.value})} placeholder="تتكتب لوحدها لو أب الاعتراف سيرافيم أو جوزيف" />
      </div>
      <div className="flex flex-wrap gap-4 mt-3">
        {p.gender === 'male' && <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={p.is_deacon} onChange={() => togglePersonFlag(p, setP, 'is_deacon')} /> شماس</label>}
        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={p.is_servant} onChange={() => togglePersonFlag(p, setP, 'is_servant')} /> خادم</label>
        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={p.in_prep_center} onChange={() => togglePersonFlag(p, setP, 'in_prep_center')} /> مركز إعداد الخدام</label>
      </div>
    </div>
  );

  const renderGrandparentRow = (title: string, p: ParentForm, setP: (v: ParentForm) => void, sg: any, setSg: (v: any) => void) => (
    p.first_name ? (
      <div className="flex items-center justify-between gap-4 bg-church-light/50 p-4 rounded-lg">
        <div>
          <p className="font-medium">{title}: {p.first_name} {p.middle_name}</p>
          <p className="text-sm text-gray-500">{sg.reason || '...'}</p>
        </div>
        <div className="flex gap-3">
          <div>
            <label className="label text-xs mb-1">الاجتماع/الخدمة</label>
            <select className="input-field" value={sg.suggested_id} onChange={e => setSg({ ...sg, suggested_id: e.target.value })}>
              <option value="">بدون خدمة</option>
              {services.map((svc: any) => (
                <option key={svc.id} value={String(svc.id)}>{svc.name}</option>
              ))}
            </select>
          </div>
        </div>
      </div>
    ) : null
  );

  const renderGrandparentReview = (title: string, p: ParentForm, sg: any) => p.first_name ? (
    <div className="bg-gray-50 p-4 rounded-lg">
      <p><strong>{title}:</strong> {p.first_name} {p.middle_name} {p.last_name} — {careLabel(p.care_situation)}{p.school_year ? ` — المرحلة: ${translate(p.school_year, schoolYears)}` : ''}{resolveOccupation(p) ? ` — ${resolveOccupation(p)}` : ''}{p.confession_father ? ` — أب اعترافه: ${p.confession_father}` : ''}{p.date_of_death && p.care_situation === 'deceased' ? ` - وفاة ${p.date_of_death}` : ''}{sg.suggested_id ? ` — الخدمة: ${services.find((sv: any) => String(sv.id) === sg.suggested_id)?.name || ''}` : ''}</p>
    </div>
  ) : null;

  const renderParentBlock = (title: string, p: ParentForm, setP: (v: ParentForm) => void, who: 'father' | 'mother') => (
    <div>
      <h3 className="text-lg font-bold mb-3">{title}</h3>
      <div className="grid grid-cols-3 gap-3">
        <div><label className="label">الاسم الأول *</label><input className="input-field" value={p.first_name} onChange={e => setP({...p, first_name: e.target.value})} />
          {(who === 'father' ? liveHints.father_name : liveHints.mother_name) && (
            <p className="text-amber-600 text-sm mt-1"><HiCheck size={14} className="inline ml-1" />{who === 'father' ? liveHints.father_name : liveHints.mother_name}</p>
          )}
        </div>
        <div><label className="label">الاسم الأوسط</label><input className="input-field" value={p.middle_name} onChange={e => setP({...p, middle_name: e.target.value})} /></div>
        <div><label className="label">الاسم الأخير</label><input className="input-field" value={p.last_name} onChange={e => setP({...p, last_name: e.target.value})} /></div>
      </div>
      <div className="grid grid-cols-2 gap-3 mt-3">
        <div>
          <label className="label">حالة الخدمة</label>
          <select
            className="input-field"
            value={p.care_situation}
            onChange={e => handleCareChange(who, e.target.value)}
          >
            {Object.entries(CARE_LABELS).map(([k, label]) => (
              <option key={k} value={k}>{label}</option>
            ))}
          </select>
        </div>
        <OccupationInput value={p.occupation} onChange={v => setP({ ...p, occupation: v })} />
      </div>
      {p.care_situation === 'deceased' ? (
        <div className="grid grid-cols-2 gap-3 mt-3">
          <div><label className="label">تاريخ الوفاة (اختياري - لمعرفة السنوية)</label><input type="date" className="input-field" value={p.date_of_death} onChange={e => setP({...p, date_of_death: e.target.value})} /></div>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 mt-3">
          <div><label className="label">تاريخ الميلاد</label><input type="date" className="input-field" value={p.date_of_birth} onChange={e => setP({...p, date_of_birth: e.target.value})} /></div>
          <div><label className="label">الهاتف</label><input className="input-field" value={p.phone} onChange={e => setP({...p, phone: e.target.value})} />
            {(who === 'father' ? liveHints.father_phone : liveHints.mother_phone) && (
              <p className="text-amber-600 text-sm mt-1"><HiCheck size={14} className="inline ml-1" />{who === 'father' ? liveHints.father_phone : liveHints.mother_phone}</p>
            )}
          </div>
          <div><label className="label">تليفون تاني</label><input className="input-field" value={p.second_phone} onChange={e => setP({...p, second_phone: e.target.value})} /></div>
          <div>
            <label className="label">الإجازة الأسبوعية (اختيار متعدد)</label>
            <div className="flex flex-wrap gap-1 mt-1">
              {Object.entries(weeklyLeaves).map(([k, label]) => {
                const currentArr = (p.weekly_leave || '').split(',').filter(Boolean);
                const isSelected = currentArr.includes(k);
                return (
                  <button
                    type="button"
                    key={k}
                    onClick={() => {
                      const next = isSelected ? currentArr.filter((x: string) => x !== k) : [...currentArr, k];
                      setP({ ...p, weekly_leave: next.join(',') });
                    }}
                    className={`px-2 py-1 text-xs rounded-lg border font-medium transition ${
                      isSelected ? 'bg-church-primary text-white border-church-primary' : 'bg-gray-50 text-gray-700 border-gray-300 hover:bg-gray-100'
                    }`}
                  >
                    {label}
                  </button>
                );
              })}
            </div>
          </div>
        </div>
      )}
      <div className="mt-3">
        <label className="label">أب الاعتراف</label>
        <input className="input-field" list="confession-fathers-wizard" value={p.confession_father} onChange={e => setConfessionFather(p, setP, e.target.value)} placeholder="ابونا سيرافيم / ابونا جوزيف / اكتب أي حد تاني" />
      </div>
      <div className="mt-3">
        <label className="label">كنيسة أب الاعتراف</label>
        <input className="input-field" list="confession-churches-wizard" value={p.confession_church} onChange={e => setP({...p, confession_church: e.target.value})} placeholder="تتكتب لوحدها لو أب الاعتراف سيرافيم أو جوزيف" />
        <datalist id="confession-churches-wizard">
          {confessionChurches.map(c => <option key={c} value={c} />)}
        </datalist>
      </div>
      <div className="flex flex-wrap gap-4 mt-3">
        {p.gender === 'male' && <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={p.is_deacon} onChange={() => togglePersonFlag(p, setP, 'is_deacon')} /> شماس</label>}
        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={p.is_servant} onChange={() => togglePersonFlag(p, setP, 'is_servant')} /> خادم</label>
        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={p.in_prep_center} onChange={() => togglePersonFlag(p, setP, 'in_prep_center')} /> مركز إعداد الخدام</label>
      </div>
    </div>
  );

  return (
    <AuthLayout>
      <div className="mb-6">
        <button onClick={() => router.back()} className="flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
          <HiArrowRight size={20} /> رجوع
        </button>
        <h1 className="text-2xl font-bold text-gray-800">إضافة أسرة جديدة</h1>
      </div>

      <div className="flex gap-2 mb-6 overflow-x-auto pb-2">
        {steps.map((s, i) => (
          <button
            key={i}
            className={`px-4 py-2 rounded-lg text-sm whitespace-nowrap ${step === i + 1 ? 'bg-church-primary text-white' : 'bg-gray-100 text-gray-600'}`}
            onClick={() => setStep(i + 1)}
          >
            {i + 1}. {s}
          </button>
        ))}
      </div>

      <div className="card">
        {step === 1 && (
          <div className="space-y-4">
            <h3 className="text-lg font-bold">بيانات الأسرة</h3>
            <div className="bg-church-light/50 p-3 rounded-lg text-sm text-gray-600">
              الاسم الافتراضي: اسم الأب (الاسم الأول + اسم العائلة) — يمكنك تغييره إذا رغبت.
            </div>
            <div>
              <label className="label">اسم الأسرة</label>
              <input
                className="input-field"
                placeholder={autoFamilyName || 'سيتم التعبئة من اسم الأب'}
                value={familyData.family_name}
                onChange={e => setFamilyData({...familyData, family_name: e.target.value})}
              />
              {liveHints.family_name && (
                <p className="text-amber-600 text-sm mt-1">
                  <HiCheck size={14} className="inline ml-1" />{liveHints.family_name}
                </p>
              )}
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">هاتف الأسرة</label>
                <input className="input-field" value={familyData.phone} onChange={e => setFamilyData({...familyData, phone: e.target.value})} />
                {liveHints.family_phone && (
                  <p className="text-amber-600 text-sm mt-1"><HiCheck size={14} className="inline ml-1" />{liveHints.family_phone}</p>
                )}
              </div>
              <div>
                <label className="label">حالة خاصة (لتقرير الافتقاد)</label>
                <select className="input-field" value={familyData.special_case} onChange={e => setFamilyData({...familyData, special_case: e.target.value})}>
                  {Object.entries(SPECIAL_CASE_LABELS).map(([k, label]) => (
                    <option key={k} value={k}>{label}</option>
                  ))}
                </select>
              </div>
            </div>
            <div className="border rounded-lg p-3 bg-gray-50">
              <h4 className="font-bold text-sm mb-2">العنوان التفصيلي</h4>
              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                  <label className="label">المنطقة</label>
                  <input className="input-field" list="families-areas" value={familyData.area} onChange={e => setFamilyData({...familyData, area: e.target.value})} placeholder="اختر أو اكتب اسم المنطقة" />
                  <datalist id="families-areas">
                    {suggestedAreas.map(a => <option key={a} value={a} />)}
                  </datalist>
                </div>
                <div>
                  <label className="label">اسم الشارع</label>
                  <input className="input-field" value={familyData.street} onChange={e => setFamilyData({...familyData, street: e.target.value})} />
                </div>
                <div>
                  <label className="label">متفرع من</label>
                  <input className="input-field" value={familyData.branch_from} onChange={e => setFamilyData({...familyData, branch_from: e.target.value})} />
                </div>
                <div className="col-span-2">
                  <label className="label">أقرب مكان مميز</label>
                  <input className="input-field" value={familyData.landmark} onChange={e => setFamilyData({...familyData, landmark: e.target.value})} />
                </div>
                <div>
                  <label className="label">رقم المنزل</label>
                  <input className="input-field" value={familyData.building_number} onChange={e => setFamilyData({...familyData, building_number: e.target.value})} />
                </div>
                <div>
                  <label className="label">الدور</label>
                  <input className="input-field" value={familyData.floor} onChange={e => setFamilyData({...familyData, floor: e.target.value})} />
                </div>
                <div className="col-span-2">
                  <label className="label">رقم الشقة</label>
                  <input className="input-field" value={familyData.apartment_number} onChange={e => setFamilyData({...familyData, apartment_number: e.target.value})} />
                </div>
                <div className="col-span-2">
                  <label className="label">تحديد الموقع (GPS)</label>
                  <div className="flex flex-wrap items-center gap-2">
                    <button type="button" className="btn" onClick={() => getGpsLocation()}>حدد موقعي الحالي</button>
                    <button type="button" className="btn-secondary" onClick={() => openMapAt(familyData.gps_location || 'مصر')}>فتح الخريطة</button>
                    {familyData.gps_location && (
                      <a href={`https://www.google.com/maps?q=${familyData.gps_location}`} target="_blank" rel="noreferrer" className="text-sm text-blue-600 underline">عرض الموقع على الخريطة</a>
                    )}
                  </div>
                  <input className="input-field mt-2" value={familyData.gps_location} onChange={e => setFamilyData({...familyData, gps_location: e.target.value})} placeholder="اكتب أو الصق الإحداثيات يدوياً لو التحديد التلقائي مش شغال — مثال: 30.123456,31.123456" />
                </div>
              </div>
            </div>
            <div>
              <label className="label">العنوان (اختياري - نص حر)</label>
              <input className="input-field" value={familyData.address} onChange={e => setFamilyData({...familyData, address: e.target.value})} placeholder="لو حابب تكتب العنوان كاملاً في سطر واحد" />
            </div>
            <div>
              <label className="label">ملاحظات</label>
              <textarea className="input-field" value={familyData.notes} onChange={e => setFamilyData({...familyData, notes: e.target.value})} rows={3} />
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-6">
            {renderParentBlock('بيانات الأب (الزوج)', fatherData, setFatherData, 'father')}
            {renderParentBlock('بيانات الأم (الزوجة)', motherData, setMotherData, 'mother')}
            <div className="border-t pt-4">
              <h3 className="text-lg font-bold mb-1">الأجداد (اختياري)</h3>
              <p className="text-sm text-gray-500 mb-3">لو السيد/الست الكبيرة ساكن/ة مع الأسرة، أدخل بياناته/ا — هيتسجل تلقائياً كجد/جدة للأسرة ويرتبط بأحفاده.</p>
              {renderGi('الجد (أبو الأب)', grandfatherData, setGrandfatherData)}
              {renderGi('الجدة (أم الأب)', grandmotherData, setGrandmotherData)}
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-4">
            <h3 className="text-lg font-bold">إضافة الأطفال</h3>
            <p className="text-sm text-gray-500">سيُأخذ اسم العائلة تلقائياً من الأب: <strong>{childSurname || '— اسم الأب'}</strong> — أدخل الاسم الأول فقط.</p>
            <div className="grid grid-cols-6 gap-3 items-end">
              <div className="col-span-2"><label className="label">الاسم الأول *</label><input className="input-field" value={childForm.first_name} onChange={e => setChildForm({...childForm, first_name: e.target.value})} placeholder="الاسم الأول للابن/الابنة" /></div>
              <div><label className="label">النوع</label><select className="input-field" value={childForm.gender} onChange={e => setChildForm({...childForm, gender: e.target.value})}><option value="male">ذكر</option><option value="female">أنثى</option></select></div>
              <div><label className="label">الاسم الأخير</label><input className="input-field" value={childSurname} disabled placeholder="تلقائي" /></div>
              <div><label className="label">تاريخ الميلاد</label><input type="date" className="input-field" value={childForm.date_of_birth} onChange={e => setChildForm({...childForm, date_of_birth: e.target.value})} /></div>
              <div><label className="label">الهاتف (اختياري)</label><input className="input-field" value={childForm.phone} onChange={e => setChildForm({...childForm, phone: e.target.value})} />
                {childForm.phone.length >= 7 && liveHints[`child_${childForm.first_name}`] && (
                  <p className="text-amber-600 text-sm mt-1"><HiCheck size={14} className="inline ml-1" />{liveHints[`child_${childForm.first_name}`]}</p>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3 items-end mt-3">
              <div>
                <label className="label">المرحلة الدراسية (سنة كام ف المدرسة أو الكلية)</label>
                <select className="input-field" value={childForm.school_year} onChange={e => setChildForm({...childForm, school_year: e.target.value})}>
                  <option value="">— بدون / لسه رضيع أو بالغ —</option>
                  {YEAR_LABELS.map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
              </div>
              <div><label className="label">أب الاعتراف</label><input className="input-field" list="confession-fathers-wizard" value={childForm.confession_father} onChange={e => setChildConfessionFather(e.target.value)} placeholder="ابونا سيرافيم / ابونا جوزيف / اكتب أي حد تاني" /></div>
              <div><label className="label">تليفون تاني (اختياري)</label><input className="input-field" value={childForm.second_phone} onChange={e => setChildForm({...childForm, second_phone: e.target.value})} /></div>
              <div>
                <label className="label">الإجازة الأسبوعية (اختيار متعدد)</label>
                <div className="flex flex-wrap gap-1 mt-1">
                  {Object.entries(weeklyLeaves).map(([k, label]) => {
                    const currentArr = (childForm.weekly_leave || '').split(',').filter(Boolean);
                    const isSelected = currentArr.includes(k);
                    return (
                      <button
                        type="button"
                        key={k}
                        onClick={() => {
                          const next = isSelected ? currentArr.filter((x: string) => x !== k) : [...currentArr, k];
                          setChildForm({ ...childForm, weekly_leave: next.join(',') });
                        }}
                        className={`px-2 py-1 text-xs rounded-lg border font-medium transition ${
                          isSelected ? 'bg-church-primary text-white border-church-primary' : 'bg-gray-50 text-gray-700 border-gray-300 hover:bg-gray-100'
                        }`}
                      >
                        {label}
                      </button>
                    );
                  })}
                </div>
              </div>
              <div>
                <label className="label">كنيسة أب الاعتراف</label>
                <input className="input-field" list="confession-churches-wizard" value={childForm.confession_church} onChange={e => setChildForm({...childForm, confession_church: e.target.value})} placeholder="تتكتب لوحدها لو أب الاعتراف سيرافيم أو جوزيف" />
              </div>
              <div className="flex flex-wrap gap-3 items-center pt-1">
                {childForm.gender === 'male' && <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={childForm.is_deacon} onChange={() => toggleChildFlag('is_deacon')} /> شماس</label>}
                <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={childForm.is_servant} onChange={() => toggleChildFlag('is_servant')} /> خادم</label>
                <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" className="h-4 w-4" checked={childForm.in_prep_center} onChange={() => toggleChildFlag('in_prep_center')} /> مركز إعداد الخدام</label>
              </div>
            </div>
            <div className="mt-4 flex justify-end">
              <button type="button" onClick={addChild} className="btn-primary flex items-center gap-2 px-6 py-2.5">
                <HiPlus size={18} /> إضافة هذا الابن / الابنة للقائمة
              </button>
            </div>
            {children.length > 0 && (
              <div className="mt-4 space-y-2">
                {children.map((child, i) => (
                  <div key={i} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                    <div>
                      <span>
                        <strong className="text-gray-800">{child.first_name} {childSurname}</strong> ({child.gender === 'male' ? 'ذكر' : 'أنثى'}) 
                        {child.date_of_birth ? ` — عمر ${calcAge(child.date_of_birth)} سنة (${child.date_of_birth})` : ' — بدون تاريخ ميلاد'}
                      </span>
                      <span className="block text-xs text-gray-500">{child.confession_father ? `أب الاعتراف: ${child.confession_father}` : ''}</span>
                      {child.phone && liveHints[`child_${child.first_name}`] && (
                        <p className="text-amber-600 text-sm mt-1"><HiCheck size={12} className="inline ml-1" />{liveHints[`child_${child.first_name}`]}</p>
                      )}
                    </div>
                    <button onClick={() => removeChild(i)} className="text-red-500 hover:text-red-700 text-sm">حذف</button>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {step === 4 && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-bold">اقتراحات الخدمات</h3>
              <button onClick={computeSuggestions} className="btn-primary text-sm">إعادة الحساب</button>
            </div>
            <p className="text-sm text-gray-500">أكّد لكل فرد: مرحلته الدراسية (سنة كام في المدرسة أو الكلية) والاجتماع/الخدمة. الحضانة و الكبار (أب/أم/بالغ) متاحة كخيارات.</p>
            {fatherData.first_name && fatherData.care_situation !== 'deceased' && (
              <div className="flex items-center justify-between gap-4 bg-church-light/50 p-4 rounded-lg">
                <div>
                  <p className="font-medium">الأب: {fatherData.first_name} {fatherData.middle_name}</p>
                  <p className="text-sm text-gray-500">{fatherSugg.reason || '...'}</p>
                </div>
                <div className="flex gap-3">
                  <div>
                    <label className="label text-xs mb-1">الاجتماع/الخدمة</label>
                    <select className="input-field" value={fatherSugg.suggested_id} onChange={e => setFatherSugg({ ...fatherSugg, suggested_id: e.target.value })}>
                      <option value="">بدون خدمة</option>
                      {services.map((svc: any) => (
                        <option key={svc.id} value={String(svc.id)}>{svc.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>
            )}
            {motherData.first_name && motherData.care_situation !== 'deceased' && (
              <div className="flex items-center justify-between gap-4 bg-church-light/50 p-4 rounded-lg">
                <div>
                  <p className="font-medium">الأم: {motherData.first_name} {motherData.middle_name}</p>
                  <p className="text-sm text-gray-500">{motherSugg.reason || '...'}</p>
                </div>
                <div className="flex gap-3">
                  <div>
                    <label className="label text-xs mb-1">الاجتماع/الخدمة</label>
                    <select className="input-field" value={motherSugg.suggested_id} onChange={e => setMotherSugg({ ...motherSugg, suggested_id: e.target.value })}>
                      <option value="">بدون خدمة</option>
                      {services.map((svc: any) => (
                        <option key={svc.id} value={String(svc.id)}>{svc.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>
            )}
            {renderGrandparentRow('الجد', grandfatherData, setGrandfatherData, grandfatherSugg, setGrandfatherSugg)}
            {renderGrandparentRow('الجدة', grandmotherData, setGrandmotherData, grandmotherSugg, setGrandmotherSugg)}
            {suggestions.length > 0 ? (
              <div className="space-y-3">
                {suggestions.map((s, i) => (
                  <div key={i} className="flex items-center justify-between gap-4 bg-gray-50 p-4 rounded-lg">
                    <div>
                      <p className="font-medium">{s.child_name}</p>
                      <p className="text-sm text-gray-500">{s.reason}</p>
                    </div>
                    <div className="flex gap-3">
                      <div>
                        <label className="label text-xs mb-1">المرحلة الدراسية</label>
                        <select className="input-field" value={children[i]?.school_year || ''} onChange={e => {
                          const nextChildren = [...children];
                          nextChildren[i] = { ...nextChildren[i], school_year: e.target.value };
                          setChildren(nextChildren);
                        }}>
                          <option value="">— بدون / رضيع / حضانة —</option>
                          <option value="nursery">حضانة</option>
                          {YEAR_LABELS.filter(([k]) => k !== 'nursery').map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="label text-xs mb-1">الاجتماع/الخدمة</label>
                        <select className="input-field" value={s.suggested_id} onChange={e => {
                          const next = [...suggestions];
                          next[i] = { ...s, suggested_id: e.target.value };
                          setSuggestions(next);
                        }}>
                          <option value="">بدون خدمة</option>
                          {services.map((svc: any) => (
                            <option key={svc.id} value={String(svc.id)}>
                              {svc.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500 text-center py-8">{children.length === 0 ? 'أضف الأطفال أولاً' : 'جاري حساب الاقتراحات...'}</p>
            )}
          </div>
        )}

        {step === 5 && (
          <div className="space-y-4">
            <h3 className="text-lg font-bold">مراجعة البيانات</h3>
            <div className="bg-gray-50 p-4 rounded-lg space-y-2">
              <p><strong>اسم الأسرة:</strong> {familyData.family_name.trim() || autoFamilyName || 'بدون اسم'}</p>
              <p><strong>هاتف الأسرة:</strong> {familyData.phone || '—'}</p>
              <p><strong>العنوان:</strong> {familyData.address || '—'}</p>
              {familyData.special_case && <p><strong>حالة خاصة:</strong> {caseLabel(familyData.special_case)}</p>}
            </div>
            {fatherData.first_name && (
              <div className="bg-gray-50 p-4 rounded-lg">
                <p><strong>الأب:</strong> {fatherData.first_name} {fatherData.middle_name} {fatherData.last_name} — متزوج — {careLabel(fatherData.care_situation)}{fatherData.school_year ? ` — المرحلة: ${translate(fatherData.school_year, schoolYears)}` : ''}{resolveOccupation(fatherData) ? ` — ${resolveOccupation(fatherData)}` : ''}{fatherData.confession_father ? ` — أب اعترافه: ${fatherData.confession_father}` : ''}{fatherData.date_of_death && fatherData.care_situation === 'deceased' ? ` - وفاة ${fatherData.date_of_death}` : ''}{fatherSugg.suggested_id ? ` — الخدمة: ${services.find((sv: any) => String(sv.id) === fatherSugg.suggested_id)?.name || ''}` : ''}</p>
              </div>
            )}
            {motherData.first_name && (
              <div className="bg-gray-50 p-4 rounded-lg">
                <p><strong>الأم:</strong> {motherData.first_name} {motherData.middle_name} {motherData.last_name} — متزوجة — {careLabel(motherData.care_situation)}{motherData.school_year ? ` — المرحلة: ${translate(motherData.school_year, schoolYears)}` : ''}{resolveOccupation(motherData) ? ` — ${resolveOccupation(motherData)}` : ''}{motherData.confession_father ? ` — أب اعترافها: ${motherData.confession_father}` : ''}{motherData.date_of_death && motherData.care_situation === 'deceased' ? ` - وفاة ${motherData.date_of_death}` : ''}{motherSugg.suggested_id ? ` — الخدمة: ${services.find((sv: any) => String(sv.id) === motherSugg.suggested_id)?.name || ''}` : ''}</p>
              </div>
            )}
            {renderGrandparentReview('الجد', grandfatherData, grandfatherSugg)}
            {renderGrandparentReview('الجدة', grandmotherData, grandmotherSugg)}
            {children.length > 0 && (
              <div className="bg-gray-50 p-4 rounded-lg">
                <p><strong>الأطفال:</strong></p>
                {children.map((c, i) => (
                  <p key={i} className="mr-4">- {c.first_name} {childSurname} ({c.gender === 'male' ? 'ذكر' : 'أنثى'}) — أعزب{c.school_year ? ` — المرحلة: ${translate(c.school_year, schoolYears)}` : ''}{c.confession_father ? ` — أب اعترافه: ${c.confession_father}` : ''} — الخدمة: {suggestions[i]?.suggested_id ? services.find((sv: any) => String(sv.id) === suggestions[i].suggested_id)?.name || '—' : 'بدون خدمة'}</p>
                ))}
              </div>
            )}
          </div>
        )}

        <div className="flex gap-3 mt-6 pt-4 border-t">
          {step > 1 && <button onClick={() => setStep(step - 1)} className="btn-secondary">السابق</button>}
          {step < 5 && <button onClick={() => {
            if (step === 2 && !fatherData.first_name && !motherData.first_name) { toast.error('أدخل اسم الأب أو الأم على الأقل'); return; }
            setStep(step + 1);
          }} className="btn-primary">التالي</button>}
          {step === 5 && (
            <button onClick={() => saveFamily(false)} className="btn-success flex items-center gap-2" disabled={loading}>
              <HiCheck size={18} />
              {loading ? (checkingDups ? 'جاري فحص التكرارات...' : 'جاري الحفظ...') : 'حفظ الأسرة'}
            </button>
          )}
        </div>
      </div>
    {dupWarnings.length > 0 && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h3 className="text-lg font-bold mb-1">توجد أسماء مطابقة في السجلات</h3>
            <p className="text-sm text-gray-500 mb-4">يبدو أن بعض الأسماء مضافون بالفعل. لا ننصح بإنشاء سجل جديد — افتح السجل الموجود وأضف بيانات الشخص هناك:</p>
            <div className="space-y-3 max-h-72 overflow-y-auto">
              {dupWarnings.map((w, i) => (
                <div key={i} className="bg-gray-50 p-3 rounded-lg">
                  <p className="font-medium mb-2">{w.label}</p>
                  {w.matches && w.matches.map((m: any) => (
                    <div key={m.id} className="flex items-center justify-between text-sm py-1">
                      <span className="text-church-primary">{m.full_name_display || m.first_name}</span>
                      <span className="text-gray-500">{m.date_of_birth || '-'} | {m.phone || 'بدون هاتف'}</span>
                      <button
                        className="btn-primary text-xs"
                        onClick={() => router.push(`/persons/${m.id}`)}
                      >
                        فتح السجل الموجود
                      </button>
                    </div>
                  ))}
                  {w.familyMatches && w.familyMatches.map((fm: any) => (
                    <div key={fm.family_id} className="flex items-center justify-between text-sm py-1">
                      <span className="text-church-primary">{fm.family_name} {fm.family_code ? `(${fm.family_code})` : ''} — {fm.member_count ?? 0} فرد</span>
                      <span className="text-gray-500">{(fm.matched_reasons || []).join('، ')}</span>
                      <button
                        className="btn-primary text-xs"
                        onClick={() => router.push(`/families/${fm.family_id}`)}
                      >
                        فتح الأسرة الموجود
                      </button>
                    </div>
                  ))}
                </div>
              ))}
            </div>
            <div className="flex gap-3 mt-5">
              <button onClick={() => setDupWarnings([])} className="btn-secondary w-full">إلغاء والعودة للمراجعة</button>
            </div>
          </div>
        </div>
      )}
      <datalist id="confession-fathers-wizard">
        <option value="ابونا سيرافيم" />
        <option value="ابونا جوزيف" />
      </datalist>
    </AuthLayout>
  );
}