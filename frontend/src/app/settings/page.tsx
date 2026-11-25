'use client';
import AuthLayout from '@/components/AuthLayout';
import { settingsAPI } from '@/lib/api';
import { useEffect, useRef, useState } from 'react';
import { HiSave, HiDownload, HiUpload, HiTrash } from 'react-icons/hi';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';

export default function SettingsPage() {
  const [settings, setSettings] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [editValues, setEditValues] = useState<Record<string, string>>({});
  const [clearing, setClearing] = useState(false);
  const [importing, setImporting] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [sampling, setSampling] = useState(false);
  const [showClearConfirm, setShowClearConfirm] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    settingsAPI.list()
      .then(res => {
        setSettings(res.data);
        const vals: Record<string, string> = {};
        res.data.forEach((s: any) => { vals[s.key] = s.value || ''; });
        setEditValues(vals);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const saveSetting = async (key: string) => {
    try {
      await settingsAPI.update(key, { key, value: editValues[key], description: settings.find(s => s.key === key)?.description });
      toast.success('تم الحفظ');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ'));
    }
  };

  const handleClear = async () => {
    setClearing(true);
    try {
      await settingsAPI.clearData();
      toast.success('تم تفريغ البيانات بنجاح');
      setShowClearConfirm(false);
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في التفريغ'));
    }
    setClearing(false);
  };

  const handleExport = async () => {
    setExporting(true);
    try {
      const res = await settingsAPI.exportData();
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `church_data_${new Date().toISOString().slice(0, 10)}.xlsx`;
      a.click();
      window.URL.revokeObjectURL(url);
      toast.success('تم التصدير بنجاح');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في التصدير'));
    }
    setExporting(false);
  };

  const handleDownloadSample = async () => {
    setSampling(true);
    try {
      const res = await settingsAPI.sampleTemplate();
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = 'church_import_sample.xlsx';
      a.click();
      window.URL.revokeObjectURL(url);
      toast.success('تم تحميل النموذج');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في تحميل النموذج'));
    }
    setSampling(false);
  };

  const handleImport = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImporting(true);
    try {
      const res = await settingsAPI.importData(file);
      toast.success(res.data.message || 'تم الاستيراد بنجاح');
      if (res.data.imported) {
        const i = res.data.imported;
        toast(`الأسر: ${i.families} | الأفراد: ${i.persons} | الخدمات: ${i.services} | المخدومين: ${i.served} | الخدام: ${i.servants}`, { icon: '📊' });
      }
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الاستيراد'));
    }
    setImporting(false);
    if (fileRef.current) fileRef.current.value = '';
  };

  return (
    <AuthLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">الإعدادات</h1>
        <p className="text-gray-500 mt-1">إعدادات النظام العامة</p>
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-10 text-gray-500">جاري التحميل...</div>
        ) : (
          <div className="space-y-6">
            {settings.map(s => (
              <div key={s.id} className="border-b pb-4">
                <label className="label">{s.description || s.key}</label>
                <div className="flex gap-2">
                  <input
                    className="input-field flex-1"
                    value={editValues[s.key] || ''}
                    onChange={e => setEditValues({ ...editValues, [s.key]: e.target.value })}
                  />
                  <button onClick={() => saveSetting(s.key)} className="btn-primary flex items-center gap-1">
                    <HiSave size={16} /> حفظ
                  </button>
                </div>
              </div>
            ))}
            {settings.length === 0 && (
              <div className="text-center py-10 text-gray-500">لا توجد إعدادات</div>
            )}
          </div>
        )}
      </div>

      <div className="card mt-6">
        <h2 className="text-lg font-bold mb-4">إدارة البيانات</h2>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <button
            onClick={handleExport}
            disabled={exporting}
            className="flex items-center justify-center gap-2 bg-church-primary text-white px-6 py-4 rounded-lg hover:bg-church-primary/90 disabled:opacity-50"
          >
            <HiDownload size={20} />
            {exporting ? 'جاري التصدير...' : 'تصدير البيانات'}
          </button>

          <button
            onClick={() => fileRef.current?.click()}
            disabled={importing}
            className="flex items-center justify-center gap-2 bg-green-600 text-white px-6 py-4 rounded-lg hover:bg-green-700 disabled:opacity-50"
          >
            <HiUpload size={20} />
            {importing ? 'جاري الاستيراد...' : 'استيراد البيانات'}
          </button>

          <button
            onClick={handleDownloadSample}
            disabled={sampling}
            className="flex items-center justify-center gap-2 bg-blue-600 text-white px-6 py-4 rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            <HiDownload size={20} />
            {sampling ? 'جاري التحميل...' : 'تحميل نموذج Excel'}
          </button>
          <input ref={fileRef} type="file" accept=".xlsx,.xls" className="hidden" onChange={handleImport} />

          <button
            onClick={() => setShowClearConfirm(true)}
            disabled={clearing}
            className="flex items-center justify-center gap-2 bg-red-600 text-white px-6 py-4 rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            <HiTrash size={20} />
            تفريغ البيانات
          </button>
        </div>

        <div className="bg-gray-50 p-4 rounded-lg mt-4 text-sm text-gray-600">
          <p className="font-medium mb-2">دليل ملف Excel للاستيراد (5 ورقات):</p>
          <p className="mb-2">اضغط «تحميل نموذج Excel» لتنزيل ملف جاهز بالأعمدة الصحيحة — امسح الصفوف المثال واكتب بياناتك فيه ثم ارفعه.</p>
          <ul className="list-disc list-inside mt-1 space-y-1">
            <li><strong>الأسر:</strong> family_code | family_name | phone | address | special_case | notes</li>
            <li><strong>الأفراد:</strong> first_name | middle_name | last_name | gender | date_of_birth | phone | whatsapp | email | national_id | marital_status | care_situation | education_stage | occupation | family_name | family_relationship | notes</li>
            <li><strong>الخدمات:</strong> name | description | min_age | max_age | gender_restriction | education_stages | notes</li>
            <li><strong>المخدومين:</strong> person_name | service_name | status — من يخدمه النظام في كل خدمة</li>
            <li><strong>الخدام:</strong> servant_name | person_name | service_name — كل خادم يخدم من في أي خدمة</li>
          </ul>
          <p className="mt-2 text-xs text-gray-500">
            <strong>family_relationship</strong>: father | mother | husband | wife | son | daughter | relative | other — بيربط الفرد بالأسرة.
            <br /><strong>gender</strong>: male | female — <strong>marital_status</strong>: single | married | divorced | widowed.
          </p>
        </div>
      </div>

      {showClearConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 className="text-xl font-bold text-red-600 mb-3">⚠️ تأكيد تفريغ البيانات</h3>
            <p className="text-gray-700 mb-2">سيتم حذف جميع البيانات نهائياً:</p>
            <ul className="list-disc list-inside text-sm text-gray-600 mb-4">
              <li>جميع الأسر والأفراد</li>
              <li>جميع الخدمات والتعيينات</li>
              <li>جميع العلاقات والزوجات</li>
            </ul>
            <p className="text-red-500 text-sm font-medium mb-4">لا يمكن التراجع عن هذا الإجراء!</p>
            <div className="flex gap-3">
              <button onClick={handleClear} disabled={clearing} className="btn-primary bg-red-600 hover:bg-red-700 flex-1">
                {clearing ? 'جاري التفريغ...' : 'نعم، افرغ البيانات'}
              </button>
              <button onClick={() => setShowClearConfirm(false)} className="btn-secondary flex-1">إلغاء</button>
            </div>
          </div>
        </div>
      )}

      <div className="card mt-6">
        <h2 className="text-lg font-bold mb-4">إحصائيات النظام</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-gray-50 p-4 rounded-lg text-center">
            <p className="text-sm text-gray-500">إصدار النظام</p>
            <p className="text-lg font-bold">1.0.0</p>
          </div>
          <div className="bg-gray-50 p-4 rounded-lg text-center">
            <p className="text-sm text-gray-500">قاعدة البيانات</p>
            <p className="text-lg font-bold">PostgreSQL</p>
          </div>
          <div className="bg-gray-50 p-4 rounded-lg text-center">
            <p className="text-sm text-gray-500">الـ API</p>
            <p className="text-lg font-bold">FastAPI</p>
          </div>
          <div className="bg-gray-50 p-4 rounded-lg text-center">
            <p className="text-sm text-gray-500">الواجهة</p>
            <p className="text-lg font-bold">Next.js</p>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
}