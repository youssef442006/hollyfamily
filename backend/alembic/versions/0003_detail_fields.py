"""person + family detail fields

Revision ID: 0003_detail_fields
Revises: 0002_promotion_fields
Create Date: 2026-09-18

- persons: add second_phone, confession_church, weekly_leave,
  is_deacon, is_servant, in_prep_center.
- families: add street, branch_from, area, landmark, building_number,
  floor, apartment_number, gps_location.
All additive / idempotent. No data is deleted.
"""
from alembic import op

revision = "0003_detail_fields"
down_revision = "0002_promotion_fields"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS second_phone VARCHAR(50)")
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS confession_church VARCHAR(255)")
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS weekly_leave VARCHAR(50)")
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS is_deacon BOOLEAN DEFAULT FALSE")
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS is_servant BOOLEAN DEFAULT FALSE")
    op.execute("ALTER TABLE persons ADD COLUMN IF NOT EXISTS in_prep_center BOOLEAN DEFAULT FALSE")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS street VARCHAR(255)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS branch_from VARCHAR(255)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS area VARCHAR(255)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS landmark VARCHAR(255)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS building_number VARCHAR(50)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS floor VARCHAR(50)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS apartment_number VARCHAR(50)")
    op.execute("ALTER TABLE families ADD COLUMN IF NOT EXISTS gps_location VARCHAR(255)")


def downgrade() -> None:
    for col in ["gps_location", "apartment_number", "floor", "building_number", "landmark", "area", "branch_from", "street"]:
        op.execute(f"ALTER TABLE families DROP COLUMN IF EXISTS {col}")
    for col in ["in_prep_center", "is_servant", "is_deacon", "weekly_leave", "confession_church", "second_phone"]:
        op.execute(f"ALTER TABLE persons DROP COLUMN IF EXISTS {col}")