# -*- coding: utf-8 -*-
"""مسح child-first نهائي للبيانات التجريبية (persons 35-37 · family 24) داخل ترانزاكشن واحد.
   ﭑلحكم الأساسي الذي يقضي على كل أخطاء الماضي: 
   · كل DELETE عبر  conn.execute()  الذي يرجع نصًّا "DELETE n" → نستخرج n بأمان (لا fetchval نهائيًا)
   · كل SELECT عبر conn.fetchval()  العادي (SELECT يعيد أرقامًا).
   البنية من pg_catalog نفسه (مصدر الحقيقة — بلا قائمة افتراضية نائبة).
   asyncpg خالص بلا ORM، يُشغَّل من داخل backend/ :  python scripts\\delete_test_data.py"""
import asyncio, sys, os
sys.stdout.reconfigure(encoding="utf-8")
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import asyncpg
from app.core.config import settings

DSN = settings.DATABASE_URL.replace("+asyncpg", "")
PIDS = tuple([35, 36, 37])
FID = 24

def rowcount(tag):
    """conn.execute يعيد مثل 'DELETE 3' — نستخرج العدد بأمان من آخر كلمة."""
    try:
        return int(tag.split()[-1])
    except Exception:
        return 0

async def main():
    conn = await asyncpg.connect(DSN)
    try:
        # ============ أ) فحص البنية الفعلية (كل FK إلى persons/families) ============
        print("== أ) البنية الفعلية: FK من pg_catalog ==")
        fk_rows = await conn.fetch("""
            SELECT tc.table_name AS tbl,
                   kcu.column_name AS col,
                   ccu.table_name AS ref_tbl
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = 'public'
              AND ccu.table_name IN ('persons', 'families')
            ORDER BY tc.table_name, kcu.column_name
        """)

        # مراجع فعلية مرتبطة بصفاتنا
        counts = {}
        for r in fk_rows:
            tbl, col = r["tbl"], r["col"]
            if tbl in ("persons", "families"):
                continue
            try:
                if col == "family_id":
                    n = await conn.fetchval(
                        f"SELECT COUNT(*) FROM {tbl} WHERE {col} = $1", FID)
                else:
                    n = await conn.fetchval(
                        f"SELECT COUNT(*) FROM {tbl} WHERE {col} = ANY($1::int[])", PIDS)
            except Exception:
                continue
            if n:
                counts[f"{tbl}.{col}"] = n
                print(f"   ⚠ {tbl:24}.{col:<16} = {n}")

        # users المرتبطون بالأشخاص التجريبيين
        n = await conn.fetchval(
            "SELECT COUNT(*) FROM users WHERE person_id = ANY($1::int[])", PIDS)
        if n:
            counts["users.person_id"] = n
            print(f"   ⚠ users.person_id = {n}")

        fam = await conn.fetchval(
            "SELECT family_name FROM families WHERE id = $1", FID)
        members = await conn.fetchval(
            "SELECT COUNT(*) FROM family_members WHERE family_id = $1", FID)
        print(f"   family 24 [{fam}] · أعضاء family_members = {members}")

        if not any(counts.values()) and members == 0:
            print("   لا توجد مراجع باقية — لا شيء للحذف ✓ (نظيفة أصلًا)")
            return

        # ============ ب) الحذف داخل ترانزاكشن واحد (child-first) ============
        print("\n== ب) الحذف داخل ترانزاكشن واحد ==")
        deleted = 0
        async with conn.transaction():
            # نكرر حذف الأبناء حتى لا يبقى شيء (child-first تلقائي من pg_catalog)
            for _ in range(8):
                made = False
                for r in fk_rows:
                    tbl, col = r["tbl"], r["col"]
                    if tbl in ("persons", "families"):
                        continue
                    try:
                        if col == "family_id":
                            n = rowcount(await conn.execute(
                                f"DELETE FROM {tbl} WHERE {col} = $1", FID))
                        else:
                            n = rowcount(await conn.execute(
                                f"DELETE FROM {tbl} WHERE {col} = ANY($1::int[])", PIDS))
                    except Exception:
                        continue
                    if n:
                        made = True
                        deleted += n
                        print(f"   ✓ {tbl:24}.{col:<16} → حذف {n}")
                if not made:
                    break

            # users المرتبطون ثم الأشخاص ثم العائلة
            n = rowcount(await conn.execute(
                "DELETE FROM users WHERE person_id = ANY($1::int[])", PIDS))
            deleted += n
            print(f"   ✓ users.person_id = حذف {n}")
            n = rowcount(await conn.execute(
                "DELETE FROM persons WHERE id = ANY($1::int[])", PIDS))
            deleted += n
            print(f"   ✓ persons = حذف {n}")
            n = rowcount(await conn.execute(
                "DELETE FROM families WHERE id = $1", FID))
            deleted += n
            print(f"   ✓ families = حذف {n}")

        # ============ ج) التحقق النهائي ============
        print("\n== ج) التحقق النهائي (الكل يجب أن يكون صفرًا) ==")
        ok = True
        n = await conn.fetchval(
            "SELECT COUNT(*) FROM persons WHERE id = ANY($1::int[])", PIDS)
        print(f"   persons 35-37 = {n}"); ok = ok and n == 0
        n = await conn.fetchval(
            "SELECT COUNT(*) FROM families WHERE id = $1", FID)
        print(f"   families 24   = {n}"); ok = ok and n == 0
        n = await conn.fetchval(
            "SELECT COUNT(*) FROM users WHERE person_id = ANY($1::int[])", PIDS)
        print(f"   users.person_id = {n}"); ok = ok and n == 0
        for r in fk_rows:
            tbl, col = r["tbl"], r["col"]
            if tbl in ("persons", "families"):
                continue
            try:
                if col == "family_id":
                    n = await conn.fetchval(
                        f"SELECT COUNT(*) FROM {tbl} WHERE {col} = $1", FID)
                else:
                    n = await conn.fetchval(
                        f"SELECT COUNT(*) FROM {tbl} WHERE {col} = ANY($1::int[])", PIDS)
            except Exception:
                continue
            if n:
                print(f"   ⚠ {tbl}.{col} = {n}"); ok = False
        print("\nالنتيجة:",
              "✓ تم التنظيف الكامل — الصفر في كل مكان، البيانات التجريبية اختفت نهائيًا"
              if ok else "⚠ يوجد باقٍ — راجع الجداول أعلاه")
        print(f"إجمالي الصفوف المحذوفة = {deleted}")
    finally:
        await conn.close()

asyncio.run(main())
