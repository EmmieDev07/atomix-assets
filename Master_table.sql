-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 11:21 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `atomix_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `criteria` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `chapter_id` int(11) NOT NULL,
  `chapter_title` varchar(255) NOT NULL,
  `chapter_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chapters`
--

INSERT INTO `chapters` (`chapter_id`, `chapter_title`, `chapter_order`) VALUES
(17, 'Chapter 2 Mixtures: Properties, classifications, and uses', 2),
(21, 'Chapter 3 :Separating Mixtures', 3),
(23, 'Chapter 1 : Science as a way of Knowing', 1),
(25, 'Chapter 4: living things and their environment', 4),
(26, 'Chapter 5 : Animal Kingdom', 5);

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `sy_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_students`
--

CREATE TABLE `class_students` (
  `class_stud_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customization_items`
--

CREATE TABLE `customization_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category` enum('hair','eyewear','outfit') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `game_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`game_id`, `game_title`, `description`) VALUES
(1, 'Default Game', 'Auto-created game');

-- --------------------------------------------------------

--
-- Table structure for table `game_access_codes`
--

CREATE TABLE `game_access_codes` (
  `code_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `access_code` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_progress`
--

CREATE TABLE `game_progress` (
  `progress_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `completed_levels` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `status` enum('locked','in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_questions`
--

CREATE TABLE `game_questions` (
  `game_question_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `answer_0` varchar(255) NOT NULL DEFAULT '',
  `answer_1` varchar(255) NOT NULL DEFAULT '',
  `answer_2` varchar(255) NOT NULL DEFAULT '',
  `answer_3` varchar(255) NOT NULL DEFAULT '',
  `correct_answer_index` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_teacher_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `game_questions`
--

INSERT INTO `game_questions` (`game_question_id`, `lesson_id`, `question_text`, `answer_0`, `answer_1`, `answer_2`, `answer_3`, `correct_answer_index`, `created_by_teacher_id`) VALUES
(1, 10, 'What is observation?', 'Making a guess', 'Using your senses to gather information', 'Writing a conclusion', 'Doing an experiment', 1, 3),
(3, 10, 'What Is Scsd', 'dssd', 'sdsds', 'sdssd', 'dsd', 1, 3),
(4, 10, 'dfds', 'sdfs', 'fsdfsd', 'fsd', 'fsdfsdf', 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `lesson_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `lesson_title` varchar(255) NOT NULL,
  `lesson_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`lesson_id`, `chapter_id`, `lesson_title`, `lesson_order`) VALUES
(10, 23, 'Lesson 1 : Scientific Methods', 1),
(11, 17, 'Lesson 1: Properties of Mixtures', 1),
(12, 17, 'Lesson 2: Classifications and Uses of Mixtures', 2),
(13, 21, 'Lesson 1: techniques of separating mixtures', 1),
(14, 21, 'Lesson 2: application and benefits of separating mixtures', 2),
(15, 25, 'Lesson 1: Respiratory system', 1),
(16, 25, 'Lesson 2: circulatory system', 2),
(17, 25, 'Lesson 3: Nervous System', 3),
(18, 26, 'Lesson 1 : Characteristics of Vertebrates and Invertebrates', 1),
(19, 26, 'Lesson 2 : economic importance of Vertebrates and Inverterbrates', 2),
(20, 26, 'Lesson 3 : Animal Endemic to the Philippines', 3),
(21, 26, 'Lesson 4 : Protecting and Caring for Animals', 4),
(22, 23, 'Lesson 2: Scientific Tools', 2),
(23, 23, 'Lesson 3 : Scientist', 3);

-- --------------------------------------------------------

--
-- Table structure for table `questions_master`
--

CREATE TABLE `questions_master` (
  `question_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(50) DEFAULT NULL,
  `created_by_teacher_id` int(11) NOT NULL,
  `visibility` enum('private','public') NOT NULL DEFAULT 'private'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `quiz_type` enum('mcq','true_false','short_answer') NOT NULL DEFAULT 'mcq',
  `total_score` int(11) NOT NULL DEFAULT 0,
  `instruction` text DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `quiz_title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_choices`
--

CREATE TABLE `quiz_choices` (
  `choice_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `quiz_question_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_year`
--

CREATE TABLE `school_year` (
  `sy_id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_year`
--

INSERT INTO `school_year` (`sy_id`, `label`, `is_active`) VALUES
(2, '2027-2028', 0),
(6, '2025-2026', 1),
(19, '2028-2029', 0);

-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

CREATE TABLE `stages` (
  `stage_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `stage_name` varchar(255) NOT NULL,
  `science_concept` varchar(255) DEFAULT NULL,
  `stage_order` int(11) NOT NULL DEFAULT 1,
  `total_levels` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `stage_title` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stages`
--

INSERT INTO `stages` (`stage_id`, `game_id`, `stage_name`, `science_concept`, `stage_order`, `total_levels`, `chapter_id`, `stage_title`) VALUES
(1, 1, 'Stage 1', 'Scientific Method', 1, 3, 23, ''),
(2, 1, 'Stage 2', 'Scientific Tools', 2, 3, 23, ''),
(3, 1, 'Stage 3', 'Being Scientist', 3, 3, 23, '');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `section` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','others') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `section`, `first_name`, `last_name`, `profile_image`, `gender`) VALUES
(43, 55, 'Grade 1', 'Shiela', 'Mae', 'profile_43_1772116377.png', 'female'),
(58, 73, NULL, 'Anna', 'Zayas', NULL, 'male');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `answer_id` int(11) NOT NULL,
  `student_quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_choice_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_avatar`
--

CREATE TABLE `student_avatar` (
  `selection_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_badges`
--

CREATE TABLE `student_badges` (
  `student_badge_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_quizzes`
--

CREATE TABLE `student_quizzes` (
  `student_quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `status` enum('locked','in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `taken_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject`, `first_name`, `last_name`, `profile_image`) VALUES
(3, 3, 'Science', 'John', 'Doe', NULL),
(7, 25, NULL, 'Macario', 'Pitok', NULL),
(9, 53, NULL, 'buff', 'qeqe', NULL),
(10, 54, NULL, 'Anna Leah', 'Zayas', NULL),
(11, 9, NULL, 'John', 'Doe', NULL),
(12, 23, NULL, 'Buff', 'Zayas', NULL),
(13, 24, NULL, 'Olarita', 'Restaurant', NULL),
(14, 56, NULL, 'Juan', 'Olarita', NULL),
(15, 58, NULL, 'John', 'Doe', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `topic_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `topic_title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `username`, `role`, `created_at`, `last_login`, `status`, `must_change_password`) VALUES
(3, 'teacher1@example.com', '$2y$10$fTf5Lg7bB1k8vWlMBWtqAujJ0yq2GXAHxMZji9tIzJRdKRbg4d7sC', 'teacher1', 'teacher', '2026-02-03 22:32:37', '2026-03-24 19:09:09', 'active', 0),
(9, 'teacher2@example.com', '$2y$10$ixpA/7tciU8atxRhy/TU2./QZ62SyMKJzoZs/ocNSMQd7CPpF8.NK', 'John', 'teacher', '2026-02-06 22:57:52', '2026-02-08 20:31:39', 'active', 1),
(23, 'alzayas24817@liceo.edu.ph', '$2y$10$kGO4ZPULnR8T5gpiBy3SNeVotfWw6eeN.L4GbiFy7dFFnq1fIm1Du', 'buff', 'teacher', '2026-02-08 19:59:30', NULL, 'active', 1),
(24, 'gen123@gmail.com', '$2y$10$nngABCUpm9LaNpZVvLoQb.VZF/nFa08ZwlC.LAppvxxf0nyjPCG6e', 'olaritarestaurant', 'teacher', '2026-02-08 20:21:12', NULL, 'active', 1),
(25, 'mac@gmail.com', '$2y$10$cZ0uy/wueholgBXOIew59u67jlFm0.70Gu7tmnCIuW9pF.WaIe1NO', 'mac', 'teacher', '2026-02-08 22:39:35', '2026-02-13 00:15:52', 'active', 1),
(53, 'zayasannaleah@gmail.com', '$2y$10$4TKpKQSW8wScAUzox5G.n./tGkWiAk6h48XvZbI5AJb/X/l7qgSqe', 'asdaddsada', 'teacher', '2026-02-20 21:25:38', '2026-03-08 20:32:33', 'active', 0),
(54, 'anco.zayas.coc@phinmaed.com', '$2y$10$VNHku2ZF4.oXmVsRN8k9veen37CuHBDwteD3QLzPcgACxZW7fZ2I.', 'adadq', 'teacher', '2026-02-20 21:26:54', NULL, 'active', 1),
(55, 'shiela@gmail.com', '$2y$10$SbSxFtnHqH84M0hQT5Uw5uN8NM7N9M5SCvdNQHuEY4BgDA1xgLZAi', 'shiela.dela.cruz842', 'student', '2026-02-23 18:43:57', NULL, 'active', 0),
(56, 'admin@atomix.com', '$2y$10$Jtg6fhUaEtGK143GgsTO9e7M5zEtvdsPiSmU2Elylg3Dasp132rQu', 'admin', 'admin', '2026-03-07 15:53:08', NULL, 'active', 0),
(58, 'teacher@atomix.com', '$2y$10$oxNUR182MzU.lt143uyAC.a8zr77AmnewTZ3ZdlCoJrDlwgRPrj0y', 'teacher', 'teacher', '2026-03-07 15:53:27', '2026-03-07 15:53:51', 'active', 0),
(73, 'anco.zayas.coc@phinmaed.co121m', '$2y$10$BOVU9ApIl5Ui5pPJ3dhLy.Vq9hL6vzGs.IZVgb1D5Ys9Q2K182bDG', 'anna.zayas722', 'student', '2026-03-24 16:49:27', NULL, 'active', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`badge_id`);

--
-- Indexes for table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`chapter_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `fk_classes_teacher` (`teacher_id`),
  ADD KEY `fk_classes_sy` (`sy_id`);

--
-- Indexes for table `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`class_stud_id`),
  ADD UNIQUE KEY `uq_class_student` (`class_id`,`student_id`),
  ADD KEY `fk_class_students_student` (`student_id`);

--
-- Indexes for table `customization_items`
--
ALTER TABLE `customization_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_items_stage` (`stage_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`);

--
-- Indexes for table `game_access_codes`
--
ALTER TABLE `game_access_codes`
  ADD PRIMARY KEY (`code_id`),
  ADD UNIQUE KEY `uq_access_code` (`access_code`),
  ADD KEY `fk_access_teacher` (`teacher_id`),
  ADD KEY `fk_access_quiz` (`quiz_id`);

--
-- Indexes for table `game_progress`
--
ALTER TABLE `game_progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD UNIQUE KEY `uq_student_stage` (`student_id`,`stage_id`),
  ADD KEY `fk_progress_stage` (`stage_id`);

--
-- Indexes for table `game_questions`
--
ALTER TABLE `game_questions`
  ADD PRIMARY KEY (`game_question_id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `teacher_id` (`created_by_teacher_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`lesson_id`),
  ADD KEY `fk_lessons_chapter` (`chapter_id`);

--
-- Indexes for table `questions_master`
--
ALTER TABLE `questions_master`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `fk_questions_lesson` (`lesson_id`),
  ADD KEY `fk_questions_teacher` (`created_by_teacher_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`),
  ADD KEY `fk_quizzes_stage` (`stage_id`),
  ADD KEY `fk_quizzes_teacher` (`teacher_id`),
  ADD KEY `fk_quiz_class` (`class_id`);

--
-- Indexes for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  ADD PRIMARY KEY (`choice_id`),
  ADD KEY `fk_choices_question` (`question_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`quiz_question_id`),
  ADD UNIQUE KEY `uq_quiz_question` (`quiz_id`,`question_id`),
  ADD KEY `fk_quiz_questions_question` (`question_id`);

--
-- Indexes for table `school_year`
--
ALTER TABLE `school_year`
  ADD PRIMARY KEY (`sy_id`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`stage_id`),
  ADD KEY `fk_stages_game` (`game_id`),
  ADD KEY `fk_stages_chapter` (`chapter_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `fk_students_user` (`user_id`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `fk_answers_student_quiz` (`student_quiz_id`),
  ADD KEY `fk_answers_question` (`question_id`),
  ADD KEY `fk_answers_choice` (`selected_choice_id`);

--
-- Indexes for table `student_avatar`
--
ALTER TABLE `student_avatar`
  ADD PRIMARY KEY (`selection_id`),
  ADD UNIQUE KEY `uq_student_item` (`student_id`,`item_id`),
  ADD KEY `fk_student_avatar_item` (`item_id`);

--
-- Indexes for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD PRIMARY KEY (`student_badge_id`),
  ADD UNIQUE KEY `uq_student_badge` (`student_id`,`badge_id`),
  ADD KEY `fk_student_badges_badge` (`badge_id`);

--
-- Indexes for table `student_quizzes`
--
ALTER TABLE `student_quizzes`
  ADD PRIMARY KEY (`student_quiz_id`),
  ADD UNIQUE KEY `uq_student_quiz` (`student_id`,`quiz_id`),
  ADD KEY `fk_student_quizzes_quiz` (`quiz_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD KEY `fk_teachers_user` (`user_id`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`topic_id`),
  ADD KEY `fk_topics_lesson` (`lesson_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `badge_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `chapter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_students`
--
ALTER TABLE `class_students`
  MODIFY `class_stud_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customization_items`
--
ALTER TABLE `customization_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `game_access_codes`
--
ALTER TABLE `game_access_codes`
  MODIFY `code_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_progress`
--
ALTER TABLE `game_progress`
  MODIFY `progress_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_questions`
--
ALTER TABLE `game_questions`
  MODIFY `game_question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `lesson_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `questions_master`
--
ALTER TABLE `questions_master`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  MODIFY `choice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `quiz_question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_year`
--
ALTER TABLE `school_year`
  MODIFY `sy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `stage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_avatar`
--
ALTER TABLE `student_avatar`
  MODIFY `selection_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_badges`
--
ALTER TABLE `student_badges`
  MODIFY `student_badge_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_quizzes`
--
ALTER TABLE `student_quizzes`
  MODIFY `student_quiz_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `topic_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_sy` FOREIGN KEY (`sy_id`) REFERENCES `school_year` (`sy_id`),
  ADD CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `class_students`
--
ALTER TABLE `class_students`
  ADD CONSTRAINT `fk_class_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_students_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customization_items`
--
ALTER TABLE `customization_items`
  ADD CONSTRAINT `fk_items_stage` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`stage_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `game_access_codes`
--
ALTER TABLE `game_access_codes`
  ADD CONSTRAINT `fk_access_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_access_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `game_progress`
--
ALTER TABLE `game_progress`
  ADD CONSTRAINT `fk_progress_stage` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`stage_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `fk_lessons_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `questions_master`
--
ALTER TABLE `questions_master`
  ADD CONSTRAINT `fk_questions_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`lesson_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_questions_teacher` FOREIGN KEY (`created_by_teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quiz_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  ADD CONSTRAINT `fk_quizzes_stage` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`stage_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quizzes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  ADD CONSTRAINT `fk_choices_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `fk_quiz_questions_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stages`
--
ALTER TABLE `stages`
  ADD CONSTRAINT `fk_stages_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stages_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `fk_answers_choice` FOREIGN KEY (`selected_choice_id`) REFERENCES `quiz_choices` (`choice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions_master` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answers_student_quiz` FOREIGN KEY (`student_quiz_id`) REFERENCES `student_quizzes` (`student_quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_avatar`
--
ALTER TABLE `student_avatar`
  ADD CONSTRAINT `fk_student_avatar_item` FOREIGN KEY (`item_id`) REFERENCES `customization_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_avatar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD CONSTRAINT `fk_student_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`badge_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_badges_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_quizzes`
--
ALTER TABLE `student_quizzes`
  ADD CONSTRAINT `fk_student_quizzes_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_quizzes_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `fk_topics_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`lesson_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
