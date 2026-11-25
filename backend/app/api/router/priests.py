from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from datetime import datetime
from app.core.database import get_db
from app.core.security import get_current_user, require_admin_role
from app.models.models import Priest, Person, User
from app.schemas.schemas import PriestCreate, PriestResponse

router = APIRouter(prefix="/api/priests", tags=["Priests"])


@router.get("", response_model=list[PriestResponse])
async def list_priests(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Priest).where(Priest.is_active == True))
    priests = result.scalars().all()
    return [PriestResponse.model_validate(p) for p in priests]


@router.get("/{priest_id}", response_model=PriestResponse)
async def get_priest(priest_id: int, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Priest).where(Priest.id == priest_id))
    priest = result.scalar_one_or_none()
    if not priest:
        raise HTTPException(status_code=404, detail="Priest not found")
    return PriestResponse.model_validate(priest)


@router.post("", response_model=PriestResponse)
async def create_priest(priest_data: PriestCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_admin_role)):
    priest = Priest(**priest_data.model_dump())
    db.add(priest)
    await db.commit()
    await db.refresh(priest)
    return PriestResponse.model_validate(priest)


@router.put("/{priest_id}", response_model=PriestResponse)
async def update_priest(priest_id: int, priest_data: PriestCreate, db: AsyncSession = Depends(get_db), current_user: User = Depends(require_admin_role)):
    result = await db.execute(select(Priest).where(Priest.id == priest_id))
    priest = result.scalar_one_or_none()
    if not priest:
        raise HTTPException(status_code=404, detail="Priest not found")
    for key, value in priest_data.model_dump(exclude_unset=True).items():
        setattr(priest, key, value)
    await db.commit()
    await db.refresh(priest)
    return PriestResponse.model_validate(priest)
