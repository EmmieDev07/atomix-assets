<?php
/**
 * Migration: Create Chapter Pretests Tables
 * Run this once to add chapter pretest support.
 */
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$queries = [

    // 1. One pretest record per chapter (teacher configures passing score, etc.)
    "CREATE TABLE IF NOT EXISTS `chapter_pretests` (
        `pretest_id`            INT NOT NULL AUTO_INCREMENT,
        `chapter_id`            INT NOT NULL,
        `created_by_teacher_id` INT NOT NULL,
        `title`                 VARCHAR(255) NOT NULL DEFAULT 'Chapter Pretest',
        `passing_score`         TINYINT UNSIGNED NOT NULL DEFAULT 70 COMMENT 'Required percentage to pass',
        `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
        `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`pretest_id`),
        UNIQUE KEY `uq_chapter_pretest` (`chapter_id`),
        KEY `fk_cp_chapter`  (`chapter_id`),
        KEY `fk_cp_teacher`  (`created_by_teacher_id`),
        CONSTRAINT `fk_cp_chapter`  FOREIGN KEY (`chapter_id`)  REFERENCES `chapters`  (`chapter_id`)  ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_cp_teacher`  FOREIGN KEY (`created_by_teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 2. MCQ questions belonging to a pretest
    "CREATE TABLE IF NOT EXISTS `chapter_pretest_questions` (
        `pq_id`                INT NOT NULL AUTO_INCREMENT,
        `pretest_id`           INT NOT NULL,
        `question_text`        TEXT NOT NULL,
        `answer_0`             VARCHAR(500) NOT NULL,
        `answer_1`             VARCHAR(500) NOT NULL,
        `answer_2`             VARCHAR(500) NOT NULL,
        `answer_3`             VARCHAR(500) NOT NULL,
        `correct_answer_index` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `question_order`       INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`pq_id`),
        KEY `fk_cpq_pretest` (`pretest_id`),
        CONSTRAINT `fk_cpq_pretest` FOREIGN KEY (`pretest_id`) REFERENCES `chapter_pretests` (`pretest_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 3. Per-student pretest results (one row per student per pretest; re-attempt updates it)
    "CREATE TABLE IF NOT EXISTS `student_pretest_results` (
        `result_id`       INT NOT NULL AUTO_INCREMENT,
        `student_id`      INT NOT NULL,
        `pretest_id`      INT NOT NULL,
        `chapter_id`      INT NOT NULL,
        `score`           INT NOT NULL DEFAULT 0,
        `total_questions` INT NOT NULL DEFAULT 0,
        `passed`          TINYINT(1) NOT NULL DEFAULT 0,
        `attempted_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`result_id`),
        UNIQUE KEY `uq_student_pretest` (`student_id`, `pretest_id`),
        KEY `fk_spr_student`  (`student_id`),
        KEY `fk_spr_pretest`  (`pretest_id`),
        CONSTRAINT `fk_spr_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`student_id`)  ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_spr_pretest`  FOREIGN KEY (`pretest_id`)  REFERENCES `chapter_pretests` (`pretest_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$success = true;
foreach ($queries as $i => $sql) {
    try {
        $db->exec($sql);
        echo "Query " . ($i + 1) . ": OK\n";
    } catch (PDOException $e) {
        echo "Query " . ($i + 1) . " FAILED: " . $e->getMessage() . "\n";
        $success = false;
    }
}

echo $success ? "\nMigration completed successfully.\n" : "\nMigration completed with errors.\n";
