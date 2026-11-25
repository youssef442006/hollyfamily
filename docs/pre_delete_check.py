# -*- coding: utf-8 -*-
"""فحص مراجع قبل حذف الأشخاص 35-37 والعائلة 24 — asyncpg مباشر بدون ORM."""
import asyncio, sys, os
sys.stdout.reconfigure(encoding='utf-8')
import asyncpg

DSN = "postgresql://postgres:postgres@localhost:5432/church_management"

async def main():
    conn = await asyncpg.connect(DSN)
    try:
        # 1) الأشخاص التجريبية
        rows = await conn.fetch("SELECT id, first_name, last_name, family_id, is_archived FROM persons WHERE id IN (35,36,37) ORDER BY id")
        print("== الأشخاص 35-37 ==")
        for r in rows:
            print(f"   id={r['id']} | {r['first_name']} {r['last_name'] or ''} | family_id={r['family_id']} | archived={r['is_archived']}")

        # 2) كل FK بeger persons / families في الـ schema الحقيقي
        fks = await conn.fetch("""
            SELECT tc.table_name AS tbl, kcu.column_name AS col,
                   ccu.table_name AS ref_tbl, ccu.column_name AS ref_col
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND ccu.table_name IN ('persons','families')
              AND tc.table_schema='public'
            ORDER BY tc.table_name
        """)
        print("\n== كل FKs إلى persons/families (الحقيقة من schema) ==")
        seen = set()
        for r in fks:
            key = (r['tbl'], r['col'])
            if key in seen: continue
            seen.add(key)
            print(f"   {r['tbl']}.{r['col']} → {r['ref_tbl']}.{r['ref_col']}")

        # 3) عدد السجلات المرتبطة فعلاً بـ 35-37 أو بالعائلة 24
        print("\n== عدد السجلات المرتبطة فعلاً (persons 35-37) ==")
        refs = {
            'family_members': 'person_id',
            'person_relationships': 'person_id',
            'users': 'person_id',
            'confessions': 'person_id',
            'visits': 'person_id',
            'visits_srv': 'servant_id',
            'service_members': 'person_id',
            'service_assignments': 'person_id',
            'servant_assignments': 'person_id',
            'priests': 'person_id',
        }
        for tbl, col in refs.items():
            if tbl == 'visits_srv': tbl = 'visits'; col = 'servant_id'
            try:
                n = await conn.fetchval(f"SELECT COUNT(*) FROM {tbl} WHERE {col} IN (35,36,37)")
                if n: print(f"   ⚠ {tbl}.{col} = {n}")
            except Exception as e:
                print(f"   ؟ {tbl}.{col}: {str(e)[:60]}")

        print("\n== عدد السجلات المرتبطة بالعائلة 24 ==")
        for tbl in ('family_members', 'visits'):
            try:
                n = await conn.fetchval(f"SELECT COUNT(*) FROM {tbl} WHERE family_id=24")
                if n: print(f"   ⚠ {tbl}.family_id = {n}")
            except Exception as e:
                print(f"   ؟ {tbl}: {str(e)[:60]}")

        # 4) العلاقات الثنائية
        n = await conn.fetchval("SELECT COUNT(*) FROM person_relationships WHERE person_id IN (35,36,37) OR related_person_id IN (35,36,37)")
        print(f"\n   person_relationships (في أي اتجاه) = {n}")

        # 5) العائلة ذاتها + أعضاءها
        fam = await conn.fetchrow("SELECT * FROM families WHERE id=24")
        print("\n== عائلة 24 ==")
        if fam:
            print(f"   {dict(fam)}"[:200])
        else:
            print("   غير موجودة (محذوفة مسبقًا؟)")
    finally:
        await conn.close()

asyncio.run(main())
