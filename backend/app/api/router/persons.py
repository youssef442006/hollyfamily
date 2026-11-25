from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, or_, and_
from typing import Optional
from datetime import date, datetime, timedelta
from app.core.database import get_db
from app.core.security import get_current_user, require_editor, require_moderator
from app.models.models import Person, User, PersonRelationship, Family, FamilyMember, RelationshipTypeEnum
from app.schemas.schemas import PersonCreate, PersonUpdate, PersonResponse, DuplicateCheckResult, CreateFamilyFromPerson, PersonRelationshipCreate, PersonRelationshipUpdate, PersonRelationshipResponse
from app.api.router.families import (
    set_current_family, end_membership, get_person_or_404,
    inverse_relationship, person_display, build_kinship_tree, RELATIONSHIP_TYPES,
)
import re
from app.api.router.uniqueness import ensure_person_phone_unique, ensure_family_phone_unique, person_with_phone, family_with_phone, phone_digits

router = APIRouter(prefix="/api/persons", tags=["Persons"])


def normalize_arabic(text: str | None) -> str:
    """Normalize Arabic text (strip diacritics, unify alef variations, yaa, taa marbuta)."""
    if not text:
        return ""
    t = text.strip()
    # Remove tashkeel / diacritics
    t = re.sub(r'[\u064B-\u065F\u0670]', '', t)
    # Unify Alef variations: [إأآا] -> ا
    t = re.sub(r'[إأآا]', 'ا', t)
    # Unify Taa Marbuta: ة -> ه
    t = re.sub(r'ة', 'ه', t)
    # Unify Yaa / Alef Maksura: ى -> ي
    t = re.sub(r'ى', 'ي', t)
    return t.lower()


def calculate_education_stage(dob: date) -> Optional[str]:
    if not dob:
        return None
    today = date.today()
    age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
    if age <= 3:
        return "nursery"
    elif age <= 5:
        return "angels"
    elif age <= 7:
        return "primary_1_2"
    elif age <= 9:
        return "primary_3_4"
    elif age <= 11:
        return "primary_5_6"
    elif age <= 14:
        return "preparatory"
    elif age <= 17:
        return "secondary"
    elif age <= 22:
        return "university"
    else:
        return "graduate"


SCHOOL_YEAR_STAGE = {
    "nursery": "nursery",
    "angels": "angels",
    "grade_1": "primary_1_2",
    "grade_2": "primary_1_2",
    "grade_3": "primary_3_4",
    "grade_4": "primary_3_4",
    "grade_5": "primary_5_6",
    "grade_6": "primary_5_6",
    "prep_1": "preparatory",
    "prep_2": "preparatory",
    "prep_3": "preparatory",
    "sec_1": "secondary",
    "sec_2": "secondary",
    "sec_3": "secondary",
    "uni_1": "university",
    "uni_2": "university",
    "uni_3": "university",
    "uni_4": "university",
    "uni_5": "university",
    "uni_6": "university",
    "uni_7": "university",
    "university": "university",
    "graduate": "graduate",
}

SCHOOL_YEAR_ORDER = [
    "nursery", "angels",
    "grade_1", "grade_2", "grade_3", "grade_4", "grade_5", "grade_6",
    "prep_1", "prep_2", "prep_3",
    "sec_1", "sec_2", "sec_3",
    "uni_1", "uni_2", "uni_3", "uni_4", "uni_5", "uni_6", "uni_7",
]

STAGE_DEFAULT_YEAR = {
    "primary_1_2": "grade_1",
    "primary_3_4": "grade_3",
    "primary_5_6": "grade_5",
    "preparatory": "prep_1",
    "secondary": "sec_1",
    "university": "uni_1",
}

STAGE_VERSION = {
    "primary_1_2": {6: "grade_1", 7: "grade_2"},
    "primary_3_4": {8: "grade_3", 9: "grade_4"},
    "primary_5_6": {10: "grade_5", 11: "grade_6"},
    "preparatory": {12: "prep_1", 13: "prep_2", 14: "prep_3"},
    "secondary": {15: "sec_1", 16: "sec_2", 17: "sec_3"},
    "university": {18: "uni_1", 19: "uni_2", 20: "uni_3", 21: "uni_4", 22: "uni_5"},
}

STAGE_SERVICE_NAME = {
    "nursery": "حضانة",
    "angels": "ملايكة",
    "primary_1_2": "أولى وثانية ابتدائي",
    "primary_3_4": "ثالثة ورابعة ابتدائي",
    "primary_5_6": "خامسة وسادسة ابتدائي",
    "preparatory": "إعدادي",
    "secondary": "ثانوي",
    "university": "جامعيين",
    "graduate": "خريجين",
}


def person_age(dob: Optional[date]) -> Optional[int]:
    if not dob:
        return None
    today = date.today()
    return today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))


HOLY_FAMILY_CHURCH = "كنيسة العائلة المقدسة"
CONFESSION_FATHERS_HOLY_FAMILY = {"ابونا سيرافيم", "ابونا جوزيف"}


def apply_confession_church(data: dict) -> dict:
    """Auto-set confession church to Holy Family Church when the
    confession father is one of the known fathers."""
    father = (data.get("confession_father") or "").strip()
    church = (data.get("confession_church") or "").strip()
    if father in CONFESSION_FATHERS_HOLY_FAMILY:
        data["confession_church"] = HOLY_FAMILY_CHURCH
    elif not church:
        data["confession_church"] = None
    return data


