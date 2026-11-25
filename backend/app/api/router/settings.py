from fastapi import APIRouter, Depends, HTTPException, UploadFile, File
from fastapi.responses import StreamingResponse
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete
from sqlalchemy.orm import selectinload
from datetime import datetime, date as dt_date
from io import BytesIO
from app.core.database import get_db
from app.core.security import get_current_user, require_admin_role
from app.models.models import Setting, User, Family, Person, Service, ServiceAssignment, FamilyMember, PersonRelationship, ServiceMember, ServantAssignment, Priest, Visit, Confession, GenderEnum, RelationshipTypeEnum, MaritalStatusEnum, EducationStageEnum
from app.schemas.schemas import SettingCreate, SettingResponse
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment

router = APIRouter(prefix="/api/settings", tags=["Settings"])

FAM_HEADERS = ["family_code", "family_name", "phone", "address", "special_case", "notes"]
PERSON_HEADERS = ["first_name", "middle_name", "last_name", "gender", "date_of_birth", "phone",
                  "whatsapp", "email", "national_id", "marital_status", "care_situation",
                  "education_stage", "school_year", "confession_father", "occupation",
                  "family_name", "family_relationship", "notes"]
SERVICE_HEADERS = ["name", "description", "min_age", "max_age", "gender_restriction", "education_stages", "notes"]
SERVED_HEADERS = ["person_name", "service_name", "status"]
SERVANT_HEADERS = ["servant_name", "person_name", "service_name"]

_XLSX_MEDIA = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"


def _style_headers(ws):
    head_font = Font(bold=True, color="FFFFFF", size=11)
    head_fill = PatternFill("solid", fgColor="2E7D32")
    mid = Alignment(horizontal="center", vertical="center")
    for c in range(1, ws.max_column + 1):
        cell = ws.cell(row=1, column=c)
        cell.font = head_font
        cell.fill = head_fill
        cell.alignment = mid
    for col in ws.columns:
        letter = col[0].column_letter
        width = max(len(str(c.value or "")) * 1.6 + 4 for c in col)
        ws.column_dimensions[letter].width = max(width, 14)
    ws.freeze_panes = "A2"


def _full_name(p):
    return f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip()


