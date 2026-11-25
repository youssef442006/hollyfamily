import type { Metadata } from 'next';
import { AuthProvider } from '@/contexts/AuthContext';
import './globals.css';
import { Toaster } from 'react-hot-toast';

export const metadata: Metadata = {
  title: 'نظام إدارة الكنيسة',
  description: 'نظام إدارة كنيسة قبطية أرثوذكسية',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl">
      <body className="font-sans">
        <AuthProvider>
          <Toaster position="top-center"
            toastOptions={{
              style: {
                fontFamily: 'Cairo, sans-serif',
                borderRadius: '12px',
                boxShadow: '0 8px 30px rgba(30,55,102,0.18)',
              },
            }}
          />
          {children}
        </AuthProvider>
      </body>
    </html>
  );
}