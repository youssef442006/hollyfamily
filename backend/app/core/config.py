from pydantic_settings import BaseSettings
from typing import List
import json
import logging

logger = logging.getLogger(__name__)

_INSECURE_KEYS = {
    "your-secret-key-change-this-in-production",
    "your-secret-key-change-this",
}

class Settings(BaseSettings):
    DATABASE_URL: str = "postgresql+asyncpg://postgres:postgres@localhost:5432/church_management"
    DATABASE_URL_SYNC: str = "postgresql://postgres:postgres@localhost:5432/church_management"
    SECRET_KEY: str = "your-secret-key-change-this-in-production"
    ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 480
    APP_NAME: str = "Coptic Church Management System"
    CORS_ORIGINS: str = '["http://localhost:3000","http://localhost:5173"]'

    @property
    def cors_origins_list(self) -> List[str]:
        return json.loads(self.CORS_ORIGINS)

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


settings = Settings()

if settings.SECRET_KEY in _INSECURE_KEYS or len(settings.SECRET_KEY) < 32:
    logger.critical(
        "SECRET_KEY في backend/.env ضعيف أو افتراضي. ولّد واحد قوي: "
        "python -c \"import secrets; print(secrets.token_urlsafe(48))\" ثم ضعه في backend/.env"
    )
