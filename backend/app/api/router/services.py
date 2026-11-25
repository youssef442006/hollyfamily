from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, update
from datetime import datetime
from app.core.database import get_db
from app.core.security import get_current_user, require_editor, require_moderator
from app.models.models import Service, ServiceMember, ServiceAssignment, Person, User
from app.schemas.schemas import ServiceCreate, ServiceUpdate, ServiceResponse, ServiceMemberCreate, ServiceMemberResponse, ServiceAssignmentCreate, ServiceAssignmentResponse
from app.schemas.schemas import PersonResponse as PRSchema

router = APIRouter(prefix="/api/services", tags=["Services"])


@router.get("", response_model=list[ServiceResponse])
async def list_services(is_active: bool = None, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    query = select(Service)
    if is_active is not None:
        query = query.where(Service.is_active == is_active)
    result = await db.execute(query)
    services = result.scalars().all()
    responses = []
    for s in services:
        mc = await db.execute(select(func.count(ServiceMember.id)).where(ServiceMember.service_id == s.id, ServiceMember.is_active == True))
        ac = await db.execute(select(func.count(ServiceAssignment.id)).where(ServiceAssignment.service_id == s.id, ServiceAssignment.status == "active"))
        resp = ServiceResponse.model_validate(s)
        resp.member_count = mc.scalar() or 0
        resp.assignment_count = ac.scalar() or 0
        responses.append(resp)
    return responses


@router.get("/{service_id}", response_model=ServiceResponse)
async def get_service(service_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Service).where(Service.id == service_id))
    service = result.scalar_one_or_none()
    if not service:
        raise HTTPException(status_code=404, detail="Service not found")
    mc = await db.execute(select(func.count(ServiceMember.id)).where(ServiceMember.service_id == service_id, ServiceMember.is_active == True))
    ac = await db.execute(select(func.count(ServiceAssignment.id)).where(ServiceAssignment.service_id == service_id, ServiceAssignment.status == "active"))
    resp = ServiceResponse.model_validate(service)
    resp.member_count = mc.scalar() or 0
    resp.assignment_count = ac.scalar() or 0
    return resp


