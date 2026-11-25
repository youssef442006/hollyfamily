# المخطط الشامل لقاعدة البيانات (Exhaustive ER Diagram)

هذا الملف يحتوي على المخطط التفصيلي (ER Diagram) لقاعدة بيانات مشروع "كنيستي". يضم المخطط جميع الجداول، والأعمدة (الحقول)، وأنواع البيانات الخاصة بها، بالإضافة إلى توضيح العلاقات المباشرة (Relationships) بين الجداول. يمكنك استعراضه باستخدام أي محرر يدعم Markdown و Mermaid (مثل VS Code).

```mermaid
erDiagram
    User {
        int id PK
        string username
        string email
        string hashed_password
        string full_name
        enum role "admin, priest, service_admin, servant"
        boolean is_active
        int person_id FK "ربط بشخص حقيقي"
        datetime created_at
        datetime updated_at
    }
    
    Family {
        int id PK
        string family_code "كود الأسرة"
        string family_name "اسم الأسرة"
        text address
        string street
        string branch_from
        string area
        string landmark
        string building_number
        string floor
        string apartment_number
        string gps_location
        string phone
        string special_case
        boolean is_active
        text notes
        datetime created_at
        datetime updated_at
    }
    
    Person {
        int id PK
        int family_id FK "الأسرة التابع لها"
        string first_name
        string middle_name
        string last_name
        enum gender "male, female"
        date date_of_birth
        boolean is_deceased
        date date_of_death
        string phone
        string whatsapp
        string second_phone
        string email
        string national_id
        enum marital_status "single, married, divorced, widowed"
        string care_situation
        enum education_stage "المرحلة الدراسية"
        string school_year
        string confession_father
        string confession_church
        string weekly_leave
        boolean is_deacon
        boolean is_servant
        boolean in_prep_center
        string occupation
        text address
        text notes
        boolean is_active
        boolean is_archived
        datetime created_at
        datetime updated_at
    }
    
    FamilyMember {
        int id PK
        int family_id FK
        int person_id FK
        enum relationship_type "father, mother, husband, wife..."
        boolean is_head
        boolean is_spouse
        boolean is_child
        date start_date
        date end_date
        boolean is_current
        text notes
        boolean is_deleted
        datetime created_at
        datetime updated_at
    }
    
    PersonRelationship {
        int id PK
        int person_id FK "الشخص الأول"
        int related_person_id FK "الشخص القريب"
        string relationship_type
        date start_date
        date end_date
        boolean is_current
        text notes
        boolean is_deleted
        datetime created_at
        datetime updated_at
    }
    
    Service {
        int id PK
        string name
        text description
        int min_age
        int max_age
        enum gender_restriction "male, female"
        text education_stages
        boolean is_active
        text notes
        datetime created_at
        datetime updated_at
    }
    
    ServiceMember {
        int id PK
        int person_id FK
        int service_id FK
        enum role "service_admin, servant, supervisor..."
        date start_date
        date end_date
        boolean is_active
        text notes
        datetime created_at
    }
    
    ServiceAssignment {
        int id PK
        int person_id FK
        int service_id FK
        int assigned_by FK "المسؤول عن التعيين"
        datetime assigned_at
        string status
        text notes
    }
    
    ServantAssignment {
        int id PK
        int servant_id FK "الخادم"
        int person_id FK "المخدوم"
        int service_id FK "الخدمة"
        datetime assigned_at
        boolean is_active
    }
    
    Priest {
        int id PK
        int person_id FK "الأب الكاهن كشخص"
        string display_name
        string phone
        boolean is_active
        text notes
        datetime created_at
    }
    
    Visit {
        int id PK
        int family_id FK "الأسرة (اختياري)"
        int person_id FK "الشخص (اختياري)"
        int servant_id FK "الخادم"
        datetime visit_date
        enum visit_type "home_visit, phone_call, follow_up..."
        enum status "pending, completed, cancelled..."
        text notes
        datetime next_visit_date
        datetime created_at
        datetime updated_at
    }
    
    Confession {
        int id PK
        int person_id FK "المعترف"
        int priest_id FK "أب الاعتراف"
        datetime confession_date
        datetime next_confession_date
        enum status "upcoming, completed, missed..."
        text notes
        datetime created_at
    }
    
    AuditLog {
        int id PK
        int user_id FK "المستخدم"
        string action
        string table_name
        int record_id
        text old_value
        text new_value
        datetime timestamp
    }
    
    Setting {
        int id PK
        string key
        text value
        text description
        datetime updated_at
    }

    %% العلاقات المباشرة (Relationships)
    User |o--o| Person : "person_id"
    Family ||--o{ Person : "family_id"
    Family ||--o{ FamilyMember : "family_id"
    Person ||--o{ FamilyMember : "person_id"
    Person ||--o{ PersonRelationship : "person_id"
    Person ||--o{ PersonRelationship : "related_person_id"
    Service ||--o{ ServiceMember : "service_id"
    Person ||--o{ ServiceMember : "person_id"
    Service ||--o{ ServiceAssignment : "service_id"
    Person ||--o{ ServiceAssignment : "person_id"
    User ||--o{ ServiceAssignment : "assigned_by"
    Service ||--o{ ServantAssignment : "service_id"
    Person ||--o{ ServantAssignment : "servant_id"
    Person ||--o{ ServantAssignment : "person_id"
    Person ||--o| Priest : "person_id"
    Family ||--o{ Visit : "family_id"
    Person ||--o{ Visit : "person_id"
    Person ||--o{ Visit : "servant_id"
    Person ||--o{ Confession : "person_id"
    Priest ||--o{ Confession : "priest_id"
    User ||--o{ AuditLog : "user_id"
```



