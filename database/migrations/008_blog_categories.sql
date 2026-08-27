CREATE TABLE IF NOT EXISTS blog_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    description TEXT NULL,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_blog_categories_status_sort (status, sort_order, name),
    INDEX idx_blog_categories_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_categories (
    post_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (post_id, category_id),
    INDEX idx_blog_post_categories_category (category_id, post_id),
    INDEX idx_blog_post_categories_primary (post_id, is_primary),
    CONSTRAINT fk_blog_post_categories_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_post_categories_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO blog_categories
(name,slug,description,seo_title,meta_description,status,sort_order,is_default,created_at,updated_at)
SELECT 'General','general','General blog posts.',NULL,NULL,'active',100,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM blog_categories WHERE is_default=1);

INSERT INTO blog_post_categories (post_id,category_id,is_primary,created_at)
SELECT p.id,c.id,1,UTC_TIMESTAMP()
FROM posts p
JOIN blog_categories c ON c.is_default=1
WHERE NOT EXISTS (
    SELECT 1 FROM blog_post_categories pc WHERE pc.post_id=p.id
);
