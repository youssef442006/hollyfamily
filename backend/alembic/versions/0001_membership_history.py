"""family membership history + person relationships

Revision ID: 0001_membership_history
Revises:
Create Date: 2026-09-11

- family_members: add start_date, end_date, is_current, notes,
  is_deleted, updated_at; backfill existing rows; replace the
  total UNIQUE(family_id, person_id) with a partial unique index
  covering current memberships only.
- create person_relationships table (directed kinship links,
  single row per direction pair enforced by partial unique index).
All operations are additive / idempotent. No data is deleted.
"""
from alembic import op
import sqlalchemy as sa

revision = "0001_membership_history"
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    # --- family_members: new history columns (idempotent) ---
    op.execute("ALTER TABLE family_members ADD COLUMN IF NOT EXISTS start_date DATE")
    op.execute("ALTER TABLE family_members ADD COLUMN IF NOT EXISTS end_date DATE")
    op.execute("ALTER TABLE family_members ADD COLUMN IF NOT EXISTS is_current BOOLEAN DEFAULT TRUE")
    op.execute("ALTER TABLE family_members ADD COLUMN IF NOT EXISTS notes TEXT")
    op.execute("ALTER TABLE family_members ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT FALSE")
    op.execute("ALTER TABLE family_members ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP")

    # --- backfill existing rows (all current, started at creation) ---
    op.execute("""
        UPDATE family_members
        SET is_current = TRUE,
            is_deleted = FALSE,
            start_date = COALESCE(start_date, created_at::date),
            updated_at = COALESCE(updated_at, created_at)
        WHERE is_current IS NULL OR is_deleted IS NULL OR start_date IS NULL
    """)
    op.execute("ALTER TABLE family_members ALTER COLUMN is_current SET DEFAULT TRUE")
    op.execute("ALTER TABLE family_members ALTER COLUMN is_current SET NOT NULL")
    op.execute("ALTER TABLE family_members ALTER COLUMN is_deleted SET DEFAULT FALSE")
    op.execute("ALTER TABLE family_members ALTER COLUMN is_deleted SET NOT NULL")

    # --- replace total unique constraint with partial unique index ---
    op.execute("ALTER TABLE family_members DROP CONSTRAINT IF EXISTS uq_family_person")
    op.execute("""
        CREATE UNIQUE INDEX IF NOT EXISTS uq_family_members_current
        ON family_members (family_id, person_id)
        WHERE is_current = TRUE AND is_deleted = FALSE
    """)
    op.execute("CREATE INDEX IF NOT EXISTS ix_family_members_person ON family_members (person_id)")
    op.execute("CREATE INDEX IF NOT EXISTS ix_family_members_current ON family_members (person_id) WHERE is_current = TRUE AND is_deleted = FALSE")

    # --- person_relationships table ---
    op.create_table(
        "person_relationships",
        sa.Column("id", sa.Integer(), primary_key=True, index=True),
        sa.Column("person_id", sa.Integer(), sa.ForeignKey("persons.id"), nullable=False),
        sa.Column("related_person_id", sa.Integer(), sa.ForeignKey("persons.id"), nullable=False),
        sa.Column("relationship_type", sa.String(30), nullable=False),
        sa.Column("start_date", sa.Date(), nullable=True),
        sa.Column("end_date", sa.Date(), nullable=True),
        sa.Column("is_current", sa.Boolean(), nullable=False, server_default=sa.true()),
        sa.Column("notes", sa.Text(), nullable=True),
        sa.Column("is_deleted", sa.Boolean(), nullable=False, server_default=sa.false()),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
        sa.CheckConstraint("person_id != related_person_id", name="ck_rel_not_self"),
    )
    op.execute("""
        CREATE UNIQUE INDEX IF NOT EXISTS uq_person_relationships_current
        ON person_relationships (person_id, related_person_id, relationship_type)
        WHERE is_current = TRUE AND is_deleted = FALSE
    """)
    op.execute("CREATE INDEX IF NOT EXISTS ix_person_relationships_person ON person_relationships (person_id) WHERE is_deleted = FALSE")
    op.execute("CREATE INDEX IF NOT EXISTS ix_person_relationships_related ON person_relationships (related_person_id) WHERE is_deleted = FALSE")


def downgrade() -> None:
    op.execute("DROP INDEX IF EXISTS uq_person_relationships_current")
    op.execute("DROP INDEX IF EXISTS ix_person_relationships_person")
    op.execute("DROP INDEX IF EXISTS ix_person_relationships_related")
    op.drop_table("person_relationships")
    op.execute("DROP INDEX IF EXISTS uq_family_members_current")
    op.execute("DROP INDEX IF EXISTS ix_family_members_person")
    op.execute("DROP INDEX IF EXISTS ix_family_members_current")
    op.execute("""
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_family_person') THEN
                ALTER TABLE family_members ADD CONSTRAINT uq_family_person UNIQUE (family_id, person_id);
            END IF;
        END $$;
    """)
