CREATE DATABASE IF NOT EXISTS elldy_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elldy_academy;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(40) NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL UNIQUE,
    slug VARCHAR(220) NULL UNIQUE,
    short_description TEXT NULL,
    description TEXT NULL,
    learning_plan TEXT NULL,
    completion_benefits TEXT NULL,
    expert_name VARCHAR(160) NULL,
    expert_title VARCHAR(190) NULL,
    expert_bio TEXT NULL,
    expert_photo TEXT NULL,
    promo_video_url TEXT NULL,
    duration VARCHAR(80) NOT NULL,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_fee DECIMAL(10,2) NULL DEFAULT NULL,
    certification_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    certificate_discount_fee DECIMAL(10,2) NULL DEFAULT NULL,
    show_fee_details TINYINT(1) NOT NULL DEFAULT 1,
    delivery_type ENUM('video', 'live_session') NOT NULL DEFAULT 'video',
    certificate_details TEXT NULL,
    certificate_title VARCHAR(220) NULL,
    first_class_link TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    material_type ENUM('video', 'live_session', 'material') NOT NULL DEFAULT 'video',
    file_url VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_material_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status ENUM('free_access', 'payment_pending', 'paid', 'completed', 'cancelled') NOT NULL DEFAULT 'free_access',
    payment_note TEXT NULL,
    payment_requested_at DATETIME NULL,
    program_payment_attempted_at DATETIME NULL,
    student_background TEXT NULL,
    learning_goals TEXT NULL,
    completion_expectation TEXT NULL,
    daily_reminders_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_reminder_sent_on DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_course (user_id, course_id),
    CONSTRAINT fk_enrollment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS learning_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    watched_seconds DECIMAL(10,2) NOT NULL DEFAULT 0,
    duration_seconds DECIMAL(10,2) NOT NULL DEFAULT 0,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment_material (enrollment_id, material_id),
    INDEX idx_learning_progress_user (user_id),
    INDEX idx_learning_progress_course (course_id),
    CONSTRAINT fk_learning_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_learning_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_learning_progress_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_learning_progress_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS live_session_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_live_session_attendance (enrollment_id, material_id),
    INDEX idx_live_session_attendance_user (user_id),
    INDEX idx_live_session_attendance_course (course_id),
    CONSTRAINT fk_live_session_attendance_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_session_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_session_attendance_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_session_attendance_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_otps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    phone VARCHAR(40) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_otps_user_phone (user_id, phone),
    CONSTRAINT fk_login_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_remember_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    selector VARCHAR(64) NOT NULL UNIQUE,
    token_hash VARCHAR(255) NOT NULL,
    user_agent_hash CHAR(64) NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_remember_user (user_id),
    INDEX idx_user_remember_expires (expires_at),
    CONSTRAINT fk_user_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS app_user_activity (
    user_id INT UNSIGNED PRIMARY KEY,
    login_count INT UNSIGNED NOT NULL DEFAULT 0,
    return_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_login_at DATETIME NULL,
    last_login_at DATETIME NULL,
    last_active_at DATETIME NULL,
    last_return_at DATETIME NULL,
    last_installed_app_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS app_installs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    install_key CHAR(64) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL,
    platform VARCHAR(80) NULL,
    user_agent TEXT NULL,
    first_installed_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    launch_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_app_installs_user (user_id),
    CONSTRAINT fk_app_install_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(240) NOT NULL UNIQUE,
    excerpt VARCHAR(500) NULL,
    body MEDIUMTEXT NOT NULL,
    featured_image_url VARCHAR(255) NULL,
    author_name VARCHAR(160) NULL,
    meta_description VARCHAR(255) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_blog_status_published (status, published_at)
);

CREATE TABLE IF NOT EXISTS whatsapp_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    business_account_id VARCHAR(80) NULL,
    phone_number_id VARCHAR(80) NULL,
    access_token TEXT NULL,
    template_name VARCHAR(120) NULL,
    enrollment_template_name VARCHAR(120) NULL,
    course_invite_template_name VARCHAR(120) NULL,
    reminder_template_name VARCHAR(120) NULL,
    certificate_template_name VARCHAR(120) NULL,
    template_language VARCHAR(20) NOT NULL DEFAULT 'en',
    graph_version VARCHAR(20) NOT NULL DEFAULT 'v20.0',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS whatsapp_invite_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NULL,
    course_title VARCHAR(190) NULL,
    contact_name VARCHAR(190) NULL,
    phone VARCHAR(40) NOT NULL,
    invite_description TEXT NULL,
    invite_duration VARCHAR(120) NULL,
    message_id VARCHAR(190) NULL,
    status ENUM('queued', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent',
    response_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_updated_at DATETIME NULL,
    INDEX idx_whatsapp_invite_sent_at (sent_at),
    INDEX idx_whatsapp_invite_course (course_id),
    INDEX idx_whatsapp_invite_message_id (message_id)
);

