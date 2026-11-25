'use client';
import { useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';

export default function LoginPage() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await login(username, password);
      toast.success('تم تسجيل الدخول بنجاح');
      router.push('/');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في بيانات الدخول'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-church-light to-blue-50">
      <div className="card w-full max-w-md mx-4">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-church-primary">نظام إدارة الكنيسة</h1>
          <p className="text-gray-500 mt-2">Coptic Church Management System</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="label">اسم المستخدم</label>
            <input
              type="text"
              className="input-field"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="label">كلمة المرور</label>
            <input
              type="password"
              className="input-field"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <button
            type="submit"
            className="btn-primary w-full"
            disabled={loading}
          >
            {loading ? 'جاري الدخول...' : 'تسجيل الدخول'}
          </button>
        </form>
        <div className="mt-6 p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
          <p className="font-medium mb-2">بيانات التجربة:</p>
          <p>Admin: admin / admin123</p>
          <p>Priest: priest / priest123</p>
          <p>Servant: servant / servant123</p>
        </div>
      </div>
    </div>
  );
}
