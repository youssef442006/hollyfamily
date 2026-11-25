import pytest


pytestmark = pytest.mark.asyncio


async def test_health(client):
    resp = await client.get("/health")
    assert resp.status_code == 200
    assert resp.json() == {"status": "healthy"}


async def test_register_user(client, admin_user):
    token = await _login_admin(client)
    resp = await client.post(
        "/api/auth/register",
        json={
            "username": "deacon1",
            "email": "deacon1@church.test",
            "full_name": "Deacon One",
            "role": "servant",
            "password": "Deacon@12345",
        },
        headers=_auth(token),
    )
    assert resp.status_code == 200, resp.text
    body = resp.json()
    assert body["username"] == "deacon1"
    assert body["role"] == "servant"
    assert body["is_active"] is True


async def test_register_duplicate_username(client, admin_user):
    token = await _login_admin(client)
    payload = {
        "username": "dup1",
        "full_name": "Dup User",
        "role": "servant",
        "password": "Dup@12345",
    }
    resp = await client.post("/api/auth/register", json=payload, headers=_auth(token))
    assert resp.status_code == 200
    resp2 = await client.post("/api/auth/register", json=payload, headers=_auth(token))
    assert resp2.status_code == 400


async def test_register_forbidden_for_non_admin(client, admin_user):
    token = await _login_admin(client)
    await client.post(
        "/api/auth/register",
        json={
            "username": "priest_x",
            "full_name": "Priest X",
            "role": "priest",
            "password": "Priest@12345",
        },
        headers=_auth(token),
    )
    non_admin_token = await _login_as("priest_x", "Priest@12345", client)
    resp = await client.post(
        "/api/auth/register",
        json={
            "username": "sneaky",
            "full_name": "Sneaky",
            "role": "admin",
            "password": "Sneaky@12345",
        },
        headers=_auth(non_admin_token),
    )
    assert resp.status_code == 403


async def test_login_success_and_me(client, admin_user):
    token = await _login_admin(client)
    resp = await client.get("/api/auth/me", headers=_auth(token))
    assert resp.status_code == 200
    body = resp.json()
    assert body["username"] == "chief"
    assert body["role"] == "admin"


async def test_login_wrong_password(client, admin_user):
    resp = await client.post(
        "/api/auth/login",
        data={"username": "chief", "password": "WrongPass@1"},
    )
    assert resp.status_code == 401


async def test_me_requires_auth(client):
    resp = await client.get("/api/auth/me")
    assert resp.status_code == 401


async def test_change_password(client, admin_user):
    token = await _login_admin(client)
    resp = await client.put(
        "/api/auth/me/password",
        json={"old_password": "Admin@12345", "new_password": "NewPass@12345"},
        headers=_auth(token),
    )
    assert resp.status_code == 200
    resp = await client.put(
        "/api/auth/me/password",
        json={"old_password": "Admin@12345", "new_password": "Short"},
        headers=_auth(token),
    )
    assert resp.status_code == 400


async def test_list_users_admin_only(client, admin_user):
    token = await _login_admin(client)
    resp = await client.get("/api/auth/users", headers=_auth(token))
    assert resp.status_code == 200
    usernames = [u["username"] for u in resp.json()]
    assert "chief" in usernames

    non_admin_token = await _login_as("chief", "Admin@12345", client)
    # register a servant then try as that servant
    await client.post(
        "/api/auth/register",
        json={
            "username": "servant_a",
            "full_name": "Servant A",
            "role": "servant",
            "password": "Servant@12345",
        },
        headers=_auth(token),
    )
    servant_token = await _login_as("servant_a", "Servant@12345", client)
    resp2 = await client.get("/api/auth/users", headers=_auth(servant_token))
    assert resp2.status_code == 403


async def test_delete_user(client, admin_user):
    token = await _login_admin(client)
    # create a target user
    created = await client.post(
        "/api/auth/register",
        json={
            "username": "victim1",
            "full_name": "Victim One",
            "role": "servant",
            "password": "Victim@12345",
        },
        headers=_auth(token),
    )
    victim_id = created.json()["id"]

    resp = await client.delete(f"/api/auth/users/{victim_id}", headers=_auth(token))
    assert resp.status_code == 200

    # deleted user is gone from the list
    users = await client.get("/api/auth/users", headers=_auth(token))
    ids = [u["id"] for u in users.json()]
    assert victim_id not in ids


async def test_cannot_delete_self(client, admin_user):
    token = await _login_admin(client)
    me = await client.get("/api/auth/me", headers=_auth(token))
    my_id = me.json()["id"]
    resp = await client.delete(f"/api/auth/users/{my_id}", headers=_auth(token))
    assert resp.status_code == 400


async def test_delete_missing_user(client, admin_user):
    token = await _login_admin(client)
    resp = await client.delete("/api/auth/users/999999", headers=_auth(token))
    assert resp.status_code == 404


async def test_change_role(client, admin_user):
    token = await _login_admin(client)
    created = await client.post(
        "/api/auth/register",
        json={
            "username": "worker1",
            "full_name": "Worker One",
            "role": "servant",
            "password": "Worker@12345",
        },
        headers=_auth(token),
    )
    uid = created.json()["id"]

    resp = await client.put(
        f"/api/auth/users/{uid}/role",
        json={"role": "priest"},
        headers=_auth(token),
    )
    assert resp.status_code == 200
    assert resp.json()["role"] == "priest"

    bad = await client.put(
        f"/api/auth/users/{uid}/role",
        json={"role": "superuser"},
        headers=_auth(token),
    )
    assert bad.status_code == 400


async def test_change_role_missing_user(client, admin_user):
    token = await _login_admin(client)
    resp = await client.put(
        "/api/auth/users/999999/role",
        json={"role": "priest"},
        headers=_auth(token),
    )
    assert resp.status_code == 404


async def test_toggle_active(client, admin_user):
    token = await _login_admin(client)
    created = await client.post(
        "/api/auth/register",
        json={
            "username": "offline1",
            "full_name": "Offline One",
            "role": "servant",
            "password": "Offline@12345",
        },
        headers=_auth(token),
    )
    uid = created.json()["id"]

    resp = await client.put(
        f"/api/auth/users/{uid}/active",
        json={"is_active": False},
        headers=_auth(token),
    )
    assert resp.status_code == 200
    assert resp.json()["is_active"] is False

    # deactivated user can no longer log in
    login = await client.post(
        "/api/auth/login",
        data={"username": "offline1", "password": "Offline@12345"},
    )
    assert login.status_code == 403


async def test_cannot_deactivate_self(client, admin_user):
    token = await _login_admin(client)
    me = await client.get("/api/auth/me", headers=_auth(token))
    my_id = me.json()["id"]
    resp = await client.put(
        f"/api/auth/users/{my_id}/active",
        json={"is_active": False},
        headers=_auth(token),
    )
    assert resp.status_code == 400


async def test_toggle_active_missing_user(client, admin_user):
    token = await _login_admin(client)
    resp = await client.put(
        "/api/auth/users/999999/active",
        json={"is_active": False},
        headers=_auth(token),
    )
    assert resp.status_code == 404


# ---------- helpers ----------

async def _login_admin(client) -> str:
    return await _login_as("chief", "Admin@12345", client)


async def _login_as(username: str, password: str, client) -> str:
    resp = await client.post(
        "/api/auth/login",
        data={"username": username, "password": password},
    )
    assert resp.status_code == 200, resp.text
    return resp.json()["access_token"]


def _auth(token: str) -> dict:
    return {"Authorization": f"Bearer {token}"}