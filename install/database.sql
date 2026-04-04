-- ================================================================
-- বাংলাদেশ স্কুল/মাদ্রাসা ম্যানেজমেন্ট সিস্টেম - ডাটাবেস স্কিমা
-- ================================================================

CREATE DATABASE IF NOT EXISTS school_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_db;

-- ----------------------------------------------------------------
-- প্রতিষ্ঠান সেটিংস
-- ----------------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('institute_name', 'আন নাজাহ তাহফিজুল কুরআন মাদ্রাসা'),
('institute_name_en', 'An Nazah Tahfizul Quran Madrasa'),
('institute_type', 'madrasa'),
('address', 'সাভার, ঢাকা'),
('phone', '01700000000'),
('email', 'info@annazah.edu.bd'),
('logo', ''),
('academic_year', '2025'),
('currency', 'BDT'),
('currency_symbol', '৳'),
('sms_api_key', ''),
('ai_api_key', ''),
('smtp_host', ''),
('smtp_user', ''),
('smtp_pass', ''),
('eiin', ''),
('board', 'বাংলাদেশ মাদ্রাসা শিক্ষা বোর্ড');

-- ----------------------------------------------------------------
-- ব্যবহারকারী ও রোল
-- ----------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    role_slug VARCHAR(50) UNIQUE NOT NULL,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (role_name, role_slug, permissions) VALUES
('সুপার অ্যাডমিন', 'super_admin', '["all"]'),
('প্রধান শিক্ষক / অধ্যক্ষ', 'principal', '["dashboard","students","teachers","attendance","exam","fees","notice","reports","settings"]'),
('শিক্ষক', 'teacher', '["dashboard","students","attendance","exam","notice"]'),
('হিসাবরক্ষক', 'accountant', '["dashboard","fees","reports"]'),
('অভিভাবক', 'parent', '["parent_portal"]'),
('ছাত্র', 'student', '["student_portal"]');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    name_bn VARCHAR(150),
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    profile_photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    reset_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Default admin user (password: admin123)
INSERT INTO users (name, name_bn, username, email, phone, password, role_id) VALUES
('Administrator', 'অ্যাডমিনিস্ট্রেটর', 'admin', 'admin@school.com', '01700000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- ----------------------------------------------------------------
-- শ্রেণী ও বিভাগ
-- ----------------------------------------------------------------
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    class_name_bn VARCHAR(100),
    class_numeric INT,
    level ENUM('ebtedayee','dakhil','alim','fazil','kamil','primary','secondary','higher_secondary') DEFAULT 'primary',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO classes (class_name, class_name_bn, class_numeric, level) VALUES
('Class 1 / এবতেদায়ী আউয়াল', 'প্রথম শ্রেণী', 1, 'ebtedayee'),
('Class 2 / এবতেদায়ী সানি', 'দ্বিতীয় শ্রেণী', 2, 'ebtedayee'),
('Class 3 / এবতেদায়ী সালেস', 'তৃতীয় শ্রেণী', 3, 'ebtedayee'),
('Class 4 / এবতেদায়ী রাবে', 'চতুর্থ শ্রেণী', 4, 'ebtedayee'),
('Class 5 / এবতেদায়ী খামেস', 'পঞ্চম শ্রেণী', 5, 'ebtedayee'),
('Class 6 / দাখিল আউয়াল', 'ষষ্ঠ শ্রেণী', 6, 'dakhil'),
('Class 7 / দাখিল সানি', 'সপ্তম শ্রেণী', 7, 'dakhil'),
('Class 8 / দাখিল সালেস', 'অষ্টম শ্রেণী', 8, 'dakhil'),
('Class 9 / দাখিল রাবে', 'নবম শ্রেণী', 9, 'dakhil'),
('Class 10 / দাখিল খামেস', 'দশম শ্রেণী', 10, 'dakhil'),
('হিফজ বিভাগ', 'হিফজ বিভাগ', 0, 'primary');

CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    capacity INT DEFAULT 40,
    class_teacher_id INT,
    room_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

INSERT INTO sections (class_id, section_name, capacity) VALUES
(1,'ক',40),(1,'খ',40),(2,'ক',40),(3,'ক',40),(4,'ক',40),
(5,'ক',40),(6,'ক',40),(7,'ক',40),(8,'ক',40),(9,'ক',40),(10,'ক',40),(11,'হিফজ',30);

-- ----------------------------------------------------------------
-- শিক্ষক তথ্য
-- ----------------------------------------------------------------
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    teacher_id_no VARCHAR(50) UNIQUE,
    name VARCHAR(150) NOT NULL,
    name_bn VARCHAR(150),
    father_name VARCHAR(150),
    mother_name VARCHAR(150),
    date_of_birth DATE,
    gender ENUM('male','female','other') DEFAULT 'male',
    religion ENUM('islam','hinduism','christianity','buddhism','other') DEFAULT 'islam',
    phone VARCHAR(20),
    email VARCHAR(150),
    address TEXT,
    nid_number VARCHAR(30),
    photo VARCHAR(255),
    qualification TEXT,
    specialization TEXT,
    joining_date DATE,
    designation VARCHAR(100),
    designation_bn VARCHAR(100),
    department VARCHAR(100),
    salary DECIMAL(10,2) DEFAULT 0,
    bank_account VARCHAR(50),
    blood_group VARCHAR(5),
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- বিষয়সমূহ
-- ----------------------------------------------------------------
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(150) NOT NULL,
    subject_name_bn VARCHAR(150),
    subject_code VARCHAR(20),
    subject_type ENUM('islamic','general','arabic','quran','science','humanities','business') DEFAULT 'general',
    full_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 33,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO subjects (subject_name, subject_name_bn, subject_code, subject_type, full_marks, pass_marks) VALUES
('Quran Majeed & Tajweed', 'কুরআন মাজিদ ও তাজবিদ', 'QUR101', 'quran', 100, 33),
('Hadith', 'হাদিস', 'HAD101', 'islamic', 100, 33),
('Fiqh', 'ফিকহ', 'FIQ101', 'islamic', 100, 33),
('Aqaid & Kalam', 'আকাইদ ও কালাম', 'AQA101', 'islamic', 100, 33),
('Arabic Language', 'আরবি ভাষা', 'ARB101', 'arabic', 100, 33),
('Bangla', 'বাংলা', 'BAN101', 'general', 100, 33),
('English', 'ইংরেজি', 'ENG101', 'general', 100, 33),
('Mathematics', 'গণিত', 'MAT101', 'general', 100, 33),
('General Science', 'সাধারণ বিজ্ঞান', 'SCI101', 'science', 100, 33),
('Bangladesh & World Studies', 'বাংলাদেশ ও বিশ্বপরিচয়', 'BWS101', 'general', 100, 33),
('Hifz (Quran Memorization)', 'হিফজ', 'HIF101', 'quran', 100, 33);

-- ----------------------------------------------------------------
-- ছাত্র তথ্য
-- ----------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_id VARCHAR(50) UNIQUE,
    roll_number VARCHAR(20),
    name VARCHAR(150) NOT NULL,
    name_bn VARCHAR(150),
    date_of_birth DATE,
    gender ENUM('male','female','other') DEFAULT 'male',
    religion ENUM('islam','hinduism','christianity','buddhism','other') DEFAULT 'islam',
    blood_group VARCHAR(5),
    photo VARCHAR(255),
    class_id INT,
    section_id INT,
    academic_year VARCHAR(10) DEFAULT '2025',
    admission_date DATE,
    admission_number VARCHAR(50),
    previous_school TEXT,
    board_registration_no VARCHAR(50),
    birth_certificate_no VARCHAR(50),
    nid_number VARCHAR(30),
    address_present TEXT,
    address_permanent TEXT,
    -- অভিভাবক তথ্য
    father_name VARCHAR(150),
    father_name_bn VARCHAR(150),
    father_phone VARCHAR(20),
    father_nid VARCHAR(30),
    father_occupation VARCHAR(100),
    father_income DECIMAL(10,2),
    mother_name VARCHAR(150),
    mother_name_bn VARCHAR(150),
    mother_phone VARCHAR(20),
    guardian_name VARCHAR(150),
    guardian_phone VARCHAR(20),
    guardian_relation VARCHAR(50),
    -- হিফজ বিভাগ
    hifz_para_complete INT DEFAULT 0,
    hifz_start_date DATE,
    -- স্ট্যাটাস
    status ENUM('active','inactive','passed','failed','transferred','expelled') DEFAULT 'active',
    scholarship TINYINT(1) DEFAULT 0,
    scholarship_type VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (section_id) REFERENCES sections(id)
);

