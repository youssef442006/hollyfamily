# -*- coding: utf-8 -*-
import asyncio, sys, os
sys.stdout.reconfigure(encoding='utf-8')
from sqlalchemy import text
from app.core.database import engine

PIDS = (35, 36, 37)
FAM = 24

async def main():
    async with engine.connect() as conn:
        print("== جداول فيها FK إلى persons (فحص الأشخاص 35-37) ==")
        tables = {
            "family_members": "person_id",
            "person_relationships": "person_id",
            "person_relationships_rev": "related_person_id",
            "users": "person_id",
            "confessions": "person_id",
            "visits": "person_id",
            "service_members": "person_id",
            "service_assignments": "person_id",
            "servant_assignments": "person_id",
            "servant_assignments_sv": "servant_id",
            "priests": "person_id",
            "audit_logs": "record_id",
        }
        for tbl, col in tables.items():
            try:
                r = await conn.execute(text(f"SELECT COUNT(*) FROM {tbl} WHERE {col} IN (35,36,37)"))
                print(f"   {tbl:26} ({col}) → {r.scalar()}")
            except Exception as e:
                print(f"   {tbl:26} ({col}) → خطأ: {str(e)[:70]}")

        print("\n== تعداد عائلة 24 + ما يتبعها ==")
        c = await conn.execute(text("SELECT id, family_name, is_active FROM families WHERE id=24"))
        print("   family 24:", c.fetchone())
        c = await conn.execute(text("SELECT id, person_id, relationship_type, is_head FROM family_members WHERE family_id=24"))
        print("   أعضاء family_members لـ24:")
        for row in c.fetchall():
            print("     ", row)
        c = await conn.execute(text("SELECT id, person_id, family_id FROM visits WHERE family_id=24"))
        print("   زيارة عائلة 24:", c.fetchall())
        c = await conn.execute(text("SELECT id, family_id FROM families WHERE family_code LIKE 'TEST%'"))
        print("   أي عائلات TEST أخرى:", c.fetchall())

    await engine.dispose()

asyncio.run(main())
