from pydantic import BaseModel, EmailStr, model_validator
from typing import Optional, List
from datetime import date, datetime
from app.models.models import (
    GenderEnum, MaritalStatusEnum, EducationStageEnum, RelationshipTypeEnum,
    ServiceMemberRoleEnum, VisitTypeEnum, VisitStatusEnum, ConfessionStatusEnum, UserRoleEnum
)


class Token(BaseModel):
    access_token: str
    token_type: str


class TokenData(BaseModel):
    username: Optional[str] = None


class UserBase(BaseModel):
    username: str
    email: Optional[str] = None
    full_name: str
    role: UserRoleEnum = UserRoleEnum.servant
    person_id: Optional[int] = None


class UserCreate(UserBase):
    password: str


class UserResponse(UserBase):
    id: int
    is_active: bool
    created_at: datetime
    class Config:
        from_attributes = True


class FamilyBase(BaseModel):
    family_name: str
    address: Optional[str] = None
    street: Optional[str] = None
    branch_from: Optional[str] = None
    area: Optional[str] = None
    landmark: Optional[str] = None
    building_number: Optional[str] = None
    floor: Optional[str] = None
    apartment_number: Optional[str] = None
    gps_location: Optional[str] = None
    phone: Optional[str] = None
    special_case: Optional[str] = None
    notes: Optional[str] = None


class FamilyCreate(FamilyBase):
    family_code: Optional[str] = None


class FamilyUpdate(BaseModel):
    family_name: Optional[str] = None
    address: Optional[str] = None
    street: Optional[str] = None
    branch_from: Optional[str] = None
    area: Optional[str] = None
    landmark: Optional[str] = None
    building_number: Optional[str] = None
    floor: Optional[str] = None
    apartment_number: Optional[str] = None
    gps_location: Optional[str] = None
    phone: Optional[str] = None
    special_case: Optional[str] = None
    notes: Optional[str] = None
    is_active: Optional[bool] = None


class FamilyDuplicateCheck(BaseModel):
    family_name: Optional[str] = None
    father_first_name: Optional[str] = None
    father_middle_name: Optional[str] = None
    father_last_name: Optional[str] = None
    phone: Optional[str] = None


class FamilyResponse(FamilyBase):
    id: int
    family_code: str
    is_active: bool
    created_at: datetime
    updated_at: Optional[datetime] = None
    member_count: Optional[int] = 0
    class Config:
        from_attributes = True


class PersonBase(BaseModel):
    first_name: str
    middle_name: Optional[str] = None
    last_name: Optional[str] = None
    gender: GenderEnum
    date_of_birth: Optional[date] = None
    is_deceased: Optional[bool] = False
    date_of_death: Optional[date] = None
    phone: Optional[str] = None
    whatsapp: Optional[str] = None
    second_phone: Optional[str] = None
    email: Optional[str] = None
    national_id: Optional[str] = None
    marital_status: Optional[MaritalStatusEnum] = None
    care_situation: Optional[str] = None
    education_stage: Optional[EducationStageEnum] = None
    school_year: Optional[str] = None
    confession_father: Optional[str] = None
    confession_church: Optional[str] = None
    weekly_leave: Optional[str] = None
    is_deacon: Optional[bool] = False
    is_servant: Optional[bool] = False
    in_prep_center: Optional[bool] = False
    occupation: Optional[str] = None
    address: Optional[str] = None
    notes: Optional[str] = None


class PersonCreate(PersonBase):
    family_id: Optional[int] = None
    suggested_service_id: Optional[int] = None

    @model_validator(mode="before")
    @classmethod
    def empty_strings_to_none(cls, data):
        if isinstance(data, dict):
            for k in ("date_of_birth", "date_of_death"):
                if data.get(k) == "":
                    data[k] = None
        return data


class PersonUpdate(BaseModel):
    first_name: Optional[str] = None
    middle_name: Optional[str] = None
    last_name: Optional[str] = None
    gender: Optional[GenderEnum] = None
    date_of_birth: Optional[date] = None
    is_deceased: Optional[bool] = None
    date_of_death: Optional[date] = None
    phone: Optional[str] = None
    whatsapp: Optional[str] = None
    second_phone: Optional[str] = None
    email: Optional[str] = None
    national_id: Optional[str] = None
    marital_status: Optional[MaritalStatusEnum] = None
    care_situation: Optional[str] = None
    education_stage: Optional[EducationStageEnum] = None
    school_year: Optional[str] = None
    confession_father: Optional[str] = None
    confession_church: Optional[str] = None
    weekly_leave: Optional[str] = None
    is_deacon: Optional[bool] = None
    is_servant: Optional[bool] = None
    in_prep_center: Optional[bool] = None
    occupation: Optional[str] = None
    address: Optional[str] = None
    notes: Optional[str] = None
    is_active: Optional[bool] = None


