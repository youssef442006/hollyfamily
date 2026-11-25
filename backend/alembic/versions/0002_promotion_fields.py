"""person promotion + confession father fields

Revision ID: 0002_promotion_fields
Revises: 0001_membership_history
Create Date: 2026-09-17

- persons: add school_year (exact grade within the stage) and
  confession_father (free text or preset priest name).
Both are additive / idempotent. No data is deleted.
"""
from alembic import op

revision = "0002_promotion_fields"
down_revision = "0001_membership_history"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS school_year VARCHAR(30)")
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS confession_father VARCHAR(255)")


def downgrade() -> None:
    op.execute("ALTER TABLE persons DROP COLUMN IF EXISTS confession_father")
    op.execute("ALTER TABLE persons DROP COLUMN IF EXISTS school_year")