-- ----------------------------------------------------------------
-- ক্লাস-বিষয়-শিক্ষক অ্যাসাইনমেন্ট
-- ----------------------------------------------------------------
CREATE TABLE class_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_id INT,
    subject_id INT NOT NULL,
    teacher_id INT,
    academic_year VARCHAR(10) DEFAULT '2025',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- উপস্থিতি
-- ----------------------------------------------------------------
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT,
    section_id INT,
    date DATE NOT NULL,
    status ENUM('present','absent','late','excused','holiday') DEFAULT 'absent',
    note VARCHAR(255),
    marked_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- টাইমটেবল
-- ----------------------------------------------------------------
CREATE TABLE timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_id INT,
    subject_id INT NOT NULL,
    teacher_id INT,
    day_of_week TINYINT NOT NULL COMMENT '0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    academic_year VARCHAR(10) DEFAULT '2025',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- পরীক্ষা
-- ----------------------------------------------------------------
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(150) NOT NULL,
    exam_name_bn VARCHAR(150),
    exam_type ENUM('monthly','half_yearly','annual','test','special') DEFAULT 'monthly',
    start_date DATE,
    end_date DATE,
    academic_year VARCHAR(10) DEFAULT '2025',
    is_published TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO exams (exam_name, exam_name_bn, exam_type, academic_year) VALUES
