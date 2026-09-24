-- WebChat schema for cPanel phpMyAdmin
-- 1) In cPanel: create an empty MySQL database + user, grant ALL
-- 2) In phpMyAdmin: select THAT database (left sidebar)
-- 3) Import this file
-- Default admin after import: admin / admin123
-- Then create includes/config.local.php from config.local.example.php
-- WebConnect Database Schema
-- Web-Based Chat, Voice & Video Communication System
-- Charset: utf8mb4 / utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- --------------------------------------------------------
-- Administrators (separate auth from users)
-- --------------------------------------------------------
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL DEFAULT '',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`),
  UNIQUE KEY `uk_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) NULL DEFAULT NULL,
  `status_message` VARCHAR(255) NOT NULL DEFAULT '',
  `presence` ENUM('online','away','offline','busy') NOT NULL DEFAULT 'offline',
  `last_seen_at` DATETIME NULL DEFAULT NULL,
  `last_activity_at` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_presence` (`presence`),
  KEY `idx_users_last_activity` (`last_activity_at`),
  KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Friend requests
-- --------------------------------------------------------
CREATE TABLE `friend_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_friend_req_pair` (`sender_id`, `receiver_id`),
  KEY `idx_friend_req_receiver_status` (`receiver_id`, `status`),
  KEY `idx_friend_req_sender_status` (`sender_id`, `status`),
  CONSTRAINT `fk_friend_req_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friend_req_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Friendships (canonical: user_low_id < user_high_id)
