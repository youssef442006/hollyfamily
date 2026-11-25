'use client';
import AuthLayout from '@/components/AuthLayout';
import FamilyTree from '@/components/FamilyTree';
import { familiesAPI } from '@/lib/api';
import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { HiArrowRight } from 'react-icons/hi';

export default function FamilyTreePage() {
  const params = useParams();
  const router = useRouter();
  const [tree, setTree] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await familiesAPI.getTree(Number(params.id));
        setTree(res.data);
      } catch {}
      setLoading(false);
    };
    load();
  }, [params.id]);

  return (
    <AuthLayout>
      <button onClick={() => router.back()} className="flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-4">
        <HiArrowRight size={20} /> رجوع
      </button>
      <h1 className="text-2xl font-bold text-gray-800 mb-6">شجرة عائلة {tree?.family_name || ''}</h1>
      <div className="card">
        {loading ? <div className="text-center py-10 text-gray-500">جاري التحميل...</div> : <FamilyTree tree={tree} />}
      </div>
    </AuthLayout>
  );
}
