-- Atomix Database Backup
-- Generated on: 2026-04-14 22:07:44

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
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `class_students`
INSERT INTO `class_students` (`class_stud_id`, `class_id`, `student_id`, `joined_at`) VALUES
('49', '28', '43', '2026-02-23'),
('66', '2', '58', '2026-03-24');

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
  CONSTRAINT `fk_classes_sy` FOREIGN KEY (`sy_id`) REFERENCES `school_year` (`sy_id`),
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `classes`
INSERT INTO `classes` (`class_id`, `teacher_id`, `sy_id`, `class_name`, `created_at`) VALUES
('1', '3', '6', 'Bonifacio', '2026-03-07 16:00:04'),
('2', '3', '6', '31', '2026-03-10 20:30:42');

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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `game_access_codes`
INSERT INTO `game_access_codes` (`code_id`, `teacher_id`, `quiz_id`, `access_code`, `is_active`, `created_at`) VALUES
('39', '3', '34', 'MZOFWC2D', '1', '2026-02-23 18:20:38'),
('40', '3', '35', '9D4F0AB8', '0', '2026-02-23 18:21:38'),
('41', '3', '36', 'C19A3D14', '1', '2026-02-23 18:45:35'),
('42', '3', '37', 'ZPFY2PP1', '1', '2026-02-23 19:11:40'),
('43', '3', '38', '5UFKXGQP', '1', '2026-02-26 22:51:56'),
('44', '3', '39', 'HRVY0TXK', '1', '2026-02-28 15:56:10'),
('45', '3', '40', '7A578A41', '1', '2026-03-07 16:01:15'),
('46', '3', '41', 'CD0B3CBC', '1', '2026-03-27 11:18:00');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `game_progress`
INSERT INTO `game_progress` (`progress_id`, `student_id`, `stage_id`, `completed_levels`, `score`, `status`, `last_updated`) VALUES
('8', '43', '1', '0', '0', 'in_progress', '2026-02-28 15:59:57'),
('9', '58', '1', '0', '0', 'in_progress', '2026-03-25 21:52:16');

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `game_questions`
INSERT INTO `game_questions` (`game_question_id`, `lesson_id`, `question_text`, `answer_0`, `answer_1`, `answer_2`, `answer_3`, `correct_answer_index`, `created_by_teacher_id`) VALUES
('1', '10', 'What is observation?', 'Making a guess', 'Using your senses to gather information', 'Writing a conclusion', 'Doing an experiment', '1', '3'),
('3', '10', 'What Is Science', 'Science is subject matter of all existence', 'Science is everywhere , Everytime, Everything', 'Science is heavily body of rocks an atmosphere', 'Science is a subject', '0', '3'),
('4', '10', 'What did Dart do when he saw the corrupted plant in the ground?', 'He ignored the plant', 'He experimented first, then observed after', 'He made a hypothesis and just quickly tried acting on it', 'He observed first, then try to expirement', '3', '3'),
('5', '10', 'What did Dart do when he has to fix the door surrounded by vines?', 'He gathered his hypothesis, made a guess and expiremented', 'He did not gather anything', 'He did not go through the door', 'He expiremented without having a guess or hypothesis first', '0', '3');

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
) ENGINE=InnoDB AUTO_INCREMENT=210 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('207', '10', 'What is observation?', 'mcq', '3', 'private'),
('208', '10', 'What is 2 + 2?', 'short_answer', '3', 'private'),
('209', '10', 'The sky is blue', 'true_false', '3', 'private');

