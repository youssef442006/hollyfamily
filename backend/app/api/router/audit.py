from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from typing import Optional
from datetime import datetime
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.models import AuditLog, User
from app.schemas.schemas import AuditLogResponse

router = APIRouter(prefix="/api/audit", tags=["Audit Logs"])


@router.get("", response_model=list[AuditLogResponse])
async def list_audit_logs(
    user_id: Optional[int] = None,
    table_name: Optional[str] = None,
    action: Optional[str] = None,
    skip: int = 0,
    limit: int = 100,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    if current_user.role.value != "admin":
        raise HTTPException(status_code=403, detail="Admin only")
    query = select(AuditLog)
    if user_id:
        query = query.where(AuditLog.user_id == user_id)
    if table_name:
        query = query.where(AuditLog.table_name == table_name)
    if action:
        query = query.where(AuditLog.action == action)
    query = query.order_by(AuditLog.timestamp.desc()).offset(skip).limit(limit)
    result = await db.execute(query)
    logs = result.scalars().all()
    responses = []
    for log in logs:
        resp = AuditLogResponse.model_validate(log)
        if log.user_id:
            user_result = await db.execute(select(User).where(User.id == log.user_id))
            user = user_result.scalar_one_or_none()
            if user:
                resp.username = user.username
        responses.append(resp)
    return responses


async def create_audit_log(db: AsyncSession, user_id: int, action: str, table_name: str, record_id: int = None, old_value: str = None, new_value: str = None):
    """Add an audit entry to the current transaction. Does NOT commit on its own —
    it relies on the caller's commit so the log is atomic with the operation."""
    log = AuditLog(user_id=user_id, action=action, table_name=table_name, record_id=record_id, old_value=old_value, new_value=new_value)
    db.add(log)
    await db.flush()
