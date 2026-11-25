# -*- coding: utf-8 -*-
"""فحص مسبق للحذف: أي سجلات تجريبية (persons 35-37 · family 24) مرتبطة بأي جداول أخرى قبل المسح."""
import asyncio, sys
sys.stdout.reconfigure(encoding='utf-8')
sys.path.insert(0, r"D:\كنيستي\backend")
from sqlalchemy import text
from app.core.database import engine, AsyncSessionLocal

IDS = (35, 36, 37)
FAM = 24

async def main():
    async with AsyncSessionLocal() as db:
        print("== 1) الأشخاص التجريبية 35-37 أنفسهم ==")
        r = await db.execute(text("SELECT id, first_name, last_name, family_id FROM persons WHERE id IN :ids"),
                             {"ids": IDS})
        for x in r.fetchall():
            print("   ", tuple(x))

        print("\n== 2) مراجع من جداول أخرى إلى هؤلاء الأشخاص ==")
        refs = [
            ("family_members",       "person_id"),
            ("person_relationships", "person_id"),
            ("person_relationships", "related_person_id"),
            ("users",                "person_id"),
            ("confessions",          "person_id"),
            ("visits",               "person_id"),
            ("visits",               "servant_id"),
            ("service_members",      "person_id"),
            ("service_assignments",  "person_id"),
            ("servant_assignments",  "person_id"),
            ("servant_assignments",  "servant_id"),
            ("priests",              "person_id"),
            ("audit_logs",           "record_id"),
        ]
        any_ref = False
        for tbl, col in refs:
            try:
                r = await db.execute(text(f"SELECT COUNT(*) FROM {tbl} WHERE {col} IN :ids"), {"ids": IDS})
                n = r.scalar_one()
                if n:
                    any_ref = True
                    print(f"   ⚠ {tbl}.{col} = {n} سجل")
            except Exception as e:
                print(f"   ؟ {tbl}.{col}  (بحث: {str(e)[:60]})")

        print("\n== 3) عائلة 24 نفسها + أعضاؤها ==")
        r = await db.execute(text("SELECT id, family_name, family_code, is_active FROM families WHERE id=:f"), {"f": FAM})
        fam = r.fetchone()
        print("   الأسرة:", tuple(fam) if fam else "غير موجودة")
        r = await db.execute(text("SELECT id, family_id, person_id, relationship_type FROM family_members WHERE family_id=:f"), {"f": FAM})
        mbrs = r.fetchall()
        print("   أعضاء: ", len(mbrs))
        for x in mbrs:
            print("      ", tuple(x))

        print("\n== 4) مراجع من جداول أخرى إلى العائلة 24 ==")
        famrefs = [
            ("family_members",  "family_id"),
            ("visits",          "family_id"),
            ("audit_logs",      "record_id"),
        ]
        for tbl, col in famrefs:
            try:
                r = await db.execute(text(f"SELECT COUNT(*) FROM {tbl} WHERE {col}=:f"), {"f": FAM})
                n = r.scalar_one()
                if n:
                    print(f"   ⚠ {tbl}.{col} = {n}")
            except Exception as e:
                print(f"   ؟ {tbl}.{col}  ({str(e)[:60]})")

        print("\n== 5) أي فهرس/قيود فريدة تخص family_code للعائلة ==")
        r = await db.execute(text("SELECT family_code FROM families WHERE id=:f"), {"f": FAM})
        code = r.scalar_one_or_none()
        if code:
            r = await db.execute(text("SELECT id, family_name FROM families WHERE family_code=:c"), {"c": code})
            others = r.fetchall()
            print("   family_code =", code, "| أصحابها:", others)

        print("\n== الخلاصة ==")
        print("   if not any_ref: يمكن مسح الأشخاص بأمان (لا توجد أي مراجع)")
        print("   ثم مسح أعضاء العائلة ثم العائلة نفسها." if not any_ref else "   يوجد مراجع — يجب مسحها أولاً أو منع المسح.")

    await engine.dispose()

asyncio.run(main())
