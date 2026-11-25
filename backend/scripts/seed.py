import sys
import os
import random
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from sqlalchemy import create_engine, select
from sqlalchemy.orm import sessionmaker
from app.core.config import settings
from app.core.database import Base
from app.core.security import get_password_hash
from app.models.models import (
    User, Family, Person, FamilyMember, PersonRelationship, Service, ServiceMember,
    ServiceAssignment, ServantAssignment, Priest, Visit, Confession, Setting,
    GenderEnum, MaritalStatusEnum, EducationStageEnum, RelationshipTypeEnum,
    ServiceMemberRoleEnum, VisitTypeEnum, VisitStatusEnum, ConfessionStatusEnum,
    UserRoleEnum
)
from datetime import datetime, date, timedelta

random.seed(42)

FAMILY_COUNT = 300
CONFESSION_INTERVAL = 30
VISIT_INTERVAL = 90

engine = create_engine(settings.DATABASE_URL_SYNC, echo=False)
SessionLocal = sessionmaker(bind=engine)

FAMILY_SURNAMES = [
    "جرجس", "حنا", "صليب", "فهمي", "ميخائيل", "عبد المسيح", "سمعان", "يعقوب",
    "إسحق", "بولس", "بطرس", "فيلبس", "مرقس", "منسى", "لوقا", "نصير", "عياد",
    "فلتاؤس", "شحاتة", "عوض", "حبيب", "كامل", "رياض", "سمير", "جودة", "يوسف",
    "داود", "إبراهيم", "موسى", "سليمان", "زكريا", "يونان", "دانيال", "حزقيال",
    "أشعياء", "ميخا", "صفنيا", "بارثا", "ساويرس", "توما", "شكرالله", "منير",
    "عزيز", "ناجي", "وديع", "حليم", "نبيل", "شفيق", "صدقي", "أنيس", "ريا",
]

MALE_NAMES = [
    "يوسف", "مينا", "كيرلس", "بطرس", "مرقس", "لوقا", "يوحنا", "متى", "أندراوس",
    "فيلبس", "توما", "يعقوب", "سمعان", "مكاريوس", "أنطونيوس", "أثناسيوس",
    "بيشوي", "أبانوب", "بولا", "صليب", "جوزيف", "ميشيل", "موريال", "بشاي",
    "ياكوب", "فارس", "خرجي", "ميلاد", "صموئيل", "هاني", "عادل", "سمير", "وائل",
    "رزق", "عشم", "جورج", "لوكاس", "إنجيلوس", "تادرس", "شنودة", "كرلس", "أرماني",
    "بسنت", "أشرف", "ماجد", "ماهر", "مسعد", "بطرقان", "دميان", "أبرام",
]

FEMALE_NAMES = [
    "مريم", "مارية", "ماري", "هيلانة", "كاترين", "مارينا", "ريتا", "ميرنا",
    "هبة", "منى", "سهر", "فيفيان", "ماجدة", "جيهان", "إلهام", "رانيا",
    "نورهان", "ميرفت", "كرستين", "فريدة", "روفيدة", "سارة", "باسنت", "تيفاني",
    "سناء", "حورية", "إيمان", "آية", "ماريانا", "كيريا", "تمارا", "خيري",
    "هالة", "سحر", "عايدة", "أمينة", "جمالات", "نبوية", "سمية", "عزيزة",
]

OCCUPATIONS = [
    "مهندس", "مهندسة", "طبيب", "طبيبة", "محاسب", "محاسبة", "معلم", "مدرسة",
    "ممرض", "ممرضة", "صيدلي", "صيدلانية", "محام", "موظف حكومي", "موظفة",
    "تاجر", "صاحب محل", "موظف بنك", "مزارع", "كهربائي", "نجار", "سائق",
    "صاحب ورشة", "استشاري", "أخصائي علاج طبيعي",
]

STREETS = [
    "شارع الجمهورية", "شارع شبرا", "شارع كلية البنات", "شارع الترعة البولاقية",
    "شارع الملك فيصل", "شارع الهرم", "طريق النصر", "شارع العروبة",
    "طريق صلاح سالم", "شارع التسعين", "شارع الوفاء والأمل", "شارع الجيش",
    "شارع محمد نجيب", "شارع النصر", "شارع الحرية", "شارع السلام",
    "شارع السادات", "شارع الكنيسة", "شارع المستشفى", "شارع النعام",
    "ميدان الطيران", "شارع عباس العقاد", "شارع مصطفى النحاس", "شارع الحجاز",
]

