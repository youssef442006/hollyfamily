"""Security helpers: password hashing, JWT, current-user dependencies.

Also seeds the centralized audit-log context (see app.core.audit_events) with
the acting user id so a single SQLAlchemy flush listener can record every
create/update/delete without touching individual write endpoints.
"""
from datetime import datetime, timedelta
from typing import Optional
from jose import JWTError, jwt
import bcrypt
from fastapi import Depends, HTTPException, Request, status
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from app.core.config import settings
from app.core.database import get_db
from app.core.audit_events import set_audit_user, setup_audit_events

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/auth/login")

# Ensure the centralized before_flush listener is registered once at import
# time so write operations audit themselves without per-endpoint edits.
setup_audit_events()


def verify_password(plain_password: str, hashed_password: str) -> bool:
    try:
        return bcrypt.checkpw(plain_password.encode("utf-8"), hashed_password.encode("utf-8"))
    except (ValueError, TypeError):
        return False


def get_password_hash(password: str) -> str:
    return bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


def create_access_token(data: dict, expires_delta: Optional[timedelta] = None) -> str:
    to_encode = data.copy()
    expire = datetime.utcnow() + (expires_delta or timedelta(minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES))
    to_encode.update({"exp": expire})
    return jwt.encode(to_encode, settings.SECRET_KEY, algorithm=settings.ALGORITHM)


async def get_current_user(request: Request, db: AsyncSession = Depends(get_db)):
    from app.models.models import User
    token = request.cookies.get("access_token")
    if not token:
        auth_header = request.headers.get("Authorization")
        if auth_header and auth_header.startswith("Bearer "):
            token = auth_header.split(" ")[1]

    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    if not token:
        raise credentials_exception

    try:
        payload = jwt.decode(token, settings.SECRET_KEY, algorithms=[settings.ALGORITHM])
        user_id: str = payload.get("sub")
        if user_id is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception

    result = await db.execute(select(User).where(User.id == int(user_id), User.is_active == True))
    user = result.scalar_one_or_none()
    if user is None:
        raise credentials_exception
    set_audit_user(user.id)
    try:
        return user
    finally:
        set_audit_user(None)


def require_role(*roles):
    async def role_checker(current_user=Depends(get_current_user)):
        if current_user.role not in roles:
            raise HTTPException(status_code=403, detail="Insufficient permissions")
        return current_user
    return role_checker


async def require_editor(current_user=Depends(get_current_user)):
    from app.models.models import UserRoleEnum
    if current_user.role not in (UserRoleEnum.admin, UserRoleEnum.priest, UserRoleEnum.service_admin):
        raise HTTPException(status_code=403, detail="لا تملك صلاحية التعديل")
    return current_user


async def require_moderator(current_user=Depends(get_current_user)):
    from app.models.models import UserRoleEnum
    if current_user.role not in (UserRoleEnum.admin, UserRoleEnum.priest):
        raise HTTPException(status_code=403, detail="لا تملك صلاحية الحذف")
    return current_user


async def require_admin_role(current_user=Depends(get_current_user)):
    from app.models.models import UserRoleEnum
    if current_user.role != UserRoleEnum.admin:
        raise HTTPException(status_code=403, detail="لا تملك صلاحية الإدارة")
    return current_user
