CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile (
  id TINYINT UNSIGNED PRIMARY KEY,
  full_name VARCHAR(190) NOT NULL DEFAULT '',
  target_role VARCHAR(190) NOT NULL DEFAULT 'WordPress Developer',
  locations VARCHAR(255) NOT NULL DEFAULT 'Hyderabad, India; Remote',
  skills TEXT NOT NULL,
  experience_summary TEXT NOT NULL,
  portfolio_url VARCHAR(500) NOT NULL DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO profile (id, full_name, target_role, locations, skills, experience_summary, portfolio_url)
VALUES (1, '', 'WordPress Developer', 'Hyderabad, India; Remote',
'WordPress, PHP, MySQL, HTML5, CSS3, JavaScript, WooCommerce, custom themes, plugin customization, SEO, performance optimization',
'WordPress/Web Developer with 3+ years of experience building and maintaining responsive websites. Customize this profile to match your actual experience.',
'');

CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(80) NOT NULL DEFAULT 'Manual',
  company VARCHAR(190) NOT NULL,
  title VARCHAR(255) NOT NULL,
  location VARCHAR(255) NOT NULL DEFAULT '',
  job_url VARCHAR(1000) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  posted_at VARCHAR(80) NOT NULL DEFAULT '',
  match_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('Saved','To review','Draft ready','Applied','Interview','Rejected','Offer','Not a fit') NOT NULL DEFAULT 'Saved',
  notes TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_url (job_url(500)),
  INDEX idx_status (status),
  INDEX idx_score (match_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drafts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  cover_letter MEDIUMTEXT NOT NULL,
  cv_bullets MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_drafts_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