@router.get("/export")
async def export_data(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    wb = openpyxl.Workbook()

    ws_fam = wb.active
    ws_fam.title = "الأسر"
    ws_fam.append(FAM_HEADERS)
    fams = (await db.execute(select(Family).where(Family.is_active == True))).scalars().all()  # noqa: E712
    for f in fams:
        ws_fam.append([f.family_code, f.family_name, f.phone, f.address, f.special_case, f.notes])
    _style_headers(ws_fam)

    members = (await db.execute(
        select(FamilyMember).where(FamilyMember.is_current == True, FamilyMember.is_deleted == False)  # noqa: E712
    )).scalars().all()
    rel_by_person = {}
    for m in members:
        rel_by_person.setdefault(m.person_id, m.relationship_type.value if m.relationship_type else "")

    fam_by_id = {f.id: f for f in fams}

    ws_pers = wb.create_sheet("الأفراد")
    ws_pers.append(PERSON_HEADERS)
    persons = (await db.execute(select(Person).where(Person.is_active == True))).scalars().all()  # noqa: E712
    for p in persons:
        fam = fam_by_id.get(p.family_id) if p.family_id else None
        ws_pers.append([
            p.first_name, p.middle_name, p.last_name,
            p.gender.value if p.gender else "",
            str(p.date_of_birth) if p.date_of_birth else "",
            p.phone, p.whatsapp, p.email, p.national_id,
            p.marital_status.value if p.marital_status else "",
            p.care_situation or "",
            p.education_stage.value if p.education_stage else "",
            p.school_year or "",
            p.confession_father or "",
            p.occupation or "",
            fam.family_name if fam else "",
            rel_by_person.get(p.id, ""),
            p.notes,
        ])
    _style_headers(ws_pers)

    ws_svc = wb.create_sheet("الخدمات")
    ws_svc.append(SERVICE_HEADERS)
    svcs = (await db.execute(select(Service).where(Service.is_active == True))).scalars().all()  # noqa: E712
    for s in svcs:
        ws_svc.append([
            s.name, s.description, s.min_age, s.max_age,
            s.gender_restriction.value if s.gender_restriction else "",
            s.education_stages or "", s.notes,
        ])
    _style_headers(ws_svc)

    pers_by_id = {p.id: p for p in persons}
    svc_by_id = {s.id: s for s in svcs}

    ws_served = wb.create_sheet("المخدومين")
    ws_served.append(SERVED_HEADERS)
    served = (await db.execute(
        select(ServiceAssignment).where(ServiceAssignment.status == "active")
    )).scalars().all()
    for a in served:
        pers = pers_by_id.get(a.person_id)
        svc = svc_by_id.get(a.service_id)
        if pers and svc:
            ws_served.append([_full_name(pers), svc.name, a.status])
    _style_headers(ws_served)

    ws_servants = wb.create_sheet("الخدام")
    ws_servants.append(SERVANT_HEADERS)
    servants = (await db.execute(
        select(ServantAssignment).where(ServantAssignment.is_active == True)
    )).scalars().all()
    for a in servants:
        servant = pers_by_id.get(a.servant_id)
        pers = pers_by_id.get(a.person_id)
        svc = svc_by_id.get(a.service_id)
        if servant and pers and svc:
            ws_servants.append([_full_name(servant), _full_name(pers), svc.name])
    _style_headers(ws_servants)

    buf = BytesIO()
    wb.save(buf)
    buf.seek(0)
    return StreamingResponse(
        buf,
        media_type=_XLSX_MEDIA,
        headers={"Content-Disposition": "attachment; filename=church_data.xlsx"}
    )


@router.get("/sample")
async def sample_template(current_user: User = Depends(get_current_user)):
    wb = openpyxl.Workbook()

    ws_fam = wb.active
    ws_fam.title = "الأسر"
    ws_fam.append(FAM_HEADERS)
    ws_fam.append(["", "أسرة فايز جرجس", "0247123456", "شبرا - شارع طه حسين", "", ""])
    _style_headers(ws_fam)

    ws_pers = wb.create_sheet("الأفراد")
    ws_pers.append(PERSON_HEADERS)
    ws_pers.append(["فايز", "جرجس", "حنا", "male", "1980-03-15", "0247123456", "01098765432",
                    "fayez@gmail.com", "28304152301111", "married", "يزور بعيادة", "university",
                    "university", "ابونا سيرافيم", "محاسب", "أسرة فايز جرجس", "father", ""])
    ws_pers.append(["كيرلس", "فايز", "جرجس", "male", "2008-07-22", "", "", "", "",
                    "single", "", "secondary", "sec_1", "ابونا جوزيف", "", "أسرة فايز جرجس", "son", ""])
    _style_headers(ws_pers)

    ws_svc = wb.create_sheet("الخدمات")
    ws_svc.append(SERVICE_HEADERS)
    ws_svc.append(["حضانة", "خدمة أطفال الحضانة", 4, 6, "female", "nursery", ""])
    _style_headers(ws_svc)

    ws_served = wb.create_sheet("المخدومين")
    ws_served.append(SERVED_HEADERS)
    ws_served.append(["فايز جرجس حنا", "حضانة", "active"])
    _style_headers(ws_served)

    ws_servants = wb.create_sheet("الخدام")
    ws_servants.append(SERVANT_HEADERS)
    ws_servants.append(["فايز جرجس حنا", "كيرلس فايز جرجس", "حضانة"])
    _style_headers(ws_servants)

    buf = BytesIO()
    wb.save(buf)
    buf.seek(0)
    return StreamingResponse(
        buf,
        media_type=_XLSX_MEDIA,
        headers={"Content-Disposition": "attachment; filename=church_import_sample.xlsx"}
    )


@router.post("/import")
async def import_data(file: UploadFile = File(...), db: AsyncSession = Depends(get_db), current_user: User = Depends(require_admin_role)):
    if not file.filename.endswith((".xlsx", ".xls")):
        raise HTTPException(status_code=400, detail="يجب رفع ملف Excel (.xlsx)")

    content = await file.read()
    try:
        wb = openpyxl.load_workbook(BytesIO(content))
    except Exception:
        raise HTTPException(status_code=400, detail="ملف Excel غير صالح أو تالف")

    imported = {"families": 0, "persons": 0, "services": 0, "served": 0, "servants": 0}

    try:
        return await _run_import(wb, db, current_user, imported)
    except HTTPException:
        await db.rollback()
        raise
    except Exception as e:
        await db.rollback()
        raise HTTPException(status_code=400, detail=f"فشل الاستيراد: {str(e)}")


async def _run_import(wb, db, current_user, imported):
    fam_map = {}
    svc_map = {}
    existing_fam_names = set((await db.execute(select(Family.family_name))).scalars().all())
    existing_svc_names = set((await db.execute(select(Service.name))).scalars().all())
    existing_fam_codes = set((await db.execute(select(Family.family_code))).scalars().all())

    rows_svc = []
    if "الخدمات" in wb.sheetnames:
        for row in wb["الخدمات"].iter_rows(min_row=2, values_only=True):
            vals = list(row) + [None]*7
            name, desc = vals[0], vals[1]
            if not name:
                continue
            mins, maxs = vals[2], vals[3]
            def _int(v):
                try:
                    return int(float(v))
                except (TypeError, ValueError):
                    return None
            gender_rest = None
            gv = vals[4]
            if gv:
                try:
                    gender_rest = GenderEnum(str(gv).strip().lower())
                except ValueError:
                    gender_rest = None
            rows_svc.append({
                "name": str(name).strip(), "desc": desc, "min_age": _int(mins), "max_age": _int(maxs),
                "gender": gender_rest, "stages": vals[5], "notes": vals[6],
            })

    for sv in rows_svc:
        key = sv["name"]
        if key in existing_svc_names:
            svc_map[key] = None
            continue
        s = Service(name=key, description=sv["desc"], min_age=sv["min_age"], max_age=sv["max_age"],
                    gender_restriction=sv["gender"], education_stages=sv["stages"], notes=sv["notes"], is_active=True)
        db.add(s)
        await db.flush()
        svc_map[key] = s.id
        existing_svc_names.add(key)
        imported["services"] += 1

    rows_fam = []
    if "الأسر" in wb.sheetnames:
        for row in wb["الأسر"].iter_rows(min_row=2, values_only=True):
            vals = list(row) + [None]*6
            if not vals[1]:
                continue
            rows_fam.append({"code": vals[0], "name": str(vals[1]).strip(), "phone": vals[2],
                             "address": vals[3], "special": vals[4], "notes": vals[5]})

    for fr in rows_fam:
        key = fr["name"]
        if key in existing_fam_names:
            fam_map[key] = None
            continue
        code = (fr["code"] or "").strip()
        if not code or code in existing_fam_codes:
            base_code = f"FAM-{len(existing_fam_codes) + 100:05d}"
            code = base_code
            gen = 1
            while code in existing_fam_codes:
                code = f"FAM-{len(existing_fam_codes) + 100 + gen:05d}"
                gen += 1
        existing_fam_codes.add(code)
        f = Family(family_code=code, family_name=key, phone=fr["phone"], address=fr["address"],
                   special_case=fr["special"], notes=fr["notes"], is_active=True)
        db.add(f)
        await db.flush()
        fam_map[key] = f.id
        existing_fam_names.add(key)
        imported["families"] += 1

    fam_objs = {f.id: f for f in (await db.execute(select(Family))).scalars().all()}
    fam_by_name = {}
    for f in fam_objs.values():
        fam_by_name.setdefault(f.family_name, f.id)

    existing_nations = set((await db.execute(select(Person.national_id).where(Person.national_id.isnot(None)))).scalars().all())
    person_by_full = {}
    all_persons = (await db.execute(select(Person))).scalars().all()
    for pp in all_persons:
        person_by_full.setdefault(_full_name(pp), pp.id)

    rows_pers = []
    if "الأفراد" in wb.sheetnames:
        for row in wb["الأفراد"].iter_rows(min_row=2, values_only=True):
            vals = list(row) + [None]*16
            if not vals[0]:
                continue
            rows_pers.append(vals)

    for vals in rows_pers:
        first, middle, last, gender, dob, phone, whatsapp, email, national, marital, care, edu, syear, conf_father, occ, fam_name, rel, notes = vals[:18]
        def safe_str(v):
            return str(v).strip() if v and str(v).strip() and str(v).strip().lower() not in ("none", "") else None
        nat = safe_str(national)
        full = f"{str(first).strip()} {safe_str(middle) or ''} {safe_str(last) or ''}".strip()
        if nat and nat in existing_nations:
            continue
        if full and full in person_by_full:
            continue
        gender_enum = GenderEnum.male
        if gender:
            try:
                gender_enum = GenderEnum(str(gender).strip().lower())
            except ValueError:
                gender_enum = GenderEnum.male
        fam_id = fam_by_name.get(str(fam_name).strip()) if fam_name else None
        p = Person(
            family_id=fam_id, first_name=str(first).strip(), middle_name=safe_str(middle), last_name=safe_str(last),
            gender=gender_enum, phone=safe_str(phone), whatsapp=safe_str(whatsapp), email=safe_str(email),
            national_id=nat, care_situation=safe_str(care), occupation=safe_str(occ), notes=safe_str(notes), is_active=True
        )
        p.school_year = safe_str(syear)
        p.confession_father = safe_str(conf_father)
        if marital:
            try:
                p.marital_status = MaritalStatusEnum(str(marital).strip().lower())
            except ValueError:
                pass
        if edu:
            try:
                p.education_stage = EducationStageEnum(str(edu).strip().lower())
            except ValueError:
                pass
        if dob:
            if isinstance(dob, datetime):
                p.date_of_birth = dob.date()
            elif isinstance(dob, str) and dob.strip():
                try:
                    p.date_of_birth = dt_date.fromisoformat(dob.strip())
                except ValueError:
                    pass
            elif isinstance(dob, (date,)):
                p.date_of_birth = dob
        if p.education_stage:
            from app.api.router.persons import resolve_school_year
            p.school_year = resolve_school_year(None, p.education_stage.value, p.date_of_birth)
        db.add(p)
        await db.flush()
        person_by_full[full] = p.id
        if nat:
            existing_nations.add(nat)
        imported["persons"] += 1
        if fam_id and rel:
            rel_type = None
            try:
                rel_type = RelationshipTypeEnum(str(rel).strip().lower())
            except ValueError:
                rel_type = None
            if rel_type:
                is_head = rel_type == RelationshipTypeEnum.father
                is_spouse = rel_type in (RelationshipTypeEnum.mother, RelationshipTypeEnum.wife, RelationshipTypeEnum.husband)
                is_child = rel_type in (RelationshipTypeEnum.son, RelationshipTypeEnum.daughter)
                db.add(FamilyMember(family_id=fam_id, person_id=p.id, relationship_type=rel_type,
                                    is_head=is_head, is_spouse=is_spouse, is_child=is_child,
                                    start_date=dt_date.today(), is_current=True, is_deleted=False))

    persons_refresh = (await db.execute(select(Person))).scalars().all()
    person_by_full = {}
    for pp in persons_refresh:
        person_by_full.setdefault(_full_name(pp), pp)

    svc_refresh = {s.id: s for s in (await db.execute(select(Service))).scalars().all()}
    svc_id_by_name = {}
    for s in svc_refresh.values():
        svc_id_by_name.setdefault(s.name, s.id)

    if "المخدومين" in wb.sheetnames:
        for row in wb["المخدومين"].iter_rows(min_row=2, values_only=True):
            pname, sname, status = (list(row) + [None]*3)[:3]
            if not pname or not sname:
                continue
            svc_id = svc_id_by_name.get(str(sname).strip())
            pers = person_by_full.get(str(pname).strip())
            if svc_id and pers:
                existing = (await db.execute(select(ServiceAssignment).where(
                    ServiceAssignment.person_id == pers.id, ServiceAssignment.service_id == svc_id
                ))).scalar_one_or_none()
                if not existing:
                    db.add(ServiceAssignment(person_id=pers.id, service_id=svc_id, assigned_by=current_user.id,
                                             status=str(status).strip() if status else "active"))
                    imported["served"] += 1

    if "الخدام" in wb.sheetnames:
        for row in wb["الخدام"].iter_rows(min_row=2, values_only=True):
            servant_name, pname, sname = (list(row) + [None]*3)[:3]
            if not servant_name or not sname:
                continue
            svc_id = svc_id_by_name.get(str(sname).strip())
            servant = person_by_full.get(str(servant_name).strip())
            pers = person_by_full.get(str(pname).strip()) if pname else None
            if svc_id and servant and pers:
                existing = (await db.execute(select(ServantAssignment).where(
                    ServantAssignment.servant_id == servant.id, ServantAssignment.person_id == pers.id,
                    ServantAssignment.service_id == svc_id, ServantAssignment.is_active == True  # noqa: E712
                ))).scalar_one_or_none()
                if not existing:
                    db.add(ServantAssignment(servant_id=servant.id, person_id=pers.id, service_id=svc_id, is_active=True))
                    imported["servants"] += 1

    await db.commit()
    return {"message": "تم الاستيراد بنجاح", "imported": imported}


@router.post("/clear")
async def clear_all_data(db: AsyncSession = Depends(get_db), current_user: User = Depends(require_admin_role)):
    await db.execute(delete(ServiceAssignment))
    await db.execute(delete(ServiceMember))
    await db.execute(delete(ServantAssignment))
    await db.execute(delete(Confession))
    await db.execute(delete(Visit))
    await db.execute(delete(PersonRelationship))
    await db.execute(delete(FamilyMember))
    # تم الإبقاء على الخدمات (services) دون مسح بناءً على طلب المستخدم
    await db.execute(delete(Person))
    await db.execute(delete(Family))
    await db.commit()
    return {"message": "تم تفريغ البيانات بنجاح (مع الحفاظ على الخدمات)"}


@router.get("", response_model=list[SettingResponse])
async def list_settings(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Setting).order_by(Setting.key))
    return result.scalars().all()


@router.get("/{key}", response_model=SettingResponse)
async def get_setting(key: str, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Setting).where(Setting.key == key))
    setting = result.scalar_one_or_none()
    if not setting:
        raise HTTPException(status_code=404, detail="Setting not found")
    return SettingResponse.model_validate(setting)


@router.put("/{key}", response_model=SettingResponse)
async def update_setting(key: str, setting_data: SettingCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_admin_role)):
    result = await db.execute(select(Setting).where(Setting.key == key))
    setting = result.scalar_one_or_none()
    if not setting:
        setting = Setting(key=key)
        db.add(setting)
    setting.value = setting_data.value
    setting.description = setting_data.description
    setting.updated_at = datetime.utcnow()
    await db.commit()
    await db.refresh(setting)
    return SettingResponse.model_validate(setting)