from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.models import Setting

DEFAULT_CONFESSION_INTERVAL_DAYS = 30
DEFAULT_VISIT_INTERVAL_DAYS = 90

CONFESSION_INTERVAL_KEY = "confession_interval_days"
VISIT_INTERVAL_KEY = "visit_interval_days"


async def get_setting_int(db: AsyncSession, key: str, default: int) -> int:
    """Read an integer setting; fall back to ``default`` when missing/invalid."""
    result = await db.execute(select(Setting).where(Setting.key == key))
    setting = result.scalar_one_or_none()
    if setting is None or not setting.value:
        return default
    try:
        return int(float(str(setting.value).strip()))
    except (TypeError, ValueError):
        return default