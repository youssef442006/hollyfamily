'use client';
import { useState, useEffect } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import toast from 'react-hot-toast';
import { errorMessage } from '@/lib/errorMessage';
import axios from 'axios';

interface PublicUser {
  username: string;
  full_name: string;
  role: string;
}

export default function LoginPage() {
  const [username, setUsername] = useState('admin');
  const [password, setPassword] = useState('123456');
  const [loading, setLoading] = useState(false);
  const [usersList, setUsersList] = useState<PublicUser[]>([]);
  const [manualInput, setManualInput] = useState(false);
  const { login } = useAuth();
  const router = useRouter();

  useEffect(() => {
    // Fetch users for the dropdown list
    axios.get('http://127.0.0.1:8000/api/auth/public-users')
      .then(res => {
        if (Array.isArray(res.data) && res.data.length > 0) {
          setUsersList(res.data);
          setUsername(res.data[0].username);
        }
      })
      .catch(() => {
        // Fallback default list if API is unreachable
        setUsersList([
          { username: 'admin', full_name: 'يوسف عاطف', role: 'مدير النظام' },
          { username: 'abouna_seraphim', full_name: 'أبونا سيرافيم', role: 'كاهن' },
          { username: 'abouna_joseph', full_name: 'أبونا جوزيف', role: 'كاهن' },
          { username: 'mr_nader_ezzat', full_name: 'مستر نادر عزت', role: 'أمين خدمة' },
        ]);
      });
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await login(username, password);
      toast.success('تم تسجيل الدخول بنجاح');
      router.push('/');
    } catch (err: any) {
      toast.error(errorMessage(err, 'خطأ في بيانات الدخول، تأكد من كلمة المرور'));
    } finally {
      setLoading(false);
    }
  };

  const getRoleBadge = (role: string) => {
    if (role === 'مدير النظام' || role === 'admin') return '👑';
    if (role === 'كاهن' || role === 'priest') return '✝️';
    if (role === 'أمين خدمة' || role === 'service_admin') return '⭐';
    return '👤';
  };

  return (
    <div className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background ornament */}
      <div className="absolute inset-0 bg-gradient-to-br from-[#15264a] via-[#1e3766] to-[#233f75]" />
      <div className="absolute -top-32 -left-32 w-[480px] h-[480px] rounded-full bg-[#c9a44c]/10 blur-3xl" />
      <div className="absolute -bottom-40 -right-24 w-[520px] h-[520px] rounded-full bg-[#c9a44c]/10 blur-3xl" />

      <div className="relative w-full max-w-md">
        <div className="bg-white/95 backdrop-blur rounded-3xl p-8 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.5)] border border-[#c9a44c]/30">
          <div className="text-center mb-7">
            <div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-b from-[#e6d088] to-[#b0872b] flex items-center justify-center shadow-xl">
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none">
                <g stroke="#14254a" strokeWidth="2" strokeLinecap="round">
                  <path d="M12 5v4M8 11h8M12 9v8M10 9L8 7M14 9l2-2" />
                </g>
                <circle cx="12" cy="11" r="2" fill="#14254a" />
              </svg>
            </div>
            <h1 className="font-display text-2xl font-bold text-church-dark">نظام إدارة الكنيسة</h1>
            <p className="text-xs tracking-widest text-[#c9a44c] font-bold uppercase mt-1.5">Coptic Church Management</p>
            <div className="cross-divider mt-4" />
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="text-xs font-bold text-slate-700">اختر المستخدم / الحساب</label>
                <button
                  type="button"
                  onClick={() => setManualInput(!manualInput)}
                  className="text-[11px] text-[#c9a44c] hover:underline font-semibold"
                >
                  {manualInput ? 'اختيار من القائمة' : 'كتابة اسم المستخدم يدوياً'}
                </button>
              </div>

              {!manualInput ? (
                <div className="relative">
                  <select
                    className="input-field !py-3 font-semibold text-slate-800"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    required
                  >
                    {usersList.map((u) => (
                      <option key={u.username} value={u.username}>
                        {getRoleBadge(u.role)} {u.full_name} ({u.role})
                      </option>
                    ))}
                  </select>
                </div>
              ) : (
                <input
                  type="text"
                  className="input-field !py-2.5"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  required
                  placeholder="اسم المستخدم"
                />
              )}
            </div>

            <div>
              <label className="label">كلمة المرور</label>
              <input
                type="password"
                className="input-field !py-2.5"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                placeholder="أدخل كلمة المرور"
              />
            </div>

            <button
              type="submit"
              className="btn-gold w-full !py-3 text-lg mt-2 shadow-lg shadow-[#c9a44c]/20"
              disabled={loading}
            >
              {loading ? 'جاري الدخول...' : 'تسجيل الدخول'}
            </button>
          </form>

          {/* Unified Initial Password Notice */}
          <div className="mt-6 p-3.5 bg-amber-50/80 rounded-2xl border border-amber-200/70 text-xs text-amber-900 leading-relaxed">
            <div className="flex items-center gap-1.5 font-bold text-amber-950 mb-1">
              <span>🔐</span>
              <span>كلمة المرور الافتراضية الموحدة:</span>
              <code className="bg-amber-200/70 px-2 py-0.5 rounded text-amber-950 font-mono text-sm">123456</code>
            </div>
            <p className="text-amber-800/90 text-[11px]">
              اختر اسمك من القائمة أعلاه واكتب كلمة المرور (123456) للدخول، ويمكن لكل مستخدم تغيير كلمة المرور في أي وقت من الإعدادات.
            </p>
          </div>
        </div>

        <p className="text-center text-white/50 text-xs mt-6 tracking-wide">
          + بركة صلوات القديسين • نظام إدارة الكنيسة القبطية
        </p>
      </div>
    </div>
  );
}