('প্রথম সাময়িক পরীক্ষা', 'প্রথম সাময়িক', 'monthly', '2025'),
('অর্ধ-বার্ষিক পরীক্ষা', 'অর্ধ-বার্ষিক', 'half_yearly', '2025'),
('বার্ষিক পরীক্ষা', 'বার্ষিক', 'annual', '2025');

CREATE TABLE exam_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    written_marks DECIMAL(5,2) DEFAULT 0,
    mcq_marks DECIMAL(5,2) DEFAULT 0,
    practical_marks DECIMAL(5,2) DEFAULT 0,
    total_marks DECIMAL(5,2) DEFAULT 0,
    grade VARCHAR(5),
    grade_point DECIMAL(3,2),
    is_absent TINYINT(1) DEFAULT 0,
    entered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_mark (exam_id, student_id, subject_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- ----------------------------------------------------------------
-- ফি কাঠামো
-- ----------------------------------------------------------------
CREATE TABLE fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_name VARCHAR(150) NOT NULL,
    fee_name_bn VARCHAR(150),
    amount DECIMAL(10,2) DEFAULT 0,
    fee_category ENUM('monthly','yearly','one_time','optional') DEFAULT 'monthly',
    applicable_class VARCHAR(255) DEFAULT 'all' COMMENT 'all or comma-separated class IDs',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO fee_types (fee_name, fee_name_bn, amount, fee_category) VALUES
('টিউশন ফি', 'টিউশন ফি', 500, 'monthly'),
('লাইব্রেরি ফি', 'লাইব্রেরি ফি', 50, 'monthly'),
('স্পোর্টস ফি', 'স্পোর্টস ফি', 30, 'monthly'),
('ভর্তি ফি', 'ভর্তি ফি', 2000, 'one_time'),
('পুনঃভর্তি ফি', 'পুনঃভর্তি ফি', 500, 'yearly'),
('পরীক্ষার ফি', 'পরীক্ষার ফি', 200, 'one_time'),
('উন্নয়ন ফি', 'উন্নয়ন ফি', 1000, 'yearly');

CREATE TABLE fee_collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    fine DECIMAL(10,2) DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','bkash','nagad','rocket','bank','cheque') DEFAULT 'cash',
    transaction_id VARCHAR(100),
    month_year VARCHAR(10) COMMENT 'Format: YYYY-MM',
    receipt_number VARCHAR(50),
    collected_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id),
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- নোটিশ ও বিজ্ঞপ্তি
-- ----------------------------------------------------------------
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_bn VARCHAR(255),
    content TEXT,
    notice_type ENUM('general','academic','exam','fee','holiday','urgent') DEFAULT 'general',
    target_audience ENUM('all','students','teachers','parents','staff') DEFAULT 'all',
    attachment VARCHAR(255),
    is_published TINYINT(1) DEFAULT 1,
    publish_date DATE,
    expire_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO notices (title, title_bn, content, notice_type, target_audience, is_published, publish_date) VALUES
