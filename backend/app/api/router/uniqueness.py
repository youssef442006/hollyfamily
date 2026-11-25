from fastapi import HTTPException
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.models import Person, Family


def phone_digits(phone) -> str:
    return "".join(ch for ch in (phone or "").strip() if ch.isdigit())


async def person_with_phone(db: AsyncSession, phone, exclude_id: int | None = None):
    d = phone_digits(phone)
    if not d:
        return None
    suffix = d[-10:] if len(d) >= 10 else d
    query = select(Person).where(
        Person.is_active == True,
        Person.phone.isnot(None),
        Person.phone != "",
        Person.phone.contains(suffix)
    )
    if exclude_id is not None:
        query = query.where(Person.id != exclude_id)
    res = await db.execute(query)
    for p in res.scalars().all():
        if phone_digits(p.phone) == d:
            return p
    return None


async def family_with_phone(db: AsyncSession, phone, exclude_id: int | None = None):
    d = phone_digits(phone)
    if not d:
        return None
    suffix = d[-10:] if len(d) >= 10 else d
    query = select(Family).where(
        Family.is_active == True,
        Family.phone.isnot(None),
        Family.phone != "",
        Family.phone.contains(suffix)
    )
    if exclude_id is not None:
        query = query.where(Family.id != exclude_id)
    res = await db.execute(query)
    for f in res.scalars().all():
        if phone_digits(f.phone) == d:
            return f
    return None


def person_conflict_msg(p: Person, phone) -> HTTPException:
    name = f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip()
    return HTTPException(status_code=400, detail=f"رقم الهاتف {phone} مسجل بالفعل على الشخص: {name}")


def family_conflict_msg(f: Family, phone) -> HTTPException:
    return HTTPException(status_code=400, detail=f"رقم الهاتف {phone} مسجل بالفعل على الأسرة: {f.family_name} ({f.family_code})")


async def ensure_person_phone_unique(db: AsyncSession, phone, exclude_id: int | None = None):
    if not phone_digits(phone):
        return
    p = await person_with_phone(db, phone, exclude_id)
    if p:
        raise person_conflict_msg(p, phone)


async def ensure_family_phone_unique(db: AsyncSession, phone, exclude_id: int | None = None):
    if not phone_digits(phone):
        return
    f = await family_with_phone(db, phone, exclude_id)
    if f:
        raise family_conflict_msg(f, phone)