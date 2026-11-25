from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func
from typing import Optional
from datetime import datetime, timedelta
from app.core.database import get_db
from app.core.security import get_current_user, require_editor, require_moderator
from app.core.setting_utils import (
    get_setting_int,
    CONFESSION_INTERVAL_KEY,
    DEFAULT_CONFESSION_INTERVAL_DAYS,
)
from app.models.models import Confession, Person, Priest, User
from app.schemas.schemas import ConfessionCreate, ConfessionUpdate, ConfessionResponse

router = APIRouter(prefix="/api/confessions", tags=["Confessions"])


@router.get("", response_model=list[ConfessionResponse])
async def list_confessions(
    person_id: Optional[int] = None,
    priest_id: Optional[int] = None,
    status: Optional[str] = None,
    skip: int = 0,
    limit: int = 50,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    query = select(Confession)
    if person_id:
        query = query.where(Confession.person_id == person_id)
    if priest_id:
        query = query.where(Confession.priest_id == priest_id)
    if status:
        query = query.where(Confession.status == status)
    query = query.order_by(Confession.confession_date.desc()).offset(skip).limit(limit)
    result = await db.execute(query)
    confessions = result.scalars().all()
    responses = []
    for c in confessions:
        resp = ConfessionResponse.model_validate(c)
        pr = await db.execute(select(Person).where(Person.id == c.person_id))
        p = pr.scalar_one_or_none()
        if p:
            resp.person_name = f"{p.first_name} {p.last_name or ''}"
        if c.priest_id:
            priest_result = await db.execute(select(Priest).where(Priest.id == c.priest_id))
            priest = priest_result.scalar_one_or_none()
            if priest:
                resp.priest_name = priest.display_name
        responses.append(resp)
    return responses


@router.get("/{confession_id}", response_model=ConfessionResponse)
async def get_confession(confession_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Confession).where(Confession.id == confession_id))
    confession = result.scalar_one_or_none()
    if not confession:
        raise HTTPException(status_code=404, detail="Confession not found")
    return ConfessionResponse.model_validate(confession)


@router.post("", response_model=ConfessionResponse)
async def create_confession(confession_data: ConfessionCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    payload = confession_data.model_dump()
    if not payload.get("next_confession_date"):
        interval = await get_setting_int(db, CONFESSION_INTERVAL_KEY, DEFAULT_CONFESSION_INTERVAL_DAYS)
        base = payload.get("confession_date") or datetime.utcnow()
        payload["next_confession_date"] = base + timedelta(days=interval)
    confession = Confession(**payload, status="upcoming")
    db.add(confession)
    if confession_data.priest_id:
        priest_result = await db.execute(select(Priest).where(Priest.id == confession_data.priest_id))
        priest = priest_result.scalar_one_or_none()
        if priest:
            person_result = await db.execute(select(Person).where(Person.id == confession_data.person_id))
            person = person_result.scalar_one_or_none()
            if person:
                person.confession_father = priest.display_name
    await db.commit()
    await db.refresh(confession)
    return ConfessionResponse.model_validate(confession)


@router.put("/{confession_id}", response_model=ConfessionResponse)
async def update_confession(confession_id: int, confession_data: ConfessionUpdate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    result = await db.execute(select(Confession).where(Confession.id == confession_id))
    confession = result.scalar_one_or_none()
    if not confession:
        raise HTTPException(status_code=404, detail="Confession not found")
    for key, value in confession_data.model_dump(exclude_unset=True).items():
        setattr(confession, key, value)
    await db.commit()
    await db.refresh(confession)
    return ConfessionResponse.model_validate(confession)


@router.post("/{confession_id}/complete")
async def complete_confession(confession_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_editor)):
    result = await db.execute(select(Confession).where(Confession.id == confession_id))
    confession = result.scalar_one_or_none()
    if not confession:
        raise HTTPException(status_code=404, detail="Confession not found")
    confession.status = "completed"
    await db.commit()
    return {"message": "Confession marked as completed"}


@router.delete("/{confession_id}")
async def delete_confession(confession_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_moderator)):
    result = await db.execute(select(Confession).where(Confession.id == confession_id))
    confession = result.scalar_one_or_none()
    if not confession:
        raise HTTPException(status_code=404, detail="Confession not found")
    await db.delete(confession)
    await db.commit()
    return {"message": "Confession deleted"}