def normalize_service_flags(data: dict) -> dict:
    """Females cannot be deacons; servant and prep-center are mutually exclusive."""
    gender = data.get("gender")
    if gender in ("female", "أنثى") or str(gender).lower() == "female":
        data["is_deacon"] = False
    if data.get("is_servant") and data.get("in_prep_center"):
        data["in_prep_center"] = False
    return data


def resolve_school_year(school_year: Optional[str], stage: Optional[str], dob: Optional[date]) -> Optional[str]:
    if school_year and school_year in SCHOOL_YEAR_STAGE:
        return school_year
    if not stage:
        return None
    if stage == "nursery":
        return "nursery"
    if stage == "angels":
        return "angels"
    if stage not in STAGE_VERSION:
        return None
    age = person_age(dob)
    if age and age in STAGE_VERSION[stage]:
        return STAGE_VERSION[stage][age]
    return STAGE_DEFAULT_YEAR.get(stage)


def next_school_year(year: str) -> Optional[str]:
    if year not in SCHOOL_YEAR_ORDER:
        return None
    idx = SCHOOL_YEAR_ORDER.index(year)
    if idx >= len(SCHOOL_YEAR_ORDER) - 1:
        return None
    return SCHOOL_YEAR_ORDER[idx + 1]


@router.get("", response_model=list[PersonResponse])
async def list_persons(
    search: Optional[str] = None,
    family_id: Optional[int] = None,
    gender: Optional[str] = None,
    education_stage: Optional[str] = None,
    is_active: Optional[bool] = True,
    skip: int = 0,
    limit: int = 50,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    query = select(Person)
    if search:
        query = query.where(
            or_(
                Person.first_name.ilike(f"%{search}%"),
                Person.middle_name.ilike(f"%{search}%"),
                Person.last_name.ilike(f"%{search}%"),
                Person.phone.ilike(f"%{search}%"),
                Person.whatsapp.ilike(f"%{search}%"),
                Person.national_id.ilike(f"%{search}%")
            )
        )
    if family_id:
        query = query.where(Person.family_id == family_id)
    if gender:
        query = query.where(Person.gender == gender)
    if education_stage:
        query = query.where(Person.education_stage == education_stage)
    if is_active is not None:
        query = query.where(Person.is_active == is_active)
    query = query.offset(skip).limit(limit)
    result = await db.execute(query)
    persons = result.scalars().all()
    responses = []
    for p in persons:
        resp = PersonResponse.model_validate(p)
        resp.full_name_display = f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip()
        responses.append(resp)
    return responses


@router.get("/duplicates")
async def list_duplicates(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    phone_expr = func.trim(Person.phone)
    phone_dupes_res = await db.execute(
        select(phone_expr)
        .where(Person.is_active == True, Person.phone != None, Person.phone != "")
        .group_by(phone_expr)
        .having(func.count(Person.id) > 1)
    )
    duplicate_phones = [row[0] for row in phone_dupes_res.all()]

    fname_expr = func.lower(func.trim(Person.first_name))
    lname_expr = func.lower(func.trim(Person.last_name))
    name_dupes_res = await db.execute(
        select(fname_expr, lname_expr)
        .where(Person.is_active == True, Person.first_name != None, Person.last_name != None)
        .group_by(fname_expr, lname_expr)
        .having(func.count(Person.id) > 1)
    )
    duplicate_names = [(row[0], row[1]) for row in name_dupes_res.all()]

    if not duplicate_phones and not duplicate_names:
        return []

    filters = []
    if duplicate_phones:
        filters.append(phone_expr.in_(duplicate_phones))
    
    for fn, ln in duplicate_names:
        filters.append(and_(fname_expr == fn, lname_expr == ln))

    persons_res = await db.execute(
        select(Person).where(Person.is_active == True, or_(*filters))
    )
    persons = persons_res.scalars().all()

    name_groups: dict = {}
    phone_groups: dict = {}
    for p in persons:
        if p.first_name and p.last_name:
            key = f"{p.first_name.strip().lower()} {p.last_name.strip().lower()}"
            name_groups.setdefault(key, []).append(p)
        if p.phone and p.phone.strip():
            phone_groups.setdefault(p.phone.strip(), []).append(p)

    groups = []
    seen_ids = set()
    for key, group in name_groups.items():
        if len(group) < 2:
            continue
        group_ids = tuple(sorted(p.id for p in group))
        if group_ids in seen_ids:
            continue
        seen_ids.add(group_ids)
        groups.append({
            "type": "name",
            "label": group[0].first_name + " " + (group[0].last_name or ""),
            "person_ids": [p.id for p in group],
            "persons": [
                {
                    "id": p.id,
                    "full_name_display": f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip(),
                    "phone": p.phone,
                    "date_of_birth": str(p.date_of_birth) if p.date_of_birth else None,
                    "family_id": p.family_id,
                    "education_stage": p.education_stage.value if p.education_stage else None,
                }
                for p in group
            ],
        })
    for key, group in phone_groups.items():
        if len(group) < 2:
            continue
        group_ids = tuple(sorted(p.id for p in group))
        if group_ids in seen_ids:
            continue
        seen_ids.add(group_ids)
        groups.append({
            "type": "phone",
            "label": key,
            "person_ids": [p.id for p in group],
            "persons": [
                {
                    "id": p.id,
                    "full_name_display": f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip(),
                    "phone": p.phone,
                    "date_of_birth": str(p.date_of_birth) if p.date_of_birth else None,
                    "family_id": p.family_id,
                    "education_stage": p.education_stage.value if p.education_stage else None,
                }
                for p in group
            ],
        })
    return groups


@router.get("/{person_id}", response_model=PersonResponse)
async def get_person(person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Person).where(Person.id == person_id))
    person = result.scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    resp = PersonResponse.model_validate(person)
    resp.full_name_display = f"{person.first_name} {person.middle_name or ''} {person.last_name or ''}".strip()
    return resp


@router.post("", response_model=PersonResponse)
async def create_person(person_data: PersonCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    person_data = apply_confession_church(person_data.model_dump())
    person_data = normalize_service_flags(person_data)
    person_data = PersonCreate(**person_data)
    await ensure_person_phone_unique(db, person_data.phone)
    if person_data.national_id:
        nid = str(person_data.national_id).strip()
        if nid:
            existing_nid = (await db.execute(
                select(Person).where(Person.national_id == nid, Person.is_active == True)
            )).scalar_one_or_none()
            if existing_nid:
                raise HTTPException(status_code=400, detail=f"الرقم القومي {nid} مسجل بالفعل على شخص آخر: {existing_nid.first_name} {existing_nid.last_name or ''}")
    if person_data.school_year and person_data.school_year in SCHOOL_YEAR_STAGE and not person_data.education_stage:
        auto_stage = SCHOOL_YEAR_STAGE[person_data.school_year]
    elif person_data.date_of_birth and not person_data.education_stage:
        auto_stage = calculate_education_stage(person_data.date_of_birth)
    else:
        auto_stage = person_data.education_stage
    person = Person(
        family_id=person_data.family_id,
        first_name=person_data.first_name,
        middle_name=person_data.middle_name,
        last_name=person_data.last_name,
        gender=person_data.gender,
        date_of_birth=person_data.date_of_birth,
        is_deceased=person_data.is_deceased,
        date_of_death=person_data.date_of_death,
        phone=person_data.phone,
        whatsapp=person_data.whatsapp,
        email=person_data.email,
        national_id=person_data.national_id,
        marital_status=person_data.marital_status,
        care_situation=person_data.care_situation,
        education_stage=auto_stage,
        school_year=person_data.school_year or resolve_school_year(person_data.school_year, auto_stage, person_data.date_of_birth),
        confession_father=person_data.confession_father,
        confession_church=person_data.confession_church,
        weekly_leave=person_data.weekly_leave,
        second_phone=person_data.second_phone,
        is_deacon=person_data.is_deacon,
        is_servant=person_data.is_servant,
        in_prep_center=person_data.in_prep_center,
        occupation=person_data.occupation,
        address=person_data.address,
        notes=person_data.notes
    )
    db.add(person)
    await db.commit()
    await db.refresh(person)
    resp = PersonResponse.model_validate(person)
    resp.full_name_display = f"{person.first_name} {person.middle_name or ''} {person.last_name or ''}".strip()
    return resp


@router.put("/{person_id}", response_model=PersonResponse)
async def update_person(person_id: int, person_data: PersonUpdate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    result = await db.execute(select(Person).where(Person.id == person_id))
    person = result.scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    update_data = person_data.model_dump(exclude_unset=True)
    merged = dict(update_data)
    if "confession_father" in update_data or "confession_church" in update_data:
        merged.setdefault("confession_father", person.confession_father)
        apply_confession_church(merged)
        if "confession_church" in merged:
            update_data["confession_church"] = merged["confession_church"]
    if "gender" in merged or "is_deacon" in merged or "is_servant" in merged or "in_prep_center" in merged:
        base = dict(update_data)
        base.setdefault("gender", person.gender)
        base.setdefault("is_deacon", person.is_deacon)
        base.setdefault("is_servant", person.is_servant)
        base.setdefault("in_prep_center", person.in_prep_center)
        normalize_service_flags(base)
        for k in ("gender", "is_deacon", "is_servant", "in_prep_center"):
            if k in update_data or True:
                if k in ("is_deacon", "is_servant", "in_prep_center"):
                    update_data[k] = base[k]
    if "date_of_birth" in update_data and update_data["date_of_birth"] and "education_stage" not in update_data and "school_year" not in update_data:
        auto_stage = calculate_education_stage(update_data["date_of_birth"])
        if auto_stage:
            update_data["education_stage"] = auto_stage
    if "school_year" in update_data and update_data["school_year"] and update_data["school_year"] in SCHOOL_YEAR_STAGE and "education_stage" not in update_data:
        update_data["education_stage"] = SCHOOL_YEAR_STAGE[update_data["school_year"]]
    if "education_stage" in update_data and (not update_data.get("school_year")):
        stage = update_data["education_stage"]
        update_data["school_year"] = resolve_school_year(None, stage if isinstance(stage, str) else (stage.value if stage else None), update_data.get("date_of_birth"))
    if "phone" in update_data:
        await ensure_person_phone_unique(db, update_data["phone"], exclude_id=person_id)
    if "national_id" in update_data and update_data["national_id"]:
        nid = str(update_data["national_id"]).strip()
        existing_nid = (await db.execute(
            select(Person).where(Person.national_id == nid, Person.id != person_id, Person.is_active == True)
        )).scalar_one_or_none()
        if existing_nid:
            raise HTTPException(status_code=400, detail=f"الرقم القومي {nid} مسجل بالفعل على شخص آخر: {existing_nid.first_name} {existing_nid.last_name or ''}")
    for key, value in update_data.items():
        setattr(person, key, value)
    person.updated_at = datetime.utcnow()
    await db.commit()
    await db.refresh(person)
    resp = PersonResponse.model_validate(person)
    resp.full_name_display = f"{person.first_name} {person.middle_name or ''} {person.last_name or ''}".strip()
    return resp


@router.delete("/{person_id}")
async def delete_person(person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_moderator)):
    result = await db.execute(select(Person).where(Person.id == person_id))
    person = result.scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    person.is_active = False
    await db.commit()
    return {"message": "Person deactivated"}


@router.post("/promote-stages")
async def promote_stages(db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    from app.models.models import Service, ServiceAssignment
    from datetime import datetime as dt

    all_persons = (await db.execute(
        select(Person).where(Person.is_active == True, Person.is_deceased == False)  # noqa: E712
    )).scalars().all()
    services = (await db.execute(select(Service).where(Service.is_active == True))).scalars().all()
    service_by_name = {}
    for s in services:
        key = (s.name or "").strip().lower()
        service_by_name[key] = s

    promoted = []
    moved = []
    unchanged = []
    for p in all_persons:
        current_year = resolve_school_year(p.school_year, p.education_stage.value if p.education_stage else None, p.date_of_birth)
        if not current_year or current_year == "university" or current_year == "graduate":
            unchanged.append(p.id)
            continue
        new_year = next_school_year(current_year)
        if not new_year:
            unchanged.append(p.id)
            continue
        new_stage = SCHOOL_YEAR_STAGE.get(new_year)
        old_stage = p.education_stage.value if p.education_stage else None
        p.school_year = new_year
        if new_stage:
            p.education_stage = new_stage
        p.updated_at = dt.utcnow()
        promoted.append({
            "person_id": p.id,
            "name": f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip(),
            "from": current_year,
            "to": new_year,
        })
        if new_stage and new_stage != old_stage:
            target_name = STAGE_SERVICE_NAME.get(new_stage)
            if target_name:
                target = service_by_name.get(target_name.strip().lower())
                if target:
                    active = (await db.execute(
                        select(ServiceAssignment).where(
                            ServiceAssignment.person_id == p.id,
                            ServiceAssignment.status == "active",
                        )
                    )).scalars().all()
                    if not any(a.service_id == target.id for a in active):
                        for a in active:
                            a.status = "cancelled"
                        await db.flush()
                        db.add(ServiceAssignment(
                            person_id=p.id, service_id=target.id,
                            assigned_by=current_user.id, assigned_at=dt.utcnow(),
                            status="active",
                            notes="نقل تلقائي في النيروز",
                        ))
                        moved.append({
                            "person_id": p.id,
                            "name": f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip(),
                            "to_service": target.name,
                        })

    await db.commit()
    return {
        "message": "تم نقل المراحل بنجاح",
        "promoted": len(promoted),
        "moved": len(moved),
        "unchanged": len(unchanged),
        "promoted_list": promoted,
        "moved_list": moved,
    }


@router.post("/check-duplicate", response_model=DuplicateCheckResult)
async def check_duplicate(person_data: PersonCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    conditions = []
    if person_data.national_id:
        conditions.append(Person.national_id == person_data.national_id)
    if person_data.phone:
        d = phone_digits(person_data.phone)
        if d:
            suffix = d[-10:] if len(d) >= 10 else d
            conditions.append(Person.phone.contains(suffix))
    if person_data.first_name and person_data.last_name:
        fn = person_data.first_name.strip()
        ln = (person_data.last_name or "").strip()
        conditions.append(and_(
            Person.first_name.ilike(fn),
            Person.last_name.ilike(ln)
        ))
        # Arabic normalized variations match (skip first letter if Alef to catch hamza differences)
        core_fn = fn[1:] if len(fn) > 2 and fn[0] in 'أإآا' else fn
        core_ln = ln[1:] if len(ln) > 2 and ln[0] in 'أإآا' else ln
        if core_fn != fn or core_ln != ln:
            conditions.append(and_(
                Person.first_name.ilike(f"%{core_fn}%"),
                Person.last_name.ilike(f"%{core_ln}%")
            ))
    if not conditions:
        return DuplicateCheckResult(is_duplicate=False, potential_matches=[], confidence=0.0)
    query = select(Person).where(and_(or_(*conditions), Person.is_active == True)).limit(15)
    result = await db.execute(query)
    matches = result.scalars().all()
    responses = []
    seen_ids = set()
    norm_fn = normalize_arabic(person_data.first_name)
    norm_ln = normalize_arabic(person_data.last_name)
    for m in matches:
        if m.id in seen_ids:
            continue
        seen_ids.add(m.id)
        # Check if match is genuine
        m_fn = normalize_arabic(m.first_name)
        m_ln = normalize_arabic(m.last_name)
        is_name_match = bool(norm_fn and norm_ln and m_fn == norm_fn and m_ln == norm_ln)
        is_phone_match = bool(person_data.phone and m.phone and phone_digits(m.phone) == phone_digits(person_data.phone))
        is_nid_match = bool(person_data.national_id and m.national_id == person_data.national_id)
        if is_name_match or is_phone_match or is_nid_match or not (norm_fn and norm_ln):
            resp = PersonResponse.model_validate(m)
            resp.full_name_display = f"{m.first_name} {m.middle_name or ''} {m.last_name or ''}".strip()
            responses.append(resp)
    confidence = min(len(responses) * 0.35, 1.0) if responses else 0.0
    return DuplicateCheckResult(is_duplicate=len(responses) > 0, potential_matches=responses, confidence=confidence)


@router.post("/check-phone")
async def check_phone_live(data: dict, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    phone = (data or {}).get("phone") or ""
    owner = await person_with_phone(db, phone)
    fam = await family_with_phone(db, phone) if not owner else None
    if owner:
        return {
            "exists": True,
            "owner_type": "person",
            "owner_id": owner.id,
            "owner_name": f"{owner.first_name} {owner.middle_name or ''} {owner.last_name or ''}".strip(),
        }
    if fam:
        return {
            "exists": True,
            "owner_type": "family",
            "owner_id": fam.id,
            "owner_name": f"{fam.family_name}",
        }
    return {"exists": False}


@router.get("/{person_id}/profile")
async def get_person_profile(person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Person).where(Person.id == person_id))
    person = result.scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    from app.models.models import FamilyMember, ServiceAssignment, Visit, Confession
    fm_result = await db.execute(
        select(FamilyMember).where(
            FamilyMember.person_id == person_id,
            FamilyMember.is_deleted == False,  # noqa: E712
        )
        .order_by(FamilyMember.is_current.desc(), FamilyMember.id)
    )
    family_memberships = fm_result.scalars().all()
    memberships_out = []
    for fm in family_memberships:
        fam = (await db.execute(select(Family).where(Family.id == fm.family_id))).scalar_one_or_none()
        memberships_out.append({
            "id": fm.id,
            "family_id": fm.family_id,
            "family_name": fam.family_name if fam else None,
            "relationship_type": fm.relationship_type,
            "is_head": fm.is_head,
            "is_current": fm.is_current,
            "start_date": str(fm.start_date) if fm.start_date else None,
            "end_date": str(fm.end_date) if fm.end_date else None,
        })
    sa_result = await db.execute(select(ServiceAssignment).where(ServiceAssignment.person_id == person_id))
    service_assignments = sa_result.scalars().all()
    v_result = await db.execute(select(Visit).where(Visit.person_id == person_id).order_by(Visit.visit_date.desc()).limit(10))
    visits = v_result.scalars().all()
    c_result = await db.execute(select(Confession).where(Confession.person_id == person_id).order_by(Confession.confession_date.desc()).limit(10))
    confessions = c_result.scalars().all()
    from app.models.models import PersonRelationship as _PR
    rel_out = (await db.execute(
        select(_PR).where(
            _PR.person_id == person_id,
            _PR.is_current == True,  # noqa: E712
            _PR.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    rel_in = (await db.execute(
        select(_PR).where(
            _PR.related_person_id == person_id,
            _PR.is_current == True,  # noqa: E712
            _PR.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    viewer_gender = person.gender.value if person.gender else None
    relationships = []
    for r in rel_out:
        other = (await db.execute(select(Person).where(Person.id == r.related_person_id))).scalar_one_or_none()
        relationships.append(serialize_relationship(r, other, True, viewer_gender))
    for r in rel_in:
        other = (await db.execute(select(Person).where(Person.id == r.person_id))).scalar_one_or_none()
        relationships.append(serialize_relationship(r, other, False, viewer_gender))
    return {
        "person": PersonResponse.model_validate(person),
        "current_family": {"family_id": person.family_id} if person.family_id else None,
        "family_memberships": memberships_out,
        "relationships": relationships,
        "service_assignments": [{"id": sa.id, "service_id": sa.service_id, "status": sa.status, "assigned_at": sa.assigned_at} for sa in service_assignments],
        "visits": [{"id": v.id, "visit_date": v.visit_date, "visit_type": v.visit_type, "status": v.status} for v in visits],
        "confessions": [{"id": c.id, "confession_date": c.confession_date, "status": c.status} for c in confessions]
    }


def serialize_relationship(db_rel, other, outgoing: bool, viewer_gender: Optional[str]) -> dict:
    if outgoing:
        label = db_rel.relationship_type
    else:
        other_gender = other.gender.value if (other is not None and other.gender) else viewer_gender
        label = inverse_relationship(db_rel.relationship_type, other_gender)
    return {
        "id": db_rel.id,
        "person_id": db_rel.person_id,
        "related_person_id": db_rel.related_person_id,
        "relationship_type": db_rel.relationship_type,
        "label": label,
        "other_person_id": other.id if other else (db_rel.related_person_id if outgoing else db_rel.person_id),
        "other_person_name": person_display(other) if other else None,
        "direction": "outgoing" if outgoing else "incoming",
        "start_date": str(db_rel.start_date) if db_rel.start_date else None,
        "end_date": str(db_rel.end_date) if db_rel.end_date else None,
        "is_current": db_rel.is_current,
        "notes": db_rel.notes,
    }


@router.get("/{person_id}/relationships")
async def list_relationships(person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    person = await get_person_or_404(db, person_id)
    out_rows = (await db.execute(
        select(PersonRelationship).where(
            PersonRelationship.person_id == person_id,
            PersonRelationship.is_current == True,  # noqa: E712
            PersonRelationship.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    in_rows = (await db.execute(
        select(PersonRelationship).where(
            PersonRelationship.related_person_id == person_id,
            PersonRelationship.is_current == True,  # noqa: E712
            PersonRelationship.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    result = []
    for r in out_rows:
        other = (await db.execute(select(Person).where(Person.id == r.related_person_id))).scalar_one_or_none()
        result.append(serialize_relationship(r, other, True, person.gender.value if person.gender else None))
    for r in in_rows:
        other = (await db.execute(select(Person).where(Person.id == r.person_id))).scalar_one_or_none()
        result.append(serialize_relationship(r, other, False, person.gender.value if person.gender else None))
    return result


@router.post("/{person_id}/relationships", response_model=PersonRelationshipResponse)
async def create_relationship(person_id: int, rel_data: PersonRelationshipCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    person = await get_person_or_404(db, person_id)
    if rel_data.relationship_type not in RELATIONSHIP_TYPES:
        raise HTTPException(status_code=400, detail="نوع صلة غير صالح")
    if rel_data.related_person_id == person_id:
        raise HTTPException(status_code=400, detail="لا يمكن ربط الشخص بنفسه")
    related = (await db.execute(select(Person).where(Person.id == rel_data.related_person_id))).scalar_one_or_none()
    if not related:
        raise HTTPException(status_code=404, detail="Related person not found")
    if related.is_archived:
        raise HTTPException(status_code=400, detail="لا يمكن الربط بسجل مؤرشف")
    existing = (await db.execute(
        select(PersonRelationship).where(
            PersonRelationship.person_id == person_id,
            PersonRelationship.related_person_id == rel_data.related_person_id,
            PersonRelationship.relationship_type == rel_data.relationship_type,
            PersonRelationship.is_current == True,  # noqa: E712
            PersonRelationship.is_deleted == False,  # noqa: E712
        )
    )).scalar_one_or_none()
    if existing:
        raise HTTPException(status_code=400, detail="هذه الصلة موجودة بالفعل")
    if rel_data.start_date and rel_data.end_date and rel_data.end_date < rel_data.start_date:
        raise HTTPException(status_code=400, detail="تاريخ النهاية قبل البداية")
    rel = PersonRelationship(person_id=person_id, **rel_data.model_dump(), is_deleted=False)
    db.add(rel)
    await db.commit()
    await db.refresh(rel)
    # explicit construction avoids async lazy-load of relationships
    return PersonRelationshipResponse(
        id=rel.id, person_id=rel.person_id, related_person_id=rel.related_person_id,
        relationship_type=rel.relationship_type, start_date=rel.start_date, end_date=rel.end_date,
        is_current=rel.is_current, notes=rel.notes, is_deleted=rel.is_deleted,
        created_at=rel.created_at, updated_at=rel.updated_at,
        related_person_name=person_display(related),
    )


@router.delete("/{person_id}/relationships/{rel_id}")
async def delete_relationship(person_id: int, rel_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    rel = (await db.execute(select(PersonRelationship).where(PersonRelationship.id == rel_id))).scalar_one_or_none()
    if not rel or rel.is_deleted:
        raise HTTPException(status_code=404, detail="Relationship not found")
    if rel.person_id != person_id and rel.related_person_id != person_id:
        raise HTTPException(status_code=404, detail="Relationship not found")
    rel.is_deleted = True
    rel.is_current = False
    rel.updated_at = datetime.utcnow()
    await db.commit()
    return {"message": "تم حذف الصلة"}


@router.get("/{person_id}/family-history")
async def get_family_history(person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    person = await get_person_or_404(db, person_id)
    rows = (await db.execute(
        select(FamilyMember).where(
            FamilyMember.person_id == person_id,
            FamilyMember.is_deleted == False,  # noqa: E712
        )
        .order_by(FamilyMember.is_current.desc(), FamilyMember.start_date.desc().nullslast(), FamilyMember.id.desc())
    )).scalars().all()
    current, previous = [], []
    current_family = None
    for m in rows:
        fam = (await db.execute(select(Family).where(Family.id == m.family_id))).scalar_one_or_none()
        entry = {
            "membership_id": m.id,
            "family_id": m.family_id,
            "family_name": fam.family_name if fam else None,
            "family_code": fam.family_code if fam else None,
            "relationship_type": m.relationship_type.value if m.relationship_type else None,
            "is_head": m.is_head,
            "is_spouse": m.is_spouse,
            "is_child": m.is_child,
            "is_current": m.is_current,
            "start_date": str(m.start_date) if m.start_date else None,
            "end_date": str(m.end_date) if m.end_date else None,
            "notes": m.notes,
        }
        if m.is_current:
            current.append(entry)
            if person.family_id == m.family_id:
                current_family = {"family_id": m.family_id, "family_name": entry["family_name"]}
        else:
            previous.append(entry)
    if not current_family and current:
        current_family = {"family_id": current[0]["family_id"], "family_name": current[0]["family_name"]}
    return {"current_family": current_family, "current": current, "previous": previous}


@router.get("/{person_id}/tree")
async def get_person_tree(person_id: int, depth: int = 3, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    await get_person_or_404(db, person_id)
    tree = await build_kinship_tree(db, [person_id], max_depth=max(1, min(depth, 5)))
    return tree


@router.post("/{person_id}/create-family")
async def create_family_from_person(person_id: int, data: CreateFamilyFromPerson, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    from app.models.models import ServiceAssignment
    person = await get_person_or_404(db, person_id)
    person_gender = person.gender.value if person.gender else None
    role = data.role or ("husband" if person_gender == "male" else "wife")
    if role not in ("father", "mother", "husband", "wife", "son", "daughter"):
        raise HTTPException(status_code=400, detail="دور غير صالح في الأسرة الجديدة")
    spouse_role = data.spouse_role or ("wife" if role == "husband" else "husband" if role == "wife" else None)

    spouse = None
    if data.spouse_person_id and data.spouse_new:
        raise HTTPException(status_code=400, detail="اختر زوجة موجودة أو أنشئ جديدة، وليس الاثنين")
    if data.spouse_person_id:
        if data.spouse_person_id == person_id:
            raise HTTPException(status_code=400, detail="لا يمكن الزواج من نفس الشخص")
        spouse = (await db.execute(select(Person).where(Person.id == data.spouse_person_id))).scalar_one_or_none()
        if not spouse:
            raise HTTPException(status_code=404, detail="Spouse not found")
        if spouse.is_archived:
            raise HTTPException(status_code=400, detail="لا يمكن اختيار سجل مؤرشف")
        if not spouse_role:
            spouse_role = "wife" if (spouse.gender.value if spouse.gender else None) == "female" else "husband"

    try:
        count = (await db.execute(select(func.max(Family.id)))).scalar() or 0
        family = Family(
            family_code=f"FAM-{str(count + 1).zfill(5)}",
            family_name=(data.family_name or f"{person.first_name} {person.middle_name or ''}".strip() or "بدون اسم")[:250],
            is_active=True,
        )
        db.add(family)
        await db.flush()

        def flags(r):
            return {
                "is_head": r in ("father", "husband"),
                "is_spouse": r in ("mother", "wife"),
                "is_child": r in ("son", "daughter"),
            }

        db.add(FamilyMember(
            family_id=family.id, person_id=person.id,
            relationship_type=RelationshipTypeEnum(role), start_date=date.today(),
            is_current=True, is_deleted=False, **flags(role),
        ))
        created = [{"person_id": person.id, "role": role}]

        if data.spouse_new:
            if not spouse_role:
                spouse_role = "wife" if person_gender == "male" else "husband"
            sd = data.spouse_new.model_dump(exclude={"suggested_service_id", "family_id"})
            if sd.get("date_of_birth") and not sd.get("education_stage"):
                sd["education_stage"] = calculate_education_stage(sd["date_of_birth"])
            sd["marital_status"] = "married"
            await ensure_person_phone_unique(db, sd.get("phone"))
            spouse = Person(family_id=family.id, **sd, is_active=True)
            db.add(spouse)
            await db.flush()
            if data.spouse_new.suggested_service_id:
                db.add(ServiceAssignment(
                    person_id=spouse.id, service_id=int(data.spouse_new.suggested_service_id),
                    assigned_by=current_user.id, status="active",
                    notes="تم التوزيع تلقائياً عند الزواج",
                ))
            created.append({"person_id": spouse.id, "role": spouse_role})

        if spouse is not None:
            clash = (await db.execute(
                select(FamilyMember).where(
                    FamilyMember.family_id == family.id,
                    FamilyMember.person_id == spouse.id,
                    FamilyMember.is_current == True,  # noqa: E712
                    FamilyMember.is_deleted == False,  # noqa: E712
                )
            )).scalar_one_or_none()
            if not clash:
                db.add(FamilyMember(
                    family_id=family.id, person_id=spouse.id,
                    relationship_type=RelationshipTypeEnum(spouse_role), start_date=date.today(),
                    is_current=True, is_deleted=False, **flags(spouse_role),
                ))
            spouse_link = "husband" if person_gender == "male" else "wife"
            link_exists = (await db.execute(
                select(PersonRelationship).where(
                    PersonRelationship.person_id == person.id,
                    PersonRelationship.related_person_id == spouse.id,
                    PersonRelationship.relationship_type == spouse_link,
                    PersonRelationship.is_current == True,  # noqa: E712
                    PersonRelationship.is_deleted == False,  # noqa: E712
                )
            )).scalar_one_or_none()
            if not link_exists:
                db.add(PersonRelationship(
                    person_id=person.id, related_person_id=spouse.id,
                    relationship_type=spouse_link, start_date=date.today(),
                    is_current=True, is_deleted=False, notes="auto: marriage",
                ))
            if not spouse.family_id:
                spouse.family_id = family.id
            await set_current_family(db, spouse.id)

        if data.end_old_membership:
            old_rows = (await db.execute(
                select(FamilyMember).where(
                    FamilyMember.person_id == person.id,
                    FamilyMember.family_id != family.id,
                    FamilyMember.is_current == True,  # noqa: E712
                    FamilyMember.is_deleted == False,  # noqa: E712
                )
            )).scalars().all()
            for m in old_rows:
                await end_membership(db, m, data.end_date)
        await set_current_family(db, person.id)
        await db.commit()
        return {
            "message": "تم إنشاء الأسرة الجديدة بنجاح",
            "family_id": family.id,
            "family_code": family.family_code,
            "members": created,
        }
    except HTTPException:
        raise
    except Exception as e:
        await db.rollback()
        raise HTTPException(status_code=400, detail=f"لم يتم حفظ أي بيانات: {str(e)[:300]}")


@router.post("/{person_id}/change-service")
async def change_person_service(person_id: int, service_id: Optional[int] = None, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    from app.models.models import Service, ServiceAssignment
    from datetime import datetime as dt
    person = (await db.execute(select(Person).where(Person.id == person_id))).scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    active = (await db.execute(
        select(ServiceAssignment).where(ServiceAssignment.person_id == person_id, ServiceAssignment.status == "active")
    )).scalars().all()
    if service_id is None:
        for a in active:
            a.status = "cancelled"
        await db.commit()
        return {"message": "تمت إزالة الشخص من الخدمة"}
    service = (await db.execute(select(Service).where(Service.id == service_id))).scalar_one_or_none()
    if not service or not service.is_active:
        raise HTTPException(status_code=404, detail="Service not found")
    if any(a.service_id == service_id for a in active):
        return {"message": f"الشخص مسجل بالفعل في {service.name}"}
    for a in active:
        a.status = "cancelled"
    new_a = ServiceAssignment(person_id=person_id, service_id=service_id, assigned_by=current_user.id, assigned_at=dt.utcnow(), status="active")
    db.add(new_a)
    await db.commit()
    return {"message": f"تم نقل الشخص إلى {service.name}"}


@router.post("/merge")
async def merge_persons(primary_person_id: int, duplicate_person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_moderator)):
    if primary_person_id == duplicate_person_id:
        raise HTTPException(status_code=400, detail="Cannot merge a person with themselves")
    # resolve chain: if the chosen "primary" is itself archived, use it anyway (safest = duplicate folded in)
    from app.models.models import FamilyMember, ServiceAssignment, Visit, Confession, ServiceMember, ServantAssignment, User as UserModel
    primary = (await db.execute(select(Person).where(Person.id == primary_person_id))).scalar_one_or_none()
    duplicate = (await db.execute(select(Person).where(Person.id == duplicate_person_id))).scalar_one_or_none()
    if not primary or not duplicate:
        raise HTTPException(status_code=404, detail="Person not found")
    if primary.is_archived and not duplicate.is_archived:
        primary, duplicate = duplicate, primary
        primary_person_id, duplicate_person_id = primary.id, duplicate.id

    # 1) fill empty identity fields on the primary from the duplicate
    for field in ["date_of_birth", "phone", "whatsapp", "email", "national_id",
                  "marital_status", "education_stage", "school_year", "confession_father", "occupation", "address"]:
        if getattr(primary, field) in (None, "") and getattr(duplicate, field) not in (None, ""):
            setattr(primary, field, getattr(duplicate, field))

    # 2) family memberships: reparent; on clash with a CURRENT primary
    # membership, end the duplicate's row as history instead of deleting
    dup_members = (await db.execute(
        select(FamilyMember).where(
            FamilyMember.person_id == duplicate_person_id,
            FamilyMember.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    for dm in dup_members:
        clash = (await db.execute(
            select(FamilyMember).where(
                FamilyMember.family_id == dm.family_id,
                FamilyMember.person_id == primary_person_id,
                FamilyMember.is_current == True,  # noqa: E712
                FamilyMember.is_deleted == False,  # noqa: E712
            )
        )).scalar_one_or_none()
        if clash:
            if dm.is_current:
                await end_membership(db, dm)
            else:
                dm.is_deleted = True
        else:
            dm.person_id = primary_person_id
    await set_current_family(db, primary_person_id)

    # 2b) relationships: reparent both sides, skip conflicts
    from app.models.models import PersonRelationship as _PRL
    dup_rels = (await db.execute(
        select(_PRL).where(
            or_( _PRL.person_id == duplicate_person_id, _PRL.related_person_id == duplicate_person_id),
            _PRL.is_deleted == False,  # noqa: E712
        )
    )).scalars().all()
    for dr in dup_rels:
        new_pid = primary_person_id if dr.person_id == duplicate_person_id else dr.person_id
        new_rid = primary_person_id if dr.related_person_id == duplicate_person_id else dr.related_person_id
        if new_pid == new_rid:
            dr.is_deleted = True
            dr.is_current = False
            continue
        clash = (await db.execute(
            select(_PRL).where(
                _PRL.person_id == new_pid,
                _PRL.related_person_id == new_rid,
                _PRL.relationship_type == dr.relationship_type,
                _PRL.is_current == True,  # noqa: E712
                _PRL.is_deleted == False,  # noqa: E712
            )
        )).scalar_one_or_none()
        if clash:
            dr.is_deleted = True
            dr.is_current = False
        else:
            dr.person_id = new_pid
            dr.related_person_id = new_rid

    # 3) reassign all other records
    for model in [ServiceAssignment, Visit, Confession, ServiceMember, ServantAssignment]:
        records = (await db.execute(select(model).where(model.person_id == duplicate_person_id))).scalars().all()
        for record in records:
            record.person_id = primary_person_id

    # 4) merge user account if any
    dup_user = (await db.execute(select(UserModel).where(UserModel.person_id == duplicate_person_id))).scalar_one_or_none()
    if dup_user:
        existing_user = (await db.execute(select(UserModel).where(UserModel.person_id == primary_person_id))).scalar_one_or_none()
        if existing_user:
            dup_user.person_id = None
        else:
            dup_user.person_id = primary_person_id

    # 5) archive the duplicate; recompute primary's current family
    duplicate.family_id = None
    duplicate.is_active = False
    duplicate.is_archived = True
    duplicate.updated_at = datetime.utcnow()
    primary.updated_at = datetime.utcnow()
    await set_current_family(db, primary_person_id)
    await db.commit()
    return {"message": f"تم الدمج. تم أرشفة السجل {duplicate_person_id}."}
