from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, or_, and_
from typing import Optional
from app.core.database import get_db
from app.core.security import get_current_user, require_editor, require_moderator
from app.models.models import Family, Person, FamilyMember, PersonRelationship, User, ServiceAssignment, GenderEnum, MaritalStatusEnum, RelationshipTypeEnum
from app.api.router.uniqueness import (ensure_person_phone_unique, ensure_family_phone_unique, person_with_phone, family_with_phone, phone_digits)
from app.api.router.audit import create_audit_log
from app.schemas.schemas import (
    FamilyCreate, FamilyUpdate, FamilyResponse, FamilyMemberCreate, FamilyMemberUpdate,
    FamilyMemberResponse, PersonCreate, PersonResponse, DuplicateCheckResult,
    FamilyWizardComplete, ServiceSuggestion, FamilyDuplicateCheck
)
from datetime import datetime, date
import json


def calc_stage(dob: date):
    if not dob:
        return None
    today = date.today()
    age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
    if age <= 3: return "nursery"
    elif age <= 5: return "angels"
    elif age <= 7: return "primary_1_2"
    elif age <= 9: return "primary_3_4"
    elif age <= 11: return "primary_5_6"
    elif age <= 14: return "preparatory"
    elif age <= 17: return "secondary"
    elif age <= 22: return "university"
    else: return "graduate"


def build_person(data: dict, family_id: int, default_marital=None) -> Person:
    """Build a Person row from a wizard person dict, applying all normalization rules."""
    from app.api.router.persons import resolve_school_year, SCHOOL_YEAR_STAGE, apply_confession_church, normalize_service_flags
    data = apply_confession_church(data)
    data = normalize_service_flags(data)
    data.setdefault("school_year", None)
    data.setdefault("education_stage", None)
    data.setdefault("date_of_birth", None)
    dob = data.get("date_of_birth")
    if isinstance(dob, str):
        dob = dob.strip() or None
    if isinstance(dob, str) and dob:
        try:
            dob = date.fromisoformat(dob)
        except ValueError:
            dob = None
    is_deceased = bool(data.get("is_deceased"))
    date_of_death = data.get("date_of_death")
    if isinstance(date_of_death, str):
        date_of_death = date_of_death.strip() or None
    if isinstance(date_of_death, str) and date_of_death:
        try:
            date_of_death = date.fromisoformat(date_of_death)
        except ValueError:
            date_of_death = None
    edu = data.get("education_stage")
    sy = data.get("school_year")
    if sy and sy in SCHOOL_YEAR_STAGE and not edu:
        edu = SCHOOL_YEAR_STAGE[sy]
    if dob and not edu:
        edu = calc_stage(dob)
    if not sy:
        sy = resolve_school_year(None, edu, dob)
    marital = data.get("marital_status") or default_marital
    if isinstance(marital, str):
        marital = marital.strip() or None
    if isinstance(marital, str):
        try:
            marital = MaritalStatusEnum(marital)
        except ValueError:
            marital = None
    return Person(
        family_id=family_id,
        first_name=data.get("first_name"),
        middle_name=data.get("middle_name"),
        last_name=data.get("last_name"),
        gender=GenderEnum(data["gender"]),
        date_of_birth=dob,
        is_deceased=is_deceased,
        date_of_death=date_of_death,
        phone=data.get("phone") if not is_deceased else None,
        whatsapp=data.get("whatsapp"),
        email=data.get("email"),
        national_id=data.get("national_id"),
        marital_status=marital,
        care_situation=data.get("care_situation"),
        education_stage=edu,
        school_year=sy,
        confession_father=data.get("confession_father"),
        confession_church=data.get("confession_church"),
        weekly_leave=data.get("weekly_leave"),
        second_phone=data.get("second_phone"),
        is_deacon=bool(data.get("is_deacon")),
        is_servant=bool(data.get("is_servant")),
        in_prep_center=bool(data.get("in_prep_center")),
        occupation=data.get("occupation"),
        address=data.get("address"),
        notes=data.get("notes"),
        is_active=True
    )


router = APIRouter(prefix="/api/families", tags=["Families"])


# ---------- kinship / membership helpers ----------
RELATIONSHIP_TYPES = {"father", "mother", "son", "daughter", "husband", "wife", "brother", "sister", "grandfather", "grandmother", "grandson", "granddaughter"}


def inverse_relationship(rel: str, other_gender: Optional[str]) -> str:
    """Inverse kinship label from the other person's perspective."""
    male = other_gender == "male"
    return {
        "father": "son" if male else "daughter",
        "mother": "son" if male else "daughter",
        "son": "father" if male else "mother",
        "daughter": "father" if male else "mother",
        "husband": "wife",
        "wife": "husband",
        "brother": "brother" if male else "sister",
        "sister": "brother" if male else "sister",
        "grandfather": "grandson" if male else "granddaughter",
        "grandmother": "grandson" if male else "granddaughter",
        "grandson": "grandfather" if male else "grandmother",
        "granddaughter": "grandfather" if male else "grandmother",
    }.get(rel, rel)


def person_display(p) -> str:
    return f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip()


