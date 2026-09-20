-- Atomix Database Backup
-- Generated on: 2026-02-22 00:19:01

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

-- Table structure for table `chapters`
CREATE TABLE `chapters` (
  `chapter_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_title` varchar(255) NOT NULL,
  `chapter_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `chapters`
INSERT INTO `chapters` (`chapter_id`, `chapter_title`, `chapter_order`) VALUES
('17', 'Chapter 2 Mixtures: Properties, classifications, and uses', '2'),
('21', 'Chapter 3 :Separating Mixtures', '3'),
('23', 'Chapter 1 : Science as a way of Knowing', '1'),
('25', 'Chapter 4: living things and their environment', '4'),
('26', 'Chapter 5 : Animal Kingdom', '5');

-- Table structure for table `class_students`
CREATE TABLE `class_students` (
  `class_stud_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` date DEFAULT NULL,
  PRIMARY KEY (`class_stud_id`),
  UNIQUE KEY `uq_class_student` (`class_id`,`student_id`),
  KEY `fk_class_students_student` (`student_id`),
  CONSTRAINT `fk_class_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_class_students_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `class_students`
INSERT INTO `class_students` (`class_stud_id`, `class_id`, `student_id`, `joined_at`) VALUES
('43', '27', '37', '2026-02-14'),
('44', '27', '38', '2026-02-14'),
('45', '27', '39', '2026-02-17'),
('46', '29', '40', NULL),
('47', '29', '41', NULL),
('48', '29', '42', '2026-02-18');

-- Table structure for table `classes`
CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `sy_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`class_id`),
  KEY `fk_classes_teacher` (`teacher_id`),
  KEY `fk_classes_sy` (`sy_id`),
  CONSTRAINT `fk_classes_sy` FOREIGN KEY (`sy_id`) REFERENCES `school_year` (`sy_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `classes`
INSERT INTO `classes` (`class_id`, `teacher_id`, `sy_id`, `class_name`, `created_at`) VALUES
('27', '3', '6', 'Grade 6- Mars', '2026-02-13 22:53:07'),
('28', '3', '6', 'Newton', '2026-02-17 22:08:52'),
('29', '3', '2', 'Rizal', '2026-02-17 22:54:24'),
('30', '3', '2', 'Bonifacio', '2026-02-20 16:55:23');

-- Table structure for table `customization_items`
CREATE TABLE `customization_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category` enum('hair','eyewear','outfit') NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `fk_items_stage` (`stage_id`),
  CONSTRAINT `fk_items_stage` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`stage_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `fk_access_quiz` (`quiz_id`),
  CONSTRAINT `fk_access_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_access_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `game_access_codes`
INSERT INTO `game_access_codes` (`code_id`, `teacher_id`, `quiz_id`, `access_code`, `is_active`, `created_at`) VALUES
('20', '3', '23', 'HLN1SGY3', '1', '2026-02-10 23:17:11'),
('21', '3', '24', 'AAE8CA6C', '1', '2026-02-11 11:59:06'),
('30', '3', '25', '0F3D5670', '1', '2026-02-20 12:24:37'),
('31', '3', '26', 'D31ECDCC', '1', '2026-02-20 12:26:02'),
('32', '3', '27', '37T3JLWA', '1', '2026-02-20 12:29:06'),
('33', '3', '28', '19E11B01', '1', '2026-02-20 12:32:25'),
('34', '3', '29', '6757EA56', '1', '2026-02-20 13:11:55'),
('35', '3', '30', '15B68F0C', '1', '2026-02-20 15:41:49'),
('36', '3', '31', '1CNF1GOP', '1', '2026-02-20 16:55:54'),
('37', '3', '32', '4932EB76', '1', '2026-02-20 17:01:20'),
('38', '3', '33', '3S3O63S6', '1', '2026-02-20 21:11:50');

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
  KEY `fk_progress_stage` (`stage_id`),
  CONSTRAINT `fk_progress_stage` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`stage_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `game_progress`
INSERT INTO `game_progress` (`progress_id`, `student_id`, `stage_id`, `completed_levels`, `score`, `status`, `last_updated`) VALUES
('3', '39', '1', '5', '33', 'completed', '2026-02-17 21:31:30'),
('7', '41', '1', '5', '33', 'completed', '2026-02-18 10:25:36');

-- Table structure for table `games`
CREATE TABLE `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`game_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `games`
INSERT INTO `games` (`game_id`, `game_title`, `description`) VALUES
('1', 'Default Game', 'Auto-created game');

-- Table structure for table `lessons`
CREATE TABLE `lessons` (
  `lesson_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `lesson_title` varchar(255) NOT NULL,
  `lesson_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`lesson_id`),
  KEY `fk_lessons_chapter` (`chapter_id`),
  CONSTRAINT `fk_lessons_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `lessons`
INSERT INTO `lessons` (`lesson_id`, `chapter_id`, `lesson_title`, `lesson_order`) VALUES
('10', '23', 'Lesson 1 : Scientific Methods', '1'),
('11', '17', 'Lesson 1: Properties of Mixtures', '1'),
('12', '17', 'Lesson 2: Classifications and Uses of Mixtures', '2'),
('13', '21', 'Lesson 1: techniques of separating mixtures', '1'),
('14', '21', 'Lesson 2: application and benefits of separating mixtures', '2'),
('15', '25', 'Lesson 1: Respiratory system', '1'),
('16', '25', 'Lesson 2: circulatory system', '2'),
('17', '25', 'Lesson 3: Nervous System', '3'),
('18', '26', 'Lesson 1 : Characteristics of Vertebrates and Invertebrates', '1'),
('19', '26', 'Lesson 2 : economic importance of Vertebrates and Inverterbrates', '2'),
('20', '26', 'Lesson 3 : Animal Endemic to the Philippines', '3'),
('21', '26', 'Lesson 4 : Protecting and Caring for Animals', '4'),
('22', '23', 'Lesson 2: Scientific Tools', '2'),
('23', '23', 'Lesson 3 : Scientist', '3');

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
  KEY `fk_questions_teacher` (`created_by_teacher_id`),
  CONSTRAINT `fk_questions_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`lesson_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_questions_teacher` FOREIGN KEY (`created_by_teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('203', '22', 'What Microscope?', 'mcq', '3', 'private');

-- Table structure for table `quiz_choices`
CREATE TABLE `quiz_choices` (
  `choice_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`choice_id`),
  KEY `fk_choices_question` (`question_id`),
  CONSTRAINT `fk_choices_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=563 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('562', '203', 'Option D', '0');

-- Table structure for table `quiz_questions`
CREATE TABLE `quiz_questions` (
  `quiz_question_id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  PRIMARY KEY (`quiz_question_id`),
  UNIQUE KEY `uq_quiz_question` (`quiz_id`,`question_id`),
  KEY `fk_quiz_questions_question` (`question_id`),
  CONSTRAINT `fk_quiz_questions_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `quiz_questions`
INSERT INTO `quiz_questions` (`quiz_question_id`, `quiz_id`, `question_id`) VALUES
('94', '25', '190'),
('95', '25', '191'),
('96', '25', '192'),
('97', '25', '198'),
('98', '25', '199'),
('99', '25', '200'),
('100', '25', '201'),
('101', '25', '202'),
('102', '26', '190'),
('103', '26', '191'),
('104', '26', '192'),
('105', '26', '198'),
('106', '26', '199'),
('107', '26', '200'),
('108', '26', '201'),
('109', '26', '202'),
('110', '27', '190'),
('111', '27', '191'),
('112', '27', '192'),
('114', '27', '193'),
('115', '27', '194'),
('116', '27', '195'),
('117', '27', '196'),
('118', '27', '197'),
('119', '27', '198'),
('120', '27', '199'),
('121', '27', '200'),
('122', '27', '201'),
('123', '27', '202'),
('113', '27', '203'),
('124', '28', '190'),
('125', '28', '191'),
('126', '28', '192'),
('128', '28', '198'),
('129', '28', '199'),
('130', '28', '200'),
('131', '28', '201'),
('132', '28', '202'),
('127', '28', '203'),
('133', '29', '190'),
('134', '29', '191'),
('135', '29', '192'),
('136', '29', '198'),
('137', '29', '199'),
('138', '29', '200'),
('139', '29', '201'),
('140', '29', '202'),
('141', '30', '190'),
('142', '30', '191'),
('143', '30', '192'),
('145', '30', '201'),
('144', '30', '203'),
('146', '31', '190'),
('147', '31', '191'),
('148', '31', '192'),
('149', '32', '198'),
('150', '32', '199'),
('151', '32', '200'),
('152', '32', '201'),
('153', '32', '202'),
('154', '32', '203'),
('155', '33', '190'),
('156', '33', '191'),
('157', '33', '192'),
('158', '33', '193'),
('159', '33', '194'),
('160', '33', '198'),
('161', '33', '199');

-- Table structure for table `quizzes`
CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `quiz_type` enum('mcq','true_false','short_answer') NOT NULL DEFAULT 'mcq',
  `total_score` int(11) NOT NULL DEFAULT 0,
  `instruction` text DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `quiz_title` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`quiz_id`),
  KEY `fk_quizzes_stage` (`stage_id`),
  KEY `fk_quizzes_teacher` (`teacher_id`),
  KEY `fk_quiz_class` (`class_id`),
  CONSTRAINT `fk_quiz_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  CONSTRAINT `fk_quizzes_stage` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`stage_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quizzes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `quizzes`
INSERT INTO `quizzes` (`quiz_id`, `stage_id`, `teacher_id`, `class_id`, `quiz_type`, `total_score`, `instruction`, `start_time`, `end_time`, `time_limit`, `quiz_title`) VALUES
('23', NULL, '3', NULL, 'mcq', '3', 'Read the Question Carefully', '2026-02-10 23:17:00', '2026-02-11 23:17:00', '5', NULL),
('24', NULL, '3', NULL, 'mcq', '4', 'Readt', '2026-02-11 11:58:00', '2026-02-12 11:58:00', '30', NULL),
('25', NULL, '3', NULL, 'mcq', '8', 'fhgf', '2026-02-20 12:22:00', '2026-02-21 12:22:00', '43', NULL),
('26', NULL, '3', NULL, 'mcq', '8', '', '2026-02-20 12:25:00', '2026-02-21 12:25:00', '21', NULL),
('27', NULL, '3', NULL, 'mcq', '14', 'Read the questions', '2026-02-20 12:28:00', '2026-02-21 12:28:00', '29', NULL),
('28', NULL, '3', NULL, 'mcq', '9', 'reaffwf', '2026-02-20 12:32:00', '2026-02-21 12:32:00', '21', 'Summative test'),
('29', NULL, '3', '27', 'mcq', '8', 'dsadad', '2026-02-20 13:11:00', '2026-02-28 13:11:00', '20', 'Quiz 1 Try'),
('30', NULL, '3', '29', 'mcq', '5', 'Read the questions carefully', '2026-02-20 15:41:00', '2026-02-21 15:41:00', NULL, 'Quiz3 Example'),
('31', NULL, '3', '30', 'mcq', '3', 'sdsf', '2026-02-20 16:55:00', '2026-02-23 16:55:00', NULL, 'Quz2'),
('32', NULL, '3', '29', 'mcq', '6', 'sfsdfs', '2026-02-20 17:01:00', '2026-02-21 17:01:00', NULL, 'Quiz4'),
('33', NULL, '3', '29', 'mcq', '7', 'Read', '2026-02-20 21:11:00', '2026-02-21 21:11:00', NULL, 'Quiz 5');

-- Table structure for table `school_year`
CREATE TABLE `school_year` (
  `sy_id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sy_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `school_year`
INSERT INTO `school_year` (`sy_id`, `label`, `is_active`) VALUES
('2', '2027-2028', '0'),
('6', '2025-2026', '1'),
('19', '2028-2029', '0');

-- Table structure for table `stages`
CREATE TABLE `stages` (
  `stage_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `stage_name` varchar(255) NOT NULL,
  `science_concept` varchar(255) DEFAULT NULL,
  `stage_order` int(11) NOT NULL DEFAULT 1,
  `total_levels` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `stage_title` varchar(255) NOT NULL,
  PRIMARY KEY (`stage_id`),
  KEY `fk_stages_game` (`game_id`),
  KEY `fk_stages_chapter` (`chapter_id`),
  CONSTRAINT `fk_stages_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stages_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `stages`
INSERT INTO `stages` (`stage_id`, `game_id`, `stage_name`, `science_concept`, `stage_order`, `total_levels`, `chapter_id`, `stage_title`) VALUES
('1', '1', 'Stage 1', 'Scientific Method', '1', '3', '23', ''),
('2', '1', 'Stage 2', 'Scientific Tools', '2', '3', '23', ''),
('3', '1', 'Stage 3', 'Being Scientist', '3', '3', '23', '');

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
  KEY `fk_answers_choice` (`selected_choice_id`),
  CONSTRAINT `fk_answers_choice` FOREIGN KEY (`selected_choice_id`) REFERENCES `quiz_choices` (`choice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_answers_student_quiz` FOREIGN KEY (`student_quiz_id`) REFERENCES `student_quizzes` (`student_quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `student_answers`
INSERT INTO `student_answers` (`answer_id`, `student_quiz_id`, `question_id`, `selected_choice_id`, `answer_text`) VALUES
('43', '46', '198', '540', NULL),
('44', '46', '200', '547', NULL),
('45', '46', '201', '552', NULL),
('46', '46', '203', '561', NULL),
('47', '46', '190', '512', NULL),
('48', '46', '202', '555', NULL),
('49', '46', '191', NULL, '4'),
('50', '46', '199', '543', NULL),
('51', '46', '192', '517', NULL),
('52', '47', '202', '558', NULL),
('53', '47', '201', '553', NULL),
('54', '47', '201', '554', NULL),
('55', '47', '191', NULL, '4'),
('56', '47', '198', '541', NULL),
('57', '47', '190', '511', NULL),
('58', '47', '200', '550', NULL),
('59', '47', '199', '543', NULL),
('60', '47', '202', '558', NULL),
('61', '47', '192', '516', NULL),
('72', '50', '201', '553', NULL),
('73', '50', '191', NULL, '4'),
('74', '50', '192', '517', NULL),
('75', '50', '190', '514', NULL),
('76', '50', '203', '562', NULL),
('83', '52', '198', '541', NULL),
('84', '52', '202', '557', NULL),
('85', '52', '199', '545', NULL),
('86', '52', '200', '547', NULL),
('87', '52', '201', '554', NULL),
('88', '52', '203', '561', NULL),
('89', '53', '199', '544', NULL),
('90', '53', '193', '522', NULL),
('91', '53', '194', '525', NULL),
('92', '53', '192', '516', NULL),
('93', '53', '190', '512', NULL),
('94', '53', '191', NULL, '4'),
('95', '53', '198', '541', NULL),
('96', '53', '198', '540', NULL),
('97', '53', '192', '516', NULL),
('98', '53', '190', '513', NULL),
('99', '53', '199', '543', NULL),
('100', '53', '191', NULL, '2'),
('101', '53', '193', '521', NULL),
('102', '53', '194', '524', NULL);

-- Table structure for table `student_avatar`
CREATE TABLE `student_avatar` (
  `selection_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  PRIMARY KEY (`selection_id`),
  UNIQUE KEY `uq_student_item` (`student_id`,`item_id`),
  KEY `fk_student_avatar_item` (`item_id`),
  CONSTRAINT `fk_student_avatar_item` FOREIGN KEY (`item_id`) REFERENCES `customization_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_avatar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_badges`
CREATE TABLE `student_badges` (
  `student_badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`student_badge_id`),
  UNIQUE KEY `uq_student_badge` (`student_id`,`badge_id`),
  KEY `fk_student_badges_badge` (`badge_id`),
  CONSTRAINT `fk_student_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`badge_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_badges_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_quizzes`
CREATE TABLE `student_quizzes` (
  `student_quiz_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `status` enum('locked','in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `taken_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`student_quiz_id`),
  UNIQUE KEY `uq_student_quiz` (`student_id`,`quiz_id`),
  KEY `fk_student_quizzes_quiz` (`quiz_id`),
  CONSTRAINT `fk_student_quizzes_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_quizzes_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `student_quizzes`
INSERT INTO `student_quizzes` (`student_quiz_id`, `student_id`, `quiz_id`, `score`, `status`, `taken_at`, `started_at`, `submitted_at`) VALUES
('46', '41', '28', '3', 'completed', NULL, '2026-02-20 12:33:30', '2026-02-20 12:33:53'),
('47', '41', '29', '3', 'completed', '2026-02-20 13:34:31', '2026-02-20 13:13:31', '2026-02-20 13:35:14'),
('50', '41', '30', '2', 'completed', '2026-02-20 17:00:38', '2026-02-20 16:54:38', '2026-02-20 16:55:05'),
('52', '41', '32', '2', 'completed', '2026-02-20 17:13:26', '2026-02-20 17:13:11', '2026-02-20 17:13:21'),
('53', '41', '33', '7', 'completed', NULL, '2026-02-20 21:12:45', '2026-02-20 21:35:23');

-- Table structure for table `students`
CREATE TABLE `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `section` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','others') DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  KEY `fk_students_user` (`user_id`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `students`
INSERT INTO `students` (`student_id`, `user_id`, `section`, `first_name`, `last_name`, `profile_image`, `gender`) VALUES
('37', '46', NULL, 'Anna Leah', 'bugo', NULL, 'female'),
('38', '47', NULL, 'Anna Leah', 'bugo', NULL, 'female'),
('39', '48', NULL, 'Jorge', 'Waslo', NULL, 'male'),
('40', '49', '', 'Dwayne', 'Mons', NULL, 'male'),
('41', '50', '', 'Mary Jane', 'Sams', NULL, 'female'),
('42', '51', NULL, 'Nino', 'Olarita', NULL, 'male');

-- Table structure for table `teachers`
CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`teacher_id`),
  KEY `fk_teachers_user` (`user_id`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `teachers`
INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject`, `first_name`, `last_name`, `profile_image`) VALUES
('3', '3', 'Science', 'John', 'Doe', NULL),
('7', '25', NULL, 'Macario', 'Pitok', NULL),
('9', '53', NULL, 'buff', 'qeqe', NULL),
('10', '54', NULL, 'Anna Leah', 'Zayas', NULL);

-- Table structure for table `topics`
CREATE TABLE `topics` (
  `topic_id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `topic_title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  PRIMARY KEY (`topic_id`),
  KEY `fk_topics_lesson` (`lesson_id`),
  CONSTRAINT `fk_topics_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`lesson_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `users`
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`, `email`, `password`, `username`, `role`, `created_at`, `last_login`, `status`, `must_change_password`) VALUES
('3', 'teacher1@example.com', '$2y$10$7CPm6jsUPP5NULlcljy6lOY/ajNYv90nRQn/fbQlFLHxUX73APgce', 'teacher1', 'teacher', '2026-02-03 22:32:37', '2026-02-21 11:31:20', 'active', '0'),
('4', 'admin@example.com', '$2y$10$pyUiRcvLfzYAJ3pZPtexC.FKNSlU4HDWm8td92pIxT6Nc7OhiHz1.', 'admin', 'admin', '2026-02-04 01:50:15', NULL, 'active', '0'),
('9', 'teacher2@example.com', '$2y$10$ixpA/7tciU8atxRhy/TU2./QZ62SyMKJzoZs/ocNSMQd7CPpF8.NK', 'John', 'teacher', '2026-02-06 22:57:52', '2026-02-08 20:31:39', 'active', '1'),
('23', 'alzayas24817@liceo.edu.ph', '$2y$10$kGO4ZPULnR8T5gpiBy3SNeVotfWw6eeN.L4GbiFy7dFFnq1fIm1Du', 'buff', 'teacher', '2026-02-08 19:59:30', NULL, 'active', '1'),
('24', 'gen123@gmail.com', '$2y$10$nngABCUpm9LaNpZVvLoQb.VZF/nFa08ZwlC.LAppvxxf0nyjPCG6e', 'olaritarestaurant', 'teacher', '2026-02-08 20:21:12', NULL, 'active', '1'),
('25', 'mac@gmail.com', '$2y$10$cZ0uy/wueholgBXOIew59u67jlFm0.70Gu7tmnCIuW9pF.WaIe1NO', 'mac', 'teacher', '2026-02-08 22:39:35', '2026-02-13 00:15:52', 'active', '1'),
('46', 'annaleahzayas@gmail.com', '$2y$10$y70304WxLSC2tCNsiQE4FeAL//zEXXaB65UpUTfympRAuRQW.xgQK', 'anna.leah.bugo490', 'student', '2026-02-13 22:53:34', NULL, 'active', '1'),
('47', 'student1@example.com', '$2y$10$ZWnGd3HifZFgKe/Al7W3oe.SOGXAQ4dADsbhj50iReaY3eyw8ImKS', 'anna.leah.bugo173', 'student', '2026-02-14 00:18:40', '2026-02-16 23:17:18', 'active', '0'),
('48', 'Jorge@gmail.com', '$2y$10$ya.w.xLdTAxmXXADpn57HedUDN6E15sXbkyg93ZVri8BG17XCH.k.', 'jorge.waslo924', 'student', '2026-02-17 20:50:34', NULL, 'active', '0'),
('49', 'dwayne@gmail.com', '$2y$10$ZImL7BPHzfPRb7/S.hAqeOs9dh5Cj9PuCTRg6gzdvAtYb4LSS/3yi', 'wayne.ons144', 'student', '2026-02-17 23:34:38', NULL, 'inactive', '1'),
('50', 'jane.smith@example.com', '$2y$10$jtYp7vhGEQTYIVJ0/zoRAufvkF9BgEJV8b3rdeWacf7rkUeVDSSkm', 'aryane.ams469', 'student', '2026-02-17 23:34:38', NULL, 'inactive', '0'),
('51', 'nino@gmail.com', '$2y$10$oooPzzSYDk7bIqwEiu4iouxL9ZBUYyf1GBDtaDqyOvKKUsL8GsHvC', 'nino.olarita225', 'student', '2026-02-18 12:04:10', NULL, 'inactive', '1'),
('53', 'zayasannaleah@gmail.com', '$2y$10$GYmb38cdQ5c.RlTLg8LNSO6Iwbcvaey6i/.L/T/STOEdq8l.dLJx2', 'asdaddsada', 'teacher', '2026-02-20 21:25:38', NULL, 'active', '1'),
('54', 'anco.zayas.coc@phinmaed.com', '$2y$10$X5PA9sADILDI7m730wvfP.YCvc4vYCwdRSqObmH8C5vG1fmP1xy2q', 'adadq', 'teacher', '2026-02-20 21:26:54', NULL, 'active', '1');

COMMIT;