class PersonResponse(PersonBase):
    id: int
    family_id: Optional[int] = None
    is_active: bool
    created_at: datetime
    full_name_display: Optional[str] = None
    class Config:
        from_attributes = True


class FamilyMemberBase(BaseModel):
    person_id: int
    relationship_type: RelationshipTypeEnum
    is_head: bool = False
    is_spouse: bool = False
    is_child: bool = False
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    is_current: bool = True
    notes: Optional[str] = None


class FamilyMemberCreate(FamilyMemberBase):
    pass


class FamilyMemberUpdate(BaseModel):
    relationship_type: Optional[RelationshipTypeEnum] = None
    is_head: Optional[bool] = None
    is_spouse: Optional[bool] = None
    is_child: Optional[bool] = None
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    is_current: Optional[bool] = None
    notes: Optional[str] = None


class FamilyMemberResponse(FamilyMemberBase):
    id: int
    family_id: int
    is_deleted: bool = False
    created_at: datetime
    updated_at: Optional[datetime] = None
    person: Optional[PersonResponse] = None
    class Config:
        from_attributes = True


class PersonRelationshipBase(BaseModel):
    related_person_id: int
    relationship_type: str
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    is_current: bool = True
    notes: Optional[str] = None


class PersonRelationshipCreate(PersonRelationshipBase):
    pass


class PersonRelationshipUpdate(BaseModel):
    relationship_type: Optional[str] = None
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    is_current: Optional[bool] = None
    notes: Optional[str] = None


class PersonRelationshipResponse(PersonRelationshipBase):
    id: int
    person_id: int
    is_deleted: bool = False
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None
    related_person_name: Optional[str] = None
    class Config:
        from_attributes = True


class ServiceBase(BaseModel):
    name: str
    description: Optional[str] = None
    min_age: Optional[int] = None
    max_age: Optional[int] = None
    gender_restriction: Optional[GenderEnum] = None
    education_stages: Optional[str] = None
    notes: Optional[str] = None


class ServiceCreate(ServiceBase):
    pass


class ServiceUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    min_age: Optional[int] = None
    max_age: Optional[int] = None
    gender_restriction: Optional[GenderEnum] = None
    education_stages: Optional[str] = None
    is_active: Optional[bool] = None
    notes: Optional[str] = None


class ServiceResponse(ServiceBase):
    id: int
    is_active: bool
    created_at: datetime
    member_count: Optional[int] = 0
    assignment_count: Optional[int] = 0
    class Config:
        from_attributes = True


class ServiceMemberBase(BaseModel):
    person_id: int
    service_id: int
    role: ServiceMemberRoleEnum = ServiceMemberRoleEnum.servant
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    notes: Optional[str] = None


class ServiceMemberCreate(ServiceMemberBase):
    pass


class ServiceMemberResponse(ServiceMemberBase):
    id: int
    is_active: bool
    created_at: datetime
    person: Optional[PersonResponse] = None
    class Config:
        from_attributes = True


class ServiceAssignmentBase(BaseModel):
    person_id: int
    service_id: int
    notes: Optional[str] = None


class ServiceAssignmentCreate(ServiceAssignmentBase):
    pass


class ServiceAssignmentResponse(ServiceAssignmentBase):
    id: int
    assigned_by: Optional[int] = None
    assigned_at: datetime
    status: str
    person: Optional[PersonResponse] = None
    class Config:
        from_attributes = True


class ServantAssignmentBase(BaseModel):
    servant_id: int
    person_id: int
    service_id: int


class ServantAssignmentCreate(ServantAssignmentBase):
    pass


class ServantAssignmentResponse(ServantAssignmentBase):
    id: int
    assigned_at: datetime
    is_active: bool
    class Config:
        from_attributes = True


class PriestBase(BaseModel):
    display_name: str
    person_id: Optional[int] = None
    phone: Optional[str] = None
    notes: Optional[str] = None


class PriestCreate(PriestBase):
    pass


class PriestResponse(PriestBase):
    id: int
    is_active: bool
    created_at: datetime
    class Config:
        from_attributes = True


