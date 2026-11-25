'use client';
import AuthLayout from '@/components/AuthLayout';
import { reportsAPI } from '@/lib/api';
import { useEffect, useState, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { HiDownload, HiExternalLink } from 'react-icons/hi';
import toast from 'react-hot-toast';
import { educationStages, translate, careSituations, specialCases, schoolYears } from '@/lib/translations';

export default function ReportsPage() {
  return (
    <Suspense fallback={<AuthLayout><div className="text-center py-10 text-gray-500">جاري التحميل...</div></AuthLayout>}>
      <ReportsInner />
    </Suspense>
  );
}

function ReportsInner() {
  const searchParams = useSearchParams();
  const [activeTab, setActiveTab] = useState('families');
  const [data, setData] = useState<any[]>([]);
  const [yearData, setYearData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  const loadData = async () => {
    setLoading(true);
    try {
      let res;
      switch (activeTab) {
        case 'families': res = await reportsAPI.families(); break;
        case 'services': res = await reportsAPI.services(); break;
        case 'age':
          const [stageRes, yearRes] = await Promise.all([reportsAPI.ageDistribution(), reportsAPI.ageDistributionByYear()]);
          setData(stageRes.data);
          setYearData(yearRes.data);
          setLoading(false);
          return;
        case 'neglected': res = await reportsAPI.neglected(); break;
        case 'withoutService': res = await reportsAPI.withoutService(); break;
        case 'special': res = await reportsAPI.specialCases(); break;
        default: res = await reportsAPI.families();
      }
      setData(res.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadData(); }, [activeTab]);

  useEffect(() => {
    const t = searchParams.get('tab');
    if (t && ['families', 'services', 'age', 'neglected', 'withoutService', 'special'].includes(t)) {
      setActiveTab(t);
    }
  }, [searchParams]);

  const exportCSV = async (type: string) => {
    try {
      const res = await reportsAPI.exportCSV(type);
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `${type}_report.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
      toast.success('تم التصدير بنجاح');
    } catch {}
  };

  const tabs = [
    { id: 'families', label: 'الأسر' },
    { id: 'services', label: 'الخدمات' },
    { id: 'age', label: 'التوزيع العمري' },
    { id: 'neglected', label: 'لم تتم المتابعة' },
    { id: 'withoutService', label: 'بدون خدمة' },
    { id: 'special', label: 'الحالات الخاصة' },
  ];

  return (
    <AuthLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">التقارير</h1>
          <p className="text-gray-500 mt-1">تقارير الكنيسة والإحصائيات</p>
        </div>
        <button onClick={() => exportCSV(activeTab)} className="btn-secondary flex items-center gap-2">
          <HiDownload size={18} /> تصدير CSV
        </button>
      </div>

      <div className="flex gap-2 mb-6 overflow-x-auto pb-2">
        {tabs.map(tab => (
          <button key={tab.id} onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2 rounded-lg text-sm whitespace-nowrap ${activeTab === tab.id ? 'bg-church-primary text-white' : 'bg-gray-100 text-gray-600'}`}>
            {tab.label}
          </button>
        ))}
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-10 text-gray-500">جاري التحميل...</div>
        ) : data.length === 0 && (activeTab !== 'age' || yearData.length === 0) ? (
          <div className="text-center py-10 text-gray-500">لا توجد بيانات</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="table-header">
                  {activeTab === 'families' && (<>
                    <th className="px-4 py-3 text-right">كود الأسرة</th>
                    <th className="px-4 py-3 text-right">اسم الأسرة</th>
                    <th className="px-4 py-3 text-right">الهاتف</th>
                    <th className="px-4 py-3 text-right">عدد الأفراد</th>
                    <th className="px-4 py-3 text-right">الأطفال</th>
                    <th className="px-4 py-3 text-right">آخر افتقاد</th>
                    <th className="px-4 py-3 text-right">منذ مدة</th>
                  </>)}
                  {activeTab === 'services' && (<>
                    <th className="px-4 py-3 text-right">الخدمة</th>
                    <th className="px-4 py-3 text-right">الخدام</th>
                    <th className="px-4 py-3 text-right">المخدومين</th>
                  </>)}
                  {activeTab === 'age' && (<>
                    <th className="px-4 py-3 text-right">المرحلة</th>
                    <th className="px-4 py-3 text-right">العدد</th>
                  </>)}
                  {activeTab === 'neglected' && (<>
                    <th className="px-4 py-3 text-right">الأسرة</th>
                    <th className="px-4 py-3 text-right">الهاتف</th>
                    <th className="px-4 py-3 text-right">آخر افتقاد</th>
                    <th className="px-4 py-3 text-right">منذ مدة</th>
                  </>)}
                  {activeTab === 'withoutService' && (<>
                    <th className="px-4 py-3 text-right">الاسم</th>
                    <th className="px-4 py-3 text-right">النوع</th>
                    <th className="px-4 py-3 text-right">المرحلة</th>
                    <th className="px-4 py-3 text-right">الهاتف</th>
                    <th className="px-4 py-3 text-right">حالة الخدمة</th>
                    <th className="px-4 py-3 text-right">إجراءات</th>
                  </>)}
                  {activeTab === 'special' && (<>
                    <th className="px-4 py-3 text-right">كود الأسرة</th>
                    <th className="px-4 py-3 text-right">اسم الأسرة</th>
                    <th className="px-4 py-3 text-right">الهاتف</th>
                    <th className="px-4 py-3 text-right">الحالة</th>
                    <th className="px-4 py-3 text-right">عدد الأفراد</th>
                    <th className="px-4 py-3 text-right">إجراءات</th>
                  </>)}
                </tr>
              </thead>
              <tbody>
                {data.map((item, i) => (
                  <tr key={i} className="border-b border-gray-100 hover:bg-gray-50">
                    {activeTab === 'families' && (<>
                      <td className="px-4 py-3 font-mono text-sm">{item.family_code}</td>
                      <td className="px-4 py-3 font-medium">{item.family_name}</td>
                      <td className="px-4 py-3">{item.phone || '-'}</td>
                      <td className="px-4 py-3">{item.total_members}</td>
                      <td className="px-4 py-3">{item.children_count}</td>
                      <td className="px-4 py-3">{item.last_visit_date ? `لم يتم افتقاده منذ ${item.last_visit_date}` : 'لم يتم افتقاده بعد'}</td>
                      <td className="px-4 py-3">{item.days_since_visit != null ? (
                        <span className={`font-bold ${item.days_since_visit >= 90 ? 'text-red-500' : 'text-gray-700'}`}>منذ {item.days_since_visit} يوم</span>
                      ) : <span className="text-red-500 font-bold">بعد</span>}</td>
                    </>)}
                    {activeTab === 'services' && (<>
                      <td className="px-4 py-3 font-medium">{item.service_name}</td>
                      <td className="px-4 py-3">{item.servants}</td>
                      <td className="px-4 py-3">{item.beneficiaries}</td>
                    </>)}
                    {activeTab === 'age' && (<>
                      <td className="px-4 py-3 font-medium">{educationStages[item.stage] || item.stage}</td>
                      <td className="px-4 py-3">{item.count}</td>
                    </>)}
                    {activeTab === 'neglected' && (<>
                      <td className="px-4 py-3 font-medium">{item.family_name}</td>
                      <td className="px-4 py-3">{item.phone || '-'}</td>
                      <td className="px-4 py-3">{item.last_visit_date ? `لم يتم افتقاده منذ ${item.last_visit_date}` : 'لم يتم افتقاده بعد'}</td>
                      <td className="px-4 py-3 text-red-500 font-bold">{item.days_since_visit != null ? `منذ ${item.days_since_visit} يوم` : 'بعد'}</td>
                    </>)}
                    {activeTab === 'withoutService' && (<>
                      <td className="px-4 py-3 font-medium">{item.full_name}</td>
                      <td className="px-4 py-3">{item.gender === 'female' ? 'أنثى' : 'ذكر'}</td>
                      <td className="px-4 py-3">{educationStages[item.education_stage] || item.education_stage || '-'}</td>
                      <td className="px-4 py-3">{item.phone || '-'}</td>
                      <td className="px-4 py-3">{item.care_situation ? translate(item.care_situation, careSituations) : '-'}</td>
                      <td className="px-4 py-3">
                        <Link href={`/persons/${item.person_id}`} className="btn-primary text-sm px-3 py-1 inline-flex items-center gap-1">
                          <HiExternalLink size={14} /> فتح الملف
                        </Link>
                      </td>
                    </>)}
                    {activeTab === 'special' && (<>
                      <td className="px-4 py-3 font-mono text-sm">{item.family_code}</td>
                      <td className="px-4 py-3 font-medium">{item.family_name}</td>
                      <td className="px-4 py-3">{item.phone || '-'}</td>
                      <td className="px-4 py-3">
                        <span className="badge-red">{translate(item.special_case, specialCases)}</span>
                      </td>
                      <td className="px-4 py-3">{item.total_members}</td>
                      <td className="px-4 py-3">
                        <Link href={`/families/${item.family_id}`} className="btn-primary text-sm px-3 py-1 inline-flex items-center gap-1">
                          <HiExternalLink size={14} /> فتح الأسرة
                        </Link>
                      </td>
                    </>)}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {activeTab === 'age' && yearData.length > 0 && (
          <div className="mt-6 border-t pt-4">
            <h3 className="text-lg font-bold text-gray-800 mb-3">التوزيع بالسنوات الدراسية</h3>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="table-header">
                    <th className="px-4 py-3 text-right">المرحلة</th>
                    <th className="px-4 py-3 text-right">السنة بالظبط</th>
                    <th className="px-4 py-3 text-right">العدد</th>
                  </tr>
                </thead>
                <tbody>
                  {yearData.map((y, i) => (
                    <tr key={i} className="border-b border-gray-100 hover:bg-gray-50">
                      <td className="px-4 py-3 font-medium">{educationStages[y.stage] || y.stage || '-'}</td>
                      <td className="px-4 py-3">{y.school_year ? schoolYears[y.school_year] || y.school_year : '-'}</td>
                      <td className="px-4 py-3">{y.count}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </AuthLayout>
  );
}
