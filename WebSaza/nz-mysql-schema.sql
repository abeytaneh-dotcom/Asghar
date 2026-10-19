CREATE TABLE IF NOT EXISTS nz_users (
  id VARCHAR(40) PRIMARY KEY,
  phone VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(24) NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL,
  last_login DATETIME NULL,
  INDEX idx_users_role (role)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_posts (
  id VARCHAR(40) PRIMARY KEY,
  user_id VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(120) NOT NULL,
  subcategory VARCHAR(120) NOT NULL,
  province VARCHAR(120) NULL,
  city VARCHAR(120) NOT NULL,
  district VARCHAR(160) NULL,
  street VARCHAR(240) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_mode ENUM('exact','approx') NOT NULL DEFAULT 'exact',
  price BIGINT UNSIGNED NOT NULL DEFAULT 0,
  chat_enabled TINYINT(1) NOT NULL DEFAULT 1,
  phone_visible TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('pending','published','rejected','deleted') NOT NULL DEFAULT 'pending',
  review_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  review_priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  moderation_json JSON NULL,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  reviewed_by VARCHAR(40) NULL,
  rejection_reason VARCHAR(800) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_posts_status_created (status, created_at),
  INDEX idx_posts_location (province, city, district),
  INDEX idx_posts_category (category, subcategory),
  INDEX idx_posts_user (user_id),
  INDEX idx_posts_price (price),
  FULLTEXT KEY ft_posts_text (title, description)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_post_attributes (
  post_id VARCHAR(40) NOT NULL,
  attr_key VARCHAR(100) NOT NULL,
  attr_value VARCHAR(500) NOT NULL,
  PRIMARY KEY (post_id, attr_key),
  INDEX idx_attr_key_value (attr_key, attr_value(120))
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id VARCHAR(40) NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds DECIMAL(8,2) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_media_post (post_id, sort_order)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_favorites (
  user_id VARCHAR(40) NOT NULL,
  post_id VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, post_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_conversations (
  id VARCHAR(40) PRIMARY KEY,
  type VARCHAR(24) NOT NULL DEFAULT 'ad',
  post_id VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_conv_post (post_id),
  INDEX idx_conv_updated (updated_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_conversation_members (
  conversation_id VARCHAR(40) NOT NULL,
  user_id VARCHAR(40) NOT NULL,
  PRIMARY KEY (conversation_id, user_id),
  INDEX idx_conv_member_user (user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_messages (
  id VARCHAR(40) PRIMARY KEY,
  conversation_id VARCHAR(40) NOT NULL,
  sender_id VARCHAR(40) NOT NULL,
  sender_name VARCHAR(120) NULL,
  text VARCHAR(2000) NOT NULL,
  moderation_json JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_msg_conv_created (conversation_id, created_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_message_reads (
  message_id VARCHAR(40) NOT NULL,
  user_id VARCHAR(40) NOT NULL,
  read_at DATETIME NOT NULL,
  PRIMARY KEY (message_id, user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_reports (
  id VARCHAR(40) PRIMARY KEY,
  post_id VARCHAR(40) NULL,
  conversation_id VARCHAR(40) NULL,
  message_id VARCHAR(40) NULL,
  reporter_id VARCHAR(40) NOT NULL,
  reason VARCHAR(200) NOT NULL,
  details TEXT NULL,
  priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  INDEX idx_reports_queue (status, priority, created_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_notifications (
  id VARCHAR(40) PRIMARY KEY,
  user_id VARCHAR(40) NOT NULL,
  text VARCHAR(1200) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_notifications_user (user_id, is_read, created_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_saved_searches (
  id VARCHAR(40) PRIMARY KEY,
  user_id VARCHAR(40) NOT NULL,
  label VARCHAR(120) NOT NULL,
  query_string VARCHAR(1600) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_saved_search_user (user_id, created_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nz_payments (
  id VARCHAR(60) PRIMARY KEY,
  user_id VARCHAR(40) NOT NULL,
  post_id VARCHAR(40) NULL,
  service_type VARCHAR(40) NOT NULL,
  amount BIGINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL,
  gateway_ref VARCHAR(160) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_payment_user (user_id, created_at),
  INDEX idx_payment_post (post_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
