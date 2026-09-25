CREATE TABLE `education_levels` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_education_levels_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('STUDENT', 'TEACHER', 'ADMIN') NOT NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(30) NULL,
  `birth_date` DATE NULL,
  `name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_profiles` (
  `user_id` INTEGER NOT NULL,
  `student_number` VARCHAR(50) NOT NULL,
  `education_level_id` INTEGER NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_student_profiles_student_number` (`student_number`),
  KEY `idx_student_profiles_education_level` (`education_level_id`),
  CONSTRAINT `fk_student_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_profiles_education_level`
    FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `teacher_profiles` (
  `user_id` INTEGER NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_teacher_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `classes` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `education_level_id` INTEGER NOT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classes_education_level_name` (`education_level_id`, `name`),
  KEY `idx_classes_education_level` (`education_level_id`),
  CONSTRAINT `fk_classes_education_level`
    FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subjects` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `class_students` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `class_id` INTEGER NOT NULL,
  `student_id` INTEGER NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_students_class_student_active` (`class_id`, `student_id`, `is_active`),
  KEY `idx_class_students_student_active` (`student_id`, `is_active`),
  KEY `idx_class_students_class_active` (`class_id`, `is_active`),
  CONSTRAINT `fk_class_students_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_class_students_student`
    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `teacher_assignments` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `teacher_id` INTEGER NOT NULL,
  `class_id` INTEGER NOT NULL,
  `subject_id` INTEGER NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_assignments_teacher_class_subject_active` (`teacher_id`, `class_id`, `subject_id`, `is_active`),
  KEY `idx_teacher_assignments_teacher_active` (`teacher_id`, `is_active`),
  KEY `idx_teacher_assignments_class_active` (`class_id`, `is_active`),
  KEY `idx_teacher_assignments_subject_active` (`subject_id`, `is_active`),
  CONSTRAINT `fk_teacher_assignments_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_assignments_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_assignments_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_sessions` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `assignment_id` INTEGER NOT NULL,
  `class_id` INTEGER NOT NULL,
  `session_date` DATE NOT NULL,
  `start_at` TIMESTAMP(6) NOT NULL,
  `end_at` TIMESTAMP(6) NOT NULL,
  `qr_payload` VARCHAR(255) NOT NULL,
  `created_by` INTEGER NOT NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_sessions_qr_payload` (`qr_payload`),
  UNIQUE KEY `uq_attendance_sessions_assignment_date_time` (`assignment_id`, `session_date`, `start_at`, `end_at`),
  KEY `idx_attendance_sessions_assignment_time` (`assignment_id`, `start_at`, `end_at`),
  KEY `idx_attendance_sessions_class_date` (`class_id`, `session_date`),
  CONSTRAINT `fk_attendance_sessions_assignment`
    FOREIGN KEY (`assignment_id`) REFERENCES `teacher_assignments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_sessions_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_sessions_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_records` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `session_id` INTEGER NOT NULL,
  `student_id` INTEGER NOT NULL,
  `scanned_at` TIMESTAMP(6) NOT NULL,
  `status` ENUM('HADIR', 'TERLAMBAT', 'TIDAK_HADIR') NOT NULL,
  `late_minutes` INTEGER UNSIGNED NULL,
  `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` TIMESTAMP(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_records_session_student` (`session_id`, `student_id`),
  KEY `idx_attendance_records_student_scanned` (`student_id`, `scanned_at`),
  KEY `idx_attendance_records_session_status` (`session_id`, `status`),
  CONSTRAINT `fk_attendance_records_session`
    FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_records_student`
    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
