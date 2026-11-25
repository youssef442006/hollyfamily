from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.core.config import settings
from app.api.router import (
    auth, families, persons, services, visits,
    confessions, dashboard, search, audit, priests, settings as settings_router, reports
)

app = FastAPI(title=settings.APP_NAME, version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins_list,
    allow_origin_regex=r"^https?://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+)(:\d+)?$",
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(auth.router)
app.include_router(families.router)
app.include_router(persons.router)
app.include_router(services.router)
app.include_router(visits.router)
app.include_router(confessions.router)
app.include_router(dashboard.router)
app.include_router(search.router)
app.include_router(audit.router)
app.include_router(priests.router)
app.include_router(settings_router.router)
app.include_router(reports.router)


@app.get("/")
async def root():
    return {"message": "Coptic Church Management System API", "version": "1.0.0"}


@app.get("/health")
async def health():
    return {"status": "healthy"}