async def set_current_family(db: AsyncSession, person_id: int):
    """Mirror the newest current membership into Person.family_id (denormalized pointer)."""
    person = (await db.execute(select(Person).where(Person.id == person_id))).scalar_one_or_none()
    if not person:
        return
    latest = (await db.execute(
        select(FamilyMember)
        .where(
            FamilyMember.person_id == person_id,
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
        .order_by(FamilyMember.start_date.desc().nullslast(), FamilyMember.id.desc())
        .limit(1)
    )).scalar_one_or_none()
    person.family_id = latest.family_id if latest else None


async def _add_relationship_if_missing(db: AsyncSession, person_id: int, related_person_id: int, rel_type: str, notes: str = "auto: kinship"):
    """Add a directed kinship row (person -> related) unless an identical current row exists."""
    exists = await db.execute(
        select(PersonRelationship).where(
            PersonRelationship.person_id == person_id,
            PersonRelationship.related_person_id == related_person_id,
            PersonRelationship.relationship_type == rel_type,
            PersonRelationship.is_current == True,  # noqa: E712
            PersonRelationship.is_deleted == False,  # noqa: E712
        )
    )
    if exists.scalar_one_or_none():
        return False
    db.add(PersonRelationship(
        person_id=person_id, related_person_id=related_person_id,
        relationship_type=rel_type, start_date=date.today(),
        is_current=True, is_deleted=False, notes=notes,
    ))
    return True


async def get_family_head_id(db: AsyncSession, family_id: int) -> Optional[int]:
    """Preferred parent of a family: head first, else spouse (mother/wife), else None."""
    head = (await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.is_head == True,  # noqa: E712
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )).scalar_one_or_none()
    if head:
        return head.person_id
    spouse = (await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.is_spouse == True,  # noqa: E712
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )).scalar_one_or_none()
    return spouse.person_id if spouse else None


