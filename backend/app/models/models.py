from sqlalchemy import (
    Column, Integer, String, Boolean, DateTime, Date, Text, Float,
    ForeignKey, CheckConstraint, UniqueConstraint, Index, Enum as SAEnum
)
from sqlalchemy.orm import relationship
from datetime import datetime, date
from app.core.database import Base
import enum


class GenderEnum(str, enum.Enum):
    male = "male"
    female = "female"


class MaritalStatusEnum(str, enum.Enum):
    single = "single"
    married = "married"
    divorced = "divorced"
    widowed = "widowed"


class EducationStageEnum(str, enum.Enum):
    nursery = "nursery"
    angels = "angels"
    primary_1_2 = "primary_1_2"
    primary_3_4 = "primary_3_4"
    primary_5_6 = "primary_5_6"
    preparatory = "preparatory"
    secondary = "secondary"
    university = "university"
    graduate = "graduate"
    mens_gathering = "mens_gathering"


class RelationshipTypeEnum(str, enum.Enum):
    father = "father"
    mother = "mother"
    husband = "husband"
    wife = "wife"
    son = "son"
    daughter = "daughter"
    grandfather = "grandfather"
    grandmother = "grandmother"
    grandson = "grandson"
    granddaughter = "granddaughter"
    relative = "relative"
    other = "other"


class ServiceMemberRoleEnum(str, enum.Enum):
    service_admin = "service_admin"
    assistant_admin = "assistant_admin"
    servant = "servant"
    supervisor = "supervisor"
    other = "other"


class VisitTypeEnum(str, enum.Enum):
    home_visit = "home_visit"
    phone_call = "phone_call"
    follow_up = "follow_up"
    emergency_visit = "emergency_visit"
    other = "other"


class VisitStatusEnum(str, enum.Enum):
    pending = "pending"
    completed = "completed"
    cancelled = "cancelled"
    rescheduled = "rescheduled"


class ConfessionStatusEnum(str, enum.Enum):
    upcoming = "upcoming"
    completed = "completed"
    missed = "missed"
    cancelled = "cancelled"


class UserRoleEnum(str, enum.Enum):
    admin = "admin"
    priest = "priest"
    service_admin = "service_admin"
    servant = "servant"


