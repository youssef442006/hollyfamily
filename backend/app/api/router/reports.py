from fastapi import APIRouter, Depends, Query
from fastapi.responses import StreamingResponse
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, and_
from typing import Optional
from datetime import datetime, timedelta
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.models import (
    Family, Person, Service, ServiceAssignment, ServiceMember,
    Visit, Confession, User
)
import io
import csv

router = APIRouter(prefix="/api/reports", tags=["Reports"])


@router.get("/without-service")
async def report_without_service(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    active_subq = (
        select(ServiceAssignment.person_id)
        .where(ServiceAssignment.status == "active")
        .distinct()
        .subquery()
    )
    result = await db.execute(
        select(
            Person.id,
            Person.first_name,
            Person.middle_name,
            Person.last_name,
            Person.gender,
            Person.date_of_birth,
            Person.phone,
            Person.education_stage,
            Person.care_situation,
            Person.family_id,
            Person.created_at,
        )
        .outerjoin(active_subq, Person.id == active_subq.c.person_id)
        .where(Person.is_active == True, active_subq.c.person_id.is_(None))
        .order_by(Person.first_name)
    )
    report = []
    for row in result.all():
        report.append({
            "person_id": row.id,
            "full_name": f"{row.first_name} {row.middle_name or ''} {row.last_name or ''}".strip(),
            "gender": row.gender.value if row.gender else None,
            "date_of_birth": row.date_of_birth.isoformat() if row.date_of_birth else None,
            "phone": row.phone,
            "education_stage": row.education_stage.value if row.education_stage else None,
            "care_situation": row.care_situation if row.care_situation else None,
            "family_id": row.family_id,
            "created_at": row.created_at.isoformat() if row.created_at else None,
        })
    return report


@router.get("/families")
async def report_families(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Family).where(Family.is_active == True))
    families = result.scalars().all()
    report = []
    for f in families:
        pc = await db.execute(select(func.count(Person.id)).where(Person.family_id == f.id))
        children_count = await db.execute(
            select(func.count(Person.id)).where(
                Person.family_id == f.id,
                Person.education_stage.in_(["nursery", "angels", "primary_1_2", "primary_3_4", "primary_5_6", "preparatory", "secondary"])
            )
        )
        last_visit = await db.execute(
            select(Visit).where(Visit.family_id == f.id).order_by(Visit.visit_date.desc()).limit(1)
        )
        lv = last_visit.scalar_one_or_none()
        report.append({
            "family_id": f.id,
            "family_code": f.family_code,
            "family_name": f.family_name,
            "phone": f.phone,
            "address": f.address,
            "total_members": pc.scalar() or 0,
            "children_count": children_count.scalar() or 0,
            "last_visit_date": lv.visit_date.isoformat() if lv else None,
            "days_since_visit": (datetime.utcnow() - lv.visit_date).days if lv else None,
        })
    return report


@router.get("/services")
async def report_services(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(select(Service).where(Service.is_active == True))
    services = result.scalars().all()
    report = []
    for s in services:
        sc = await db.execute(
            select(func.count(ServiceMember.id)).where(ServiceMember.service_id == s.id, ServiceMember.is_active == True)
        )
        ac = await db.execute(
            select(func.count(ServiceAssignment.id)).where(ServiceAssignment.service_id == s.id, ServiceAssignment.status == "active")
        )
        report.append({
            "service_id": s.id,
            "service_name": s.name,
            "servants": sc.scalar() or 0,
            "beneficiaries": ac.scalar() or 0
        })
    return report


@router.get("/age-distribution")
async def report_age_distribution(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    stages = ["nursery", "angels", "primary_1_2", "primary_3_4", "primary_5_6", "preparatory", "secondary", "university", "graduate"]
    report = []
    for stage in stages:
        c = await db.execute(
            select(func.count(Person.id)).where(Person.education_stage == stage, Person.is_active == True)
        )
        report.append({"stage": stage, "count": c.scalar() or 0})
    return report


@router.get("/age-distribution/by-year")
async def report_age_distribution_by_year(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(
        select(Person.education_stage, Person.school_year, func.count(Person.id))
        .where(Person.is_active == True)
        .group_by(Person.education_stage, Person.school_year)
        .order_by(Person.education_stage, Person.school_year)
    )
    report = [
        {
            "stage": row[0].value if row[0] else None,
            "school_year": row[1],
            "count": row[2],
        }
        for row in result.all()
    ]
    return report


@router.get("/neglected")
async def report_neglected(days: int = 90, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    cutoff = datetime.utcnow() - timedelta(days=days)
    result = await db.execute(
        select(Family).where(Family.is_active == True)
    )
    families = result.scalars().all()
    neglected = []
    for f in families:
        last_visit = await db.execute(
            select(Visit).where(Visit.family_id == f.id).order_by(Visit.visit_date.desc()).limit(1)
        )
        lv = last_visit.scalar_one_or_none()
        if not lv or lv.visit_date < cutoff:
            neglected.append({
                "family_id": f.id,
                "family_name": f.family_name,
                "phone": f.phone,
                "last_visit_date": lv.visit_date.isoformat() if lv else None,
                "days_since_visit": (datetime.utcnow() - lv.visit_date).days if lv else None
            })
    return neglected


@router.get("/special-cases")
async def report_special_cases(db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    result = await db.execute(
        select(Family).where(Family.is_active == True, Family.special_case.isnot(None), Family.special_case != "")
    )
    families = result.scalars().all()
    report = []
    for f in families:
        pc = await db.execute(select(func.count(Person.id)).where(Person.family_id == f.id, Person.is_active == True))
        persons_result = await db.execute(select(Person).where(Person.family_id == f.id, Person.is_active == True))
        persons = persons_result.scalars().all()
        report.append({
            "family_id": f.id,
            "family_code": f.family_code,
            "family_name": f.family_name,
            "phone": f.phone,
            "address": f.address,
            "special_case": f.special_case,
            "total_members": pc.scalar() or 0,
            "members": [
                {"id": p.id, "name": f"{p.first_name} {p.middle_name or ''} {p.last_name or ''}".strip(), "age": ((datetime.utcnow().year - p.date_of_birth.year) - ((datetime.utcnow().month, datetime.utcnow().day) < (p.date_of_birth.month, p.date_of_birth.day))) if p.date_of_birth else None}
                for p in persons
            ],
        })
    return report


@router.get("/export/csv/{report_type}")
async def export_csv(report_type: str, db: AsyncSession = Depends(get_db), current_user: User = Depends(get_current_user)):
    output = io.StringIO()
    writer = csv.writer(output)
    if report_type == "families":
        writer.writerow(["Family Code", "Family Name", "Phone", "Address"])
        result = await db.execute(select(Family).where(Family.is_active == True))
        for f in result.scalars().all():
            writer.writerow([f.family_code, f.family_name, f.phone, f.address])
    elif report_type == "persons":
        writer.writerow(["First Name", "Middle Name", "Last Name", "Gender", "DOB", "Phone", "Education Stage"])
        result = await db.execute(select(Person).where(Person.is_active == True))
        for p in result.scalars().all():
            writer.writerow([p.first_name, p.middle_name, p.last_name, p.gender.value if p.gender else '', p.date_of_birth, p.phone, p.education_stage.value if p.education_stage else ''])
    output.seek(0)
    return StreamingResponse(
        io.BytesIO(output.getvalue().encode("utf-8-sig")),
        media_type="text/csv",
        headers={"Content-Disposition": f"attachment; filename={report_type}_report.csv"}
    )
