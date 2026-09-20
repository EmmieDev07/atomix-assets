-- Atomix Database Backup
-- Generated on: 2026-08-01 00:33:53

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Table structure for table `badges`
CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `criteria` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `chapter_pretest_questions`
CREATE TABLE `chapter_pretest_questions` (
  `pq_id` int(11) NOT NULL AUTO_INCREMENT,
  `pretest_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `answer_0` varchar(500) NOT NULL,
  `answer_1` varchar(500) NOT NULL,
  `answer_2` varchar(500) NOT NULL,
  `answer_3` varchar(500) NOT NULL,
  `correct_answer_index` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `question_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pq_id`),
  KEY `fk_cpq_pretest` (`pretest_id`),
  CONSTRAINT `fk_cpq_pretest` FOREIGN KEY (`pretest_id`) REFERENCES `chapter_pretests` (`pretest_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `chapter_pretest_questions`
INSERT INTO `chapter_pretest_questions` (`pq_id`, `pretest_id`, `question_text`, `answer_0`, `answer_1`, `answer_2`, `answer_3`, `correct_answer_index`, `question_order`) VALUES
('7', '3', 'adadwvrewfcwe', 'dsf', 'dfds', 'sdf', 'sfsdf', '0', '1'),
('8', '4', 'sfs', 'fsf', 'sfsf', 'sf', 'sfssf', '0', '1'),
('9', '1', 'What is the first step in a scientific investigation?', 'Conduct an experiment', 'Ask a question', 'Draw a conclusion', 'Gather data', '1', '1'),
('10', '1', 'What is a hypothesis?', 'A final answer', 'A laboratory tool', 'An intelligent guess', 'A science book', '2', '2'),
('11', '1', 'What do scientists do to test a hypothesis?', 'Write a report', 'Conduct an experiment', 'Draw a conclusion', 'Ask another question', '1', '3'),
('12', '1', 'What do you call the facts and information gathered during an experiment?', 'Data', 'Guesses', 'Stories', 'Problems', '0', '4'),
('13', '1', 'In which step do scientists decide whether to accept or reject a hypothesis?', 'Asking a question', 'Gathering data', 'Drawing a conclusion', 'Making observations', '2', '5'),
('14', '2', 'What is a mixture?', 'A new substance', 'Physically combined substances', 'Pure water', 'A chemical change', '0', '1'),
('15', '2', 'Which of the following is a mixture?', 'Gold', 'Oxygen', 'Salt water', 'Iron', '2', '2'),
('16', '2', 'Which method can be used to separate sand and water?', 'Magnetism', 'Filtration', 'Burning', 'Freezing', '1', '3'),
('17', '2', 'Which tool attracts metal objects?', 'Filter', 'Spoon', 'Magnet', 'Cup', '2', '4'),
('18', '2', 'Salt water is an example of a:', 'Heterogeneous mixture', 'Pure substance', 'Homogeneous mixture', 'Solid', '2', '5'),
('19', '5', 'What is Animal?', 'dada', 'dsd', 'fdf', 'dfd', '0', '1');

-- Table structure for table `chapter_pretests`
CREATE TABLE `chapter_pretests` (
  `pretest_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `created_by_teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Chapter Pretest',
  `passing_score` tinyint(3) unsigned NOT NULL DEFAULT 70,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pretest_id`),
  UNIQUE KEY `uq_chapter_pretest` (`chapter_id`),
  KEY `fk_cp_chapter` (`chapter_id`),
  KEY `fk_cp_teacher` (`created_by_teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `chapter_pretests`
INSERT INTO `chapter_pretests` (`pretest_id`, `chapter_id`, `created_by_teacher_id`, `title`, `passing_score`, `is_active`, `created_at`) VALUES
('1', '23', '3', 'Chapter Pretest', '70', '1', '2026-05-26 22:54:56'),
('2', '17', '3', 'Chapter Pretest', '70', '1', '2026-05-30 00:25:11'),
('3', '21', '3', 'Chapter Pretest', '70', '1', '2026-05-30 00:25:19'),
('4', '25', '3', 'Chapter Pretest', '70', '1', '2026-06-09 16:52:10'),
('5', '26', '3', 'Chapter Pretest', '70', '1', '2026-07-05 21:52:52');

-- Table structure for table `chapters`
CREATE TABLE `chapters` (
  `chapter_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_title` varchar(255) NOT NULL,
  `chapter_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `chapters`
INSERT INTO `chapters` (`chapter_id`, `chapter_title`, `chapter_order`) VALUES
('17', 'Chapter 2 Mixtures: Properties, classifications, and uses', '2'),
('21', 'Chapter 3 :Separating Mixtures', '3'),
('23', 'Chapter 1 : Science as a way of Knowing', '1'),
('25', 'Chapter 4: living things and their environment', '4'),
('26', 'Chapter 5 : Animal Kingdom', '5'),
('27', 'Science', '6');

-- Table structure for table `class_chapter_access`
CREATE TABLE `class_chapter_access` (
  `class_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`class_id`,`chapter_id`),
  KEY `idx_class_chapter_access_chapter` (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `class_chapter_access`
INSERT INTO `class_chapter_access` (`class_id`, `chapter_id`, `is_locked`, `updated_at`) VALUES
('4', '17', '0', '2026-06-07 22:54:29'),
('4', '21', '0', '2026-06-08 15:08:13'),
('4', '23', '0', '2026-06-24 09:41:18'),
('4', '25', '0', '2026-06-08 15:08:15'),
('4', '26', '0', '2026-07-05 20:28:37'),
('8', '17', '0', '2026-07-06 10:32:55'),
('8', '23', '0', '2026-07-06 10:18:06');

-- Table structure for table `class_exam_access`
CREATE TABLE `class_exam_access` (
  `class_id` int(11) NOT NULL,
  `exam_key` varchar(64) NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`class_id`,`exam_key`),
  KEY `idx_class_exam_access_key` (`exam_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `class_exam_access`
INSERT INTO `class_exam_access` (`class_id`, `exam_key`, `is_locked`, `updated_at`) VALUES
('4', 'post_chapter_4_exam', '0', '2026-07-12 21:57:56');

-- Table structure for table `class_students`
CREATE TABLE `class_students` (
  `class_stud_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` date DEFAULT NULL,
  PRIMARY KEY (`class_stud_id`),
  UNIQUE KEY `uq_class_student` (`class_id`,`student_id`),
  KEY `fk_class_students_student` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `class_students`
INSERT INTO `class_students` (`class_stud_id`, `class_id`, `student_id`, `joined_at`) VALUES
('1', '1', '1', '2026-07-23'),
('3', '1', '3', '2026-07-23'),
('4', '1', '4', '2026-07-23'),
('5', '1', '5', '2026-07-23'),
('9', '2', '9', NULL),
('11', '2', '11', NULL),
('19', '2', '15', '2026-08-01'),
('20', '2', '16', '2026-08-01');

-- Table structure for table `classes`
CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `sy_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`class_id`),
  KEY `fk_classes_teacher` (`teacher_id`),
  KEY `fk_classes_sy` (`sy_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `classes`
INSERT INTO `classes` (`class_id`, `teacher_id`, `sy_id`, `class_name`, `created_at`) VALUES
('2', '23', '1', 'sadada', '2026-07-31 13:51:07');

-- Table structure for table `customization_items`
CREATE TABLE `customization_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category` enum('hair','eyewear','outfit') NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `fk_items_stage` (`stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `exam_classes`
CREATE TABLE `exam_classes` (
  `exam_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  PRIMARY KEY (`exam_id`,`class_id`),
  KEY `fk_exam_classes_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `exam_questions`
CREATE TABLE `exam_questions` (
  `exam_question_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `choices_json` text DEFAULT NULL,
  `correct_answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`exam_question_id`),
  KEY `fk_exam_questions_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `exam_results`
CREATE TABLE `exam_results` (
  `exam_result_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`exam_result_id`),
  KEY `exam_id` (`exam_id`,`student_id`),
  KEY `fk_exam_results_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `exam_sections`
CREATE TABLE `exam_sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `section_type` enum('multiple_choice','true_false','identification','matching') NOT NULL,
  `section_label` varchar(120) NOT NULL,
  `instructions` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`section_id`),
  KEY `fk_exam_sections_exam` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `exams`
CREATE TABLE `exams` (
  `exam_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `exam_title` varchar(255) NOT NULL,
  `school_name` varchar(255) NOT NULL DEFAULT '',
  `department_subtitle` varchar(255) NOT NULL DEFAULT '',
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`exam_id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `game_access_codes`
CREATE TABLE `game_access_codes` (
  `code_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `access_code` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`code_id`),
  UNIQUE KEY `uq_access_code` (`access_code`),
  KEY `fk_access_teacher` (`teacher_id`),
  KEY `fk_access_quiz` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `game_progress`
CREATE TABLE `game_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `completed_levels` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `status` enum('locked','in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `uq_student_stage` (`student_id`,`stage_id`),
  KEY `fk_progress_stage` (`stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `game_questions`
CREATE TABLE `game_questions` (
  `game_question_id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `answer_0` varchar(255) NOT NULL DEFAULT '',
  `answer_1` varchar(255) NOT NULL DEFAULT '',
  `answer_2` varchar(255) NOT NULL DEFAULT '',
  `answer_3` varchar(255) NOT NULL DEFAULT '',
  `correct_answer_index` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_teacher_id` int(11) NOT NULL,
  PRIMARY KEY (`game_question_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `teacher_id` (`created_by_teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `games`
CREATE TABLE `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `lessons`
CREATE TABLE `lessons` (
  `lesson_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `lesson_title` varchar(255) NOT NULL,
  `lesson_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`lesson_id`),
  KEY `fk_lessons_chapter` (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `lessons`
INSERT INTO `lessons` (`lesson_id`, `chapter_id`, `lesson_title`, `lesson_order`) VALUES
('10', '23', 'Lesson 1 : Scientific Methods', '1'),
('11', '17', 'Lesson 1: Properties of Mixtures', '1'),
('12', '17', 'Lesson 2: Classifications and Uses of Mixtures', '2'),
('13', '21', 'Lesson 1: techniques of separating mixtures', '1'),
('14', '21', 'Lesson 2: application and benefits of separating mixtures', '2'),
('15', '25', 'Lesson 1: DIgstive System', '1'),
('16', '25', 'Lesson 2: Respiratory System', '2'),
('17', '25', 'Lesson 3: Circulatory System', '3'),
('18', '26', 'Lesson 1 : Characteristics of Vertebrates and Invertebrates', '1'),
('19', '26', 'Lesson 2 : economic importance of Vertebrates and Inverterbrates', '2'),
('20', '26', 'Lesson 3 : Animal Endemic to the Philippines', '3'),
('21', '26', 'Lesson 4 : Protecting and Caring for Animals', '4'),
('22', '23', 'Lesson 2: Scientific Tools', '2'),
('23', '23', 'Lesson 3 : Scientist', '3'),
('25', '25', 'Lesson 4: Nervous System', '4'),
('26', '25', 'Lesson 5-7 : Musculoskeletal, Integumentary, Excretory System', '5'),
('27', '27', 'djhsjda', '1');

-- Table structure for table `questions_master`
CREATE TABLE `questions_master` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(50) DEFAULT NULL,
  `created_by_teacher_id` int(11) NOT NULL,
  `visibility` enum('private','public') NOT NULL DEFAULT 'private',
  PRIMARY KEY (`question_id`),
  KEY `fk_questions_lesson` (`lesson_id`),
  KEY `fk_questions_teacher` (`created_by_teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `questions_master`
INSERT INTO `questions_master` (`question_id`, `lesson_id`, `question_text`, `question_type`, `created_by_teacher_id`, `visibility`) VALUES
('187', '10', 'What is observation?', 'mcq', '7', 'private'),
('188', '10', 'What is 2 + 2?', 'short_answer', '7', 'private'),
('189', '10', 'The sky is blue', 'true_false', '7', 'private'),
('190', '10', 'What is observation?', 'mcq', '3', 'public'),
('191', '10', 'What is 2 + 2?', 'short_answer', '3', 'public'),
('192', '10', 'The sky is blue', 'true_false', '3', 'public'),
('193', '11', 'What is a mixture?', 'mcq', '3', 'private'),
('194', '11', 'Which of the following is an example of a heterogeneous mixture?', 'mcq', '3', 'private'),
('195', '11', 'Which of the following is an example of a homogeneous mixture?', 'mcq', '3', 'private'),
('196', '11', 'In a mixture, the substances are held together by what type of bonding?', 'mcq', '3', 'private'),
('197', '11', 'Which property can be used to separate a mixture?', 'mcq', '3', 'private'),
('198', '13', 'Which technique is used to separate a solid from a liquid in a heterogeneous mixture?', 'mcq', '3', 'private'),
('199', '13', 'Which method is best for separating salt from saltwater?', 'mcq', '3', 'private'),
('200', '13', 'Which separation technique uses differences in boiling points?', 'mcq', '3', 'private'),
('201', '13', 'What tool is used in filtration to separate substances?', 'mcq', '3', 'private'),
('202', '13', 'Which method would you use to separate iron filings from sand?', 'mcq', '3', 'private'),
('203', '22', 'What Microscope?', 'mcq', '3', 'private'),
('205', '10', 'What is the systematic study of the natural world called?', 'short_answer', '3', 'public'),
('206', '10', 'A scientific theory is just a guess', 'true_false', '3', 'public'),
('212', '11', 'What is observation?', 'mcq', '3', 'private'),
('214', '11', 'The sky is blue', 'true_false', '3', 'private');

-- Table structure for table `quiz_choices`
CREATE TABLE `quiz_choices` (
  `choice_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`choice_id`),
  KEY `fk_choices_question` (`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=593 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `quiz_choices`
INSERT INTO `quiz_choices` (`choice_id`, `question_id`, `choice_text`, `is_correct`) VALUES
('504', '187', 'Making a guess', '0'),
('505', '187', 'Using your senses to gather information', '1'),
('506', '187', 'Writing a conclusion', '0'),
('507', '187', 'Doing an experiment', '0'),
('508', '188', '4', '1'),
('509', '189', 'True', '1'),
('510', '189', 'False', '0'),
('511', '190', 'Making a guess', '0'),
('512', '190', 'Using your senses to gather information', '1'),
('513', '190', 'Writing a conclusion', '0'),
('514', '190', 'Doing an experiment', '0'),
('516', '192', 'True', '1'),
('517', '192', 'False', '0'),
('518', '191', '4', '1'),
('519', '193', 'A substance made of only one element', '0'),
('520', '193', 'A chemical reaction', '0'),
('521', '193', 'A combination of two or more substances not chemically combined', '1'),
('522', '193', 'A pure compound', '0'),
('523', '194', 'Salt water', '0'),
('524', '194', 'Air', '0'),
('525', '194', 'Sand and water', '1'),
('526', '194', 'Sugar solution', '0'),
('527', '195', 'Oil and water', '0'),
('528', '195', 'Sand and iron filings', '0'),
('529', '195', 'Salt dissolved in water', '1'),
('530', '195', 'Trail mix', '0'),
('531', '196', 'Chemical bonds', '0'),
('532', '196', 'Magnetic force', '0'),
('533', '196', 'No chemical bonds', '1'),
('534', '196', 'Strong nuclear force', '0'),
('535', '197', 'Color only', '0'),
('536', '197', 'Melting point only', '0'),
('537', '197', 'Physical properties like size or solubility', '1'),
('538', '197', 'Taste only', '0'),
('539', '198', 'Distillation', '0'),
('540', '198', 'Filtration', '1'),
('541', '198', 'Evaporation', '0'),
('542', '198', 'Chromatography', '0'),
('543', '199', 'Magnetism', '0'),
('544', '199', 'Filtration', '0'),
('545', '199', 'Evaporation', '1'),
('546', '199', 'Sieving', '0'),
('547', '200', 'Sieving', '0'),
('548', '200', 'Distillation', '1'),
('549', '200', 'Magnetic separation', '0'),
('550', '200', 'Decanting', '0'),
('551', '201', 'Magnet', '0'),
('552', '201', 'Thermometer', '0'),
('553', '201', 'Filter paper', '1'),
('554', '201', 'Burner', '0'),
('555', '202', 'Evaporation', '0'),
('556', '202', 'Distillation', '0'),
('557', '202', 'Magnetic separation', '1'),
('558', '202', 'Chromatography', '0'),
('559', '203', 'Option A', '1'),
('560', '203', 'Option B', '0'),
('561', '203', 'Option C', '0'),
('562', '203', 'Option D', '0'),
('568', '205', 'Science', '1'),
('569', '206', 'True', '0'),
('570', '206', 'False', '1'),
('586', '212', 'Making a guess', '0'),
('587', '212', 'Using your senses to gather information', '1'),
('588', '212', 'Writing a conclusion', '0'),
('589', '212', 'Doing an experiment', '0'),
('591', '214', 'True', '1'),
('592', '214', 'False', '0');

-- Table structure for table `quiz_questions`
CREATE TABLE `quiz_questions` (
  `quiz_question_id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  PRIMARY KEY (`quiz_question_id`),
  UNIQUE KEY `uq_quiz_question` (`quiz_id`,`question_id`),
  KEY `fk_quiz_questions_question` (`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=275 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `quiz_questions`
INSERT INTO `quiz_questions` (`quiz_question_id`, `quiz_id`, `question_id`) VALUES
('162', '34', '193'),
('163', '34', '194'),
('164', '34', '195'),
('165', '34', '196'),
('166', '34', '197'),
('167', '34', '198'),
('168', '34', '199'),
('169', '34', '200'),
('170', '34', '201'),
('171', '34', '202'),
('172', '35', '190'),
('173', '35', '191'),
('174', '35', '192'),
('175', '36', '190'),
('176', '36', '191'),
('177', '36', '192'),
('179', '36', '199'),
('180', '36', '200'),
('178', '36', '203'),
('181', '37', '190'),
('182', '37', '191'),
('183', '37', '192'),
('184', '37', '198'),
('185', '37', '199'),
('188', '38', '198'),
('189', '38', '202'),
('186', '38', '205'),
('187', '38', '206'),
('190', '39', '190'),
('193', '39', '203'),
('191', '39', '205'),
('194', '40', '198'),
('195', '40', '199'),
('196', '40', '200'),
('197', '40', '201'),
('198', '40', '202'),
('199', '41', '190'),
('200', '41', '205'),
('201', '41', '206'),
('202', '42', '191'),
('203', '43', '190'),
('204', '43', '191'),
('205', '43', '192'),
('206', '43', '205'),
('207', '43', '206'),
('212', '44', '190'),
('213', '44', '191'),
('214', '44', '192'),
('215', '44', '205'),
('216', '44', '206'),
('221', '45', '190'),
('222', '45', '191'),
('223', '45', '192'),
('224', '45', '205'),
('225', '45', '206'),
('230', '46', '190'),
('231', '46', '191'),
('232', '46', '192'),
('233', '46', '205'),
('234', '46', '206'),
('239', '47', '190'),
('240', '47', '191'),
('241', '47', '192'),
('242', '47', '205'),
('243', '47', '206'),
('244', '48', '190'),
('245', '48', '191'),
('246', '48', '192'),
('247', '48', '205'),
('248', '48', '206'),
('249', '49', '198'),
('250', '49', '199'),
('251', '49', '200'),
('252', '49', '201'),
('253', '49', '202'),
('254', '50', '190'),
('255', '50', '205'),
('256', '51', '193'),
('257', '51', '194'),
('258', '51', '195'),
('259', '51', '196'),
('260', '51', '197'),
('261', '51', '212'),
('262', '51', '214'),
('263', '52', '190'),
('264', '52', '191'),
('265', '52', '192'),
('268', '52', '203'),
('266', '52', '205'),
('267', '52', '206'),
('269', '53', '190'),
('270', '53', '191'),
('271', '53', '192'),
('274', '53', '203'),
('272', '53', '205'),
('273', '53', '206');

-- Table structure for table `quizzes`
CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `quiz_type` enum('mcq','true_false','short_answer','lesson','chapter_graded','summative') NOT NULL DEFAULT 'lesson',
  `total_score` int(11) NOT NULL DEFAULT 0,
  `instruction` text DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `quiz_title` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`quiz_id`),
  KEY `fk_quizzes_stage` (`stage_id`),
  KEY `fk_quizzes_teacher` (`teacher_id`),
  KEY `fk_quiz_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `school_year`
CREATE TABLE `school_year` (
  `sy_id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sy_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `school_year`
INSERT INTO `school_year` (`sy_id`, `label`, `is_active`) VALUES
('1', '2025-2026', '1');

-- Table structure for table `stages`
CREATE TABLE `stages` (
  `stage_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `stage_name` varchar(255) NOT NULL,
  `science_concept` varchar(255) DEFAULT NULL,
  `stage_order` int(11) NOT NULL DEFAULT 1,
  `total_levels` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  PRIMARY KEY (`stage_id`),
  KEY `fk_stages_game` (`game_id`),
  KEY `fk_stages_chapter` (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `stages`
INSERT INTO `stages` (`stage_id`, `game_id`, `stage_name`, `science_concept`, `stage_order`, `total_levels`, `chapter_id`) VALUES
('1', '1', 'Stage 1', 'Scientific Method', '1', '3', '23'),
('2', '1', 'Stage 2', 'Scientific Tools', '2', '3', '23'),
('3', '1', 'Stage 3', 'Being Scientist', '3', '3', '23'),
('19', '1', 'Stage 1', 'Properties of Mixtures', '1', '3', '17'),
('20', '1', 'Stage 2', 'Classifications and Usess Mixtures', '2', '3', '17'),
('21', '1', 'Stage 1', 'Lesson 1: techniques of separating mixtures', '1', '3', '21'),
('22', '1', 'Stage 2', 'Lesson 2: application and benefits of separating mixtures', '2', '3', '21'),
('23', '1', 'Stage 1', 'Lesson 1: Digestive System', '1', '3', '25'),
('24', '1', 'Stage 2', 'Lesson 2: Respiratory System', '2', '3', '25'),
('25', '1', 'Stage 3', 'Lesson 3: Circulatory System', '3', '3', '25'),
('26', '1', 'Stage 4', 'Lesson 4: Nervous System', '4', '3', '25'),
('27', '1', 'Stage 5', 'Lesson 5-7: Musculoskeletal, Integumentary, Excretory System', '5', '3', '25'),
('28', '1', 'Stage 1', 'Lesson 1: Characteristics of Vertebrates and Invertebrates', '1', '3', '26'),
('29', '1', 'Stage 2', 'Lesson 2: Economic importance of Vertebrates and Invertebrates', '2', '3', '26'),
('30', '1', 'Stage 3', 'Lesson 3: Animal Endemic to the Philippines', '3', '3', '26'),
('31', '1', 'Stage 4', 'Lesson 4: Protecting and Caring for Animals', '4', '3', '26');

-- Table structure for table `student_answers`
CREATE TABLE `student_answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_choice_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `fk_answers_student_quiz` (`student_quiz_id`),
  KEY `fk_answers_question` (`question_id`),
  KEY `fk_answers_choice` (`selected_choice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_assessment_results`
CREATE TABLE `student_assessment_results` (
  `assessment_result_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL DEFAULT 0,
  `stage_id` int(11) DEFAULT NULL,
  `assessment_type` enum('stage','chapter') NOT NULL DEFAULT 'stage',
  `score` int(11) NOT NULL DEFAULT 0,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assessment_result_id`),
  KEY `idx_student_assessment` (`student_id`,`assessment_type`,`chapter_id`,`stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_avatar`
CREATE TABLE `student_avatar` (
  `selection_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  PRIMARY KEY (`selection_id`),
  UNIQUE KEY `uq_student_item` (`student_id`,`item_id`),
  KEY `fk_student_avatar_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_badges`
CREATE TABLE `student_badges` (
  `student_badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`student_badge_id`),
  UNIQUE KEY `uq_student_badge` (`student_id`,`badge_id`),
  KEY `fk_student_badges_badge` (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_pretest_results`
CREATE TABLE `student_pretest_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `pretest_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uq_student_pretest` (`student_id`,`pretest_id`),
  KEY `fk_spr_student` (`student_id`),
  KEY `fk_spr_pretest` (`pretest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_quizzes`
CREATE TABLE `student_quizzes` (
  `student_quiz_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `status` enum('locked','in_progress','completed','passed','submitted') NOT NULL DEFAULT 'in_progress',
  `taken_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`student_quiz_id`),
  KEY `fk_sq_quiz` (`quiz_id`),
  KEY `fk_sq_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `students`
CREATE TABLE `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','others') DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  KEY `fk_students_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `students`
INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `last_name`, `profile_image`, `gender`) VALUES
('5', '165', 'Arlene', 'Broquet', NULL, 'female'),
('9', '192', 'Mary', 'aksjksajd', NULL, 'female'),
('11', '194', 'sybsa', 'dad', NULL, 'male'),
('15', '198', 'Anna Leah', 'Zayas', NULL, 'male'),
('16', '199', 'Anna Leah', 'Zayas', NULL, 'male');

-- Table structure for table `teacher_archive_logs`
CREATE TABLE `teacher_archive_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `teacher_user_id` int(11) DEFAULT NULL,
  `admin_user_id` int(11) DEFAULT NULL,
  `archive_reason` varchar(500) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_tal_teacher_id` (`teacher_id`),
  KEY `idx_tal_teacher_user_id` (`teacher_user_id`),
  KEY `idx_tal_admin_user_id` (`admin_user_id`),
  KEY `idx_tal_archived_at` (`archived_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `teacher_archive_logs`
INSERT INTO `teacher_archive_logs` (`log_id`, `teacher_id`, `teacher_user_id`, `admin_user_id`, `archive_reason`, `archived_at`) VALUES
('1', '22', '188', '186', 'adad', '2026-07-31 13:05:42');

-- Table structure for table `teacher_chapter_access`
CREATE TABLE `teacher_chapter_access` (
  `teacher_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`teacher_id`,`chapter_id`),
  KEY `idx_teacher_chapter_access_chapter` (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `teacher_class_chapter_access`
CREATE TABLE `teacher_class_chapter_access` (
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`teacher_id`,`class_id`,`chapter_id`),
  KEY `idx_tcca_class` (`class_id`),
  KEY `idx_tcca_chapter` (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `teacher_permissions`
CREATE TABLE `teacher_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_perm` (`teacher_id`,`permission`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `teacher_permissions`
INSERT INTO `teacher_permissions` (`id`, `teacher_id`, `permission`) VALUES
('4', '6', 'can_manage_classes'),
('1', '6', 'can_manage_questions'),
('2', '6', 'can_manage_quizzes'),
('3', '6', 'can_view_reports');

-- Table structure for table `teachers`
CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`teacher_id`),
  KEY `fk_teachers_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `teachers`
INSERT INTO `teachers` (`teacher_id`, `user_id`, `first_name`, `last_name`, `profile_image`) VALUES
('22', '188', 'Anna Leah', 'Zayas', NULL),
('23', '189', 'Anna', 'Zayas', NULL),
('24', '190', 'Anna Leah', 'Zayas', NULL);

-- Table structure for table `users`
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `email_verification_token_hash` char(64) DEFAULT NULL,
  `email_verification_code_hash` char(64) DEFAULT NULL,
  `email_verification_code_expires_at` datetime DEFAULT NULL,
  `email_verification_expires_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`, `email`, `password`, `username`, `role`, `created_at`, `last_login`, `status`, `email_verified_at`, `email_verification_token_hash`, `email_verification_code_hash`, `email_verification_code_expires_at`, `email_verification_expires_at`, `must_change_password`) VALUES
('186', 'admin@example.com', '$2y$10$WzW2LpA30dYb8zandbIINutTyuuJvH6ZMX5/koMcPWg9VzX65iHZm', 'admin', 'admin', '2026-07-31 12:28:22', '2026-08-01 00:24:10', 'active', NULL, NULL, NULL, NULL, NULL, '0'),
('188', 'anco.zayas.coc@phinmaed.com', '$2y$10$UKgz6JFVT7XXdNVNFwkleeNVmsAU0sGWNABhyoPFPO79BelGcOiKS', 'ssdsdsf', 'teacher', '2026-07-31 12:40:02', NULL, 'inactive', '2026-07-31 12:41:47', NULL, NULL, NULL, NULL, '1'),
('189', 'zayasannaleah@gmail.com', '$2y$10$x2QwpbVI.7zevtlwQbzeu.KbtuG4KI5YqYBc9CdACzQXDCXSyj0fa', 'adadada', 'teacher', '2026-07-31 12:48:06', NULL, 'active', '2026-07-31 12:49:16', NULL, NULL, NULL, NULL, '0'),
('190', 'zaya1sannaleah@gmail.com', '$2y$10$NTwhaZvt81AvzFD7RXVJGuHZi1mw9/If2O87hjCxK9md7X52cRo32', 'sfsf', 'teacher', '2026-07-31 13:05:09', NULL, 'pending', NULL, NULL, '7f9cb6f220b3b6685bfbd7628f1733c3fc26fd99008c06d69f2e28ecbf4c4a17', '2026-08-01 13:12:17', NULL, '1'),
('192', 'annaleahzayas@gmail.com', '$2y$10$.g.kY13HCHWoE6A.ezFsbuDc0Ez543JdSwlMJ.SGvIymNq9.nX9/q', 'ary.aksjksajd765', 'student', '2026-07-31 14:31:57', NULL, 'active', '2026-07-31 14:31:57', NULL, NULL, NULL, NULL, '1'),
('194', 'esicroel7@gmail.com', '$2y$10$jLegllLXlUnczfCx4hVS2.eU1/gxj4cX.gQKT0LClYQjaSIwgHD1y', 'sybsa.dad268', 'student', '2026-07-31 14:37:58', NULL, 'active', '2026-07-31 14:38:59', NULL, NULL, '2026-08-01 14:37:58', NULL, '0'),
('198', 'annaleahzayas2@gmail.com', '$2y$10$ZeFGJOiL.KyCSXjtGL9rGe8/4JKNclX7iuL.k5nkjsd9H5kftsZsK', 'anna.leah.zayas620', 'student', '2026-08-01 00:25:17', NULL, 'active', '2026-08-01 00:28:12', NULL, NULL, '2026-08-01 18:26:40', NULL, '0'),
('199', 'anco.zayas.coc1@phinmaed.com', '$2y$10$Fp/kCkAmz4PQMcBhne5TsuoRHV0u4Awrzqgmjk9oes516Cze75Q.C', 'anna.leah.zayas851', 'student', '2026-08-01 00:30:54', NULL, 'pending', NULL, NULL, 'c814e7dbf6a3a43231c21ffa6be355278b4e120b32b2ad825b699a0b1b6275ed', NULL, '2026-08-02 00:30:54', '1');

COMMIT;