('স্বাগতম', 'স্বাগতম', 'স্কুল ম্যানেজমেন্ট সিস্টেমে আপনাকে স্বাগতম।', 'general', 'all', 1, CURDATE());

-- ----------------------------------------------------------------
-- SMS লগ
-- ----------------------------------------------------------------
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    message TEXT,
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    sms_type VARCHAR(50),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------
-- লাইব্রেরি
-- ----------------------------------------------------------------
CREATE TABLE library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_bn VARCHAR(255),
    author VARCHAR(150),
    isbn VARCHAR(50),
    category VARCHAR(100),
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE book_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT,
    teacher_id INT,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    fine DECIMAL(8,2) DEFAULT 0,
    issued_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES library_books(id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- ছুটির তালিকা
-- ----------------------------------------------------------------
CREATE TABLE holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(150) NOT NULL,
    holiday_name_bn VARCHAR(150),
    holiday_type ENUM('national','religious','school','exam') DEFAULT 'national',
    start_date DATE NOT NULL,
    end_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO holidays (holiday_name, holiday_name_bn, holiday_type, start_date, end_date) VALUES
('শবে বরাত', 'শবে বরাত', 'religious', '2025-02-13', '2025-02-14'),
('মাতৃভাষা দিবস', 'আন্তর্জাতিক মাতৃভাষা দিবস', 'national', '2025-02-21', '2025-02-21'),
('স্বাধীনতা দিবস', 'মহান স্বাধীনতা দিবস', 'national', '2025-03-26', '2025-03-26'),
('ঈদুল ফিতর', 'ঈদুল ফিতর ছুটি', 'religious', '2025-03-30', '2025-04-05'),
('ঈদুল আযহা', 'ঈদুল আযহা ছুটি', 'religious', '2025-06-06', '2025-06-12');

-- ----------------------------------------------------------------
-- অভিভাবক পোর্টাল সেশন/মেসেজ
-- ----------------------------------------------------------------
CREATE TABLE parent_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    parent_phone VARCHAR(20),
    message TEXT,
    reply TEXT,
    status ENUM('unread','read','replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME,
    replied_by INT,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- AI চ্যাট লগ
-- ----------------------------------------------------------------
CREATE TABLE ai_chat_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100),
    message TEXT NOT NULL,
    response TEXT,
    context VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------
-- অ্যাক্টিভিটি লগ
-- ----------------------------------------------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    module VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ================================================================
-- ইন্ডেক্স
-- ================================================================
CREATE INDEX idx_students_class ON students(class_id, section_id, academic_year);
CREATE INDEX idx_attendance_date ON attendance(date, class_id);
CREATE INDEX idx_exam_marks ON exam_marks(exam_id, student_id);
CREATE INDEX idx_fee_student ON fee_collections(student_id, month_year);
