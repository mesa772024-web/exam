CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','teacher','admin') DEFAULT 'student',
    department_id INT NULL,
    class_id INT NULL,
    token VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    option_e TEXT,
    correct_option CHAR(1) NOT NULL,
    explanation TEXT,
    image_path VARCHAR(255),
    lab_values TEXT,
    category_id INT,
    subcategory_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id)
);

CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    course VARCHAR(255),
    lecturer VARCHAR(255),
    department_id INT,
    class_id INT,
    number_of_questions INT DEFAULT 0,
    time_minutes INT DEFAULT 0,
    description TEXT,
    scheduled_date DATETIME,
    token VARCHAR(64),
    randomize_questions TINYINT(1) DEFAULT 0,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE exam_questions (
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    position INT DEFAULT 0,
    PRIMARY KEY (exam_id, question_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE student_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    score DECIMAL(5,2),
    status ENUM('scheduled','in_progress','finished') DEFAULT 'scheduled',
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_exam_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1),
    is_flagged TINYINT(1) DEFAULT 0,
    FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
);
