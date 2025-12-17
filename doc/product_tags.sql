-- Many-to-many join table between products and tags
-- Description: Product-Tag association

CREATE TABLE `product_tags` (
    `product_id` VARCHAR(26) NOT NULL,
    `tag_id` VARCHAR(26) NOT NULL,
    PRIMARY KEY (`product_id`, `tag_id`),
    INDEX `idx_product_tags_product` (`product_id`),
    INDEX `idx_product_tags_tag` (`tag_id`),
    CONSTRAINT `fk_product_tags_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product-Tag associations';