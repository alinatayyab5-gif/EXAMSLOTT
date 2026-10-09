CREATE DATABASE IF NOT EXISTS examslot CHARACTER SET utf8mb4;
USE examslot;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,            -- NULL until student sets password
  role ENUM('admin','student') NOT NULL,
  reset_token_hash CHAR(64) NULL,             -- sha256 of emailed token
  reset_expires DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  city VARCHAR(80) NOT NULL,
  address VARCHAR(255),
  contact VARCHAR(30),
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  credit_hours TINYINT NOT NULL,
  department VARCHAR(100),
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30), cnic VARCHAR(20), dob DATE, gender VARCHAR(10), address VARCHAR(255),
  father_name VARCHAR(120), parent_cnic VARCHAR(20), occupation VARCHAR(100),
  parent_contact VARCHAR(30), emergency_contact VARCHAR(30),
  reg_no VARCHAR(30) NOT NULL UNIQUE,
  program VARCHAR(100), semester VARCHAR(20), session_batch VARCHAR(30),
  prev_qualification VARCHAR(100), prev_institute VARCHAR(150), cgpa VARCHAR(10),
  branch_id INT NULL,
  sheet_saved TINYINT(1) NOT NULL DEFAULT 0,
  branch_unlocked TINYINT(1) NOT NULL DEFAULT 0,  -- one-time unlock flags
  sheet_unlocked TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id)  -- no cascade: blocks hard delete
) ENGINE=InnoDB;

CREATE TABLE assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  UNIQUE (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB;

CREATE TABLE slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  exam_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NULL,
  UNIQUE (course_id, exam_date, start_time),
  FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB;

CREATE TABLE selections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  slot_id INT NOT NULL,
  UNIQUE (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (slot_id) REFERENCES slots(id)      -- chosen slots can't be deleted
) ENGINE=InnoDB;

CREATE TABLE requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  type ENUM('branch','datesheet') NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_remark VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;
