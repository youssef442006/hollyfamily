from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, or_
from typing import Optional
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.models import Person, Family, Service, ServiceMember, User
from app.schemas.schemas import GlobalSearchResult, PersonResponse, FamilyResponse, ServiceResponse

router = APIRouter(prefix="/api/search", tags=["Search"])


@router.get("", response_model=GlobalSearchResult)
async def global_search(
    q: str = Query(..., min_length=1),
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    persons_result = await db.execute(
        select(Person).where(
            or_(
                Person.first_name.ilike(f"%{q}%"),
                Person.middle_name.ilike(f"%{q}%"),
                Person.last_name.ilike(f"%{q}%"),
                Person.phone.ilike(f"%{q}%"),
                Person.whatsapp.ilike(f"%{q}%"),
                Person.national_id.ilike(f"%{q}%")
            )
        ).limit(20)
    )
    persons = persons_result.scalars().all()
    families_result = await db.execute(
        select(Family).where(
            or_(
                Family.family_name.ilike(f"%{q}%"),
                Family.phone.ilike(f"%{q}%"),
                Family.family_code.ilike(f"%{q}%"),
                Family.address.ilike(f"%{q}%")
            )
        ).limit(20)
    )
    families = families_result.scalars().all()
    services_result = await db.execute(
        select(Service).where(
            or_(
                Service.name.ilike(f"%{q}%"),
                Service.description.ilike(f"%{q}%")
            )
        ).limit(20)
    )
    services = services_result.scalars().all()
    person_responses = []
    for p in persons:
        resp = PersonResponse.model_validate(p)
        resp.full_name_display = f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip()
        person_responses.append(resp)
    family_responses = [FamilyResponse.model_validate(f) for f in families]
    service_responses = [ServiceResponse.model_validate(s) for s in services]
    return GlobalSearchResult(persons=person_responses, families=family_responses, services=service_responses)