CREATE TABLE IF NOT EXISTS zoom_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    client_id VARCHAR(190) NULL,
    client_secret TEXT NULL,
    sdk_version VARCHAR(20) NOT NULL DEFAULT '5.1.4',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS razorpay_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    key_id VARCHAR(120) NULL,
    key_secret TEXT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS s3_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    access_key_id VARCHAR(160) NULL,
    secret_access_key TEXT NULL,
    region VARCHAR(80) NOT NULL DEFAULT 'ap-south-1',
    bucket_name VARCHAR(190) NULL,
    upload_prefix VARCHAR(190) NOT NULL DEFAULT 'course-videos',
    public_base_url VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS certificate_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status ENUM('requested', 'payment_pending', 'approved', 'issued', 'rejected') NOT NULL DEFAULT 'requested',
    payment_note TEXT NULL,
    applied_at DATETIME NULL,
    dashboard_url VARCHAR(500) NULL,
    dashboard_review_status ENUM('not_submitted', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'not_submitted',
    dashboard_review_note TEXT NULL,
    dashboard_submitted_at DATETIME NULL,
    dashboard_reviewed_at DATETIME NULL,
    certificate_url VARCHAR(255) NULL,
    certificate_code VARCHAR(80) NULL,
    issued_at DATETIME NULL,
    downloaded_at DATETIME NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    admin_note TEXT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_certificate_enrollment (enrollment_id),
    CONSTRAINT fk_certificate_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_certificate_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_certificate_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

INSERT INTO whatsapp_settings (id, business_account_id, phone_number_id, template_name, template_language, graph_version)
VALUES (1, '467530709787499', '886937197845294', 'elldy_academy_otp', 'en', 'v20.0')
ON DUPLICATE KEY UPDATE id = VALUES(id);

INSERT INTO admins (username, password_hash)
VALUES ('admin', '$2y$10$qC1XpFocA/CszXNavd1gr.zr998.UNkPuP3HzxywETEzqgcVEC.k2')
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT INTO courses (title, slug, short_description, description, learning_plan, completion_benefits, duration, fee, first_class_link, is_active) VALUES
('Data Analytics & BI Foundations', 'data-analytics-bi-foundations', 'Build Excel, SQL, Power BI, and dashboard thinking with business-ready analytics cases.', 'A practical analytics program for trainees who want to understand business data, clean messy datasets, write SQL, create KPI dashboards, and explain insights to decision makers using real company-style cases.', 'Business case understanding and KPI framing
Excel analytics and data cleaning
SQL queries for business reporting
Power BI dashboard design
Data storytelling for management
Real-time sales, finance, and operations cases', 'Business-ready analytics project portfolio
Power BI dashboard case study
Course completion certificate
Interview-ready BI and reporting confidence
Reusable templates for KPI, sales, and operations analysis', '10 weeks', 12999.00, 'https://meet.google.com/data-bi-demo', 1),
('Advanced Business Intelligence with Power BI', 'advanced-business-intelligence-with-power-bi', 'Design executive BI dashboards, data models, DAX measures, and decision-ready reports.', 'A focused BI course for trainees who want to move beyond charts and build structured dashboards for sales, marketing, finance, HR, and operations teams using professional BI practices.', 'Power BI data modelling
DAX measures and calculated columns
Executive dashboard layout
Sales, revenue, and customer analytics
Data refresh and report publishing
Stakeholder presentation with insights', 'Advanced Power BI project portfolio
Executive dashboard presentation
Course completion certificate
BI analyst workflow confidence
Templates for business review dashboards', '8 weeks', 15999.00, 'https://meet.google.com/power-bi-demo', 1),
('Real-Time Business Analytics Cases', 'real-time-business-analytics-cases', 'Solve practical business problems using data, analytics logic, BI dashboards, and insight delivery.', 'A case-led program for trainees who want to think like analytics consultants: understand business problems, ask the right questions, analyse data, prepare dashboards, and recommend actions.', 'Problem discovery from business scenarios
Customer, sales, finance, and operations cases
Root-cause analysis with data
Dashboard requirement planning
Insight writing and recommendation structure
Presentation of analytics findings', 'Business case solving portfolio
Analytics consulting mindset
Course completion certificate
Confidence to discuss data with business teams
Reusable case-study frameworks', '6 weeks', 9999.00, 'https://meet.google.com/business-cases-demo', 1)
ON DUPLICATE KEY UPDATE
slug = VALUES(slug),
short_description = VALUES(short_description),
description = VALUES(description),
learning_plan = VALUES(learning_plan),
completion_benefits = VALUES(completion_benefits),
duration = VALUES(duration),
fee = VALUES(fee),
first_class_link = VALUES(first_class_link),
is_active = VALUES(is_active);

INSERT INTO materials (course_id, title, description, file_url)
SELECT id, 'Welcome pack', 'Class schedule, learning path, and first-session preparation notes.', 'https://example.com/welcome-pack.pdf'
FROM courses
WHERE title = 'Data Analytics & BI Foundations'
LIMIT 1;
