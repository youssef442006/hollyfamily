'use client';
import { useState } from 'react';

export const OCCUPATIONS = [
  'طبيب', 'صيدلي', 'مهندس', 'محامٍ', 'محاسب', 'مدرس', 'ممرض',
  'موظف حكومي', 'موظف بنك', 'موظف شركة', 'مندوب مبيعات', 'سكرتير',
  'عامل', 'سائق', 'نجار', 'سباك', 'كهربائي', 'حداد', 'ميكانيكي',
  'فني', 'خياط', 'حلاق', 'كوافير', 'طباخ', 'جزار', 'مزارع',
  'تاجر', 'صاحب محل', 'صاحب ورشة', 'مقاول', 'أمين مخزن', 'كاشير',
  'مترجم', 'صحفي', 'مصور', 'مصمم', 'مبرمج',
  'ضابط', 'قاضٍ / وكيل نيابة', 'كاهن / راهب', 'شماس',
  'ربة منزل', 'طالب', 'على المعاش', 'بدون عمل',
];

export default function OccupationInput({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const [open, setOpen] = useState(false);
  const q = (value || '').trim();
  const matches = OCCUPATIONS.filter(o => !q || o.includes(q));
  return (
    <div className="relative">
      <label className="label">المهنة (اكتب للبحث)</label>
      <input
        className="input-field"
        value={value}
        placeholder="اكتب للبحث أو أدخل مهنة جديدة"
        onChange={e => { onChange(e.target.value); setOpen(true); }}
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
      />
      {open && (
        <div className="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto mt-1">
          {matches.map(o => (
            <div
              key={o}
              className="px-3 py-2 hover:bg-church-light cursor-pointer text-sm"
              onMouseDown={() => { onChange(o); setOpen(false); }}
            >
              {o}
            </div>
          ))}
          {matches.length === 0 && (
            <div className="px-3 py-2 text-sm text-gray-500">سيُحفظ "{q}" كمهنة جديدة</div>
          )}
        </div>
      )}
    </div>
  );
}