@router.post("", response_model=ServiceResponse)
async def create_service(service_data: ServiceCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    service = Service(**service_data.model_dump())
    db.add(service)
    await db.commit()
    await db.refresh(service)
    resp = ServiceResponse.model_validate(service)
    resp.member_count = 0
    resp.assignment_count = 0
    return resp


@router.put("/{service_id}", response_model=ServiceResponse)
async def update_service(service_id: int, service_data: ServiceUpdate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    result = await db.execute(select(Service).where(Service.id == service_id))
    service = result.scalar_one_or_none()
    if not service:
        raise HTTPException(status_code=404, detail="Service not found")
    for key, value in service_data.model_dump(exclude_unset=True).items():
        setattr(service, key, value)
    service.updated_at = datetime.utcnow()
    await db.commit()
    await db.refresh(service)
    return ServiceResponse.model_validate(service)


@router.delete("/{service_id}")
async def delete_service(service_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_moderator)):
    result = await db.execute(select(Service).where(Service.id == service_id))
    service = result.scalar_one_or_none()
    if not service:
        raise HTTPException(status_code=404, detail="Service not found")
    await db.execute(update(ServiceAssignment).where(ServiceAssignment.service_id == service_id, ServiceAssignment.status == "active").values(status="cancelled"))
    await db.execute(update(ServiceMember).where(ServiceMember.service_id == service_id, ServiceMember.is_active == True).values(is_active=False))
    service.is_active = False
    await db.commit()
    return {"message": "Service deactivated"}


@router.get("/{service_id}/members", response_model=list[ServiceMemberResponse])
async def get_service_members(service_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(ServiceMember).where(ServiceMember.service_id == service_id, ServiceMember.is_active == True))
    members = result.scalars().all()
    responses = []
    for m in members:
        person_result = await db.execute(select(Person).where(Person.id == m.person_id))
        person = person_result.scalar_one_or_none()
        person_resp = PRSchema.model_validate(person) if person else None
        if person_resp is not None:
            person_resp.full_name_display = f"{person.first_name} {person.middle_name or ''} {person.last_name or ''}".strip()
        responses.append(ServiceMemberResponse(
            id=m.id,
            person_id=m.person_id,
            service_id=m.service_id,
            role=m.role,
            is_active=m.is_active,
            created_at=m.created_at,
            person=person_resp,
        ))
    return responses


@router.post("/{service_id}/members", response_model=ServiceMemberResponse)
async def add_service_member(service_id: int, member_data: ServiceMemberCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    existing = await db.execute(
        select(ServiceMember).where(
            ServiceMember.service_id == service_id,
            ServiceMember.person_id == member_data.person_id,
            ServiceMember.is_active == True,
        )
    )
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="هذا الشخص خادم بالفعل في هذه الخدمة")
    member_data.service_id = service_id
    member = ServiceMember(**member_data.model_dump(), is_active=True)
    db.add(member)
    await db.commit()
    await db.refresh(member)
    person_result = await db.execute(select(Person).where(Person.id == member.person_id))
    person = person_result.scalar_one_or_none()
    person_resp = PRSchema.model_validate(person) if person else None
    if person_resp is not None:
        person_resp.full_name_display = f"{person.first_name} {person.middle_name or ''} {person.last_name or ''}".strip()
    return ServiceMemberResponse(
        id=member.id,
        person_id=member.person_id,
        service_id=member.service_id,
        role=member.role,
        is_active=member.is_active,
        created_at=member.created_at,
        person=person_resp,
    )


@router.get("/{service_id}/assignments", response_model=list[ServiceAssignmentResponse])
async def get_service_assignments(service_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(ServiceAssignment).where(ServiceAssignment.service_id == service_id, ServiceAssignment.status == "active"))
    assignments = result.scalars().all()
    responses = []
    for a in assignments:
        person_result = await db.execute(select(Person).where(Person.id == a.person_id))
        person = person_result.scalar_one_or_none()
        resp = ServiceAssignmentResponse.model_validate(a)
        if person:
            from app.schemas.schemas import PersonResponse as PR
            resp.person = PR.model_validate(person)
        responses.append(resp)
    return responses


@router.post("/{service_id}/assignments", response_model=ServiceAssignmentResponse)
async def create_service_assignment(service_id: int, assignment_data: ServiceAssignmentCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    assignment_data.service_id = service_id
    assignment = ServiceAssignment(**assignment_data.model_dump(), assigned_by=current_user.id, assigned_at=datetime.utcnow(), status="active")
    db.add(assignment)
    await db.commit()
    await db.refresh(assignment)
    return ServiceAssignmentResponse.model_validate(assignment)


@router.post("/{service_id}/auto-assign")
async def auto_assign_service(service_id: int, person_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    from datetime import date as d_date
    person_result = await db.execute(select(Person).where(Person.id == person_id))
    person = person_result.scalar_one_or_none()
    if not person:
        raise HTTPException(status_code=404, detail="Person not found")
    service_result = await db.execute(select(Service).where(Service.id == service_id))
    service = service_result.scalar_one_or_none()
    if not service:
        raise HTTPException(status_code=404, detail="Service not found")
    if service.is_active == False:  # noqa: E712
        raise HTTPException(status_code=400, detail="هذه الخدمة غير نشطة")
    age = None
    if person.date_of_birth:
        today = d_date.today()
        age = today.year - person.date_of_birth.year - ((today.month, today.day) < (person.date_of_birth.month, person.date_of_birth.day))
    if service.min_age is not None and age is not None and age < service.min_age:
        raise HTTPException(status_code=400, detail=f"الفرد عمره {age} سنة والخدمة دي تبدأ من {service.min_age} سنة")
    if service.max_age is not None and age is not None and age > service.max_age:
        raise HTTPException(status_code=400, detail=f"الفرد عمره {age} سنة والخدمة دي للفئة لحد {service.max_age} سنة")
    if service.gender_restriction and person.gender and service.gender_restriction != person.gender.value:
        raise HTTPException(status_code=400, detail="الخدمة دي مخصصة لجنس تاني")
    assignment = ServiceAssignment(person_id=person_id, service_id=service_id, assigned_by=current_user.id, assigned_at=datetime.utcnow(), status="active")
    db.add(assignment)
    await db.commit()
    return {"message": f"Assigned {person.first_name} to {service.name}"}
