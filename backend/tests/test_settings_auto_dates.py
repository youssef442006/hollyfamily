from datetime import datetime, timedelta

import pytest
import pytest_asyncio
from sqlalchemy.ext.asyncio import async_sessionmaker

from app.models.models import Person, Priest, GenderEnum

pytestmark = pytest.mark.asyncio


def _parse(value):
    dt = datetime.fromisoformat(value)
    return dt.replace(tzinfo=None)


@pytest_asyncio.fixture
async def person_and_priest(engine):
    SessionLocal = async_sessionmaker(engine, expire_on_commit=False)
    async with SessionLocal() as session:
        person = Person(first_name="Auto", last_name="Test", gender=GenderEnum.male, is_active=True)
        session.add(person)
        await session.flush()
        priest = Priest(display_name="Abuna Auto", is_active=True)
        session.add(priest)
        await session.flush()
        return {"person_id": person.id, "priest_id": priest.id}


async def _login_admin(client) -> str:
    resp = await client.post(
        "/api/auth/login",
        data={"username": "chief", "password": "Admin@12345"},
    )
    assert resp.status_code == 200, resp.text
    return resp.json()["access_token"]


def _auth(token: str) -> dict:
    return {"Authorization": f"Bearer {token}"}


async def test_confession_auto_next_date_default(client, admin_user, person_and_priest):
    token = await _login_admin(client)
    base = datetime.utcnow().replace(microsecond=0)
    resp = await client.post(
        "/api/confessions",
        json={
            "person_id": person_and_priest["person_id"],
            "priest_id": person_and_priest["priest_id"],
            "confession_date": base.isoformat(),
        },
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    body = resp.json()
    assert _parse(body["next_confession_date"]) == base + timedelta(days=30)


async def test_confession_interval_setting_respected(client, admin_user, person_and_priest):
    token = await _login_admin(client)
    resp = await client.put(
        "/api/settings/confession_interval_days",
        json={"key": "confession_interval_days", "value": "7", "description": "test"},
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    base = datetime.utcnow().replace(microsecond=0)
    resp = await client.post(
        "/api/confessions",
        json={
            "person_id": person_and_priest["person_id"],
            "priest_id": person_and_priest["priest_id"],
            "confession_date": base.isoformat(),
        },
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    assert _parse(resp.json()["next_confession_date"]) == base + timedelta(days=7)


async def test_visit_auto_next_date_default(client, admin_user):
    token = await _login_admin(client)
    base = datetime.utcnow() + timedelta(days=1)
    resp = await client.post(
        "/api/visits",
        json={"visit_date": base.isoformat(), "visit_type": "home_visit"},
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    assert _parse(resp.json()["next_visit_date"]) == base + timedelta(days=90)


async def test_visit_interval_setting_respected(client, admin_user):
    token = await _login_admin(client)
    resp = await client.put(
        "/api/settings/visit_interval_days",
        json={"key": "visit_interval_days", "value": "21", "description": "test"},
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    base = datetime.utcnow() + timedelta(days=2)
    resp = await client.post(
        "/api/visits",
        json={"visit_date": base.isoformat(), "visit_type": "follow_up"},
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    assert _parse(resp.json()["next_visit_date"]) == base + timedelta(days=21)


async def test_explicit_next_visit_date_respected(client, admin_user):
    token = await _login_admin(client)
    base = datetime.utcnow() + timedelta(days=1)
    explicit = base + timedelta(days=365)
    resp = await client.post(
        "/api/visits",
        json={
            "visit_date": base.isoformat(),
            "visit_type": "other",
            "next_visit_date": explicit.isoformat(),
        },
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    assert _parse(resp.json()["next_visit_date"]) == explicit


async def test_settings_put_upserts_new_key(client, admin_user):
    token = await _login_admin(client)
    resp = await client.put(
        "/api/settings/brand_new_setting",
        json={"key": "brand_new_setting", "value": "33", "description": "upsert"},
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    get = await client.get("/api/settings/brand_new_setting", headers=_auth(token))
    assert get.status_code == 200
    assert get.json()["value"] == "33"