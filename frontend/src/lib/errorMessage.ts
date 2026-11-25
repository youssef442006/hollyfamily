export function errorMessage(err: any, fallback = 'حدث خطأ'): string {
  const detail = err?.response?.data?.detail;
  if (typeof detail === 'string') return detail;
  if (Array.isArray(detail)) {
    const msgs = detail
      .map((e: any) => {
        if (!e) return '';
        if (typeof e === 'string') return e;
        const loc = Array.isArray(e.loc) ? e.loc.filter((x: any) => x !== 'body').join(' → ') : '';
        const msg = e.msg || e.message || '';
        return loc ? `${loc}: ${msg}` : msg;
      })
      .filter(Boolean);
    return msgs.length ? msgs.join('\n') : fallback;
  }
  if (typeof err?.response?.data?.message === 'string') return err.response.data.message;
  if (typeof err?.message === 'string') return err.message;
  return fallback;
}