from fastapi import APIRouter, Depends, HTTPException, status, Response, Body, Request
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from datetime import timedelta
from pydantic import BaseModel
from app.core.database import get_db
from app.core.security import verify_password, create_access_token, get_current_user, get_password_hash, require_admin_role
from app.models.models import User
from app.schemas.schemas import UserCreate, UserResponse, Token
from app.core.config import settings
from app.core.rate_limit import login_limiter, login_limit_key

router = APIRouter(prefix="/api/auth", tags=["Authentication"])






class ActiveUpdateRequest(BaseModel):
    is_active: bool

class RoleUpdateRequest(BaseModel):
    role: str

class ChangePasswordRequest(BaseModel):
    old_password: str
    new_password: str


class PublicUserItem(BaseModel):
    username: str
    full_name: str
    role: str


@router.get("/public-users", response_model=list[PublicUserItem])
async def list_public_users(db: AsyncSession = Depends(get_db)):
    result = await db.execute(
        select(User).where(User.is_active == True).order_by(
            User.role.asc(), User.id.asc()
        )
    )
    users = result.scalars().all()
    role_names = {
        "admin": "مدير النظام",
        "priest": "كاهن",
        "service_admin": "أمين خدمة",
        "servant": "خادم",
    }
    return [
        PublicUserItem(
            username=u.username,
            full_name=u.full_name,
            role=role_names.get(u.role.value if hasattr(u.role, 'value') else str(u.role), "مستخدم")
        )
        for u in users
    ]


@router.post("/login", response_model=Token)
async def login(request: Request, response: Response, form_data: OAuth2PasswordRequestForm = Depends(), db: AsyncSession = Depends(get_db)):
    key = login_limit_key(request, form_data.username)
    if login_limiter.is_locked(key):
        raise HTTPException(status_code=status.HTTP_429_TOO_MANY_REQUESTS, detail="محاولات كثيرة جدًا، حاول بعد 5 دقائق")
    result = await db.execute(select(User).where(User.username == form_data.username))
    user = result.scalar_one_or_none()
    if not user or not verify_password(form_data.password, user.hashed_password):
        login_limiter.record_failure(key)
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Incorrect username or password")
    if not user.is_active:
        login_limiter.record_failure(key)
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Inactive user")
    login_limiter.reset(key)
    access_token = create_access_token(data={"sub": str(user.id), "role": user.role.value})
    
    response.set_cookie(
        key="access_token",
        value=access_token,
        httponly=True,
        secure=False,  # Set to True in production (HTTPS)
        samesite="lax",
        max_age=settings.ACCESS_TOKEN_EXPIRE_MINUTES * 60
    )
    
    return Token(access_token=access_token, token_type="bearer")


@router.post("/logout")
async def logout(response: Response):
    response.delete_cookie("access_token")
    return {"message": "Logged out successfully"}


@router.post("/register", response_model=UserResponse)
async def register(user_data: UserCreate, db: AsyncSession = Depends(get_db), current_user=Depends(require_admin_role)):
    existing = await db.execute(select(User).where(User.username == user_data.username))
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="Username already exists")
    user = User(
        username=user_data.username,
        email=user_data.email,
        full_name=user_data.full_name,
        role=user_data.role,
        person_id=user_data.person_id,
        hashed_password=get_password_hash(user_data.password)
    )
    db.add(user)
    await db.commit()
    await db.refresh(user)
    return user


@router.get("/me", response_model=UserResponse)
async def get_me(current_user: User = Depends(get_current_user)):
    return current_user


@router.put("/me/password")
async def change_password(
    req: ChangePasswordRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if len(req.new_password) < 8:
        raise HTTPException(status_code=400, detail="يجب ألا تقل كلمة المرور الجديدة عن 8 أحرف")
    if not verify_password(req.old_password, current_user.hashed_password):
        raise HTTPException(status_code=400, detail="كلمة المرور الحالية غير صحيحة")
    current_user.hashed_password = get_password_hash(req.new_password)
    await db.commit()
    return {"message": "تم تحديث كلمة المرور بنجاح"}


@router.get("/users", response_model=list[UserResponse])
async def list_users(db: AsyncSession = Depends(get_db), current_user=Depends(require_admin_role)):
    result = await db.execute(select(User))
    return result.scalars().all()


@router.delete("/users/{user_id}")
async def delete_user(
    user_id: int,
    db: AsyncSession = Depends(get_db),
    current_user=Depends(require_admin_role),
):
    """حذف مستخدم نهائياً (أدمن فقط)"""
    if current_user.id == user_id:
        raise HTTPException(
            status_code=400,
            detail="لا يمكنك حذف حسابك الخاص",
        )
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(status_code=404, detail="المستخدم غير موجود")
    await db.delete(user)
    await db.commit()
    return {"message": "تم حذف المستخدم بنجاح"}


@router.put("/users/{user_id}/role", response_model=UserResponse)
async def update_user_role(
    user_id: int,
    role_data: RoleUpdateRequest,
    db: AsyncSession = Depends(get_db),
    current_user=Depends(require_admin_role),
):
    """تغيير دور / صلاحيات مستخدم (أدمن فقط)"""
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(status_code=404, detail="المستخدم غير موجود")
    from app.models.models import UserRoleEnum
    try:
        user.role = UserRoleEnum(role_data.role)
    except ValueError:
        raise HTTPException(status_code=400, detail="دور غير صالح")
    await db.commit()
    await db.refresh(user)
    return user


@router.put("/users/{user_id}/active", response_model=UserResponse)
async def update_user_active(
    user_id: int,
    user_data: ActiveUpdateRequest,
    db: AsyncSession = Depends(get_db),
    current_user=Depends(require_admin_role),
):
    """تفعيل / تعطيل مستخدم (أدمن فقط)"""
    if current_user.id == user_id and not user_data.is_active:
        raise HTTPException(
            status_code=400,
            detail="لا يمكنك تعطيل حسابك الخاص",
        )
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(status_code=404, detail="المستخدم غير موجود")
    user.is_active = user_data.is_active
    await db.commit()
    await db.refresh(user)
    return user

