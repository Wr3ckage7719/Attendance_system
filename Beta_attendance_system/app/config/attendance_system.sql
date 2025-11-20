-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2025 at 06:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `beta_attendance_system`
--
CREATE DATABASE IF NOT EXISTS `beta_attendance_system`;
USE `beta_attendance_system`;
-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `user_id`, `first_name`, `last_name`) VALUES
(1, 1, 'System', 'Administrator');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `class_id`, `student_id`, `date`, `status`) VALUES
(21, 9, '12542532145213', '2025-04-10', 'absent'),
(22, 9, '23642362346', '2025-04-10', 'present'),
(23, 9, '2364352352345', '2025-04-10', 'absent'),
(24, 9, '1123160070035', '2025-04-10', 'present'),
(25, 9, '12542532145213', '2025-04-11', 'absent'),
(26, 9, '23642362346', '2025-04-11', 'present'),
(27, 9, '2364352352345', '2025-04-11', 'absent'),
(28, 9, '1123160070035', '2025-04-11', 'present'),
(33, 7, '12542532145213', '2025-04-11', 'present'),
(34, 7, '23642362346', '2025-04-11', 'present'),
(35, 7, '2364352352345', '2025-04-11', 'absent'),
(36, 7, '1123160070035', '2025-04-11', 'present'),
(37, 7, '12542532145213', '2025-04-12', 'absent'),
(38, 7, '23642362346', '2025-04-12', 'present'),
(39, 7, '2364352352345', '2025-04-12', 'absent'),
(40, 7, '1123160070035', '2025-04-12', 'present'),
(41, 7, '12542532145213', '2025-04-13', 'present'),
(42, 7, '23642362346', '2025-04-13', 'absent'),
(43, 7, '2364352352345', '2025-04-13', 'absent'),
(44, 7, '1123160070035', '2025-04-13', 'present'),
(45, 7, '12542532145213', '2025-04-14', 'absent'),
(46, 7, '23642362346', '2025-04-14', 'present'),
(47, 7, '2364352352345', '2025-04-14', 'absent'),
(48, 7, '1123160070035', '2025-04-14', 'present'),
(49, 7, '12542532145213', '2025-04-10', 'late'),
(50, 7, '23642362346', '2025-04-10', 'present'),
(51, 7, '2364352352345', '2025-04-10', 'absent'),
(52, 7, '1123160070035', '2025-04-10', 'present');

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `block_id` int(11) NOT NULL,
  `block_name` varchar(1) NOT NULL CHECK (`block_name` in ('A','B','C','D','E')),
  `course_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `year_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blocks`
--

INSERT INTO `blocks` (`block_id`, `block_name`, `course_id`, `created_at`, `year_level`) VALUES
(1, 'A', 5, '2025-04-09 07:57:22', 3),
(2, 'A', 2, '2025-04-09 08:22:24', 2),
(5, 'A', 8, '2025-04-09 08:22:24', 3),
(9, 'A', 8, '2025-04-10 14:12:02', 1),
(10, 'B', 8, '2025-04-10 14:12:02', 1),
(11, 'C', 8, '2025-04-10 14:12:02', 1),
(12, 'D', 8, '2025-04-10 14:12:02', 1),
(13, 'E', 8, '2025-04-10 14:12:02', 1),
(14, 'A', 5, '2025-04-10 14:12:09', 1),
(15, 'B', 5, '2025-04-10 14:12:09', 1),
(16, 'C', 5, '2025-04-10 14:12:09', 1),
(17, 'D', 5, '2025-04-10 14:12:09', 1),
(18, 'E', 5, '2025-04-10 14:12:09', 1),
(19, 'A', 2, '2025-04-10 14:12:14', 1),
(20, 'B', 2, '2025-04-10 14:12:14', 1),
(21, 'C', 2, '2025-04-10 14:12:14', 1),
(22, 'D', 2, '2025-04-10 14:12:14', 1),
(23, 'E', 2, '2025-04-10 14:12:14', 1),
(24, 'A', 5, '2025-04-10 16:09:20', 2),
(25, 'B', 5, '2025-04-10 16:09:20', 2),
(26, 'C', 5, '2025-04-10 16:09:20', 2),
(27, 'D', 5, '2025-04-10 16:09:20', 2),
(28, 'E', 5, '2025-04-10 16:09:20', 2),
(29, 'A', 11, '2025-04-11 12:10:06', 1),
(30, 'B', 11, '2025-04-11 12:10:06', 1),
(31, 'C', 11, '2025-04-11 12:10:06', 1),
(32, 'D', 11, '2025-04-11 12:10:06', 1),
(33, 'E', 11, '2025-04-11 12:10:06', 1),
(34, 'A', 11, '2025-04-11 12:10:57', 2),
(35, 'B', 11, '2025-04-11 12:10:58', 2),
(36, 'C', 11, '2025-04-11 12:10:58', 2),
(37, 'D', 11, '2025-04-11 12:10:58', 2),
(38, 'E', 11, '2025-04-11 12:10:58', 2),
(39, 'A', 8, '2025-04-11 14:09:21', 2),
(40, 'B', 8, '2025-04-11 14:09:22', 2),
(41, 'C', 8, '2025-04-11 14:09:22', 2),
(42, 'D', 8, '2025-04-11 14:09:22', 2),
(43, 'E', 8, '2025-04-11 14:09:22', 2),
(44, 'A', 2, '2025-04-11 14:11:11', 3),
(45, 'B', 2, '2025-04-11 14:11:11', 3),
(46, 'C', 2, '2025-04-11 14:11:11', 3),
(47, 'D', 2, '2025-04-11 14:11:11', 3),
(48, 'E', 2, '2025-04-11 14:11:11', 3),
(49, 'A', 10, '2025-04-11 14:13:03', 2),
(50, 'B', 10, '2025-04-11 14:13:03', 2),
(51, 'C', 10, '2025-04-11 14:13:03', 2),
(52, 'D', 10, '2025-04-11 14:13:03', 2),
(53, 'E', 10, '2025-04-11 14:13:03', 2),
(54, 'B', 2, '2025-04-11 14:47:19', 2);

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `Class_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `Teacher_id` int(11) NOT NULL,
  `Subject_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `block_id` int(11) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL CHECK (`year_level` between 1 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`Class_id`, `course_id`, `Teacher_id`, `Subject_id`, `day_of_week`, `start_time`, `end_time`, `created_at`, `block_id`, `year_level`) VALUES
(7, 2, 0, 1, 'Monday', '08:30:00', '09:30:00', '2025-04-09 10:47:29', 2, 2),
(8, 2, 0, 2, 'Monday', '10:30:00', '12:00:00', '2025-04-09 11:15:56', 2, 2),
(9, 2, 0, 3, 'Monday', '16:00:00', '17:30:00', '2025-04-09 14:18:50', 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `class_days`
--

CREATE TABLE `class_days` (
  `class_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_days`
--

INSERT INTO `class_days` (`class_id`, `day_of_week`) VALUES
(7, 'Monday'),
(7, 'Wednesday'),
(7, 'Saturday'),
(8, 'Tuesday'),
(8, 'Thursday'),
(9, 'Monday'),
(9, 'Wednesday'),
(9, 'Friday');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_name`, `course_code`, `description`, `created_at`) VALUES
(2, 'Bachelor of Science in Computer Science', 'BSCS', '', '2025-04-08 17:01:36'),
(5, 'Bachelor of Secondary Education', 'BSED', '', '2025-04-08 17:09:15'),
(6, 'BS in Accountancy', 'BSA', '', '2025-04-09 06:37:37'),
(7, 'BS in Business Administration', 'BSBA', '', '2025-04-09 06:38:04'),
(8, 'Bachelor of Science in Criminology', 'BSCrim', '', '2025-04-09 06:38:34'),
(9, 'Bachelor of Science in Entrepreneurship', 'BSEntrep', '', '2025-04-09 06:39:04'),
(10, 'Bachelor of Science in Nursing', 'BSN', '', '2025-04-09 06:39:40'),
(11, 'Bachelor of Arts in Communication', 'BAComm', '', '2025-04-09 06:40:05');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `Student_Id` varchar(255) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `Last_name` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `block_id` int(11) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL CHECK (`year_level` between 1 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`Student_Id`, `first_name`, `Last_name`, `Email`, `phone_number`, `block_id`, `year_level`) VALUES
('1123160070035', 'Niccolo', 'Perez', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('1231423523', 'RARA', 'VAVA', 'TERWE@gmail.com', '09128313124', 2, 2),
('123562154215235', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('12421424215', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('12442351424`', 'ETET', 'BEBE', 'YEYEYE@gmail.com', '0912423123123', 2, 2),
('1252356621621626', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('1253215125215215', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('1253215126216216', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('12542532145213', 'Jack', 'Colen', 'YAYA@gmail.xcom', '0912341424', 2, 2),
('1255122342152', 'TATATATAT', 'HAHAHAHAH', 'AHAHAHAHAH@gmail.com', '0912521421125', 24, 2),
('126216216', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('1262162162`16216`', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('126216`263126216', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('126236126216', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('126236216216216216', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('1634326236326', 'YAYAYAYAY', 'JEJEJEJEJ', 'HAHAHAH@gmail.com', '09251345235126', 2, 2),
('214234234234', 'ewrgewgv', 'ewrgwregwreg', 'gewrgwergwreg@gmail.com', '0921542155234', 2, 2),
('2152153215125', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('2152512354215', 'asdbasbsdb', 'asbasbasbasdb', 'qwhgwgwgw@gmail.com', '273646237235', 2, 2),
('21525125', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 54, 2),
('21532153215215215', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('216215234524214', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('21621612621532`1', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('234513554234', 'GUstavo', 'Fring', 'AVAECASC@gmasd.acoa', '091232135213', 2, 2),
('2356263216216326', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('236216216216216', '216321632162163', '2632162162163', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('23642362346', 'AGAGAGA', 'HAHAHAH', '555@GMALSAC.COCM', '09124124125123', 2, 2),
('2364352352345', 'Nic', 'Per', 'bal@gmail.com', '09480267856867', 2, 2),
('266363426', 'badfbba', 'basbasdbasb', 'asbasdbas@gmail.com', 'asbasbasdb', 2, 2),
('2rqer12rf213', '2f21f23f', 'wfqwefqwef', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('412425125216', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('471347237237', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('51252521521', '21521521521', '5215215125215', 'balonniccolo@gmail.com', '09480205567', 19, 1),
('523523523523', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 19, 1),
('6T1521523', '12341251253', '215215125', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('ASDFASDF', 'ASFASDFASDF', 'ASDFASDFASDF', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('ASDFASDFASDF', 'ASDFASDFASDF', 'ASDFASDFASDF', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('CXZXzXCzx', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('fqwefqwefqwf', 'qwefqwfqwf', 'wefqwefqwef', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('QWERQWERQWERQWERQWER', 'QWERQWERQWER', 'QWERQWERQWERQWER', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('QWERQWERQWRQWERQ', 'EWRQWERQWERQWER', 'QWERQWERQWERQWRQWER', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('XCBXCVB', 'VCBXCVBXCVB', 'XCVBXCVBXCVB', 'balonniccolo@gmail.com', '09480205567', 2, 2),
('`23213`144214', 'Niccolo', 'Perez Balon', 'balonniccolo@gmail.com', '09480205567', 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `subject`
--

CREATE TABLE `subject` (
  `Subject_id` int(11) NOT NULL,
  `Subject_Name` varchar(255) NOT NULL,
  `Subject_code` varchar(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `year_level` int(11) DEFAULT NULL CHECK (`year_level` between 1 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject`
--

INSERT INTO `subject` (`Subject_id`, `Subject_Name`, `Subject_code`, `course_id`, `created_at`, `year_level`) VALUES
(1, 'Information Management (DBMS)', 'CC105', 2, '2025-04-08 17:08:00', 2),
(2, 'Algorithms and Complexity in JAVA', 'AL101', 2, '2025-04-08 17:08:09', 2),
(3, 'Calculus', 'GEC102', 2, '2025-04-08 17:08:44', 2),
(4, 'College Algebra ', 'GEC105', 11, '2025-04-09 10:21:03', 1),
(5, 'Graphics and Visual Computing 1 (Geometric Modelling)', 'CS Elective', 2, '2025-04-09 17:08:09', 2),
(6, 'Gender and Society', 'GEC 11', 2, '2025-04-09 17:08:34', 2),
(7, 'Life and Works of Rizal', 'GEC 9', 2, '2025-04-09 17:09:00', 2),
(8, 'Application Software 3: Sound Design using Digital Audio Workstation', 'AS 103', 2, '2025-04-09 17:09:52', 2),
(9, 'Data Structures and Algorithms in JAVA', 'CC 104', 2, '2025-04-09 17:10:16', 2),
(10, 'Discrete Structures 2', 'DS 102', 2, '2025-04-09 17:10:30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `Teacher_id` int(11) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`Teacher_id`, `first_name`, `last_name`, `user_id`, `email`, `created_at`) VALUES
(0, 'Niccolo', 'Perez', 2, 'balonniccolo@gmail.com', '2025-04-08 15:30:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('teacher','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin@admin.com', '$2y$10$lLlmlwOw0iDR3FakRlXvi.7kfzd2BEB0MgVApHdpmECJaIgqisyZK', 'admin', '2025-04-08 15:06:45'),
(2, 'balonniccolo@gmail.com', '$2y$10$8TLIyISdp6FmVvoBD2uZiu/nObFszDUfCF4JZJ8ryTK7uwIHshIS6', 'teacher', '2025-04-08 15:30:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_attendance` (`class_id`,`student_id`,`date`),
  ADD KEY `attendance_ibfk_1` (`student_id`);

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`block_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`Class_id`),
  ADD UNIQUE KEY `unique_schedule` (`Teacher_id`,`day_of_week`,`start_time`),
  ADD KEY `block_id` (`block_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `Subject_id` (`Subject_id`);

--
-- Indexes for table `class_days`
--
ALTER TABLE `class_days`
  ADD PRIMARY KEY (`class_id`,`day_of_week`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`Student_Id`),
  ADD KEY `block_id` (`block_id`);

--
-- Indexes for table `subject`
--
ALTER TABLE `subject`
  ADD PRIMARY KEY (`Subject_id`),
  ADD UNIQUE KEY `Subject_code` (`Subject_code`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`Teacher_id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=686;

--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `Class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `subject`
--
ALTER TABLE `subject`
  MODIFY `Subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`Student_Id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`Class_id`) ON DELETE CASCADE;

--
-- Constraints for table `blocks`
--
ALTER TABLE `blocks`
  ADD CONSTRAINT `blocks_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_4` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`block_id`),
  ADD CONSTRAINT `classes_ibfk_5` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `classes_ibfk_6` FOREIGN KEY (`Teacher_id`) REFERENCES `teachers` (`Teacher_id`),
  ADD CONSTRAINT `classes_ibfk_7` FOREIGN KEY (`Subject_id`) REFERENCES `subject` (`Subject_id`);

--
-- Constraints for table `class_days`
--
ALTER TABLE `class_days`
  ADD CONSTRAINT `class_days_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`Class_id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
