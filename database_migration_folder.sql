ALTER TABLE `folders`
  ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  ADD COLUMN `deleted_at` datetime DEFAULT NULL,
  ADD KEY `idx_folders_user_deleted` (`user_id`, `deleted_at`);