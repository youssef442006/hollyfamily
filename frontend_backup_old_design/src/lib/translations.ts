export const educationStages: Record<string, string> = {
  nursery: 'حضانة',
  angels: 'ملايكة',
  primary_1_2: 'أولى وثانية ابتدائي',
  primary_3_4: 'ثالثة ورابعة ابتدائي',
  primary_5_6: 'خامسة وسادسة ابتدائي',
  preparatory: 'إعدادي',
  secondary: 'ثانوي',
  university: 'جامعيين',
  graduate: 'خريجين',
  mens_gathering: 'اجتماع الرجال',
};

export const schoolYears: Record<string, string> = {
  nursery: 'حضانة',
  angels: 'ملايكة',
  grade_1: 'أولى ابتدائي',
  grade_2: 'تانية ابتدائي',
  grade_3: 'تالتة ابتدائي',
  grade_4: 'رابعة ابتدائي',
  grade_5: 'خامسة ابتدائي',
  grade_6: 'سادسة ابتدائي',
  prep_1: 'أولى إعدادي',
  prep_2: 'تانية إعدادي',
  prep_3: 'تالتة إعدادي',
  sec_1: 'أولى ثانوي',
  sec_2: 'تانية ثانوي',
  sec_3: 'تالتة ثانوي',
  uni_1: 'أولى جامعة',
  uni_2: 'تانية جامعة',
  uni_3: 'تالتة جامعة',
  uni_4: 'رابعة جامعة',
  uni_5: 'خامسة جامعة',
  uni_6: 'سادسة جامعة',
  uni_7: 'سابعة جامعة',
  university: 'جامعي',
  graduate: 'خريج',
};

export const relationshipTypes: Record<string, string> = {
  father: 'أب',
  mother: 'أم',
  husband: 'زوج',
  wife: 'زوجة',
  son: 'ابن',
  daughter: 'ابنة',
  grandfather: 'جد',
  grandmother: 'جدة',
  grandson: 'حفيد',
  granddaughter: 'حفيدة',
  relative: 'قريب',
  other: 'أخرى',
};

export const maritalStatuses: Record<string, string> = {
  single: 'أعزب',
  married: 'متزوج',
  divorced: 'مطلق',
  widowed: 'أرمل',
};

export const careSituations: Record<string, string> = {
  normal: 'عادي',
  deceased: 'متوفي/متوفاة',
  widowed: 'أرمل/أرملة',
  abroad: 'مسافر بالخارج',
  special_needs: 'ذو احتياجات خاصة',
};

export const specialCases: Record<string, string> = {
  '': '',
  financial_need: 'تحتاج دعم مادي',
  medical_need: 'تحتاج رعاية صحية',
  financial_medical: 'دعم مادي + رعاية صحية',
  prisoner: 'أحد الأفراد مسجون',
  orphaned: 'أسرة بها أيتام',
};

export const serviceRoles: Record<string, string> = {
  service_admin: 'أمين خدمة',
  assistant_admin: 'أمين مساعد',
  servant: 'خادم',
  supervisor: 'مسؤول',
  other: 'أخرى',
};

export const visitTypes: Record<string, string> = {
  home_visit: 'زيارة منزلية',
  phone_call: 'اتصال',
  follow_up: 'متابعة',
  emergency_visit: 'زيارة طارئة',
  other: 'أخرى',
};

export const visitStatuses: Record<string, string> = {
  pending: 'معلق',
  completed: 'مكتمل',
  cancelled: 'ملغي',
  rescheduled: 'أعيد جدولتها',
};

export const confessionStatuses: Record<string, string> = {
  upcoming: 'قادم',
  completed: 'مكتمل',
  missed: 'فائت',
  cancelled: 'ملغي',
};

export const genders: Record<string, string> = {
  male: 'ذكر',
  female: 'أنثى',
};

export const suggestedAreas = [
  'عزبة البكري',
  'أرض الدلتا 1',
  'أرض الدلتا 2',
  'الخزف والصيني',
  'بطن الجبل',
  'أرض أطلس',
];

export const weeklyLeaves: Record<string, string> = {
  saturday: 'السبت',
  sunday: 'الأحد',
  monday: 'الاثنين',
  tuesday: 'الثلاثاء',
  wednesday: 'الأربعاء',
  thursday: 'الخميس',
  friday: 'الجمعة',
  none: 'بدون (إجازة متغيرة)',
};

export const confessionChurches = [
  'كنيسة العائلة المقدسة',
  'كنيسة أخرى',
];

export const kinshipTypes: Record<string, string> = {
  father: 'أب',
  mother: 'أم',
  son: 'ابن',
  daughter: 'ابنة',
  husband: 'زوج',
  wife: 'زوجة',
  brother: 'أخ',
  sister: 'أخت',
};

export function translate(value: string | null | undefined, map: Record<string, string>): string {
  if (!value) return '-';
  return map[value] || value;
}