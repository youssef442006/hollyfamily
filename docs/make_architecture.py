# -*- coding: utf-8 -*-
"""يولّد مخطط معماري SVG بالعربية (RTL) لنظام كنيستي — مبنى من الكود الفعلي."""
import os, math

W, H = 1480, 1520
BG = "#fafaf6"
INK = "#1f2937"

def esc(s):
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")

def t(x, y, s, size=13, fill=INK, anchor="middle", weight="400", family="'Segoe UI', Tahoma"):
    return f'<text x="{x}" y="{y}" font-size="{size}" fill="{fill}" text-anchor="{anchor}" font-weight="{weight}" font-family="{family}">{esc(s)}</text>'

def r(x, y, w, h, fill, stroke="#94a3b8", sw=1, rx=10):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}" rx="{rx}"/>'

def a(x1, y1, x2, y2, color="#16a34a", dashed=False, label=None, lx=None, ly=None, w=2.2):
    dx, dy = x2 - x1, y2 - y1
    L = math.hypot(dx, dy)
    if L == 0:
        return ""
    ang = math.atan2(dy, dx)
    s = f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{color}" stroke-width="{w}" fill="none"' + (' stroke-dasharray="7,5"' if dashed else '') + '/>'
    hl, hwid = 11, 5
    px, py = x2 - math.cos(ang) * hl, y2 - math.sin(ang) * hl
    l1x = px - hwid * math.sin(ang); l1y = py + hwid * math.cos(ang)
    l2x = px + hwid * math.sin(ang); l2y = py - hwid * math.cos(ang)
    s += f'<polygon points="{x2},{y2} {l1x},{l1y} {l2x},{l2y}" fill="{color}"/>'
    if label:
        mx, my = (x1 + x2) / 2 - 60, (y1 + y2) / 2
        s += t(lx if lx is not None else W // 2, ly if ly is not None else my - 8, label, 12.5, "#334155", "middle", "700")
    return s

out = []
out.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" style="background:{BG}">')
out.append(r(0, 0, W, H, BG))
out.append(t(W // 2, 42, "كنيستي — مخطط البنية والتسلسل (من الكود الفعلي للمشروع)", 28, INK, "middle", "800"))
out.append(t(W // 2, 68, "React (Vite) → FastAPI (async) → SQLAlchemy → PostgreSQL — كل الأسهم والتفاصيل مأخوذة من الملفات الحقيقية", 14.5, "#475569"))

# ============================================================
# 1) طبقة الواجهة Frontend
# ============================================================
FY = 100; FH = 210
out.append(r(40, FY, W - 80, FH, "#e0f2fe", "#0284c7", 1.6))
out.append(t(W // 2, FY + 28, "الواجهة الأمامية — React SPA (Vite · المنفذ 5173)", 20, "#0c4a6e", "middle", "800"))
out.append(t(W // 2, FY + 56, "src/app/** : dashboard · persons · persons/[id]/tree · persons/[id]/family-history · families · families/[id]/tree · families/new", 13, "#075985"))
out.append(t(W // 2, FY + 80, "services · services/[id] · confessions · visits · reports · search · priests · audit · settings · duplicates", 13, "#075985"))
out.append(t(W // 2, FY + 106, "src/lib/api.ts → fetch  (BaseURL /api) — يحمل JWT في رأس Authorization أو Cookie access_token (httpOnly)", 14, "#0369a1", "middle", "700"))
out.append(t(W // 2, FY + 134, "AuthContext (login/logout/me) · AuthLayout · Sidebar · FamilyTree · OccupationInput — عربي/إنجليزي (RTL)", 12.5, "#0c4a6e"))

# ============================================================
# 2) طبقة API (FastAPI)
# ============================================================
AY = FY + FH + 40; AH = 540
out.append(r(40, AY, W - 80, AH, "#fef3c7", "#f59e0b", 1.6))
out.append(t(W // 2, AY + 26, "الخادم الخلفي — FastAPI (uvicorn · المنفذ 8000 · async SQLAlchemy)", 20, "#78350f", "middle", "800"))

# الحماية في الأعلى
out.append(t(W // 2, AY + 56, "طبقة الحماية (middleware + dependencies) — تُحقن في كل مسار:", 14.5, "#92400e", "middle", "700"))
sec = [
    "Rate Limiter على login — 5 محاولات فاشلة خلال 5 دقائق = قفل 5 دقائق → 429",
    "create_access_token → JWT (sub=user.id, role) · من SECRET_KEY المرفوض إذا لم يتغير (أمان!)",
    "get_current_user ← Cookie access_token أو Bearer → يحل user + is_active",
    "require_admin_role (admin فقط) · require_editor_role/editor (admin+priest+editor)",
    "require_servant_crew (خدام/خدامات) · require_moderator (حذف)",
    "AuditLog — تسجيل كل تغيير (user_id, action, table_name, old/new_value) للـ admin فقط",
]
sy = AY + 76
for s_ in sec:
    out.append(t(W // 2, sy, "▸ " + s_, 13.5, "#78350f"))
    sy += 30

# الروترز
out.append(r(W // 2 - 480, sy + 8, 960, 210, "#f1f5f9", "#94a3b8", 1))
out.append(t(W // 2, sy + 30, "الـ routers — كل group له بادئة /api وفحص دور:", 15, "#334155", "middle", "800"))
routes = [
    ("auth",        "/api/auth",         "login · logout · register · me · me/password · me/users  (rate-limited login)"),
    ("persons",     "/api/persons",      "CRUD · tree · check-duplicate · merge · promote-stages · family-history · create-family"),
    ("families",    "/api/families",     "CRUD · wizard (إنشاء أسرة+أفراد كاملة) · members · tree · check-duplicate · create-complete"),
    ("confessions", "/api/confessions",  "CRUD · complete اعتراف · مرتبطة بـ priests"),
    ("visits",      "/api/visits",       "CRUD زيارة · ربط family/person/servant · حالة زيارة (home/phone/follow-up)"),
    ("services",    "/api/services",     "CRUD الأنشطة · الأعضاء · خدمة القدامة · auto-assign"),
    ("priests",     "/api/priests",      "الكهنوت (priests)"),
    ("reports",     "/api/reports",      "age-distribution · families · services · neglected · without-service · special-cases · export CSV"),
    ("persons/duplicates", "/api/persons/duplicates", "كشف المكرر (بحسب national_id/phone/الاسم)"),
    ("search",      "/api/search",       "بحث موحد"),
    ("dashboard",   "/api/dashboard",    "إحصائيات الرئيسية"),
    ("audit",       "/api/audit",        "سجل التدقيق (admin only)"),
    ("settings",    "/api/settings",     "إعدادات النظام + import/export") ]
sy2 = sy + 52
for nm, pref, desc in routes:
    out.append(t(W // 2 - 460, sy2, "• " + nm, 13, "#0f172a", "start", "800"))
    out.append(t(W // 2 + 150, sy2, pref, 12.5, "#0369a1", "start"))
    out.append(t(W // 2 - 300, sy2 + 19, desc, 12, "#475569", "start"))
    sy2 += 38

# ============================================================
# 3) طبقة قاعدة البيانات
# ============================================================
DY = AY + AH + 40; DH = 260
out.append(r(40, DY, W - 80, DH, "#dcfce7", "#16a34a", 1.6))
out.append(t(W // 2, DY + 26, "قاعدة البيانات — PostgreSQL (async · SQLAlchemy ORM · على هذا الجهاز، مع نسخة SQLite للاختبار)", 19, "#14532d", "middle", "800"))

# الأعمدة
cols = [
    ("persons",      "id · family_id · الأسماء · gender · dob · الهاتف · العنوان · marrage/افتقار · is_deceased · is_archived · دراسات/مهنة"),
    ("families",     "id · family_code (فريد) · family_name · العنوان/الحي/المعلم · phone · special_case · is_active"),
    ("family_members","family_id · person_id · relationship_type (رئيس/زوجة/ابن) · is_head/spouse/child · is_current · تاريخ البداية/النهاية"),
    ("person_relationships", "person_id ↔ related_person_id · relationship_type (أب/أم/ابن/زوجة...) · is_current · start/end_date"),
    ("users",        "id · username · hashed_password · role (admin/priest/editor/servant) · is_active · person_id"),
    ("services",     "id · name · المدة العمرية · gender_restriction · is_active → ServiceMember (person)"),
    ("service_assignments","person_id · service_id · status · ⏺ عبر ServiceAssignment/ServantAssignment"),
    ("confessions",  "id · person_id · priest_id · confession_date · next_date · status"),
    ("visits",       "id · family_id/person_id · servant_id · visit_date · type · status · next_visit"),
    ("priests",      "id · person_id · display_name · phone"),
    ("audit_logs",   "id · user_id · action · table_name · record_id · old/new_value · timestamp"),
    ("settings",     "key · value · description") ]
cx = 60; cy = DY + 52; cw = (W - 120 - 5 * 16) / 6
row = 0
for nm, desc in cols:
    col = row % 3
    row += 1
    out.append(r(cx + col * (cw + 16), cy + (row // 3) * 78, cw, 62, "#ffffff", "#16a34a", 1))
    out.append(t(cx + col * (cw + 16) + cw // 2, cy + (row // 3) * 78 + 20, nm, 13.5, "#14532d", "middle", "800"))
    out.append(t(cx + col * (cw + 16) + cw // 2, cy + (row // 3) * 78 + 48, desc[:44] + ("…" if len(desc) > 44 else ""), 11, "#334155"))

# ============================================================
# الأسهم الرئيسية
# ============================================================
out.append(a(W // 2, FY + FH, W // 2, AY, "#0284c7", False, "طلبات HTTP: POST /api/auth/login → ثم GET/POST /api/**  مع JWT", W // 2, (FY + FH + AY) // 2 + 10))
out.append(a(W // 2, AY + AH, W // 2, DY, "#16a34a", False, "استعلامات SQL عبر AsyncSession (get_db · asyncpg)", W // 2, (AY + AH + DY) // 2 + 10))
out.append(t(W // 2, H - 40, "المخطط مولّد آليًا من الشيفرة المصدرية الفعلية — D:\\كنيستي (backend + frontend + PostgreSQL · SQLite للاختبار)", 12.5, "#94a3b8"))

out.append("</svg>")
svg = "\n".join(out)
os.makedirs(r"D:\كنيستي\docs", exist_ok=True)
p = r"D:\كنيستي\docs\architecture-flow.svg"
open(p, "w", encoding="utf-8").write(svg)
print("تم توليد المخطط:", p)
print("الحجم:", len(svg), "بايت")
print("مسارات SVG:", svg.count("<rect"), "مستطيلات |", svg.count("<text"), "نصوص")