class VisitBase(BaseModel):
    family_id: Optional[int] = None
    person_id: Optional[int] = None
    servant_id: Optional[int] = None
    visit_date: datetime
    visit_type: VisitTypeEnum
    notes: Optional[str] = None
    next_visit_date: Optional[datetime] = None


class VisitCreate(VisitBase):
    pass


class VisitUpdate(BaseModel):
    visit_date: Optional[datetime] = None
    visit_type: Optional[VisitTypeEnum] = None
    status: Optional[VisitStatusEnum] = None
    notes: Optional[str] = None
    next_visit_date: Optional[datetime] = None


class VisitResponse(VisitBase):
    id: int
    status: VisitStatusEnum
    created_at: datetime
    updated_at: Optional[datetime] = None
    person_name: Optional[str] = None
    servant_name: Optional[str] = None
    family_name: Optional[str] = None
    class Config:
        from_attributes = True


class ConfessionBase(BaseModel):
    person_id: int
    priest_id: Optional[int] = None
    confession_date: datetime
    next_confession_date: Optional[datetime] = None
    notes: Optional[str] = None


class ConfessionCreate(ConfessionBase):
    pass


class ConfessionUpdate(BaseModel):
    priest_id: Optional[int] = None
    confession_date: Optional[datetime] = None
    next_confession_date: Optional[datetime] = None
    status: Optional[ConfessionStatusEnum] = None
    notes: Optional[str] = None


class ConfessionResponse(ConfessionBase):
    id: int
    status: ConfessionStatusEnum
    created_at: datetime
    person_name: Optional[str] = None
    priest_name: Optional[str] = None
    class Config:
        from_attributes = True


class AuditLogResponse(BaseModel):
    id: int
    user_id: Optional[int] = None
    action: str
    table_name: str
    record_id: Optional[int] = None
    old_value: Optional[str] = None
    new_value: Optional[str] = None
    timestamp: datetime
    username: Optional[str] = None
    class Config:
        from_attributes = True


class SettingBase(BaseModel):
    key: str
    value: Optional[str] = None
    description: Optional[str] = None


class SettingCreate(SettingBase):
    pass


class SettingResponse(SettingBase):
    id: int
    updated_at: Optional[datetime] = None
    class Config:
        from_attributes = True


class DashboardStats(BaseModel):
    total_families: int = 0
    total_persons: int = 0
    total_children: int = 0
    total_servants: int = 0
    total_services: int = 0
    total_beneficiaries: int = 0
    persons_without_service: int = 0
    families_needing_visit: int = 0
    upcoming_confessions: int = 0
    service_stats: List[dict] = []


class DuplicateCheckResult(BaseModel):
    is_duplicate: bool
    potential_matches: List[PersonResponse] = []
    confidence: float = 0.0


class MergeRequest(BaseModel):
    primary_person_id: int
    duplicate_person_id: int


class GlobalSearchResult(BaseModel):
    persons: List[PersonResponse] = []
    families: List[FamilyResponse] = []
    services: List[ServiceResponse] = []


class FamilyWizardStep1(BaseModel):
    family_name: Optional[str] = None
    address: Optional[str] = None
    street: Optional[str] = None
    branch_from: Optional[str] = None
    area: Optional[str] = None
    landmark: Optional[str] = None
    building_number: Optional[str] = None
    floor: Optional[str] = None
    apartment_number: Optional[str] = None
    gps_location: Optional[str] = None
    phone: Optional[str] = None
    special_case: Optional[str] = None
    notes: Optional[str] = None


class FamilyWizardStep2(BaseModel):
    father: Optional[PersonCreate] = None
    mother: Optional[PersonCreate] = None


class FamilyWizardStep3(BaseModel):
    children: List[PersonCreate] = []


class FamilyWizardComplete(BaseModel):
    family: FamilyWizardStep1
    father: Optional[PersonCreate] = None
    mother: Optional[PersonCreate] = None
    grandfather: Optional[PersonCreate] = None
    grandmother: Optional[PersonCreate] = None
    children: List[dict] = []


class CreateFamilyFromPerson(BaseModel):
    family_name: Optional[str] = None
    role: Optional[str] = None
    spouse_person_id: Optional[int] = None
    spouse_new: Optional[PersonCreate] = None
    spouse_role: Optional[str] = None
    end_old_membership: bool = True
    end_date: Optional[date] = None


class ServiceSuggestion(BaseModel):
    person_id: int
    person_name: str
    suggested_service_id: int
    suggested_service_name: str
    reason: str