async def link_new_child_as_grandchild(db: AsyncSession, family_id: int, child_id: int):
    """A child added to a family is auto-linked as a grandchild to the parents of the family's head."""
    parent_id = await get_family_head_id(db, family_id)
    if not parent_id:
        return
    grandparent_rows = (await db.execute(
        select(PersonRelationship).where(
            PersonRelationship.person_id == parent_id,
            PersonRelationship.relationship_type.in_(["father", "mother"]),
            PersonRelationship.is_current == True,  # noqa: E712
            PersonRelationship.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    for gr in grandparent_rows:
        gp = (await db.execute(select(Person).where(Person.id == gr.related_person_id))).scalar_one_or_none()
        if not gp or gp.is_archived:
            continue
        rel = "grandfather" if gr.relationship_type == "father" else "grandmother"
        await _add_relationship_if_missing(db, child_id, gp.id, rel, notes="auto: grandchild link")


async def link_grandparent_to_family(db: AsyncSession, family_id: int, grandparent_id: int, gp_gender: Optional[str]):
    """A grandfather/grandmother is linked as parent of the family head, and all children become grandchildren."""
    parent_id = await get_family_head_id(db, family_id)
    if not parent_id:
        return
    parent_rel = "father" if gp_gender == "male" else "mother"
    await _add_relationship_if_missing(db, parent_id, grandparent_id, parent_rel, notes="auto: grandparent link")
    grand_rel = "grandfather" if gp_gender == "male" else "grandmother"
    child_rows = (await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.is_child == True,  # noqa: E712
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    for c in child_rows:
        if c.person_id != grandparent_id:
            await _add_relationship_if_missing(db, c.person_id, grandparent_id, grand_rel, notes="auto: grandparent link")


async def end_membership(db: AsyncSession, membership: FamilyMember, end_date: Optional[date] = None):
    membership.is_current = False
    membership.end_date = end_date or date.today()
    membership.updated_at = datetime.utcnow()


async def get_person_or_404(db: AsyncSession, person_id: int) -> Person:
    person = (await db.execute(select(Person).where(Person.id == person_id))).scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    return person


async def build_kinship_tree(db: AsyncSession, root_ids: list, max_depth: int = 3) -> dict:
    """Dynamic family-tree VIEW from persons + relationships. No stored tree."""
    from collections import deque
    persons_map: dict = {}
    edges: list = []
    seen_edges: set = set()
    visited: set = set()

    async def get_info(pid: int):
        if pid in persons_map:
            return persons_map[pid]
        p = (await db.execute(select(Person).where(Person.id == pid))).scalar_one_or_none()
        if not p:
            return None
        info = {
            "id": p.id,
            "name": person_display(p),
            "gender": p.gender.value if p.gender else None,
            "date_of_birth": str(p.date_of_birth) if p.date_of_birth else None,
            "is_deceased": bool(p.is_deceased),
        }
        persons_map[pid] = info
        return info

    queue = deque([(rid, 0) for rid in root_ids])
    while queue:
        pid, depth = queue.popleft()
        if pid in visited or depth > max_depth:
            continue
        visited.add(pid)
        info = await get_info(pid)
        if not info or depth == max_depth:
            continue
        out_rows = (await db.execute(
            select(PersonRelationship).where(
                PersonRelationship.person_id == pid,
                PersonRelationship.is_current == True,  # noqa: E712
                PersonRelationship.is_deleted == False,  # noqa: E712
            )
        )).scalars().all()
        for r in out_rows:
            key = (pid, r.related_person_id, r.relationship_type)
            if key not in seen_edges:
                seen_edges.add(key)
                edges.append({"from": pid, "to": r.related_person_id, "label": r.relationship_type})
            queue.append((r.related_person_id, depth + 1))
        in_rows = (await db.execute(
            select(PersonRelationship).where(
                PersonRelationship.related_person_id == pid,
                PersonRelationship.is_current == True,  # noqa: E712
                PersonRelationship.is_deleted == False,  # noqa: E712
            )
        )).scalars().all()
        for r in in_rows:
            other_info = await get_info(r.person_id)
            other_gender = other_info.get("gender") if other_info else None
            label = inverse_relationship(r.relationship_type, other_gender)
            key = (pid, r.person_id, label)
            if key not in seen_edges:
                seen_edges.add(key)
                edges.append({"from": pid, "to": r.person_id, "label": label})
            queue.append((r.person_id, depth + 1))
    return {"root_ids": root_ids, "persons": persons_map, "edges": edges}


@router.get("", response_model=list[FamilyResponse])
async def list_families(
    search: Optional[str] = None,
    has_children: Optional[bool] = None,
    needs_visit: Optional[bool] = None,
    is_active: Optional[bool] = None,
    min_members: Optional[int] = None,
    max_members: Optional[int] = None,
    skip: int = 0,
    limit: int = 50,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    query = select(Family)
    if search:
        query = query.outerjoin(Person, Family.id == Person.family_id).where(
            or_(
                Family.family_name.ilike(f"%{search}%"),
                Family.phone.ilike(f"%{search}%"),
                Person.first_name.ilike(f"%{search}%"),
                Person.last_name.ilike(f"%{search}%")
            )
        ).distinct()
    if is_active is not None:
        query = query.where(Family.is_active == is_active)
    query = query.offset(skip).limit(limit)
    result = await db.execute(query)
    families = result.scalars().all()
    
    family_ids = [f.id for f in families]
    counts_map = {}
    if family_ids:
        counts_query = select(Person.family_id, func.count(Person.id)).where(Person.family_id.in_(family_ids)).group_by(Person.family_id)
        counts_result = await db.execute(counts_query)
        counts_map = {row[0]: row[1] for row in counts_result.all()}

    responses = []
    for f in families:
        resp = FamilyResponse.model_validate(f)
        resp.member_count = counts_map.get(f.id, 0)
        responses.append(resp)
    return responses


@router.post("/check-duplicate", response_model=list[dict])
async def check_family_duplicate(
    data: FamilyDuplicateCheck,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    conditions = []
    reasons = []

    if data.family_name and data.family_name.strip():
        conditions.append(Family.family_name.ilike(f"%{data.family_name.strip()}%"))
        reasons.append("اسم أسرة مطابق")

    if data.phone and data.phone.strip():
        conditions.append(Family.phone == data.phone.strip())
        reasons.append("رقم هاتف مطابق")

    father_cond = None
    if data.father_first_name and data.father_first_name.strip():
        fname = data.father_first_name.strip()
        fnames = [func.lower(func.trim(Person.first_name)).like(func.lower(f"%{fname}%"))]
        if data.father_middle_name and data.father_middle_name.strip():
            mname = data.father_middle_name.strip()
            fnames.append(func.lower(func.trim(Person.middle_name)).like(func.lower(f"%{mname}%")))
        if data.father_last_name and data.father_last_name.strip():
            lname = data.father_last_name.strip()
            fnames.append(func.lower(func.trim(Person.last_name)).like(func.lower(f"%{lname}%")))
        father_cond = and_(*fnames)
        reasons.append("أب بنفس الاسم")

    if not conditions and not father_cond:
        return []

    f_cond = and_(*conditions) if conditions else None
    query = select(Family, Person).outerjoin(Person, Family.id == Person.family_id)
    filt = []
    if f_cond is not None:
        filt.append(f_cond)
    if father_cond is not None:
        filt.append(father_cond)
    query = query.where(or_(*filt), Family.is_active == True).limit(15)

    result = await db.execute(query)
    rows = result.all()

    matches = {}
    for fam, person in rows:
        if fam.id not in matches:
            matches[fam.id] = {
                "family_id": fam.id,
                "family_name": fam.family_name,
                "phone": fam.phone,
                "family_code": fam.family_code,
                "matched_reasons": [],
            }
        matched = False
        if f_cond is not None and (fam.family_name and data.family_name and data.family_name.strip() in fam.family_name):
            if "اسم أسرة مطابق" not in matches[fam.id]["matched_reasons"]:
                matches[fam.id]["matched_reasons"].append("اسم أسرة مطابق")
                matched = True
        if f_cond is not None and data.phone and data.phone.strip() and fam.phone == data.phone.strip():
            if "رقم هاتف مطابق" not in matches[fam.id]["matched_reasons"]:
                matches[fam.id]["matched_reasons"].append("رقم هاتف مطابق")
                matched = True
        if father_cond is not None and person is not None and person.first_name and data.father_first_name and data.father_first_name.strip().lower() in (person.first_name or "").lower():
            if "أب بنفس الاسم" not in matches[fam.id]["matched_reasons"]:
                matches[fam.id]["matched_reasons"].append("أب بنفس الاسم")
                matched = True

    family_ids = list(matches.keys())
    if family_ids:
        counts_query = select(Person.family_id, func.count(Person.id)).where(Person.family_id.in_(family_ids)).group_by(Person.family_id)
        counts_result = await db.execute(counts_query)
        counts_map = {row[0]: row[1] for row in counts_result.all()}
        for fid in family_ids:
            matches[fid]["member_count"] = counts_map.get(fid, 0)

    return list(matches.values())


@router.get("/{family_id}", response_model=FamilyResponse)
async def get_family(family_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Family).where(Family.id == family_id))
    family = result.scalar_one_or_none()
    if not family:
        raise HTTPException(status_code=404, detail="Family not found")
    count_result = await db.execute(select(func.count(Person.id)).where(Person.family_id == family_id))
    resp = FamilyResponse.model_validate(family)
    resp.member_count = count_result.scalar() or 0
    return resp


@router.post("", response_model=FamilyResponse)
async def create_family(family_data: FamilyCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    await ensure_family_phone_unique(db, family_data.phone)
    if not family_data.family_code:
        max_result = await db.execute(select(func.max(Family.id)))
        max_id = max_result.scalar() or 0
        family_code = f"FAM-{str(max_id + 1).zfill(5)}"
    else:
        family_code = family_data.family_code
    family = Family(
        family_code=family_code,
        family_name=family_data.family_name,
        address=family_data.address,
        street=family_data.street,
        branch_from=family_data.branch_from,
        area=family_data.area,
        landmark=family_data.landmark,
        building_number=family_data.building_number,
        floor=family_data.floor,
        apartment_number=family_data.apartment_number,
        gps_location=family_data.gps_location,
        phone=family_data.phone,
        special_case=family_data.special_case,
        notes=family_data.notes
    )
    db.add(family)
    await db.commit()
    await db.refresh(family)
    resp = FamilyResponse.model_validate(family)
    resp.member_count = 0
    return resp


@router.put("/{family_id}", response_model=FamilyResponse)
async def update_family(family_id: int, family_data: FamilyUpdate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    result = await db.execute(select(Family).where(Family.id == family_id))
    family = result.scalar_one_or_none()
    if not family:
        raise HTTPException(status_code=404, detail="Family not found")
    update_data = family_data.model_dump(exclude_unset=True)
    if "phone" in update_data:
        await ensure_family_phone_unique(db, update_data["phone"], exclude_id=family_id)
    for key, value in update_data.items():
        setattr(family, key, value)
    family.updated_at = datetime.utcnow()
    await db.commit()
    await db.refresh(family)
    return FamilyResponse.model_validate(family)


@router.delete("/{family_id}")
async def delete_family(family_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_moderator)):
    result = await db.execute(select(Family).where(Family.id == family_id))
    family = result.scalar_one_or_none()
    if not family:
        raise HTTPException(status_code=404, detail="Family not found")
    family.is_active = False
    await db.commit()
    return {"message": "Family deactivated"}


@router.get("/{family_id}/members", response_model=list[FamilyMemberResponse])
async def get_family_members(family_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(
        select(FamilyMember)
        .where(FamilyMember.family_id == family_id, FamilyMember.is_deleted == False)  # noqa: E712
        .order_by(FamilyMember.is_current.desc(), FamilyMember.id)
    )
    members = result.scalars().all()
    responses = []
    for m in members:
        person_result = await db.execute(select(Person).where(Person.id == m.person_id))
        person = person_result.scalar_one_or_none()
        resp = FamilyMemberResponse.model_validate(m)
        if person:
            resp.person = PersonResponse.model_validate(person)
        responses.append(resp)
    return responses


@router.post("/{family_id}/members", response_model=FamilyMemberResponse)
async def add_family_member(family_id: int, member_data: FamilyMemberCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    family = (await db.execute(select(Family).where(Family.id == family_id))).scalar_one_or_none()
    if not family:
        raise HTTPException(status_code=404, detail="Family not found")
    person = (await db.execute(select(Person).where(Person.id == member_data.person_id))).scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    existing = await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.person_id == member_data.person_id,
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="Person already a current member of this family")
    data = member_data.model_dump()
    data.setdefault("start_date", date.today())
    data["is_current"] = True
    data["is_deleted"] = False
    member = FamilyMember(family_id=family_id, **data)
    db.add(member)
    await set_current_family(db, member_data.person_id)
    await db.flush()
    rel_type = data.get("relationship_type")
    if rel_type in ("son", "daughter"):
        await link_new_child_as_grandchild(db, family_id, member_data.person_id)
    elif rel_type in ("grandfather", "grandmother"):
        gender = person.gender.value if person.gender else None
        await link_grandparent_to_family(db, family_id, member_data.person_id, gender)
    await db.commit()
    await db.refresh(member)
    resp = FamilyMemberResponse.model_validate(member)
    resp.person = PersonResponse.model_validate(person)
    return resp


@router.post("/{family_id}/members/end/{person_id}")
async def end_family_membership(
    family_id: int, person_id: int, end_date: Optional[date] = None,
    db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor),
):
    result = await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.person_id == person_id,
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )
    member = result.scalar_one_or_none()
    if not member:
        raise HTTPException(status_code=404, detail="Current membership not found")
    await end_membership(db, member, end_date)
    await set_current_family(db, person_id)
    await db.commit()
    return {"message": "تم إنهاء العضوية مع الاحتفاظ بالسجل التاريخي"}


@router.delete("/{family_id}/members/{person_id}")
async def remove_family_member(family_id: int, person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    """End membership (kept as history) instead of deleting the row."""
    result = await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.person_id == person_id,
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )
    member = result.scalar_one_or_none()
    if not member:
        raise HTTPException(status_code=404, detail="Member not found")
    if member.is_current:
        await end_membership(db, member)
    await set_current_family(db, person_id)
    await db.commit()
    return {"message": "تم إنهاء العضوية مع الاحتفاظ بالسجل التاريخي"}


@router.put("/{family_id}/members/{person_id}", response_model=FamilyMemberResponse)
async def update_family_member(
    family_id: int, person_id: int, member_data: FamilyMemberUpdate,
    db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor),
):
    result = await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.person_id == person_id,
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )
    member = result.scalar_one_or_none()
    if not member:
        raise HTTPException(status_code=404, detail="Current membership not found")
    data = member_data.model_dump(exclude_unset=True)
    if "relationship_type" in data and data["relationship_type"] is not None:
        new_role = data["relationship_type"]
        member.relationship_type = new_role
        member.is_head = new_role in (RelationshipTypeEnum.father, RelationshipTypeEnum.husband)
        member.is_spouse = new_role in (RelationshipTypeEnum.mother, RelationshipTypeEnum.wife)
        member.is_child = new_role in (RelationshipTypeEnum.son, RelationshipTypeEnum.daughter)
        data.pop("relationship_type", None)
        data.pop("is_head", None)
        data.pop("is_spouse", None)
        data.pop("is_child", None)
    for k, v in data.items():
        if v is not None:
            setattr(member, k, v)
    await db.commit()
    await db.refresh(member)
    person_result = await db.execute(select(Person).where(Person.id == member.person_id))
    person = person_result.scalar_one_or_none()
    resp = FamilyMemberResponse.model_validate(member)
    if person:
        resp.person = PersonResponse.model_validate(person)
    return resp


@router.post("/{family_id}/members/wizard", response_model=dict)
async def family_wizard(family_id: int, wizard_data: FamilyWizardComplete, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    try:
        result = await db.execute(select(Family).where(Family.id == family_id))
        family = result.scalar_one_or_none()
        if not family:
            raise HTTPException(status_code=404, detail="Family not found")
        all_phones = []
        for p in [getattr(wizard_data, "father", None), getattr(wizard_data, "mother", None)]:
            if p:
                all_phones.append(p.phone)
        for c in wizard_data.children:
            all_phones.append(c.get("phone"))
        seen = {}
        for ph in all_phones:
            if not ph:
                continue
            digits = phone_digits(ph)
            if digits:
                if digits in seen:
                    raise HTTPException(status_code=400, detail=f"رقم الهاتف {ph} مكرر أكثر من مرة، لا يمكن تكرار رقم هاتفين")
                seen[digits] = ph
        for p in [p for p in [getattr(wizard_data, "father", None), getattr(wizard_data, "mother", None)] if p]:
            await ensure_person_phone_unique(db, p.phone)
        for c in wizard_data.children:
            await ensure_person_phone_unique(db, c.get("phone"))
        created_persons = []
        assignments_created = []
        if wizard_data.father:
            father = build_person(wizard_data.father.model_dump(), family_id, default_marital="married")
            db.add(father)
            await db.flush()
            member = FamilyMember(family_id=family_id, person_id=father.id, relationship_type="father", is_head=True, start_date=date.today(), is_current=True, is_deleted=False)
            db.add(member)
            created_persons.append({"person_id": father.id, "name": f"{father.first_name} {father.last_name or ''}".strip(), "role": "father"})
            father_svc = wizard_data.father.model_dump().get("suggested_service_id")
            if father_svc:
                db.add(ServiceAssignment(person_id=father.id, service_id=int(father_svc), assigned_by=current_user.id, status="active", notes="تم التوزيع تلقائياً عند إضافة الأسرة"))
                assignments_created.append({"person_id": father.id, "service_id": int(father_svc)})
        if wizard_data.mother:
            mother = build_person(wizard_data.mother.model_dump(), family_id, default_marital="married")
            db.add(mother)
            await db.flush()
            member = FamilyMember(family_id=family_id, person_id=mother.id, relationship_type="mother", is_spouse=True, start_date=date.today(), is_current=True, is_deleted=False)
            db.add(member)
            created_persons.append({"person_id": mother.id, "name": f"{mother.first_name} {mother.last_name or ''}".strip(), "role": "mother"})
            mother_svc = wizard_data.mother.model_dump().get("suggested_service_id")
            if mother_svc:
                db.add(ServiceAssignment(person_id=mother.id, service_id=int(mother_svc), assigned_by=current_user.id, status="active", notes="تم التوزيع تلقائياً عند إضافة الأسرة"))
                assignments_created.append({"person_id": mother.id, "service_id": int(mother_svc)})
        for child_data in wizard_data.children:
            child = build_person(child_data, family_id, default_marital="single")
            db.add(child)
            await db.flush()
            rel_type = "son" if child.gender.value == "male" else "daughter"
            member = FamilyMember(family_id=family_id, person_id=child.id, relationship_type=rel_type, is_child=True, start_date=date.today(), is_current=True, is_deleted=False)
            db.add(member)
            created_persons.append({"person_id": child.id, "name": f"{child.first_name} {child.last_name or ''}".strip(), "role": rel_type})
            child_svc = child_data.get("suggested_service_id")
            if child_svc:
                db.add(ServiceAssignment(person_id=child.id, service_id=int(child_svc), assigned_by=current_user.id, status="active", notes="تم التوزيع تلقائياً عند إضافة الأسرة"))
                assignments_created.append({"person_id": child.id, "service_id": int(child_svc)})
        await db.commit()
        return {"message": "تم إضافة الأعضاء بنجاح", "persons_created": created_persons, "assignments_created": assignments_created}
    except HTTPException:
        await db.rollback()
        raise
    except Exception as e:
        await db.rollback()
        raise HTTPException(status_code=400, detail=f"لم يتم حفظ أي بيانات، برجاء مراجعة المدخلات: {str(e)[:300]}")


@router.post("/create-complete", response_model=dict)
async def create_family_complete(
    wizard_data: FamilyWizardComplete,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_editor)
):
    try:
        all_phones = []
        for p in [p for p in [getattr(wizard_data, "father", None), getattr(wizard_data, "mother", None), getattr(wizard_data, "grandfather", None), getattr(wizard_data, "grandmother", None)] if p]:
            all_phones.append(p.phone)
        for c in wizard_data.children:
            all_phones.append(c.get("phone"))
        seen = {}
        for ph in all_phones:
            if not ph:
                continue
            digits = phone_digits(ph)
            if digits:
                if digits in seen:
                    raise HTTPException(status_code=400, detail=f"رقم الهاتف {ph} مكرر أكثر من مرة، لا يمكن تكرار رقم هاتفين")
                seen[digits] = ph
        await ensure_family_phone_unique(db, wizard_data.family.phone)
        for p in [p for p in [getattr(wizard_data, "father", None), getattr(wizard_data, "mother", None), getattr(wizard_data, "grandfather", None), getattr(wizard_data, "grandmother", None)] if p]:
            await ensure_person_phone_unique(db, p.phone)
        for c in wizard_data.children:
            await ensure_person_phone_unique(db, c.get("phone"))

        count_result = await db.execute(select(func.max(Family.id)))
        count = count_result.scalar() or 0
        curr_num = count + 1
        family_code = f"FAM-{str(curr_num).zfill(5)}"
        while (await db.execute(select(Family.id).where(Family.family_code == family_code))).scalar_one_or_none():
            curr_num += 1
            family_code = f"FAM-{str(curr_num).zfill(5)}"

        provided_family_name = (wizard_data.family.family_name or '').strip()
        if not provided_family_name:
            if wizard_data.father:
                provided_family_name = f"{wizard_data.father.first_name} {wizard_data.father.middle_name or ''}".strip()
            elif wizard_data.mother:
                provided_family_name = f"{wizard_data.mother.first_name} {wizard_data.mother.middle_name or ''}".strip()
            else:
                provided_family_name = "أسرة بدون اسم"

        family = Family(
            family_code=family_code,
            family_name=provided_family_name[:250],
            phone=wizard_data.family.phone,
            address=wizard_data.family.address,
            street=wizard_data.family.street,
            branch_from=wizard_data.family.branch_from,
            area=wizard_data.family.area,
            landmark=wizard_data.family.landmark,
            building_number=wizard_data.family.building_number,
            floor=wizard_data.family.floor,
            apartment_number=wizard_data.family.apartment_number,
            gps_location=wizard_data.family.gps_location,
            special_case=wizard_data.family.special_case,
            notes=wizard_data.family.notes,
            is_active=True
        )
        db.add(family)
        await db.flush()

        created_persons = []
        assignments_created = []

        def build_person(data: dict, family_id: int, default_marital=None) -> Person:
            from app.api.router.persons import resolve_school_year, SCHOOL_YEAR_STAGE, apply_confession_church, normalize_service_flags
            data = apply_confession_church(data)
            data = normalize_service_flags(data)
            data.setdefault("school_year", None)
            data.setdefault("education_stage", None)
            data.setdefault("date_of_birth", None)
            dob = data.get("date_of_birth")
            if isinstance(dob, str):
                dob = dob.strip() or None
            if isinstance(dob, str) and dob:
                try:
                    dob = date.fromisoformat(dob)
                except ValueError:
                    dob = None
            is_deceased = bool(data.get("is_deceased"))
            date_of_death = data.get("date_of_death")
            if isinstance(date_of_death, str):
                date_of_death = date_of_death.strip() or None
            if isinstance(date_of_death, str) and date_of_death:
                try:
                    date_of_death = date.fromisoformat(date_of_death)
                except ValueError:
                    date_of_death = None
            edu = data.get("education_stage")
            from app.api.router.persons import resolve_school_year, SCHOOL_YEAR_STAGE
            sy = data.get("school_year")
            if sy and sy in SCHOOL_YEAR_STAGE and not edu:
                edu = SCHOOL_YEAR_STAGE[sy]
            if dob and not edu:
                edu = calc_stage(dob)
            if not sy:
                sy = resolve_school_year(None, edu, dob)
            marital = data.get("marital_status") or default_marital
            if isinstance(marital, str):
                marital = marital.strip() or None
            if isinstance(marital, str):
                try:
                    marital = MaritalStatusEnum(marital)
                except ValueError:
                    marital = None
            return Person(
                family_id=family_id,
                first_name=data.get("first_name"),
                middle_name=data.get("middle_name"),
                last_name=data.get("last_name"),
                gender=GenderEnum(data["gender"]),
                date_of_birth=dob,
                is_deceased=is_deceased,
                date_of_death=date_of_death,
                phone=data.get("phone") if not is_deceased else None,
                whatsapp=data.get("whatsapp"),
                email=data.get("email"),
                national_id=data.get("national_id"),
                marital_status=marital,
                care_situation=data.get("care_situation"),
                education_stage=edu,
                school_year=sy,
                confession_father=data.get("confession_father"),
                confession_church=data.get("confession_church"),
                weekly_leave=data.get("weekly_leave"),
                second_phone=data.get("second_phone"),
                is_deacon=bool(data.get("is_deacon")),
                is_servant=bool(data.get("is_servant")),
                in_prep_center=bool(data.get("in_prep_center")),
                occupation=data.get("occupation"),
                address=data.get("address"),
                notes=data.get("notes"),
                is_active=True
            )

        if wizard_data.father:
            father = build_person(wizard_data.father.model_dump(), family.id, default_marital="married")
            db.add(father)
            await db.flush()
            db.add(FamilyMember(family_id=family.id, person_id=father.id, relationship_type=RelationshipTypeEnum.father, is_head=True, start_date=date.today(), is_current=True, is_deleted=False))
            created_persons.append({"person_id": father.id, "name": f"{father.first_name} {father.last_name or ''}".strip(), "role": "father"})
            father_svc = wizard_data.father.model_dump().get("suggested_service_id")
            if father_svc:
                db.add(ServiceAssignment(
                    person_id=father.id,
                    service_id=int(father_svc),
                    assigned_by=current_user.id,
                    status="active",
                    notes="تم التوزيع تلقائياً عند إضافة الأسرة"
                ))
                assignments_created.append({"person_id": father.id, "service_id": int(father_svc)})

        if wizard_data.mother:
            mother = build_person(wizard_data.mother.model_dump(), family.id, default_marital="married")
            db.add(mother)
            await db.flush()
            db.add(FamilyMember(family_id=family.id, person_id=mother.id, relationship_type=RelationshipTypeEnum.mother, is_spouse=True, start_date=date.today(), is_current=True, is_deleted=False))
            created_persons.append({"person_id": mother.id, "name": f"{mother.first_name} {mother.last_name or ''}".strip(), "role": "mother"})
            mother_svc = wizard_data.mother.model_dump().get("suggested_service_id")
            if mother_svc:
                db.add(ServiceAssignment(
                    person_id=mother.id,
                    service_id=int(mother_svc),
                    assigned_by=current_user.id,
                    status="active",
                    notes="تم التوزيع تلقائياً عند إضافة الأسرة"
                ))
                assignments_created.append({"person_id": mother.id, "service_id": int(mother_svc)})

# --- grandparents (optional) ---
        grandfather = None
        grandmother = None
        if wizard_data.grandfather:
            grandfather = build_person(wizard_data.grandfather.model_dump(), family.id, default_marital="widowed")
            db.add(grandfather)
            await db.flush()
            db.add(FamilyMember(family_id=family.id, person_id=grandfather.id, relationship_type=RelationshipTypeEnum.grandfather, is_head=False, is_spouse=False, is_child=False, start_date=date.today(), is_current=True, is_deleted=False))
            created_persons.append({"person_id": grandfather.id, "name": f"{grandfather.first_name} {grandfather.last_name or ''}".strip(), "role": "grandfather"})
            grandfather_svc = wizard_data.grandfather.model_dump().get("suggested_service_id")
            if grandfather_svc:
                db.add(ServiceAssignment(
                    person_id=grandfather.id, service_id=int(grandfather_svc),
                    assigned_by=current_user.id, status="active", notes="تم التوزيع تلقائياً عند إضافة الأسرة"
                ))
                assignments_created.append({"person_id": grandfather.id, "service_id": int(grandfather_svc)})
        if wizard_data.grandmother:
            grandmother = build_person(wizard_data.grandmother.model_dump(), family.id, default_marital="widowed")
            db.add(grandmother)
            await db.flush()
            db.add(FamilyMember(family_id=family.id, person_id=grandmother.id, relationship_type=RelationshipTypeEnum.grandmother, is_head=False, is_spouse=False, is_child=False, start_date=date.today(), is_current=True, is_deleted=False))
            created_persons.append({"person_id": grandmother.id, "name": f"{grandmother.first_name} {grandmother.last_name or ''}".strip(), "role": "grandmother"})
            grandmother_svc = wizard_data.grandmother.model_dump().get("suggested_service_id")
            if grandmother_svc:
                db.add(ServiceAssignment(
                    person_id=grandmother.id, service_id=int(grandmother_svc),
                    assigned_by=current_user.id, status="active", notes="تم التوزيع تلقائياً عند إضافة الأسرة"
                ))
                assignments_created.append({"person_id": grandmother.id, "service_id": int(grandmother_svc)})

        for child_data in wizard_data.children:
            child = build_person(child_data, family.id, default_marital="single")
            db.add(child)
            await db.flush()
            rel_type = RelationshipTypeEnum.son if child.gender.value == "male" else RelationshipTypeEnum.daughter
            db.add(FamilyMember(family_id=family.id, person_id=child.id, relationship_type=rel_type, is_child=True, start_date=date.today(), is_current=True, is_deleted=False))
            created_persons.append({"person_id": child.id, "name": f"{child.first_name} {child.last_name or ''}".strip(), "role": rel_type.value})
            suggested_service_id = child_data.get("suggested_service_id")
            if suggested_service_id:
                db.add(ServiceAssignment(
                    person_id=child.id,
                    service_id=int(suggested_service_id),
                    assigned_by=current_user.id,
                    status="active",
                    notes="تم التوزيع تلقائياً عند إضافة الأسرة"
                ))
                assignments_created.append({"person_id": child.id, "service_id": int(suggested_service_id)})

        # --- auto kinship links (single directed rows) ---
        father_id = father.id if wizard_data.father else None
        mother_id = mother.id if wizard_data.mother else None
        if father_id and mother_id:
            db.add(PersonRelationship(
                person_id=father_id, related_person_id=mother_id,
                relationship_type="husband", start_date=date.today(),
                is_current=True, is_deleted=False, notes="auto: family creation",
            ))
        # grandchildren-aware: every child gets father/mother rows + grandparent rows
        gp_rows = []
        if grandfather:
            gp_rows.append((grandfather.id, "grandfather", "father"))
        if grandmother:
            gp_rows.append((grandmother.id, "grandmother", "mother"))
        if father_id or mother_id:
            child_ids = [p["person_id"] for p in created_persons if p["role"] in ("son", "daughter")]
            for cid in child_ids:
                if father_id:
                    db.add(PersonRelationship(
                        person_id=cid, related_person_id=father_id,
                        relationship_type="father", start_date=date.today(),
                        is_current=True, is_deleted=False, notes="auto: family creation",
                    ))
                if mother_id:
                    db.add(PersonRelationship(
                        person_id=cid, related_person_id=mother_id,
                        relationship_type="mother", start_date=date.today(),
                        is_current=True, is_deleted=False, notes="auto: family creation",
                    ))
                for gp_id, gp_rel, parent_link in gp_rows:
                    db.add(PersonRelationship(
                        person_id=cid, related_person_id=gp_id,
                        relationship_type=gp_rel, start_date=date.today(),
                        is_current=True, is_deleted=False, notes="auto: grandparent link",
                    ))
        # link grandparents as parents of father/mother (primary parent = father, else mother)
        if gp_rows:
            primary = father_id or mother_id
            if primary:
                for gp_id, gp_rel, parent_link in gp_rows:
                    db.add(PersonRelationship(
                        person_id=primary, related_person_id=gp_id,
                        relationship_type=parent_link, start_date=date.today(),
                        is_current=True, is_deleted=False, notes="auto: grandparent link",
                    ))

        await db.commit()
        return {
            "message": "تم إنشاء الأسرة بنجاح",
            "family_id": family.id,
            "family_code": family.family_code,
            "persons_created": created_persons,
            "assignments_created": assignments_created
        }
    except Exception as e:
        await db.rollback()
        raise HTTPException(status_code=400, detail=f"لم يتم حفظ أي بيانات، برجاء مراجعة المدخلات: {str(e)[:300]}")


@router.get("/{family_id}/tree")
async def get_family_tree(family_id: int, depth: int = 3, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    family = (await db.execute(select(Family).where(Family.id == family_id))).scalar_one_or_none()
    if not family:
        raise HTTPException(status_code=404, detail="Family not found")
    member_rows = (await db.execute(
        select(FamilyMember).where(
            FamilyMember.family_id == family_id,
            FamilyMember.is_current == True,  # noqa: E712
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    root_ids = [m.person_id for m in member_rows]
    tree = await build_kinship_tree(db, root_ids, max_depth=max(1, min(depth, 5)))
    tree["family_id"] = family_id
    tree["family_name"] = family.family_name
    return tree
