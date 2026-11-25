from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, and_, or_
from typing import Optional
from datetime import datetime, timedelta
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.models import (
    Family, Person, Service, ServiceAssignment, ServiceMember,
    Visit, Confession, User
)
from app.schemas.schemas import DashboardStats

router = APIRouter(prefix="/api/dashboard", tags=["Dashboard"])


@router.get("", response_model=DashboardStats)
async def get_dashboard(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    fc = await db.execute(select(func.count(Family.id)).where(Family.is_active == True))
    pc = await db.execute(select(func.count(Person.id)).where(Person.is_active == True))
    children_count = await db.execute(
        select(func.count(Person.id)).where(
            Person.is_active == True,
            Person.education_stage.in_(["nursery", "angels", "primary_1_2", "primary_3_4", "primary_5_6", "preparatory", "secondary"])
        )
    )
    sc = await db.execute(select(func.count(ServiceMember.id)).where(ServiceMember.is_active == True))
    svc = await db.execute(select(func.count(Service.id)).where(Service.is_active == True))
    bac = await db.execute(select(func.count(ServiceAssignment.id)).where(ServiceAssignment.status == "active"))
    active_subq = (
        select(ServiceAssignment.person_id)
        .where(ServiceAssignment.status == "active")
        .distinct()
        .subquery()
    )
    without_svc = await db.execute(
        select(func.count(Person.id))
        .outerjoin(active_subq, Person.id == active_subq.c.person_id)
        .where(Person.is_active == True, active_subq.c.person_id.is_(None))
    )
    needs_visit = await db.execute(
        select(func.count(Visit.id)).where(
            Visit.next_visit_date <= datetime.utcnow() + timedelta(days=7),
            Visit.status == "pending"
        )
    )
    upcoming_conf = await db.execute(
        select(func.count(Confession.id)).where(
            Confession.next_confession_date >= datetime.utcnow(),
            Confession.next_confession_date <= datetime.utcnow() + timedelta(days=30),
            Confession.status == "upcoming"
        )
    )
    services_result = await db.execute(select(Service).where(Service.is_active == True))
    services = services_result.scalars().all()
    
    svc_ids = [s.id for s in services]
    mc_map = {}
    ac_map = {}
    
    if svc_ids:
        mc_query = select(ServiceMember.service_id, func.count(ServiceMember.id)).where(
            ServiceMember.service_id.in_(svc_ids), ServiceMember.is_active == True
        ).group_by(ServiceMember.service_id)
        mc_result = await db.execute(mc_query)
        mc_map = {row[0]: row[1] for row in mc_result.all()}
        
        ac_query = select(ServiceAssignment.service_id, func.count(ServiceAssignment.id)).where(
            ServiceAssignment.service_id.in_(svc_ids), ServiceAssignment.status == "active"
        ).group_by(ServiceAssignment.service_id)
        ac_result = await db.execute(ac_query)
        ac_map = {row[0]: row[1] for row in ac_result.all()}

    service_stats = []
    for svc_item in services:
        service_stats.append({
            "service_id": svc_item.id,
            "service_name": svc_item.name,
            "servants": mc_map.get(svc_item.id, 0),
            "beneficiaries": ac_map.get(svc_item.id, 0)
        })
    return DashboardStats(
        total_families=fc.scalar() or 0,
        total_persons=pc.scalar() or 0,
        total_children=children_count.scalar() or 0,
        total_servants=sc.scalar() or 0,
        total_services=svc.scalar() or 0,
        total_beneficiaries=bac.scalar() or 0,
        persons_without_service=without_svc.scalar() or 0,
        families_needing_visit=needs_visit.scalar() or 0,
        upcoming_confessions=upcoming_conf.scalar() or 0,
        service_stats=service_stats
    )