-- Table structure for table `quiz_choices`
CREATE TABLE `quiz_choices` (
  `choice_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`choice_id`),
  KEY `fk_choices_question` (`question_id`),
  CONSTRAINT `fk_choices_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=578 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('571', '207', 'Making a guess', '0'),
('572', '207', 'Using your senses to gather information', '1'),
('573', '207', 'Writing a conclusion', '0'),
('574', '207', 'Doing an experiment', '0'),
('575', '208', '4', '1'),
('576', '209', 'True', '1'),
('577', '209', 'False', '0');

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
) ENGINE=InnoDB AUTO_INCREMENT=202 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('192', '39', '208'),
('194', '40', '198'),
('195', '40', '199'),
('196', '40', '200'),
('197', '40', '201'),
('198', '40', '202'),
('199', '41', '190'),
('200', '41', '205'),
('201', '41', '206');

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
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `quizzes`
INSERT INTO `quizzes` (`quiz_id`, `stage_id`, `teacher_id`, `class_id`, `quiz_type`, `total_score`, `instruction`, `start_time`, `end_time`, `time_limit`, `quiz_title`) VALUES
('34', NULL, '3', '27', 'mcq', '10', 'Read the question carefully', '2026-02-23 18:19:00', '2026-02-24 18:19:00', '30', 'Quiz'),
('35', NULL, '3', '28', 'mcq', '3', 'Read carefully', '2026-02-24 18:21:00', '2026-02-25 18:21:00', '10', 'Quiz'),
('36', NULL, '3', '28', 'mcq', '6', '', '2026-02-23 18:44:00', '2026-02-23 19:44:00', '5', 'Quiz 2'),
('37', NULL, '3', '28', 'mcq', '5', 'sfs', '2026-02-23 19:11:00', '2026-02-24 19:11:00', '5', 'Quiz 3'),
('38', NULL, '3', '28', 'mcq', '4', 'Read each question carefully before answering.', '2026-02-26 22:51:00', '2026-02-27 22:51:00', '3', 'Quiz 1'),
('39', NULL, '3', '28', 'mcq', '4', 'Read the question carefully', '2026-02-28 15:55:00', '2026-03-01 15:55:00', '3', 'Defense Quiz'),
('40', NULL, '3', '1', 'mcq', '5', 'Read the question carefully', '2026-03-07 16:00:00', '2026-03-09 16:00:00', '5', 'Quiz'),
('41', NULL, '3', '2', 'mcq', '3', '', '2026-03-27 11:17:00', '2026-03-28 11:17:00', '2', 'Quiz Test');

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
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `student_answers`
INSERT INTO `student_answers` (`answer_id`, `student_quiz_id`, `question_id`, `selected_choice_id`, `answer_text`) VALUES
('133', '56', '200', '548', NULL),
('134', '56', '190', '512', NULL),
('135', '56', '192', '516', NULL),
('136', '56', '199', '545', NULL),
('137', '56', '191', NULL, '4'),
('138', '56', '203', '559', NULL),
('139', '57', '191', NULL, '4'),
('140', '57', '192', '516', NULL),
('141', '57', '199', '543', NULL),
('142', '57', '198', '542', NULL),
('143', '57', '190', '513', NULL),
('144', '58', '198', '540', NULL),
('145', '58', '202', '556', NULL),
('146', '58', '205', NULL, 'Science'),
('147', '58', '206', '570', NULL),
('148', '59', '208', NULL, '4'),
('149', '59', '203', '560', NULL),
('150', '59', '190', '513', NULL),
('151', '59', '205', NULL, 'DAD'),
('152', '60', '205', NULL, 'dadad'),
('153', '60', '190', '511', NULL),
('154', '60', '206', '569', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `student_quizzes`
INSERT INTO `student_quizzes` (`student_quiz_id`, `student_id`, `quiz_id`, `score`, `status`, `taken_at`, `started_at`, `submitted_at`) VALUES
('56', '43', '36', '6', 'completed', NULL, '2026-02-23 18:46:21', '2026-02-23 18:46:53'),
('57', '43', '37', '2', 'completed', NULL, '2026-02-23 19:12:13', '2026-02-23 19:12:34'),
('58', '43', '38', '3', 'completed', NULL, '2026-02-26 22:55:20', '2026-02-26 22:55:46'),
('59', '43', '39', '1', 'completed', NULL, '2026-02-28 15:58:18', '2026-02-28 15:58:44'),
('60', '58', '41', '0', 'completed', NULL, '2026-03-27 11:18:15', '2026-03-27 11:18:34');

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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `students`
INSERT INTO `students` (`student_id`, `user_id`, `section`, `first_name`, `last_name`, `profile_image`, `gender`) VALUES
('43', '55', 'Grade 1', 'Shiela', 'Mae', 'profile_43_1772116377.png', 'female'),
('58', '73', NULL, 'Anna', 'Zayas', NULL, 'male');

-- Table structure for table `teacher_permissions`
CREATE TABLE `teacher_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_perm` (`teacher_id`,`permission`),
  CONSTRAINT `fk_tp_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `teacher_permissions`
INSERT INTO `teacher_permissions` (`id`, `teacher_id`, `permission`) VALUES
('105', '3', 'can_manage_classes'),
('102', '3', 'can_manage_questions'),
('103', '3', 'can_manage_quizzes'),
('106', '3', 'can_manage_students'),
('104', '3', 'can_view_reports'),
('24', '7', 'can_manage_classes'),
('21', '7', 'can_manage_questions'),
('22', '7', 'can_manage_quizzes'),
('25', '7', 'can_manage_students'),
('23', '7', 'can_view_reports'),
('29', '9', 'can_manage_classes'),
('26', '9', 'can_manage_questions'),
('27', '9', 'can_manage_quizzes'),
('30', '9', 'can_manage_students'),
('28', '9', 'can_view_reports'),
('114', '10', 'can_manage_classes'),
('111', '10', 'can_manage_questions'),
('112', '10', 'can_manage_quizzes'),
('115', '10', 'can_manage_students'),
('113', '10', 'can_view_reports'),
('9', '11', 'can_manage_classes'),
('6', '11', 'can_manage_questions'),
('7', '11', 'can_manage_quizzes'),
('10', '11', 'can_manage_students'),
('8', '11', 'can_view_reports'),
('14', '12', 'can_manage_classes'),
('11', '12', 'can_manage_questions'),
('12', '12', 'can_manage_quizzes'),
('15', '12', 'can_manage_students'),
('13', '12', 'can_view_reports'),
('19', '13', 'can_manage_classes'),
('16', '13', 'can_manage_questions'),
('17', '13', 'can_manage_quizzes'),
('20', '13', 'can_manage_students'),
('18', '13', 'can_view_reports'),
('39', '14', 'can_manage_classes'),
('36', '14', 'can_manage_questions'),
('37', '14', 'can_manage_quizzes'),
('40', '14', 'can_manage_students'),
('38', '14', 'can_view_reports'),
('93', '15', 'can_manage_classes'),
('91', '15', 'can_manage_questions'),
('94', '15', 'can_manage_students'),
('92', '15', 'can_view_reports');

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `teachers`
INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject`, `first_name`, `last_name`, `profile_image`) VALUES
('3', '3', 'Science', 'John', 'Doe', NULL),
('7', '25', NULL, 'Macario', 'Pitok', NULL),
('9', '53', NULL, 'buff', 'qeqe', NULL),
('10', '54', NULL, 'Anna Leah', 'Zayas', NULL),
('11', '9', NULL, 'John', 'Doe', NULL),
('12', '23', NULL, 'Buff', 'Zayas', NULL),
('13', '24', NULL, 'Olarita', 'Restaurant', NULL),
('14', '56', NULL, 'Juan', 'Olarita', NULL),
('15', '58', NULL, 'John', 'Doe', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`, `email`, `password`, `username`, `role`, `created_at`, `last_login`, `status`, `must_change_password`) VALUES
('3', 'teacher1@example.com', '$2y$10$fTf5Lg7bB1k8vWlMBWtqAujJ0yq2GXAHxMZji9tIzJRdKRbg4d7sC', 'teacher1', 'teacher', '2026-02-03 22:32:37', '2026-04-01 16:58:01', 'active', '0'),
('9', 'teacher2@example.com', '$2y$10$ixpA/7tciU8atxRhy/TU2./QZ62SyMKJzoZs/ocNSMQd7CPpF8.NK', 'John', 'teacher', '2026-02-06 22:57:52', '2026-02-08 20:31:39', 'active', '1'),
('23', 'alzayas24817@liceo.edu.ph', '$2y$10$kGO4ZPULnR8T5gpiBy3SNeVotfWw6eeN.L4GbiFy7dFFnq1fIm1Du', 'buff', 'teacher', '2026-02-08 19:59:30', NULL, 'active', '1'),
('24', 'gen123@gmail.com', '$2y$10$nngABCUpm9LaNpZVvLoQb.VZF/nFa08ZwlC.LAppvxxf0nyjPCG6e', 'olaritarestaurant', 'teacher', '2026-02-08 20:21:12', NULL, 'active', '1'),
('25', 'mac@gmail.com', '$2y$10$cZ0uy/wueholgBXOIew59u67jlFm0.70Gu7tmnCIuW9pF.WaIe1NO', 'mac', 'teacher', '2026-02-08 22:39:35', '2026-02-13 00:15:52', 'active', '1'),
('53', 'zayasannaleah@gmail.com', '$2y$10$4TKpKQSW8wScAUzox5G.n./tGkWiAk6h48XvZbI5AJb/X/l7qgSqe', 'asdaddsada', 'teacher', '2026-02-20 21:25:38', '2026-03-08 20:32:33', 'active', '0'),
('54', 'anco.zayas.coc@phinmaed.com', '$2y$10$VNHku2ZF4.oXmVsRN8k9veen37CuHBDwteD3QLzPcgACxZW7fZ2I.', 'adadq', 'teacher', '2026-02-20 21:26:54', NULL, 'active', '1'),
('55', 'shiela@gmail.com', '$2y$10$SbSxFtnHqH84M0hQT5Uw5uN8NM7N9M5SCvdNQHuEY4BgDA1xgLZAi', 'shiela.dela.cruz842', 'student', '2026-02-23 18:43:57', NULL, 'active', '0'),
('56', 'admin@atomix.com', '$2y$10$Jtg6fhUaEtGK143GgsTO9e7M5zEtvdsPiSmU2Elylg3Dasp132rQu', 'admin', 'admin', '2026-03-07 15:53:08', NULL, 'active', '0'),
('58', 'teacher@atomix.com', '$2y$10$oxNUR182MzU.lt143uyAC.a8zr77AmnewTZ3ZdlCoJrDlwgRPrj0y', 'teacher', 'teacher', '2026-03-07 15:53:27', '2026-03-07 15:53:51', 'active', '0'),
('73', 'anco.zayas.coc@phinmaed.co121m', '$2y$10$zqQAosoIBdxAw6W1VyIpO.gBThD6Ua2d9oM.5Z9byMhGfLeD9i3xi', 'anna.zayas722', 'student', '2026-03-24 16:49:27', NULL, 'active', '0');

COMMIT;