CARE_CASES = [
    "أسرة تحتاج متابعة مستمرة", "حالة اجتماعية صعبة", "مسن يحتاج رعاية",
    "أسرة لديها ابن ذي احتياجات خاصة", "قريب للخدمة حديثًا",
]

EDU_RULES = {
    "nursery": [0, 3], "angels": [3, 5], "primary_1_2": [5, 7],
    "primary_3_4": [7, 9], "primary_5_6": [9, 11], "preparatory": [11, 14],
    "secondary": [14, 17], "university": [17, 25], "graduate": [25, 120],
}
ALL_STAGES = list(EDU_RULES.keys())


def get_stage(dob):
    today = date.today()
    age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
    for stage, (lo, hi) in EDU_RULES.items():
        if lo <= age < hi:
            return EducationStageEnum(stage)
    return EducationStageEnum.graduate


def random_date(start, end):
    delta = end - start
    return start + timedelta(days=random.randint(0, delta.days))


def make_phone():
    return "01" + "".join(random.choice("0123456789") for _ in range(9))


def seed():
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)
    db = SessionLocal()

    # ---------------- users ----------------
    admin_user = User(
        username="admin", email="admin@church.com", full_name="System Admin",
        role=UserRoleEnum.admin, hashed_password=get_password_hash("admin123"),
    )
    priest_user = User(
        username="priest", email="priest@church.com", full_name="الأب أبانوب",
        role=UserRoleEnum.priest, hashed_password=get_password_hash("priest123"),
    )
    servant_user = User(
        username="servant", email="servant@church.com", full_name="خدمة مريم",
        role=UserRoleEnum.servant, hashed_password=get_password_hash("servant123"),
    )
    db.add_all([admin_user, priest_user, servant_user])
    db.flush()

    # ---------------- services ----------------
    services_data = [
        ("حضانة", "خدمة حضانة الأطفال من 0 حتى 3 سنوات", 0, 3),
        ("ملايكة", "خدمة ملايكة للأطفال من 3 حتى 5 سنوات", 3, 5),
        ("أولى وثانية ابتدائي", "خدمة الصف الأول والثاني الابتدائي", 5, 7),
        ("ثالثة ورابعة ابتدائي", "خدمة الصف الثالث والرابع الابتدائي", 7, 9),
        ("خامسة وسادسة ابتدائي", "خدمة الصف الخامس والسادس الابتدائي", 9, 11),
        ("إعدادي", "خدمة المرحلة الإعدادية", 11, 14),
        ("ثانوي", "خدمة المرحلة الثانوية", 14, 17),
        ("جامعيين", "خدمة طلاب الجامعة", 17, 25),
        ("خريجين", "خدمة الخريجين", 25, 120),
        ("اجتماع الأنبا موسى للرجال", "مواعيد رجال الكنيسة", 25, 120),
        ("اجتماع الأم دولاجي للأمهات", "اجتماع الأمهات في الكنيسة", 25, 120),
    ]
    services = []
    for name, desc, min_age, max_age in services_data:
        svc = Service(name=name, description=desc, min_age=min_age, max_age=max_age, is_active=True)
        db.add(svc)
        services.append(svc)
    db.flush()

    # ---------------- priests ----------------
    priests = []
    for pname, phone in (("الأب أبانوب", "01011111111"), ("الأب كيرلس", "01011111112"),
                         ("الأب مرقس", "01011111113"), ("الأب مكاريوس", "01011111114")):
        pr = Priest(display_name=pname, phone=phone, is_active=True)
        db.add(pr)
        priests.append(pr)
    db.flush()

    # ---------------- families & persons ----------------
    now = date.today()
    nid_used = set()
    families = []
    persons = []
    today = date.today()

    for i in range(FAMILY_COUNT):
        surname = random.choice(FAMILY_SURNAMES)
        street = random.choice(STREETS)
        address = f"{street} - عمارة {random.randint(1, 99)} شقة {random.randint(1, 120)}"
        fam = Family(
            family_code=f"FAM-{i + 1:05d}",
            family_name=f"عائلة {surname}",
            phone=make_phone(),
            address=address,
            special_case=random.choice(CARE_CASES) if random.random() < 0.15 else None,
            is_active=True,
        )
        db.add(fam)
        families.append(fam)
    db.flush()

    def new_nid():
        while True:
            nid = str(random.choice([2, 3])) + "".join(random.choice("0123456789") for _ in range(13))
            if nid not in nid_used:
                nid_used.add(nid)
                return nid

    def add_person(first, last, gender, dob, marital, occ=None, family_id=None, father_name=None):
        p = Person(
            first_name=first, middle_name=father_name or random.choice(MALE_NAMES), last_name=last,
            gender=gender, date_of_birth=dob, phone=make_phone() if random.random() < 0.8 else None,
            whatsapp=make_phone() if random.random() < 0.6 else None,
            national_id=new_nid(), marital_status=marital,
            occupation=occ if random.random() < 0.8 else None,
            education_stage=get_stage(dob), is_active=True,
        )
        if random.random() < 0.12:
            p.care_situation = random.choice(CARE_CASES)
        if gender == GenderEnum.male and dob and (now.year - dob.year) >= 30 and random.random() < 0.05:
            p.is_deacon = True
        if p.education_stage in (EducationStageEnum.preparatory, EducationStageEnum.secondary) and random.random() < 0.08:
            p.in_prep_center = True
        if p.education_stage and p.education_stage.value in ("secondary", "university") and random.random() < 0.5:
            p.weekly_leave = random.choice(["الجمعة", "السبت"])
        p.family_id = family_id
        db.add(p)
        persons.append(p)
        return p

    def rand_age(a, b):
        return random.randint(a, b)

    # build families
    solo_count = int(FAMILY_COUNT * 0.06)
    for fi, fam in enumerate(families):
        if fi < solo_count:
            gender = GenderEnum.male if random.random() < 0.5 else GenderEnum.female
            marital = random.choice([MaritalStatusEnum.single, MaritalStatusEnum.divorced, MaritalStatusEnum.widowed])
            person = add_person(
                random.choice(MALE_NAMES if gender == GenderEnum.male else FEMALE_NAMES),
                random.choice(FAMILY_SURNAMES), gender,
                now - timedelta(days=365 * rand_age(28, 68)), marital,
                family_id=fam.id,
            )
            person.is_head_in_family = True
            continue
        father = add_person(
            random.choice(MALE_NAMES), fam.family_name.split(" ")[1], GenderEnum.male,
            now - timedelta(days=365 * rand_age(32, 68)), MaritalStatusEnum.married,
            family_id=fam.id,
        )
        father.is_head_in_family = True
        mother = add_person(
            random.choice(FEMALE_NAMES), fam.family_name.split(" ")[1], GenderEnum.female,
            now - timedelta(days=365 * rand_age(28, 62)), MaritalStatusEnum.married,
            family_id=fam.id,
        )
        mother.is_spouse_in_family = True
        kids = random.choices([1, 2, 3, 4], weights=[18, 34, 30, 18])[0]
        for _ in range(kids):
            kg = GenderEnum.male if random.random() < 0.5 else GenderEnum.female
            child_age = rand_age(0, 28)
            add_person(
                random.choice(MALE_NAMES if kg == GenderEnum.male else FEMALE_NAMES),
                fam.family_name.split(" ")[1], kg,
                now - timedelta(days=365 * child_age), MaritalStatusEnum.single,
                family_id=fam.id, father_name=father.first_name,
            )

    db.flush()

    # ---------------- family memberships ----------------
    fam_persons = {}
    for p in persons:
        fam_persons.setdefault(p.family_id, []).append((p, getattr(p, "is_head_in_family", False), getattr(p, "is_spouse_in_family", False)))
    memberships = []
    for fam_id, plist in fam_persons.items():
        father = None
        wife = None
        for p, is_head, is_spouse in plist:
            if is_head:
                father = p
            elif is_spouse:
                wife = p
        for p, is_head, is_spouse in plist:
            if is_head:
                rel = RelationshipTypeEnum.father if p.gender == GenderEnum.male else RelationshipTypeEnum.mother
                memberships.append((fam_id, p, rel, True, False, False))
            elif is_spouse:
                rel = RelationshipTypeEnum.mother
                memberships.append((fam_id, p, rel, False, True, False))
            else:
                rel = RelationshipTypeEnum.son if p.gender == GenderEnum.male else RelationshipTypeEnum.daughter
                memberships.append((fam_id, p, rel, False, False, True))
                # directed relationship to parents
                if father:
                    db.add(PersonRelationship(person_id=p.id, related_person_id=father.id,
                                              relationship_type=RelationshipTypeEnum.father,
                                              is_current=True, is_deleted=False))
                if wife:
                    db.add(PersonRelationship(person_id=p.id, related_person_id=wife.id,
                                              relationship_type=RelationshipTypeEnum.mother,
                                              is_current=True, is_deleted=False))
    for fam_id, p, rel, is_head, is_spouse, is_child in memberships:
        db.add(FamilyMember(family_id=fam_id, person_id=p.id, relationship_type=rel,
                            is_head=is_head, is_spouse=is_spouse, is_child=is_child,
                            start_date=now - timedelta(days=365 * 2), is_current=True, is_deleted=False))
    db.flush()

    # assign confession fathers for adults
    priest_names = [pr.display_name for pr in priests]
    for p in persons:
        age = (now - p.date_of_birth).days // 365 if p.date_of_birth else 0
        if age >= 12:
            p.confession_father = random.choice(priest_names) if random.random() < 0.85 else None

    # ---------------- servants / service members ----------------
    servant_pool = [p for p in persons if p.date_of_birth and (now - p.date_of_birth).days // 365 >= 18]
    chosen = random.sample(servant_pool, min(46, len(servant_pool)))
    for sidx, serv in enumerate(chosen):
        serv.is_servant = True
        # each servant serves in 1-2 services
        svc_a = services[sidx % len(services)]
        db.add(ServiceMember(person_id=serv.id, service_id=svc_a.id,
                             role=ServiceMemberRoleEnum.servant,
                             start_date=now - timedelta(days=random.randint(90, 1500)), is_active=True))
        if random.random() < 0.35 and len(services) > 1:
            svc_b = services[(sidx * 3 + 1) % len(services)]
            db.add(ServiceMember(person_id=serv.id, service_id=svc_b.id,
                                 role=ServiceMemberRoleEnum.assistant_admin if random.random() < 0.2 else ServiceMemberRoleEnum.servant,
                                 start_date=now - timedelta(days=random.randint(60, 900)), is_active=True))
    # service admins
    admins_svc = random.sample(chosen, 9)
    for serv, svc in zip(admins_svc, random.sample(services, 9)):
        db.add(ServiceMember(person_id=serv.id, service_id=svc.id,
                             role=ServiceMemberRoleEnum.service_admin,
                             start_date=now - timedelta(days=random.randint(400, 1600)), is_active=True))
    db.flush()

    # ---------------- service assignments (المخدومين) ----------------
    service_assignments = []
    served_records = []
    for p in persons:
        if not p.date_of_birth:
            continue
        age = (now - p.date_of_birth).days // 365
        if age > 26 or age < 3:
            continue
        stage = p.education_stage
        if stage in (EducationStageEnum.nursery,):
            svc = services[0]
        elif stage in (EducationStageEnum.angels,):
            svc = services[1]
        elif stage in (EducationStageEnum.primary_1_2,):
            svc = services[2]
        elif stage in (EducationStageEnum.primary_3_4,):
            svc = services[3]
        elif stage in (EducationStageEnum.primary_5_6,):
            svc = services[4]
        elif stage in (EducationStageEnum.preparatory,):
            svc = services[5]
        elif stage in (EducationStageEnum.secondary,):
            svc = services[6]
        elif stage in (EducationStageEnum.university,):
            svc = services[7]
        else:
            svc = None
        if svc and random.random() < 0.85:
            existing = None
            for sa in service_assignments:
                if sa["person"] == p and sa["service_id"] == svc.id:
                    existing = sa
                    break
            if not existing:
                service_assignments.append({"person": p, "service_id": svc.id})
                served_records.append(ServiceAssignment(person_id=p.id, service_id=svc.id,
                                                        assigned_by=admin_user.id, status="active"))
    db.add_all(served_records)
    db.flush()

    # ---------------- messenger/servant assignments ----------------
    servants_by_person = {}
    sm_rows = (db.execute(select(ServiceMember).where(ServiceMember.is_active == True))).scalars().all()  # noqa: E712
    for sm in sm_rows:
        servants_by_person.setdefault(sm.person_id, []).append(sm.service_id)
    sa_by_svc = {}
    for rec in served_records:
        sa_by_svc.setdefault(rec.service_id, []).append(rec)
    servant_assign_count = 0
    for svc_id, recs in sa_by_svc.items():
        servant_ids = [sid for sid, svc_ids in servants_by_person.items() if svc_id in svc_ids]
        if not servant_ids or not recs:
            continue
        subsample = random.sample(recs, min(len(recs), random.randint(3, 14)))
        for rec in subsample:
            sid = random.choice(servant_ids)
            existing = (db.execute(select(ServantAssignment).where(
                ServantAssignment.servant_id == sid, ServantAssignment.person_id == rec.person_id,
                ServantAssignment.service_id == svc_id, ServantAssignment.is_active == True  # noqa: E712
            ))).scalar_one_or_none()
            if not existing:
                db.add(ServantAssignment(servant_id=sid, person_id=rec.person_id,
                                         service_id=svc_id, is_active=True))
                servant_assign_count += 1
    db.flush()

    # ---------------- settings ----------------
    settings_data = [
        ("church_name", "كنيسة السيدة العذراء مريم", "اسم الكنيسة"),
        ("church_address", "شارع الكنيسة، القاهرة", "عنوان الكنيسة"),
        ("education_stage_rules", '{"nursery": [0,3], "angels": [3,5], "primary_1_2": [5,7], "primary_3_4": [7,9], "primary_5_6": [9,11], "preparatory": [11,14], "secondary": [14,17], "university": [17,25], "graduate": [25,120]}', "قواعد توزيع المراحل"),
        ("confession_interval_days", str(CONFESSION_INTERVAL), "عدد أيام الاعتراف القادم - يُحسب تلقائيًا عند إنشاء اعتراف جديد"),
        ("visit_interval_days", str(VISIT_INTERVAL), "عدد أيام الزيارة القادمة - تُحسب تلقائيًا عند إنشاء زيارة جديدة"),
    ]
    for key, value, desc in settings_data:
        db.add(Setting(key=key, value=value, description=desc))
    db.flush()

    # ---------------- visits ----------------
    now_dt = datetime.utcnow()
    visit_rows = []
    servant_ids = [ch.id for ch in chosen]
    for fam in families:
        count = random.randint(4, 10)
        sched = sorted(random_date(now_dt - timedelta(days=730), now_dt + timedelta(days=30)) for _ in range(count))
        for vdate in sched:
            vtype = random.choices(
                [VisitTypeEnum.home_visit, VisitTypeEnum.phone_call, VisitTypeEnum.follow_up, VisitTypeEnum.emergency_visit],
                weights=[60, 25, 12, 3])[0]
            if vdate > now_dt:
                status = VisitStatusEnum.pending
                next_v = vdate + timedelta(days=VISIT_INTERVAL)
            else:
                status = random.choices([VisitStatusEnum.completed, VisitStatusEnum.cancelled, VisitStatusEnum.rescheduled],
                                        weights=[86, 8, 6])[0]
                next_v = vdate + timedelta(days=VISIT_INTERVAL)
                if vtype == VisitTypeEnum.follow_up and random.random() < 0.3:
                    next_v = vdate + timedelta(days=VISIT_INTERVAL // 2)
            visit_rows.append(Visit(
                family_id=fam.id, person_id=None,
                servant_id=random.choice(servant_ids),
                visit_date=vdate, visit_type=vtype, status=status,
                next_visit_date=next_v,
                notes=f"زيارة لعائلة {fam.family_name}" if random.random() < 0.25 else None,
            ))
    for chunk_start in range(0, len(visit_rows), 300):
        db.add_all(visit_rows[chunk_start:chunk_start + 300])
        db.flush()

    # ---------------- confessions ----------------
    confession_rows = []
    confession_eligible = [p for p in persons
                           if p.date_of_birth and (now - p.date_of_birth).days // 365 >= 12]
    confession_people = [p for p in confession_eligible if random.random() < 0.62]
    for p in confession_people:
        n = random.randint(3, 12)
        last_conf = None
        for _ in range(n):
            cdate = random_date(now_dt - timedelta(days=730), now_dt)
            next_c = cdate + timedelta(days=CONFESSION_INTERVAL)
            status = ConfessionStatusEnum.completed
            last_conf = (cdate, next_c)
            confession_rows.append(Confession(
                person_id=p.id, priest_id=random.choice(priests).id,
                confession_date=cdate, next_confession_date=next_c,
                status=status,
            ))
        if last_conf and random.random() < 0.3:
            cdate = now_dt + timedelta(days=random.randint(1, CONFESSION_INTERVAL))
            confession_rows.append(Confession(
                person_id=p.id, priest_id=random.choice(priests).id,
                confession_date=cdate, next_confession_date=cdate + timedelta(days=CONFESSION_INTERVAL),
                status=ConfessionStatusEnum.upcoming,
            ))
    for chunk_start in range(0, len(confession_rows), 300):
        db.add_all(confession_rows[chunk_start:chunk_start + 300])
        db.flush()

    db.commit()

    # ---------------- summary ----------------
    print("Seed data created successfully!")
    print(f"Families: {len(families)}")
    print(f"Persons: {len(persons)}")
    print(f"Family memberships: {len(memberships)}")
    print(f"Services: {len(services)}")
    print(f"Priests: {len(priests)}")
    print(f"Service members (servants): {len(sm_rows)}")
    print(f"Service assignments (served): {len(served_records)}")
    print(f"Servant assignments: {servant_assign_count}")
    print(f"Visits: {len(visit_rows)}")
    print(f"Confessions: {len(confession_rows)}")
    print("Users: admin/priest/servant")
    db.close()


if __name__ == "__main__":
    seed()