# Coptic Church Management System
# نظام إدارةكنيسة قبطية أرثوذكسية

## Overview
A comprehensive management system for Coptic Orthodox Churches covering:
- Families & Persons management
- Services & Service Assignments
- Servants & Beneficiaries
- Confessions management
- Pastoral care (Visits & Follow-up)
- Dashboard & Reports
- Role-based access control
- Audit logging
- Duplicate detection & Person merging
- Automatic service assignment by age/education stage

## Tech Stack

### Backend
- Python 3.11+
- FastAPI
- SQLAlchemy 2.0 (Async)
- PostgreSQL
- Alembic (Migrations)
- JWT Authentication

### Frontend
- Next.js 14
- TypeScript
- Tailwind CSS
- RTL Arabic Layout
- Recharts

## Quick Start

### Prerequisites
- Python 3.11+
- Node.js 18+
- PostgreSQL

### 1. Database Setup
```sql
CREATE DATABASE church_management;
```

### 2. Backend Setup
```bash
cd backend
python -m venv venv
# Windows:
venv\Scripts\activate
# Linux/Mac:
source venv/bin/activate

pip install -r requirements.txt

# Copy and configure .env
cp .env.example .env

# Run migrations
alembic upgrade head

# Seed database
python scripts/seed.py

# Start server
uvicorn app.main:app --reload --port 8000
```

### 3. Frontend Setup
```bash
cd frontend
npm install
npm run dev
```

### 4. Access
- Frontend: http://localhost:3000
- API Docs: http://localhost:8000/docs

## Default Users
| Username | Password | Role |
|----------|----------|------|
| admin | admin123 | Admin |
| priest | priest123 | Priest |
| servant | servant123 | Servant |

## Architecture

### Database Design
```
Person (ONE record per person)
  ├── Family Membership
  ├── Service Assignment
  ├── Servant Assignment (in ServiceMember)
  ├── Visits
  ├── Confessions
  └── Priest Profile
```

**Key Principle:** ONE PERSON = ONE RECORD. No data duplication.

### API Modules
- `/api/auth` - Authentication & User management
- `/api/families` - Family management with wizard
- `/api/persons` - Person CRUD with duplicate detection
- `/api/services` - Service management & assignments
- `/api/visits` - Pastoral care & follow-up
- `/api/confessions` - Confession management
- `/api/dashboard` - Statistics & overview
- `/api/search` - Global search
- `/api/reports` - Reports & CSV export
- `/api/audit` - Audit logging
- `/api/priests` - Priest management
- `/api/settings` - System settings

### Features
1. **Family Wizard** - 5-step family creation
2. **Auto Service Assignment** - Based on age/education stage
3. **Duplicate Detection** - Smart person matching
4. **Person Merge** - Admin tool for merging duplicates
5. **Role-Based Access** - Admin, Priest, Service Admin, Servant
6. **Audit Logging** - Track all changes
7. **Global Search** - Search across all entities
8. **Reports** - Exportable CSV reports

## Environment Variables
See `.env.example` for required configuration.

## License
Private - For church use only.
