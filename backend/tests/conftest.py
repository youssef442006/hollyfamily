import os
import sys

BACKEND_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if BACKEND_DIR not in sys.path:
    sys.path.insert(0, BACKEND_DIR)

import pytest
import pytest_asyncio
import httpx
from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker
from sqlalchemy.pool import StaticPool

from app.core.database import Base, get_db
from app.main import app
from app.core.security import get_password_hash
from app.models.models import User, UserRoleEnum

TEST_DSN = "sqlite+aiosqlite://"


@pytest_asyncio.fixture
async def engine():
    eng = create_async_engine(
        TEST_DSN,
        poolclass=StaticPool,
        connect_args={"check_same_thread": False},
    )
    async with eng.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    yield eng
    await eng.dispose()


@pytest_asyncio.fixture
async def client(engine):
    SessionLocal = async_sessionmaker(engine, expire_on_commit=False)

    async def _get_db_override():
        async with SessionLocal() as session:
            try:
                yield session
            finally:
                await session.close()

    app.dependency_overrides[get_db] = _get_db_override
    transport = httpx.ASGITransport(app=app)
    async with httpx.AsyncClient(transport=transport, base_url="http://testserver") as ac:
        yield ac
    app.dependency_overrides.clear()


@pytest_asyncio.fixture
async def admin_user(engine):
    """Seed one admin row directly in the shared in-memory DB."""
    SessionLocal = async_sessionmaker(engine, expire_on_commit=False)
    async with SessionLocal() as session:
        admin = User(
            username="chief",
            email="chief@church.test",
            full_name="Chief Admin",
            role=UserRoleEnum.admin,
            is_active=True,
            hashed_password=get_password_hash("Admin@12345"),
        )
        session.add(admin)
        await session.commit()
        await session.refresh(admin)
        return admin.id


@pytest.fixture
def member_username() -> str:
    return "member1"


@pytest.fixture
def member_password() -> str:
    return "Member@12345"