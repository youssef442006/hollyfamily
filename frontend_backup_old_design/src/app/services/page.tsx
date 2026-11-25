'use client';
import AuthLayout from '@/components/AuthLayout';
import { servicesAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { HiPlus, HiCog, HiTrash } from 'react-icons/hi';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';

export default function ServicesPage() {
  const [services, setServices] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAdd, setShowAdd] = useState(false);
  const [newService, setNewService] = useState({ name: '', description: '', min_age: '', max_age: '', notes: '' });

  const loadServices = async () => {
    try {
      const res = await servicesAPI.list();
      setServices(res.data);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { loadServices(); }, []);

  const deleteService = async (id: number, name: string) => {
    if (!window.confirm(`هل أنت متأكد من حذف الخدمة "${name}"؟`)) return;
    try {
      await servicesAPI.deleteService(id);
      toast.success('تم حذف الخدمة');
      loadServices();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في الحذف'));
    }
  };

  const addService = async () => {
    if (!newService.name) { toast.error('أدخل اسم الخدمة'); return; }
    try {
      await servicesAPI.create({
        name: newService.name,
        description: newService.description,
        min_age: newService.min_age ? parseInt(newService.min_age) : null,
        max_age: newService.max_age ? parseInt(newService.max_age) : null,
        notes: newService.notes,
      });
      toast.success('تمت إضافة الخدمة');
      setShowAdd(false);
      setNewService({ name: '', description: '', min_age: '', max_age: '', notes: '' });
      loadServices();
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ'));
    }
  };

  return (
    <AuthLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">الخدمات</h1>
          <p className="text-gray-500 mt-1">إدارة خدمات الكنيسة</p>
        </div>
        <button onClick={() => setShowAdd(true)} className="btn-primary flex items-center gap-2">
          <HiPlus size={20} /> إضافة خدمة
        </button>
      </div>

      {loading ? (
        <div className="text-center py-20 text-gray-500">جاري التحميل...</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {services.map((svc) => (
            <div key={svc.id} className="card hover:shadow-md transition-shadow relative">
              <Link href={`/services/${svc.id}`} className="block">
                <div className="flex items-center gap-3 mb-3">
                  <div className="bg-church-light p-2 rounded-lg">
                    <HiCog size={24} className="text-church-primary" />
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-800">{svc.name}</h3>
                    <p className="text-sm text-gray-500">{svc.description}</p>
                  </div>
                </div>
                <div className="flex justify-between text-sm text-gray-600 mt-4 pt-3 border-t">
                  <span>خدام: {svc.member_count || 0}</span>
                  <span>مخدومين: {svc.assignment_count || 0}</span>
                </div>
                {svc.min_age !== null && svc.max_age !== null && (
                  <p className="text-xs text-gray-400 mt-2">العمر: {svc.min_age} - {svc.max_age} سنة</p>
                )}
              </Link>
              <button
                onClick={() => deleteService(svc.id, svc.name)}
                className="absolute top-3 left-3 text-red-500 hover:text-red-700"
                title="حذف"
              >
                <HiTrash size={18} />
              </button>
            </div>
          ))}
        </div>
      )}

      {showAdd && (
        <div className="modal-overlay" onClick={() => setShowAdd(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold mb-4">إضافة خدمة جديدة</h3>
            <div className="space-y-3">
              <div><label className="label">اسم الخدمة *</label><input className="input-field" value={newService.name} onChange={e => setNewService({...newService, name: e.target.value})} /></div>
              <div><label className="label">الوصف</label><input className="input-field" value={newService.description} onChange={e => setNewService({...newService, description: e.target.value})} /></div>
              <div className="grid grid-cols-2 gap-3">
                <div><label className="label">الحد الأدنى للعمر</label><input type="number" className="input-field" value={newService.min_age} onChange={e => setNewService({...newService, min_age: e.target.value})} /></div>
                <div><label className="label">الحد الأقصى للعمر</label><input type="number" className="input-field" value={newService.max_age} onChange={e => setNewService({...newService, max_age: e.target.value})} /></div>
              </div>
              <div className="flex gap-3 mt-4">
                <button onClick={addService} className="btn-primary">إضافة</button>
                <button onClick={() => setShowAdd(false)} className="btn-secondary">إلغاء</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AuthLayout>
  );
}