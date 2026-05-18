-- ============================================================
-- Free Subdomain Platform — MySQL schema (optional)
--
-- You only need to run this file if you set
--     'db_driver' => 'mysql'
-- in api/config.php. By default the platform uses SQLite, which
-- is created automatically on first request — no SQL required.
--
-- Apply this file from cPanel -> phpMyAdmin -> Import, or from
-- a shell with:   mysql -u <user> -p <db> < install.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    phone           VARCHAR(20)  NOT NULL UNIQUE,
    email           VARCHAR(255) NULL,
    name            VARCHAR(255) NULL,
    is_admin        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otps (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    phone           VARCHAR(20) NOT NULL,
    code            VARCHAR(8)  NOT NULL,
    expires_at      DATETIME    NULL,
    used            TINYINT(1)  NOT NULL DEFAULT 0,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_phone (phone)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS institutions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    brand           VARCHAR(32)  NOT NULL,
    slug            VARCHAR(64)  NOT NULL,
    name_bn         VARCHAR(255) NULL,
    name_en         VARCHAR(255) NOT NULL,
    category        VARCHAR(64)  NOT NULL,
    division        VARCHAR(64)  NULL,
    district        VARCHAR(64)  NULL,
    upazila         VARCHAR(64)  NULL,
    address         TEXT         NULL,
    eiin            VARCHAR(32)  NULL,
    contact_name    VARCHAR(255) NULL,
    contact_phone   VARCHAR(20)  NULL,
    contact_email   VARCHAR(255) NULL,
    website         VARCHAR(255) NULL,
    logo_url        VARCHAR(512) NULL,
    banner_url      VARCHAR(512) NULL,
    about_bn        TEXT         NULL,
    about_en        TEXT         NULL,
    status          VARCHAR(16)  NOT NULL DEFAULT 'pending',
    review_notes    TEXT         NULL,
    owner_user_id   INT          NULL,
    cf_record_id    VARCHAR(64)  NULL,
    dns_status      VARCHAR(16)  NULL,
    dns_message     TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at     DATETIME     NULL,
    UNIQUE KEY uq_brand_slug (brand, slug),
    INDEX idx_inst_status (status),
    INDEX idx_inst_brand (brand),
    INDEX idx_inst_owner (owner_user_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reserved_slugs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(64)  NOT NULL UNIQUE,
    reason          VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS claim_documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    institution_id  INT          NOT NULL,
    doc_type        VARCHAR(64)  NOT NULL,
    filename        VARCHAR(255) NOT NULL,
    stored_path     VARCHAR(512) NOT NULL,
    content_type    VARCHAR(128) NOT NULL,
    uploaded_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_docs_inst (institution_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notices (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    institution_id  INT          NOT NULL,
    title           VARCHAR(255) NOT NULL,
    body            TEXT         NOT NULL,
    pinned          TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notices_inst (institution_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id   INT          NULL,
    institution_id  INT          NULL,
    action          VARCHAR(64)  NOT NULL,
    detail          TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- The application will also auto-seed:
--   - the admin user (api/config.php -> admin_phone)
--   - the default reserved slugs (www, api, admin, …)
--   - 8 verified demo institutions + 4 seeded placeholders
-- on first request, so you do NOT need to insert seed data here.
-- ------------------------------------------------------------
