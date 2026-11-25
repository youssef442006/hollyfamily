"""Centralized audit logging via SQLAlchemy session event listeners.

Instead of editing ~43 write endpoints across 8 routers, a single ``before_flush``
listener inspects the session INSERT/UPDATE/DELETE collections and appends an
AuditLog row into the SAME transaction. The caller's own commit() persists the
audit entry atomically with the operation.

The acting user id is propagated via a ContextVar set by security.get_current_user().
"""
import json
from contextvars import ContextVar
from typing import Any, Optional

from sqlalchemy import event
from sqlalchemy.orm.session import Session

_audit_user_ctx: ContextVar = ContextVar("audit_user_id", default=None)

_AUDIT_SKIP_TABLES = {"audit_logs"}

_SNAPSHOT_FIELDS = (
    "family_code", "family_name", "first_name", "middle_name", "last_name",
    "gender", "phone", "whatsapp", "second_phone", "email", "national_id",
    "marital_status", "relationship_type", "is_current", "is_deleted",
    "is_active", "is_head", "is_spouse", "is_child", "is_deceased",
    "is_deacon", "is_servant", "in_prep_center", "username", "role",
    "service_id", "person_id", "family_id", "status", "notes", "address",
    "street", "area", "building_number", "floor", "apartment_number",
    "visit_date", "visit_type", "confession_date", "next_confession_date",
    "record_id", "key", "value", "updated_at", "date_of_birth",
    "date_of_death", "occupation", "confession_father", "confession_church",
    "education_stage", "school_year", "is_head_of_family", "is_active_member",
)


def set_audit_user(user_id: Optional[int]) -> None:
    _audit_user_ctx.set(user_id)


def _snapshot(obj: Any) -> Optional[str]:
    data = {}
    for field in _SNAPSHOT_FIELDS:
        if hasattr(obj, field):
            value = getattr(obj, field)
            if value is not None and not hasattr(value, "__tablename__"):
                data[field] = str(value)
    return json.dumps(data, ensure_ascii=False, default=str) if data else None


def _record(session: Session, obj: Any, action: str) -> None:
    table = getattr(obj, "__tablename__", None)
    if not table or table in _AUDIT_SKIP_TABLES:
        return
    from app.models.models import AuditLog  # late import avoids circular deps
    row_id = getattr(obj, "id", None)
    session.add(AuditLog(
        user_id=_audit_user_ctx.get(),
        action=action,
        table_name=table,
        record_id=row_id,
        old_value=_snapshot(obj) if action == "update" else None,
        new_value=_snapshot(obj) if action in ("create", "update") else None,
    ))


def _before_flush(session: Session, flush_context: Any, instances: Any) -> None:
    for obj in session.new:
        _record(session, obj, "create")
    for obj in session.dirty:
        _record(session, obj, "update")
    for obj in session.deleted:
        _record(session, obj, "delete")


_registered = False


def setup_audit_events() -> None:
    global _registered
    if _registered:
        return
    event.listen(Session, "before_flush", _before_flush)
    _registered = True


def set_audit_user_context(user_id) -> None:
    set_audit_user(user_id)