-- --------------------------------------------------------
CREATE TABLE `friendships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_low_id` INT UNSIGNED NOT NULL,
  `user_high_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_friendship_pair` (`user_low_id`, `user_high_id`),
  KEY `idx_friendship_high` (`user_high_id`),
  CONSTRAINT `fk_friendship_low` FOREIGN KEY (`user_low_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friendship_high` FOREIGN KEY (`user_high_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- User blocks
-- --------------------------------------------------------
CREATE TABLE `user_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_id` INT UNSIGNED NOT NULL,
  `blocked_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_block_pair` (`blocker_id`, `blocked_id`),
  KEY `idx_block_blocked` (`blocked_id`),
  CONSTRAINT `fk_block_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Private messages
-- --------------------------------------------------------
CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `message_type` ENUM('text','image','file','voice','call') NOT NULL DEFAULT 'text',
  `body` TEXT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `mime_type` VARCHAR(100) NULL DEFAULT NULL,
  `file_size` INT UNSIGNED NULL DEFAULT NULL,
  `voice_duration` DECIMAL(8,2) NULL DEFAULT NULL,
  `reply_to_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `delivery_status` ENUM('sent','delivered','read') NOT NULL DEFAULT 'sent',
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_sender` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_receiver` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` DATETIME NULL DEFAULT NULL,
  `read_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_msg_conversation` (`sender_id`, `receiver_id`, `created_at`),
  KEY `idx_msg_receiver_status` (`receiver_id`, `delivery_status`, `is_deleted`),
  KEY `idx_msg_reply` (`reply_to_id`),
  KEY `idx_msg_created` (`created_at`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Message reactions
-- --------------------------------------------------------
CREATE TABLE `message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_msg_reaction` (`message_id`, `user_id`, `reaction`),
  KEY `idx_msg_reaction_user` (`user_id`),
  CONSTRAINT `fk_msg_reaction_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Message read receipts (extra detail; delivery_status also on messages)
-- --------------------------------------------------------
CREATE TABLE `message_read_receipts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('delivered','read') NOT NULL DEFAULT 'delivered',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_msg_receipt` (`message_id`, `user_id`),
  KEY `idx_msg_receipt_user` (`user_id`),
  CONSTRAINT `fk_msg_receipt_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receipt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Typing indicators (ephemeral; cleaned by expiry)
-- --------------------------------------------------------
CREATE TABLE `typing_indicators` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `conversation_type` ENUM('private','group') NOT NULL DEFAULT 'private',
  `target_id` INT UNSIGNED NOT NULL COMMENT 'other user id or group id',
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_typing` (`user_id`, `conversation_type`, `target_id`),
  KEY `idx_typing_target` (`conversation_type`, `target_id`, `expires_at`),
  CONSTRAINT `fk_typing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Group chats
-- --------------------------------------------------------
CREATE TABLE `group_chats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `avatar` VARCHAR(255) NULL DEFAULT NULL,
  `owner_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_owner` (`owner_id`),
  CONSTRAINT `fk_group_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_member` (`group_id`, `user_id`),
  KEY `idx_group_member_user` (`user_id`),
  CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `message_type` ENUM('text','image','file','voice') NOT NULL DEFAULT 'text',
  `body` TEXT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `mime_type` VARCHAR(100) NULL DEFAULT NULL,
  `file_size` INT UNSIGNED NULL DEFAULT NULL,
  `voice_duration` DECIMAL(8,2) NULL DEFAULT NULL,
  `reply_to_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gmsg_group_created` (`group_id`, `created_at`),
  KEY `idx_gmsg_sender` (`sender_id`),
  KEY `idx_gmsg_reply` (`reply_to_id`),
  CONSTRAINT `fk_gmsg_group` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `group_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gmsg_reaction` (`message_id`, `user_id`, `reaction`),
  KEY `idx_gmsg_reaction_user` (`user_id`),
  CONSTRAINT `fk_gmsg_reaction_msg` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_message_reads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gmsg_read` (`message_id`, `user_id`),
  KEY `idx_gmsg_read_user` (`user_id`),
  CONSTRAINT `fk_gmsg_read_msg` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Voice / Video call sessions & signalling
-- --------------------------------------------------------
CREATE TABLE `voice_call_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `caller_id` INT UNSIGNED NOT NULL,
  `callee_id` INT UNSIGNED NOT NULL,
  `call_type` ENUM('voice','video') NOT NULL DEFAULT 'voice',
  `status` ENUM('ringing','accepted','rejected','ended','missed','cancelled') NOT NULL DEFAULT 'ringing',
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `answered_at` DATETIME NULL DEFAULT NULL,
  `ended_at` DATETIME NULL DEFAULT NULL,
  `end_reason` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_call_callee_status` (`callee_id`, `status`),
  KEY `idx_call_caller_status` (`caller_id`, `status`),
  KEY `idx_call_started` (`started_at`),
  CONSTRAINT `fk_call_caller` FOREIGN KEY (`caller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_callee` FOREIGN KEY (`callee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `voice_call_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `signal_type` ENUM('offer','answer','ice','hangup') NOT NULL,
  `payload` MEDIUMTEXT NOT NULL,
  `is_consumed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_signal_call_consumed` (`call_id`, `is_consumed`, `id`),
  KEY `idx_signal_sender` (`sender_id`),
  CONSTRAINT `fk_signal_call` FOREIGN KEY (`call_id`) REFERENCES `voice_call_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signal_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Notifications
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `body` VARCHAR(500) NOT NULL DEFAULT '',
  `link` VARCHAR(255) NULL DEFAULT NULL,
  `related_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Forums
-- --------------------------------------------------------
CREATE TABLE `forums` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `slug` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forum_slug` (`slug`),
  KEY `idx_forum_sort` (`sort_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `forum_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `forum_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_forum` (`forum_id`, `is_hidden`, `created_at`),
  KEY `idx_post_user` (`user_id`),
  CONSTRAINT `fk_post_forum` FOREIGN KEY (`forum_id`) REFERENCES `forums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_post` (`post_id`, `created_at`),
  KEY `idx_comment_user` (`user_id`),
  CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_likes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_post_like` (`post_id`, `user_id`),
  KEY `idx_like_user` (`user_id`),
  CONSTRAINT `fk_like_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_like_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_shares` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_share_post` (`post_id`),
  KEY `idx_share_user` (`user_id`),
  CONSTRAINT `fk_share_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_share_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Reports
-- --------------------------------------------------------
CREATE TABLE `reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT UNSIGNED NOT NULL,
  `target_type` ENUM('user','private_message','group_message','forum_post') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `status` ENUM('open','reviewed','resolved') NOT NULL DEFAULT 'open',
  `admin_note` VARCHAR(500) NULL DEFAULT NULL,
  `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_status` (`status`, `created_at`),
  KEY `idx_reports_reporter` (`reporter_id`),
  KEY `idx_reports_target` (`target_type`, `target_id`),
  CONSTRAINT `fk_report_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Rate limiting
-- --------------------------------------------------------
CREATE TABLE `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rate_key` VARCHAR(191) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rate_key` (`rate_key`),
  KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed: demo admin — username admin / password admin123
-- --------------------------------------------------------
INSERT INTO `admins` (`username`, `email`, `password_hash`, `display_name`, `must_change_password`, `is_active`)
VALUES (
  'admin',
  'admin@webconnect.local',
  '$2y$10$zE./5pEuI.ionFmEqF6YJ.N34ov0lWWma4pvYtTa8gcY6lYoGHVCS',
  'System Administrator',
  0,
  1
);

INSERT INTO `forums` (`name`, `description`, `slug`, `sort_order`, `is_active`) VALUES
('General Discussion', 'Talk about anything related to WebConnect.', 'general-discussion', 1, 1),
('Technology', 'Share tech tips, tools, and ideas.', 'technology', 2, 1),
('Student Discussion', 'A space for students to collaborate.', 'student-discussion', 3, 1),
('Announcements', 'Official platform announcements.', 'announcements', 4, 1);

SET FOREIGN_KEY_CHECKS = 1;
