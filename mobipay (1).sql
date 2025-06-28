-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2025 at 03:02 PM
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
-- Database: `mobipay`
--

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `AgentId` int(11) NOT NULL,
  `AgentName` varchar(100) NOT NULL,
  `Location` varchar(100) NOT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`AgentId`, `AgentName`, `Location`, `Phone`, `Status`) VALUES
(1, 'Thabo Mokoena', 'Maseru', '62010001', 'Active'),
(2, 'Naledi Mokoena', 'Hlotse', '62010002', 'Active'),
(3, 'Tsepo Moeketsi', 'Teyateyaneng', '62010003', 'Inactive'),
(4, 'Refiloe Khabo', 'Mafeteng', '62010004', 'Active'),
(5, 'Palesa Nthunya', 'Mohale\'s Hoek', '62010005', 'Active'),
(6, 'Katleho Ramatla', 'Qacha\'s Nek', '62010006', 'Active'),
(7, 'Mpho Ralethe', 'Leribe', '62010007', 'Active'),
(8, 'Khumo Ramohapi', 'Butha-Buthe', '62010008', 'Inactive'),
(9, 'Lebohang Phoka', 'Quthing', '62010009', 'Inactive'),
(10, 'Teboho Mofokeng', 'Mokhotlong', '62010010', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `type` varchar(255) NOT NULL,
  `description` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction`
--

INSERT INTO `transaction` (`transaction_id`, `user_id`, `amount`, `date`, `type`, `description`) VALUES
(1, 2, 500.00, '2025-04-08', 'topup', 'nwenwe'),
(2, 2, 20.00, '2025-04-22', 'topup', 'nwenwe'),
(3, 2, 500.00, '2025-04-22', 'topup', 'nwenwe'),
(4, 5, 500.00, '2025-04-22', 'topup', 'nwenwe'),
(5, 6, 500.00, '2025-04-22', 'topup', 'nwenwe'),
(6, 6, 25.00, '2025-04-22', 'send', 'hello'),
(7, 5, 25.00, '2025-04-22', 'received', 'hello'),
(8, 6, 5.00, '2025-04-22', 'cashout', 'Cash out to Lebohang Phoka - haha'),
(9, 5, 30.00, '2025-04-22', 'send', 'yello'),
(10, 6, 30.00, '2025-04-22', 'received', 'yello'),
(11, 5, 100.00, '2025-04-22', 'send', 'hello'),
(12, 6, 100.00, '2025-04-22', 'received', 'hello'),
(13, 5, 70.00, '2025-04-22', 'send', 'biscuit'),
(14, 1, 70.00, '2025-04-22', 'received', 'biscuit'),
(15, 5, 100.00, '2025-04-22', 'cashout', 'Cash out to Naledi Mokoena - haha'),
(16, 5, 30.00, '2025-04-23', 'cashout', 'Cash out to Naledi Mokoena - haha'),
(17, 5, 50.00, '2025-04-22', 'send', 'hello'),
(18, 6, 50.00, '2025-04-22', 'received', 'hello'),
(19, 5, 50.00, '2025-04-22', 'send', 'hello'),
(20, 6, 50.00, '2025-04-22', 'received', 'hello'),
(21, 5, 50.00, '2025-04-22', 'cashout', 'Cash out to Mpho Ralethe'),
(22, 5, 100.00, '2025-04-22', 'topup', 'nwenwe'),
(23, 5, 10.00, '2025-04-25', 'send', 'biscuit'),
(24, 6, 10.00, '2025-04-25', 'received', 'biscuit'),
(25, 18, 100.00, '2025-04-23', 'send', 'here'),
(26, 5, 100.00, '2025-04-23', 'received', 'here'),
(27, 18, 500.00, '2025-04-25', 'topup', 'nwenwe'),
(28, 18, 250.00, '2025-04-23', 'send', 'here'),
(29, 6, 250.00, '2025-04-23', 'received', 'here'),
(30, 5, 10.00, '2025-04-30', 'cashout', 'Cash out to Khumo Ramohapi - cashout');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone_number`, `password_hash`, `pin_hash`, `role`, `status`) VALUES
(5, 'Rethabiole', 'c@gmail.com', '101', '$2y$10$ZFobPQ3c4042u7OHiSd1UO/JiNRcRfmhdfBKlb/7itxjOcYmHhKNW', '$2y$10$cokWOoouUauGPVqAwXwTtuXn.LF6cj5VWXYUnKnGLH.20jpDA2RaC', 'user', 'Active'),
(6, 'Boitumelo', 'boity@gmail.com', '58988', '$2y$10$nzXuZzbOKqLl1S8gv3MByObSiaWIgFx0eHjUzmLToAQGtjB4p8DWa', '$2y$10$/Z4D3AP/w.4U8ubHUkV67u7Z6QHGCExYHbYSzx/pHb2Cab5cqP692', 'user', 'Active'),
(8, 'Rethabile Matela', 'r.matela@gmail.com', '63192634', '$2y$10$7sEKSV4/FVrR7XU7MoDViuo2bwHjgQ/YZwD8CuoLUPgMpFxMcMtbq', '$2y$10$Wev56ec.Q5qS.79IDMiYzOyLML8ojhikS0EdKPiCGH4qPRvnqYHVq', 'admin', 'Active'),
(9, 'Boitumelo Lekau', 'boitumelo@gmail.com', '58398399', '$2y$10$6TiyoJsPhHuQ4bRNXi3/5.Ow3ji.P/gI7aqJb55WZwnz93wMpqPIC', '$2y$10$vlIivVIp7a.e/jUxW/Pl0.uXvX9WHLIg.rmhIg.rcbYADiva85hLi', 'admin', 'Active'),
(10, 'Tsita Makhele', 'tsitasmakhele@gmail.com', '58328405', '$2y$10$1wUFPNNGWz29hbYmEugdjOaUi9WjM6Url3GiqKJuJUS9uPSYx/j12', '$2y$10$d6BAF/sdK9HSrcnAqNm6X.Oi9ASJ1hitl9iJJS1Oh5Ii.giJ3u0IK', 'admin', 'Active'),
(11, 'Khotsofalang', 'Tlhoni@gmail.com', '53687999', '$2y$10$bp7JGEjzmMa1xqq5dFDYMOc3Tkkr4zqgHs6ivH79hmXzRSRNPezEa', '$2y$10$mQw.IBix.qJlx6LYyCkX9.sB/Xj/FPg02Twi3sNyvVh9Ikpzbgh2G', 'admin', 'Inactive'),
(14, 'Retha', 'unc@gmail.com', '1010', '$2y$10$AKOfwfBxn0jZlDpu5mATmOShKbStZV2/alm7cINj/rrg6WVb7Xd6y', '$2y$10$g5lCROMysBLwEXLV3Qxp8OZl40uAFFXPRsgwgBF2c2FJRLkSSlezq', 'user', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `user_agent_bridge`
--

CREATE TABLE `user_agent_bridge` (
  `user_id` int(11) NOT NULL,
  `AgentId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_balance` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`wallet_id`, `user_id`, `current_balance`) VALUES
(1, 5, 225.00),
(2, 6, 350.00),
(4, 8, 0.00),
(5, 9, 0.00),
(6, 10, 0.00),
(7, 11, 0.00),
(10, 14, 100.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`AgentId`);

--
-- Indexes for table `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`transaction_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

--
-- Indexes for table `user_agent_bridge`
--
ALTER TABLE `user_agent_bridge`
  ADD PRIMARY KEY (`user_id`,`AgentId`),
  ADD KEY `AgentId` (`AgentId`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`wallet_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `AgentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_agent_bridge`
--
ALTER TABLE `user_agent_bridge`
  ADD CONSTRAINT `user_agent_bridge_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_agent_bridge_ibfk_2` FOREIGN KEY (`AgentId`) REFERENCES `agents` (`AgentId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