class User(Base):
    __tablename__ = "users"
    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(100), unique=True, nullable=False, index=True)
    email = Column(String(255), unique=True, nullable=True)
    hashed_password = Column(String(255), nullable=False)
    full_name = Column(String(255), nullable=False)
    role = Column(SAEnum(UserRoleEnum), default=UserRoleEnum.servant, nullable=False)
    is_active = Column(Boolean, default=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    person = relationship("Person", back_populates="user_account", foreign_keys=[person_id])


class Family(Base):
    __tablename__ = "families"
    id = Column(Integer, primary_key=True, index=True)
    family_code = Column(String(50), unique=True, nullable=False, index=True)
    family_name = Column(String(255), nullable=False)
    address = Column(Text, nullable=True)
    street = Column(String(255), nullable=True)
    branch_from = Column(String(255), nullable=True)
    area = Column(String(255), nullable=True)
    landmark = Column(String(255), nullable=True)
    building_number = Column(String(50), nullable=True)
    floor = Column(String(50), nullable=True)
    apartment_number = Column(String(50), nullable=True)
    gps_location = Column(String(255), nullable=True)
    phone = Column(String(50), nullable=True)
    special_case = Column(String(50), nullable=True)
    is_active = Column(Boolean, default=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    members = relationship("FamilyMember", back_populates="family", cascade="all, delete-orphan")
    persons = relationship("Person", back_populates="family")
    visits = relationship("Visit", back_populates="family")


class Person(Base):
    __tablename__ = "persons"
    id = Column(Integer, primary_key=True, index=True)
    family_id = Column(Integer, ForeignKey("families.id"), nullable=True)
    first_name = Column(String(100), nullable=False)
    middle_name = Column(String(100), nullable=True)
    last_name = Column(String(100), nullable=True)
    gender = Column(SAEnum(GenderEnum), nullable=False)
    date_of_birth = Column(Date, nullable=True)
    is_deceased = Column(Boolean, default=False)
    date_of_death = Column(Date, nullable=True)
    phone = Column(String(50), nullable=True)
    whatsapp = Column(String(50), nullable=True)
    second_phone = Column(String(50), nullable=True)
    email = Column(String(255), nullable=True)
    national_id = Column(String(20), unique=True, nullable=True, index=True)
    marital_status = Column(SAEnum(MaritalStatusEnum), nullable=True)
    care_situation = Column(String(50), nullable=True)
    education_stage = Column(SAEnum(EducationStageEnum), nullable=True)
    school_year = Column(String(30), nullable=True)
    confession_father = Column(String(255), nullable=True)
    confession_church = Column(String(255), nullable=True)
    weekly_leave = Column(String(50), nullable=True)
    is_deacon = Column(Boolean, default=False)
    is_servant = Column(Boolean, default=False)
    in_prep_center = Column(Boolean, default=False)
    occupation = Column(String(255), nullable=True)
    address = Column(Text, nullable=True)
    notes = Column(Text, nullable=True)
    is_active = Column(Boolean, default=True)
    is_archived = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    family = relationship("Family", back_populates="persons")
    family_memberships = relationship("FamilyMember", back_populates="person", cascade="all, delete-orphan")
    service_assignments = relationship("ServiceAssignment", back_populates="person", cascade="all, delete-orphan")
    servant_assignments = relationship(
        "ServantAssignment",
        back_populates="servant",
        foreign_keys="ServantAssignment.servant_id",
        cascade="all, delete-orphan"
    )
    visits_as_subject = relationship("Visit", back_populates="person", foreign_keys="Visit.person_id")
    visits_as_servant = relationship("Visit", back_populates="servant", foreign_keys="Visit.servant_id")
    confessions = relationship("Confession", back_populates="person", cascade="all, delete-orphan")
    user_account = relationship("User", back_populates="person", uselist=False, foreign_keys=[User.person_id])


class FamilyMember(Base):
    __tablename__ = "family_members"
    id = Column(Integer, primary_key=True, index=True)
    family_id = Column(Integer, ForeignKey("families.id"), nullable=False)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    relationship_type = Column(SAEnum(RelationshipTypeEnum), nullable=False)
    is_head = Column(Boolean, default=False)
    is_spouse = Column(Boolean, default=False)
    is_child = Column(Boolean, default=False)
    start_date = Column(Date, nullable=True)
    end_date = Column(Date, nullable=True)
    is_current = Column(Boolean, default=True, nullable=False)
    notes = Column(Text, nullable=True)
    is_deleted = Column(Boolean, default=False, nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    family = relationship("Family", back_populates="members")
    person = relationship("Person", back_populates="family_memberships")
    __table_args__ = (
        Index(
            "uq_family_members_current", "family_id", "person_id",
            unique=True,
            postgresql_where=is_current.is_(True) & is_deleted.is_(False),
        ),
    )


class PersonRelationship(Base):
    """Directed kinship link between two persons, independent of family membership.

    Only ONE directed row is stored (e.g. David -> father -> Atef);
    the inverse (Atef -> son -> David) is derived in code via INVERSE map.
    """
    __tablename__ = "person_relationships"
    id = Column(Integer, primary_key=True, index=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    related_person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    relationship_type = Column(String(30), nullable=False)
    start_date = Column(Date, nullable=True)
    end_date = Column(Date, nullable=True)
    is_current = Column(Boolean, default=True, nullable=False)
    notes = Column(Text, nullable=True)
    is_deleted = Column(Boolean, default=False, nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    person = relationship("Person", foreign_keys=[person_id])
    related_person = relationship("Person", foreign_keys=[related_person_id])
    __table_args__ = (
        CheckConstraint("person_id != related_person_id", name="ck_rel_not_self"),
        Index(
            "uq_person_relationships_current",
            "person_id", "related_person_id", "relationship_type",
            unique=True,
            postgresql_where=is_current.is_(True) & is_deleted.is_(False),
        ),
    )


class Service(Base):
    __tablename__ = "services"
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False, unique=True)
    description = Column(Text, nullable=True)
    min_age = Column(Integer, nullable=True)
    max_age = Column(Integer, nullable=True)
    gender_restriction = Column(SAEnum(GenderEnum), nullable=True)
    education_stages = Column(Text, nullable=True)
    is_active = Column(Boolean, default=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    members = relationship("ServiceMember", back_populates="service", cascade="all, delete-orphan")
    assignments = relationship("ServiceAssignment", back_populates="service", cascade="all, delete-orphan")
    servant_assignments = relationship("ServantAssignment", back_populates="service", cascade="all, delete-orphan")


class ServiceMember(Base):
    __tablename__ = "service_members"
    id = Column(Integer, primary_key=True, index=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    service_id = Column(Integer, ForeignKey("services.id"), nullable=False)
    role = Column(SAEnum(ServiceMemberRoleEnum), default=ServiceMemberRoleEnum.servant, nullable=False)
    start_date = Column(Date, nullable=True)
    end_date = Column(Date, nullable=True)
    is_active = Column(Boolean, default=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    person = relationship("Person", foreign_keys=[person_id])
    service = relationship("Service", back_populates="members")


class ServiceAssignment(Base):
    __tablename__ = "service_assignments"
    id = Column(Integer, primary_key=True, index=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    service_id = Column(Integer, ForeignKey("services.id"), nullable=False)
    assigned_by = Column(Integer, ForeignKey("users.id"), nullable=True)
    assigned_at = Column(DateTime, default=datetime.utcnow)
    status = Column(String(20), default="active")
    notes = Column(Text, nullable=True)
    person = relationship("Person", back_populates="service_assignments")
    service = relationship("Service", back_populates="assignments")
    assigner = relationship("User", foreign_keys=[assigned_by])


class ServantAssignment(Base):
    __tablename__ = "servant_assignments"
    id = Column(Integer, primary_key=True, index=True)
    servant_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    service_id = Column(Integer, ForeignKey("services.id"), nullable=False)
    assigned_at = Column(DateTime, default=datetime.utcnow)
    is_active = Column(Boolean, default=True)
    servant = relationship("Person", back_populates="servant_assignments", foreign_keys=[servant_id])
    person = relationship("Person", foreign_keys=[person_id])
    service = relationship("Service", back_populates="servant_assignments")


class Priest(Base):
    __tablename__ = "priests"
    id = Column(Integer, primary_key=True, index=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=True)
    display_name = Column(String(255), nullable=False)
    phone = Column(String(50), nullable=True)
    is_active = Column(Boolean, default=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    person = relationship("Person", foreign_keys=[person_id])


class Visit(Base):
    __tablename__ = "visits"
    id = Column(Integer, primary_key=True, index=True)
    family_id = Column(Integer, ForeignKey("families.id"), nullable=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=True)
    servant_id = Column(Integer, ForeignKey("persons.id"), nullable=True)
    visit_date = Column(DateTime, nullable=False)
    visit_type = Column(SAEnum(VisitTypeEnum), nullable=False)
    status = Column(SAEnum(VisitStatusEnum), default=VisitStatusEnum.pending)
    notes = Column(Text, nullable=True)
    next_visit_date = Column(DateTime, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    family = relationship("Family", back_populates="visits")
    person = relationship("Person", back_populates="visits_as_subject", foreign_keys=[person_id])
    servant = relationship("Person", back_populates="visits_as_servant", foreign_keys=[servant_id])


class Confession(Base):
    __tablename__ = "confessions"
    id = Column(Integer, primary_key=True, index=True)
    person_id = Column(Integer, ForeignKey("persons.id"), nullable=False)
    priest_id = Column(Integer, ForeignKey("priests.id"), nullable=True)
    confession_date = Column(DateTime, nullable=False)
    next_confession_date = Column(DateTime, nullable=True)
    status = Column(SAEnum(ConfessionStatusEnum), default=ConfessionStatusEnum.upcoming)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    person = relationship("Person", back_populates="confessions")
    priest = relationship("Priest", foreign_keys=[priest_id])


class AuditLog(Base):
    __tablename__ = "audit_logs"
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=True)
    action = Column(String(100), nullable=False)
    table_name = Column(String(100), nullable=False)
    record_id = Column(Integer, nullable=True)
    old_value = Column(Text, nullable=True)
    new_value = Column(Text, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow, index=True)
    user = relationship("User", foreign_keys=[user_id])


class Setting(Base):
    __tablename__ = "settings"
    id = Column(Integer, primary_key=True, index=True)
    key = Column(String(100), unique=True, nullable=False)
    value = Column(Text, nullable=True)
    description = Column(Text, nullable=True)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
