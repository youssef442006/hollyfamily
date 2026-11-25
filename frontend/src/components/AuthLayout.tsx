'use client';
import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import Sidebar from './Sidebar';

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.push('/login');
    }
  }, [user, loading, router]);

  if (loading) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center gap-4">
        <div className="w-14 h-14 rounded-full border-4 border-[#c9a44c]/30 border-t-[#c9a44c] animate-spin" />
        <div className="text-church-primary text-lg font-display font-bold">جاري التحميل...</div>
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen">
      <Sidebar />
      <main className="mr-0 lg:mr-64 min-h-screen">
        <div className="p-5 lg:p-8 lg:px-10 max-w-[1600px] mx-auto">
          {children}
        </div>
      </main>
    </div>
  );
}