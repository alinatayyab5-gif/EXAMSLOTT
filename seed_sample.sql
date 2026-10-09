USE examslot;
-- Safe to run more than once (INSERT IGNORE skips duplicate codes)
INSERT IGNORE INTO branches (name, code, city, address, contact, status) VALUES
('Lahore Campus','LHR-01','Lahore','Main Boulevard','042-1111111','active'),
('Karachi Campus','KHI-01','Karachi','Shahrah-e-Faisal','021-2222222','active'),
('Islamabad Campus','ISB-01','Islamabad','Blue Area','051-3333333','active');

INSERT IGNORE INTO courses (code, title, credit_hours, department, status) VALUES
('CS101','Introduction to Computing',3,'Computer Science','active'),
('CS201','Data Structures',3,'Computer Science','active'),
('CS301','Database Systems',3,'Computer Science','active'),
('MTH101','Calculus I',3,'Mathematics','active'),
('MTH201','Linear Algebra',3,'Mathematics','active'),
('ENG101','English Composition',2,'English','active'),
('PHY101','Applied Physics',3,'Physics','active'),
('STA101','Statistics',3,'Mathematics','active');

-- Exam slots (dates are relative to today so they are never in the past; safe to re-run)
INSERT IGNORE INTO slots (course_id, exam_date, start_time, end_time)
SELECT c.id, DATE_ADD(CURDATE(), INTERVAL d.days DAY), d.t, d.e
FROM courses c
JOIN (
  SELECT 'CS101' code, 10 days, '09:00:00' t, '11:00:00' e UNION ALL
  SELECT 'CS101', 12, '14:00:00', '16:00:00' UNION ALL
  SELECT 'CS201', 11, '09:00:00', '11:00:00' UNION ALL
  SELECT 'CS201', 13, '14:00:00', '16:00:00' UNION ALL
  SELECT 'CS301', 12, '09:00:00', '11:00:00' UNION ALL
  SELECT 'CS301', 14, '14:00:00', '16:00:00' UNION ALL
  SELECT 'MTH101', 10, '14:00:00', '16:00:00' UNION ALL
  SELECT 'MTH101', 15, '09:00:00', '11:00:00' UNION ALL
  SELECT 'MTH201', 11, '14:00:00', '16:00:00' UNION ALL
  SELECT 'MTH201', 16, '09:00:00', '11:00:00' UNION ALL
  SELECT 'ENG101', 13, '09:00:00', '11:00:00' UNION ALL
  SELECT 'ENG101', 17, '09:00:00', '11:00:00' UNION ALL
  SELECT 'PHY101', 14, '09:00:00', '11:00:00' UNION ALL
  SELECT 'PHY101', 18, '14:00:00', '16:00:00' UNION ALL
  SELECT 'STA101', 15, '14:00:00', '16:00:00' UNION ALL
  SELECT 'STA101', 19, '09:00:00', '11:00:00'
) d ON d.code = c.code;
