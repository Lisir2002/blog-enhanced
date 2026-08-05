-- MySQL schema for Blog CMS

CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64)  NOT NULL UNIQUE,
    email           VARCHAR(191) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    display_name    VARCHAR(100),
    role            VARCHAR(32)  NOT NULL DEFAULT 'visitor',
    bio             TEXT,
    avatar          VARCHAR(500),
    url             VARCHAR(500),
    status          VARCHAR(32)  NOT NULL DEFAULT 'active',
    remember_token  VARCHAR(100),
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    INDEX idx_users_email (email),
    INDEX idx_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    slug        VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    parent_id   BIGINT UNSIGNED DEFAULT 0,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    INDEX idx_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    INDEX idx_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(200) NOT NULL UNIQUE,
    title           VARCHAR(255) NOT NULL,
    content_md      LONGTEXT,
    content_html    LONGTEXT,
    excerpt         TEXT,
    cover           VARCHAR(500),
    category_id    BIGINT UNSIGNED,
    author_id       BIGINT UNSIGNED,
    status          VARCHAR(32)  NOT NULL DEFAULT 'draft',
    comment_status  VARCHAR(32)  NOT NULL DEFAULT 'open',
    views           INT UNSIGNED NOT NULL DEFAULT 0,
    published_at    DATETIME,
    seo_title       VARCHAR(255),
    seo_description TEXT,
    deleted_at      DATETIME,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    INDEX idx_posts_slug        (slug),
    INDEX idx_posts_status      (status),
    INDEX idx_posts_author      (author_id),
    INDEX idx_posts_category    (category_id),
    INDEX idx_posts_published   (published_at),
    CONSTRAINT fk_posts_author   FOREIGN KEY (author_id)   REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_tag (
    post_id BIGINT UNSIGNED NOT NULL,
    tag_id  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_post_tag_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_tag_tag  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE,
    INDEX idx_post_tag_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id      BIGINT UNSIGNED NOT NULL,
    parent_id    BIGINT UNSIGNED DEFAULT 0,
    author_name  VARCHAR(100),
    author_email  VARCHAR(191),
    author_url    VARCHAR(500),
    author_ip     VARCHAR(45),
    author_ua     VARCHAR(500),
    content       TEXT NOT NULL,
    status        VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL,
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_comments_post    (post_id),
    INDEX idx_comments_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    filename     VARCHAR(255) NOT NULL,
    path         VARCHAR(500) NOT NULL,
    mime         VARCHAR(100),
    size         BIGINT UNSIGNED DEFAULT 0,
    width        INT,
    height       INT,
    alt          VARCHAR(255),
    uploaded_by  BIGINT UNSIGNED,
    created_at   DATETIME NOT NULL,
    CONSTRAINT fk_media_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_media_uploaded (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS options (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    key_name    VARCHAR(100) NOT NULL UNIQUE,
    value       LONGTEXT,
    autoload    TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    INDEX idx_options_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(200) NOT NULL UNIQUE,
    title           VARCHAR(255) NOT NULL,
    content_md     LONGTEXT,
    content_html   LONGTEXT,
    status          VARCHAR(32) NOT NULL DEFAULT 'published',
    author_id       BIGINT UNSIGNED,
    seo_title       VARCHAR(255),
    seo_description TEXT,
    created_at     DATETIME NOT NULL,
    updated_at     DATETIME NOT NULL,
    CONSTRAINT fk_pages_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
