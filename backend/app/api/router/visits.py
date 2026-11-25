from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, or_
from typing import Optional
from datetime import datetime, timedelta
from app.core.database import get_db
from app.core.security import get_current_user, require_editor, require_moderator
from app.core.setting_utils import (
    get_setting_int,
    VISIT_INTERVAL_KEY,
    DEFAULT_VISIT_INTERVAL_DAYS,
)
from app.models.models import Visit, Person, Family, User
from app.schemas.schemas import VisitCreate, VisitUpdate, VisitResponse

router = APIRouter(prefix="/api/visits", tags=["Visits"])


@router.get("", response_model=list[VisitResponse])
async def list_visits(
    family_id: Optional[int] = None,
    person_id: Optional[int] = None,
    servant_id: Optional[int] = None,
    status: Optional[str] = None,
    visit_type: Optional[str] = None,
    skip: int = 0,
    limit: int = 50,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    query = select(Visit)
    if family_id:
        query = query.where(Visit.family_id == family_id)
    if person_id:
        query = query.where(Visit.person_id == person_id)
    if servant_id:
        query = query.where(Visit.servant_id == servant_id)
    if status:
        query = query.where(Visit.status == status)
    if visit_type:
        query = query.where(Visit.visit_type == visit_type)
    query = query.order_by(Visit.visit_date.desc()).offset(skip).limit(limit)
    result = await db.execute(query)
    visits = result.scalars().all()
    responses = []
    for v in visits:
        resp = VisitResponse.model_validate(v)
        if v.person_id:
            pr = await db.execute(select(Person).where(Person.id == v.person_id))
            p = pr.scalar_one_or_none()
            if p:
                resp.person_name = f"{p.first_name} {p.last_name or ''}"
        if v.servant_id:
            sr = await db.execute(select(Person).where(Person.id == v.servant_id))
            s = sr.scalar_one_or_none()
            if s:
                resp.servant_name = f"{s.first_name} {s.last_name or ''}"
        if v.family_id:
            fr = await db.execute(select(Family).where(Family.id == v.family_id))
            f = fr.scalar_one_or_none()
            if f:
                resp.family_name = f.family_name
        responses.append(resp)
    return responses


@router.get("/{visit_id}", response_model=VisitResponse)
async def get_visit(visit_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Visit).where(Visit.id == visit_id))
    visit = result.scalar_one_or_none()
    if not visit:
        raise HTTPException(status_code=404, detail="Visit not found")
    return VisitResponse.model_validate(visit)


@router.post("", response_model=VisitResponse)
async def create_visit(visit_data: VisitCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    payload = visit_data.model_dump()
    if not payload.get("next_visit_date"):
        interval = await get_setting_int(db, VISIT_INTERVAL_KEY, DEFAULT_VISIT_INTERVAL_DAYS)
        base = payload.get("visit_date") or datetime.utcnow()
        payload["next_visit_date"] = base + timedelta(days=interval)
    if payload.get("visit_date") and payload["visit_date"] < datetime.utcnow():
        payload["status"] = "completed"
    visit = Visit(**payload)
    db.add(visit)
    await db.commit()
    await db.refresh(visit)
    return VisitResponse.model_validate(visit)


@router.put("/{visit_id}", response_model=VisitResponse)
async def update_visit(visit_id: int, visit_data: VisitUpdate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    result = await db.execute(select(Visit).where(Visit.id == visit_id))
    visit = result.scalar_one_or_none()
    if not visit:
        raise HTTPException(status_code=404, detail="Visit not found")
    for key, value in visit_data.model_dump(exclude_unset=True).items():
        setattr(visit, key, value)
    visit.updated_at = datetime.utcnow()
    await db.commit()
    await db.refresh(visit)
    return VisitResponse.model_validate(visit)


@router.delete("/{visit_id}")
async def delete_visit(visit_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_moderator)):
    result = await db.execute(select(Visit).where(Visit.id == visit_id))
    visit = result.scalar_one_or_none()
    if not visit:
        raise HTTPException(status_code=404, detail="Visit not found")
    await db.delete(visit)
    await db.commit()
    return {"message": "Visit deleted"}
