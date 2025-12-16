# 商品管理模块设计文档

## 1. 概述

商品管理模块提供完整的商品信息管理功能，包括品牌管理、分类管理、商品信息管理、商品标签、图片管理（腾讯云 COS 存储）。

## 2. 数据模型

### 2.1 ER 图

```
┌─────────────┐       ┌─────────────────┐       ┌─────────────────┐
│    Brand    │       │    Category     │       │      Tag        │
├─────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)     │       │ id (PK)         │       │ id (PK)         │
│ name        │       │ name            │       │ name            │
│ slug        │       │ slug            │       │ slug            │
│ logo_url    │       │ description     │       │ color           │
│ description │       │ parent_id       │◄──┐   │ sort_order      │
│ sort_order  │       │ sort_order      │   │   │ is_active       │
│ is_active   │       │ is_active       │   │   │ created_at      │
│ created_at  │       │ created_at      │   │   └─────────────────┘
│ updated_at  │       │ updated_at      │   │           │
└─────────────┘       └─────────────────┘   │           │ M:N
      │                       │             │           │
      │ 1:N                   │ 1:N         │   ┌───────┴───────┐
      │                       │             │   │  product_tags │
      ▼                       ▼             │   └───────┬───────┘
┌─────────────────────────────────────────┐ │           │
│                 Product                  │ │           │
├─────────────────────────────────────────┤ │           │
│ id (PK)                                 │◄┴───────────┘
│ brand_id                                │
│ category_id                             │
│ name                                    │
│ slug                                    │
│ style_number (款号)                      │
│ season (季节)                            │
│ color (颜色名)                           │
│ description                             │
│ is_active                               │
│ created_at                              │
│ updated_at                              │
└─────────────────────────────────────────┘
          │                   │
          │ 1:N               │ 1:N
          ▼                   ▼
┌─────────────────┐   ┌─────────────────┐
│   ProductSku    │   │  ProductImage   │
├─────────────────┤   ├─────────────────┤
│ id (PK)         │   │ id (PK)         │
│ product_id      │   │ product_id      │
│ sku_code        │   │ cos_key         │
│ color_code      │   │ url             │
│ size_unit       │   │ thumbnail_url   │
│ size_value      │   │ sort_order      │
│ spec_info       │   │ is_primary      │
│ price           │   │ file_size       │
│ original_price  │   │ width           │
│ cost_price      │   │ height          │
│ is_active       │   │ created_at      │
│ sort_order      │   └─────────────────┘
│ created_at      │
│ updated_at      │
└─────────────────┘
```

> **说明**：
> - 商品信息与库存分离设计，SKU 不管理库存（库存由独立模块管理）
> - 价格字段存储在 SKU 上，同一商品的不同 SKU 可以有不同的参考价格
> - Product.color 记录商品颜色名，SKU 记录 color_code + size_unit + size_value

### 2.2 Entity 定义

#### Brand 实体

```php
// src/Entity/Brand.php
#[ORM\Entity(repositoryClass: BrandRepository::class)]
#[ORM\Table(name: 'brands')]
#[ORM\Index(name: 'idx_brand_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Brand
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 120, unique: true)]
    private string $slug;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'brand')]
    private Collection $products;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
}
```

#### Category 实体

```php
// src/Entity/Category.php
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
#[ORM\Index(name: 'idx_category_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_category_parent', columns: ['parent_id'])]
#[ORM\HasLifecycleCallbacks]
class Category
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 120, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Category $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $children;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    private Collection $products;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
}
```

#### Tag 实体

```php
// src/Entity/Tag.php
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tags')]
#[ORM\Index(name: 'idx_tag_slug', columns: ['slug'])]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 50)]
    private string $name;

    #[ORM\Column(length: 60, unique: true)]
    private string $slug;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;  // HEX 颜色值，如 #FF5733

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'tags')]
    private Collection $products;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

#### Product 实体

```php
// src/Entity/Product.php
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_product_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_product_style_number', columns: ['style_number'])]
#[ORM\Index(name: 'idx_product_season', columns: ['season'])]
#[ORM\Index(name: 'idx_product_brand', columns: ['brand_id'])]
#[ORM\Index(name: 'idx_product_category', columns: ['category_id'])]
#[ORM\Index(name: 'idx_product_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'brand_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Brand $brand = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 220, unique: true)]
    private string $slug;

    #[ORM\Column(length: 50, name: 'style_number')]
    private string $styleNumber;  // 款号

    #[ORM\Column(length: 20)]
    private string $season;  // 季节：2024SS, 2024AW, 2024FW 等

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;  // 颜色名，如：红色、深蓝

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_tags')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: ProductSku::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $skus;

    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $images;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Getters & Setters...

    public function getPrimaryImage(): ?ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->isPrimary()) {
                return $image;
            }
        }
        return $this->images->first() ?: null;
    }

    /**
     * 获取价格区间（最低价 - 最高价）
     */
    public function getPriceRange(): array
    {
        $prices = $this->skus
            ->filter(fn($sku) => $sku->isActive())
            ->map(fn($sku) => (float) $sku->getPrice())
            ->toArray();

        if (empty($prices)) {
            return ['min' => null, 'max' => null];
        }

        return ['min' => min($prices), 'max' => max($prices)];
    }

    /**
     * 获取 SKU 数量
     */
    public function getSkuCount(): int
    {
        return $this->skus->filter(fn($sku) => $sku->isActive())->count();
    }
}
```

#### ProductSku 实体

```php
// src/Entity/ProductSku.php
#[ORM\Entity(repositoryClass: ProductSkuRepository::class)]
#[ORM\Table(name: 'product_skus')]
#[ORM\Index(name: 'idx_sku_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_sku_code', columns: ['sku_code'])]
#[ORM\Index(name: 'idx_sku_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class ProductSku
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'skus')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private Product $product;

    #[ORM\Column(length: 50, unique: true, name: 'sku_code')]
    private string $skuCode;  // SKU 编码，如：DR-2024SS-001-S-RED

    #[ORM\Column(length: 20, nullable: true, name: 'color_code')]
    private ?string $colorCode = null;  // 颜色代码，如：RED、BLU、BLK

    #[ORM\Column(length: 20, nullable: true, name: 'size_unit')]
    private ?string $sizeUnit = null;  // 尺码单位，如：EU、US、CM

    #[ORM\Column(length: 20, nullable: true, name: 'size_value')]
    private ?string $sizeValue = null;  // 尺码值，如：S、M、L、38、39、40

    #[ORM\Column(type: 'json', nullable: true, name: 'spec_info')]
    private ?array $specInfo = null;  // 规格摘要，如：{"颜色": "红色", "尺码": "S"}

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;  // 参考价格

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true, name: 'original_price')]
    private ?string $originalPrice = null;  // 原价/吊牌价

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true, name: 'cost_price')]
    private ?string $costPrice = null;  // 成本价（可选）

    #[ORM\Column(type: 'boolean', options: ['default' => true], name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0], name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Getters & Setters...
}
```

#### ProductImage 实体

```php
// src/Entity/ProductImage.php
#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'idx_image_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_image_cos_key', columns: ['cos_key'])]
class ProductImage
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private Product $product;

    #[ORM\Column(length: 255, name: 'cos_key')]
    private string $cosKey;  // COS 对象键，如：products/2024/01/abc123.jpg

    #[ORM\Column(length: 500)]
    private string $url;  // 完整 CDN URL

    #[ORM\Column(length: 500, nullable: true, name: 'thumbnail_url')]
    private ?string $thumbnailUrl = null;  // 缩略图 URL（COS 图片处理）

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_primary')]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'integer', nullable: true, name: 'file_size')]
    private ?int $fileSize = null;  // 文件大小（字节）

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $width = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $height = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

### 2.3 数据库 Schema

> **注意：不使用外键约束**，数据完整性由应用层保证，便于后续分库分表和数据迁移。

```sql
-- 品牌表
CREATE TABLE brands (
    id VARCHAR(26) NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    logo_url VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0 NOT NULL,
    is_active TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_brand_slug (slug),
    INDEX idx_brand_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 分类表
CREATE TABLE categories (
    id VARCHAR(26) NOT NULL,
    parent_id VARCHAR(26) DEFAULT NULL COMMENT '父分类ID',
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0 NOT NULL,
    is_active TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_category_slug (slug),
    INDEX idx_category_slug (slug),
    INDEX idx_category_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 标签表
CREATE TABLE tags (
    id VARCHAR(26) NOT NULL,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(60) NOT NULL,
    color VARCHAR(7) DEFAULT NULL COMMENT 'HEX颜色值',
    sort_order INT DEFAULT 0 NOT NULL,
    is_active TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_tag_slug (slug),
    INDEX idx_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 商品表
CREATE TABLE products (
    id VARCHAR(26) NOT NULL,
    brand_id VARCHAR(26) DEFAULT NULL COMMENT '品牌ID',
    category_id VARCHAR(26) DEFAULT NULL COMMENT '分类ID',
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    style_number VARCHAR(50) NOT NULL COMMENT '款号',
    season VARCHAR(20) NOT NULL COMMENT '季节：2024SS/2024AW/2024FW',
    color VARCHAR(50) DEFAULT NULL COMMENT '颜色名',
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_product_slug (slug),
    INDEX idx_product_slug (slug),
    INDEX idx_product_style_number (style_number),
    INDEX idx_product_season (season),
    INDEX idx_product_brand (brand_id),
    INDEX idx_product_category (category_id),
    INDEX idx_product_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 商品SKU表（价格存储在此，库存由独立模块管理）
CREATE TABLE product_skus (
    id VARCHAR(26) NOT NULL,
    product_id VARCHAR(26) NOT NULL COMMENT '商品ID',
    sku_code VARCHAR(50) NOT NULL COMMENT 'SKU编码',
    color_code VARCHAR(20) DEFAULT NULL COMMENT '颜色代码：RED/BLU/BLK',
    size_unit VARCHAR(20) DEFAULT NULL COMMENT '尺码单位：EU/US/CM',
    size_value VARCHAR(20) DEFAULT NULL COMMENT '尺码值：S/M/L/38/39/40',
    spec_info JSON DEFAULT NULL COMMENT '规格摘要：{"颜色":"红色","尺码":"S"}',
    price DECIMAL(10, 2) NOT NULL COMMENT '参考价格',
    original_price DECIMAL(10, 2) DEFAULT NULL COMMENT '原价/吊牌价',
    cost_price DECIMAL(10, 2) DEFAULT NULL COMMENT '成本价',
    is_active TINYINT(1) DEFAULT 1 NOT NULL,
    sort_order INT DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_sku_code (sku_code),
    INDEX idx_sku_product (product_id),
    INDEX idx_sku_code (sku_code),
    INDEX idx_sku_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 商品标签关联表
CREATE TABLE product_tags (
    product_id VARCHAR(26) NOT NULL COMMENT '商品ID',
    tag_id VARCHAR(26) NOT NULL COMMENT '标签ID',
    PRIMARY KEY (product_id, tag_id),
    INDEX idx_product_tags_product (product_id),
    INDEX idx_product_tags_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 商品图片表
CREATE TABLE product_images (
    id VARCHAR(26) NOT NULL,
    product_id VARCHAR(26) NOT NULL COMMENT '商品ID',
    cos_key VARCHAR(255) NOT NULL COMMENT 'COS对象键',
    url VARCHAR(500) NOT NULL COMMENT '完整CDN URL',
    thumbnail_url VARCHAR(500) DEFAULT NULL COMMENT '缩略图URL',
    sort_order INT DEFAULT 0 NOT NULL,
    is_primary TINYINT(1) DEFAULT 0 NOT NULL,
    file_size INT DEFAULT NULL COMMENT '文件大小(字节)',
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    INDEX idx_image_product (product_id),
    INDEX idx_image_cos_key (cos_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 3. API 设计

### 3.1 品牌管理 API

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| GET | /api/brands | 获取品牌列表 | ✗ |
| GET | /api/brands/{id} | 获取单个品牌 | ✗ |
| POST | /api/brands | 创建品牌 | ✓ |
| PUT | /api/brands/{id} | 更新品牌 | ✓ |
| DELETE | /api/brands/{id} | 删除品牌 | ✓ |

### 3.2 分类管理 API

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| GET | /api/categories | 获取分类列表（树形结构） | ✗ |
| GET | /api/categories/{id} | 获取单个分类 | ✗ |
| POST | /api/categories | 创建分类 | ✓ |
| PUT | /api/categories/{id} | 更新分类 | ✓ |
| DELETE | /api/categories/{id} | 删除分类 | ✓ |

### 3.3 标签管理 API

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| GET | /api/tags | 获取标签列表 | ✗ |
| GET | /api/tags/{id} | 获取单个标签 | ✗ |
| POST | /api/tags | 创建标签 | ✓ |
| PUT | /api/tags/{id} | 更新标签 | ✓ |
| DELETE | /api/tags/{id} | 删除标签 | ✓ |

### 3.4 商品管理 API

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| GET | /api/products | 获取商品列表（分页、搜索、筛选） | ✗ |
| GET | /api/products/{id} | 获取商品详情（含SKU列表） | ✗ |
| GET | /api/products/slug/{slug} | 通过 slug 获取商品 | ✗ |
| POST | /api/products | 创建商品 | ✓ |
| PUT | /api/products/{id} | 更新商品 | ✓ |
| DELETE | /api/products/{id} | 删除商品 | ✓ |
| PATCH | /api/products/{id}/status | 切换上下架状态 | ✓ |
| POST | /api/products/{id}/tags | 设置商品标签 | ✓ |

### 3.5 商品 SKU API

> **注意**：库存由独立模块管理，SKU 只管理商品信息和参考价格

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| GET | /api/products/{id}/skus | 获取商品的 SKU 列表 | ✗ |
| GET | /api/skus/{skuCode} | 通过 SKU 编码获取 SKU | ✗ |
| POST | /api/products/{id}/skus | 添加 SKU | ✓ |
| PUT | /api/products/{id}/skus/{skuId} | 更新 SKU | ✓ |
| DELETE | /api/products/{id}/skus/{skuId} | 删除 SKU | ✓ |
| PATCH | /api/products/{id}/skus/{skuId}/status | 切换 SKU 上下架状态 | ✓ |

### 3.6 商品图片 API（腾讯云 COS）

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| POST | /api/products/{id}/images/presign | 获取上传预签名 URL | ✓ |
| POST | /api/products/{id}/images | 确认图片上传完成 | ✓ |
| PUT | /api/products/{id}/images/{imageId} | 更新图片信息 | ✓ |
| DELETE | /api/products/{id}/images/{imageId} | 删除图片 | ✓ |
| PATCH | /api/products/{id}/images/sort | 调整图片排序 | ✓ |
| PATCH | /api/products/{id}/images/{imageId}/primary | 设为主图 | ✓ |

### 3.7 请求/响应示例

#### 获取商品列表

**Request:**
```http
GET /api/products?page=1&limit=20&brand=01HX&category=01HY&season=2024SS&search=连衣裙&min_price=100&max_price=1000&tags[]=01HZ1&tags[]=01HZ2&is_active=1&sort_by=created_at&sort_order=desc
```

**Response:**
```json
{
    "data": [
        {
            "id": "01HX1234567890ABCDEFGH",
            "name": "春季新款碎花连衣裙",
            "slug": "spring-floral-dress-2024",
            "style_number": "DR2024SS001",
            "season": "2024SS",
            "is_active": true,
            "price_range": {"min": "499.00", "max": "699.00"},
            "sku_count": 6,
            "brand": {
                "id": "01HXBRAND12345",
                "name": "ZARA",
                "slug": "zara"
            },
            "category": {
                "id": "01HXCATEGORY123",
                "name": "连衣裙",
                "slug": "dresses"
            },
            "tags": [
                {"id": "01HZTAG001", "name": "新品", "color": "#FF5733"},
                {"id": "01HZTAG002", "name": "热卖", "color": "#33FF57"}
            ],
            "primary_image": {
                "url": "https://cdn.example.com/products/2024/01/abc123.jpg",
                "thumbnail_url": "https://cdn.example.com/products/2024/01/abc123.jpg?imageMogr2/thumbnail/200x"
            },
            "created_at": "2024-01-15T10:30:00+08:00",
            "updated_at": "2024-01-20T14:20:00+08:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 156,
        "total_pages": 8
    }
}
```

#### 获取商品详情（含 SKU 列表）

**Request:**
```http
GET /api/products/01HX1234567890ABCDEFGH
```

**Response:**
```json
{
    "data": {
        "id": "01HX1234567890ABCDEFGH",
        "name": "春季新款碎花连衣裙",
        "slug": "spring-floral-dress-2024",
        "style_number": "DR2024SS001",
        "season": "2024SS",
        "description": "优雅碎花设计，轻盈面料...",
        "is_active": true,
        "brand": {"id": "01HXBRAND12345", "name": "ZARA", "slug": "zara"},
        "category": {"id": "01HXCATEGORY123", "name": "连衣裙", "slug": "dresses"},
        "tags": [{"id": "01HZTAG001", "name": "新品", "color": "#FF5733"}],
        "skus": [
            {
                "id": "01HXSKU001",
                "sku_code": "DR-2024SS-001-S-RED",
                "color_code": "RED",
                "size_unit": "CN",
                "size_value": "S",
                "spec_info": {"颜色": "红色", "尺码": "S"},
                "price": "599.00",
                "original_price": "799.00",
                "is_active": true
            },
            {
                "id": "01HXSKU002",
                "sku_code": "DR-2024SS-001-M-RED",
                "color_code": "RED",
                "size_unit": "CN",
                "size_value": "M",
                "spec_info": {"颜色": "红色", "尺码": "M"},
                "price": "599.00",
                "original_price": "799.00",
                "is_active": true
            }
        ],
        "images": [
            {
                "id": "01HXIMG001",
                "url": "https://cdn.example.com/products/2024/01/abc123.jpg",
                "thumbnail_url": "https://cdn.example.com/products/2024/01/abc123.jpg?imageMogr2/thumbnail/200x",
                "is_primary": true,
                "sort_order": 0
            }
        ],
        "price_range": {"min": "599.00", "max": "599.00"},
        "sku_count": 2,
        "created_at": "2024-01-15T10:30:00+08:00",
        "updated_at": "2024-01-20T14:20:00+08:00"
    }
}
```

#### 创建商品

**Request:**
```http
POST /api/products
Content-Type: application/json
Authorization: Bearer <token>

{
    "name": "春季新款碎花连衣裙",
    "style_number": "DR2024SS001",
    "season": "2024SS",
    "description": "优雅碎花设计，轻盈面料...",
    "brand_id": "01HXBRAND12345",
    "category_id": "01HXCATEGORY123",
    "tag_ids": ["01HZTAG001", "01HZTAG002"],
    "is_active": true
}
```

#### 添加 SKU

**Request:**
```http
POST /api/products/01HX1234567890ABCDEFGH/skus
Content-Type: application/json
Authorization: Bearer <token>

{
    "sku_code": "DR-2024SS-001-S-RED",
    "color_code": "RED",
    "size_unit": "CN",
    "size_value": "S",
    "spec_info": {"颜色": "红色", "尺码": "S"},
    "price": 599.00,
    "original_price": 799.00,
    "cost_price": 200.00,
    "is_active": true
}
```

#### 获取图片上传预签名 URL

**Request:**
```http
POST /api/products/01HX1234567890ABCDEFGH/images/presign
Content-Type: application/json
Authorization: Bearer <token>

{
    "filename": "product-image.jpg",
    "content_type": "image/jpeg",
    "file_size": 1024000
}
```

**Response:**
```json
{
    "presigned_url": "https://bucket.cos.ap-shanghai.myqcloud.com/products/2024/01/abc123.jpg?sign=xxx",
    "cos_key": "products/2024/01/abc123.jpg",
    "expires_at": "2024-01-15T11:00:00+08:00"
}
```

#### 确认图片上传完成

**Request:**
```http
POST /api/products/01HX1234567890ABCDEFGH/images
Content-Type: application/json
Authorization: Bearer <token>

{
    "cos_key": "products/2024/01/abc123.jpg",
    "is_primary": true,
    "sort_order": 0
}
```

## 4. 目录结构

```
backend/src/
├── Controller/
│   └── Product/
│       ├── BrandController.php
│       ├── CategoryController.php
│       ├── TagController.php
│       ├── ProductController.php
│       ├── ProductSkuController.php
│       └── ProductImageController.php
├── Dto/
│   └── Product/
│       ├── CreateBrandRequest.php
│       ├── UpdateBrandRequest.php
│       ├── CreateCategoryRequest.php
│       ├── UpdateCategoryRequest.php
│       ├── CreateTagRequest.php
│       ├── UpdateTagRequest.php
│       ├── CreateProductRequest.php
│       ├── UpdateProductRequest.php
│       ├── ProductListFilter.php
│       ├── CreateSkuRequest.php
│       ├── UpdateSkuRequest.php
│       ├── ImagePresignRequest.php
│       └── ConfirmImageRequest.php
├── Entity/
│   ├── Brand.php
│   ├── Category.php
│   ├── Tag.php
│   ├── Product.php
│   ├── ProductSku.php
│   └── ProductImage.php
├── Repository/
│   ├── BrandRepository.php
│   ├── CategoryRepository.php
│   ├── TagRepository.php
│   ├── ProductRepository.php
│   ├── ProductSkuRepository.php
│   └── ProductImageRepository.php
└── Service/
    └── Product/
        ├── BrandService.php
        ├── CategoryService.php
        ├── TagService.php
        ├── ProductService.php
        ├── ProductSkuService.php
        ├── ProductImageService.php
        ├── CosService.php
        └── SlugGenerator.php
```

## 5. DTO 定义

### 5.1 CreateProductRequest

```php
// src/Dto/Product/CreateProductRequest.php
class CreateProductRequest
{
    #[Assert\NotBlank(message: '商品名称不能为空')]
    #[Assert\Length(max: 200, maxMessage: '商品名称最多200个字符')]
    public string $name;

    #[Assert\NotBlank(message: '款号不能为空')]
    #[Assert\Length(max: 50, maxMessage: '款号最多50个字符')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9\-_]+$/', message: '款号只能包含字母、数字、横线和下划线')]
    public string $styleNumber;

    #[Assert\NotBlank(message: '季节不能为空')]
    #[Assert\Regex(pattern: '/^\d{4}(SS|AW|FW|PF|RS)$/', message: '季节格式错误，如：2024SS、2024AW')]
    public string $season;

    #[Assert\Length(max: 5000, maxMessage: '商品描述最多5000个字符')]
    public ?string $description = null;

    public ?string $brandId = null;

    public ?string $categoryId = null;

    #[Assert\All([new Assert\Length(exactly: 26)])]
    public array $tagIds = [];

    public bool $isActive = true;
}
```

### 5.2 CreateSkuRequest

```php
// src/Dto/Product/CreateSkuRequest.php
class CreateSkuRequest
{
    #[Assert\NotBlank(message: 'SKU编码不能为空')]
    #[Assert\Length(max: 50, maxMessage: 'SKU编码最多50个字符')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9\-_]+$/', message: 'SKU编码只能包含字母、数字、横线和下划线')]
    public string $skuCode;

    #[Assert\Length(max: 20, maxMessage: '颜色代码最多20个字符')]
    public ?string $colorCode = null;  // 颜色代码：RED、BLU、BLK

    #[Assert\Length(max: 20, maxMessage: '尺码单位最多20个字符')]
    public ?string $sizeUnit = null;  // 尺码单位：EU、US、CM、CN

    #[Assert\Length(max: 20, maxMessage: '尺码值最多20个字符')]
    public ?string $sizeValue = null;  // 尺码值：S、M、L、38、39、40

    public ?array $specInfo = null;  // 规格摘要：{"颜色": "红色", "尺码": "S"}

    #[Assert\NotBlank(message: '价格不能为空')]
    #[Assert\Positive(message: '价格必须大于0')]
    public float $price;

    #[Assert\PositiveOrZero(message: '原价必须大于等于0')]
    public ?float $originalPrice = null;

    #[Assert\PositiveOrZero(message: '成本价必须大于等于0')]
    public ?float $costPrice = null;

    public bool $isActive = true;
}
```

### 5.3 ProductListFilter

```php
// src/Dto/Product/ProductListFilter.php
class ProductListFilter
{
    #[Assert\Positive]
    public int $page = 1;

    #[Assert\Range(min: 1, max: 100)]
    public int $limit = 20;

    public ?string $search = null;

    public ?string $brandId = null;

    public ?string $categoryId = null;

    #[Assert\Regex(pattern: '/^\d{4}(SS|AW|FW|PF|RS)?$/')]
    public ?string $season = null;

    public ?string $styleNumber = null;

    #[Assert\PositiveOrZero]
    public ?float $minPrice = null;

    #[Assert\PositiveOrZero]
    public ?float $maxPrice = null;

    public ?bool $isActive = null;

    public array $tagIds = [];

    #[Assert\Choice(choices: ['created_at', 'updated_at', 'price', 'name', 'style_number', 'season'])]
    public string $sortBy = 'created_at';

    #[Assert\Choice(choices: ['asc', 'desc'])]
    public string $sortOrder = 'desc';
}
```

### 5.4 ImagePresignRequest

```php
// src/Dto/Product/ImagePresignRequest.php
class ImagePresignRequest
{
    #[Assert\NotBlank(message: '文件名不能为空')]
    #[Assert\Length(max: 200)]
    public string $filename;

    #[Assert\NotBlank(message: '文件类型不能为空')]
    #[Assert\Choice(choices: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], message: '不支持的图片格式')]
    public string $contentType;

    #[Assert\NotBlank(message: '文件大小不能为空')]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(value: 10485760, message: '图片大小不能超过10MB')]
    public int $fileSize;
}
```

## 6. 腾讯云 COS 集成

### 6.1 环境配置

```env
# .env
COS_SECRET_ID=your_secret_id
COS_SECRET_KEY=your_secret_key
COS_REGION=ap-shanghai
COS_BUCKET=your-bucket-1234567890
COS_CDN_DOMAIN=https://cdn.example.com
```

### 6.2 CosService

```php
// src/Service/Product/CosService.php
class CosService
{
    private Client $cosClient;

    public function __construct(
        #[Autowire(env: 'COS_SECRET_ID')] private string $secretId,
        #[Autowire(env: 'COS_SECRET_KEY')] private string $secretKey,
        #[Autowire(env: 'COS_REGION')] private string $region,
        #[Autowire(env: 'COS_BUCKET')] private string $bucket,
        #[Autowire(env: 'COS_CDN_DOMAIN')] private string $cdnDomain,
    ) {
        $this->cosClient = new Client([
            'region' => $region,
            'schema' => 'https',
            'credentials' => [
                'secretId' => $secretId,
                'secretKey' => $secretKey,
            ],
        ]);
    }

    /**
     * 生成上传预签名 URL
     */
    public function generatePresignedUrl(string $key, string $contentType, int $expiresIn = 3600): array
    {
        $command = $this->cosClient->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $presignedUrl = $this->cosClient->createPresignedRequest($command, "+{$expiresIn} seconds")
            ->getUri()
            ->__toString();

        return [
            'presigned_url' => $presignedUrl,
            'cos_key' => $key,
            'expires_at' => (new \DateTimeImmutable())->modify("+{$expiresIn} seconds"),
        ];
    }

    /**
     * 生成对象键
     */
    public function generateKey(string $filename, string $prefix = 'products'): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $date = date('Y/m');
        $hash = bin2hex(random_bytes(16));

        return "{$prefix}/{$date}/{$hash}.{$ext}";
    }

    /**
     * 获取完整 CDN URL
     */
    public function getCdnUrl(string $key): string
    {
        return rtrim($this->cdnDomain, '/') . '/' . ltrim($key, '/');
    }

    /**
     * 获取缩略图 URL（使用 COS 数据万象图片处理）
     */
    public function getThumbnailUrl(string $key, int $width = 200): string
    {
        return $this->getCdnUrl($key) . "?imageMogr2/thumbnail/{$width}x";
    }

    /**
     * 检查对象是否存在
     */
    public function objectExists(string $key): bool
    {
        try {
            $this->cosClient->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取对象元数据
     */
    public function getObjectMetadata(string $key): ?array
    {
        try {
            $result = $this->cosClient->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return [
                'content_length' => $result['ContentLength'] ?? null,
                'content_type' => $result['ContentType'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 删除对象
     */
    public function deleteObject(string $key): bool
    {
        try {
            $this->cosClient->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 批量删除对象
     */
    public function deleteObjects(array $keys): void
    {
        if (empty($keys)) {
            return;
        }

        $objects = array_map(fn($key) => ['Key' => $key], $keys);

        $this->cosClient->deleteObjects([
            'Bucket' => $this->bucket,
            'Delete' => [
                'Objects' => $objects,
            ],
        ]);
    }
}
```

### 6.3 ProductImageService

```php
// src/Service/Product/ProductImageService.php
class ProductImageService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductImageRepository $imageRepository,
        private CosService $cosService,
        private EntityManagerInterface $em,
    ) {}

    /**
     * 获取上传预签名
     */
    public function getPresignedUrl(string $productId, ImagePresignRequest $dto): array
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new \InvalidArgumentException('商品不存在');
        }

        $key = $this->cosService->generateKey($dto->filename);

        return $this->cosService->generatePresignedUrl($key, $dto->contentType);
    }

    /**
     * 确认图片上传完成
     */
    public function confirmUpload(string $productId, ConfirmImageRequest $dto): ProductImage
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new \InvalidArgumentException('商品不存在');
        }

        // 验证 COS 对象存在
        if (!$this->cosService->objectExists($dto->cosKey)) {
            throw new \InvalidArgumentException('图片上传未完成');
        }

        // 获取图片元数据
        $metadata = $this->cosService->getObjectMetadata($dto->cosKey);

        // 如果设为主图，取消其他主图
        if ($dto->isPrimary) {
            $this->imageRepository->clearPrimaryForProduct($productId);
        }

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setCosKey($dto->cosKey);
        $image->setUrl($this->cosService->getCdnUrl($dto->cosKey));
        $image->setThumbnailUrl($this->cosService->getThumbnailUrl($dto->cosKey));
        $image->setIsPrimary($dto->isPrimary);
        $image->setSortOrder($dto->sortOrder ?? $product->getImages()->count());
        $image->setFileSize($metadata['content_length'] ?? null);

        $this->imageRepository->save($image, true);

        return $image;
    }

    /**
     * 删除图片
     */
    public function delete(string $productId, string $imageId): void
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image || $image->getProduct()->getId() !== $productId) {
            throw new \InvalidArgumentException('图片不存在');
        }

        // 从 COS 删除
        $this->cosService->deleteObject($image->getCosKey());

        // 从数据库删除
        $this->imageRepository->remove($image, true);
    }

    /**
     * 设为主图
     */
    public function setPrimary(string $productId, string $imageId): ProductImage
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image || $image->getProduct()->getId() !== $productId) {
            throw new \InvalidArgumentException('图片不存在');
        }

        $this->imageRepository->clearPrimaryForProduct($productId);
        $image->setIsPrimary(true);
        $this->imageRepository->save($image, true);

        return $image;
    }

    /**
     * 调整排序
     */
    public function updateSort(string $productId, array $sortData): void
    {
        $this->em->beginTransaction();
        try {
            foreach ($sortData as $item) {
                $image = $this->imageRepository->find($item['id']);
                if ($image && $image->getProduct()->getId() === $productId) {
                    $image->setSortOrder($item['sort_order']);
                    $this->imageRepository->save($image);
                }
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
```

## 7. Composer 依赖

```bash
# 安装腾讯云 COS SDK
composer require qcloud/cos-sdk-v5
```

## 8. Season 字段说明

| 值 | 含义 |
|----|------|
| 2024SS | 2024 Spring/Summer 春夏 |
| 2024AW | 2024 Autumn/Winter 秋冬 |
| 2024FW | 2024 Fall/Winter 秋冬（同 AW） |
| 2024PF | 2024 Pre-Fall 早秋 |
| 2024RS | 2024 Resort/Cruise 度假系列 |

## 9. 安全配置

在 `config/packages/security.yaml` 中添加:

```yaml
access_control:
    # 公开接口（GET 请求）
    - { path: ^/api/brands$, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/brands/[^/]+$, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/categories, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/tags, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/products$, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/products/[^/]+$, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/products/slug/, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/api/products/sku/, methods: [GET], roles: PUBLIC_ACCESS }

    # 需要认证的管理接口
    - { path: ^/api/brands, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/api/categories, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/api/tags, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/api/products, roles: IS_AUTHENTICATED_FULLY }
```

## 10. 实施步骤

1. 安装腾讯云 COS SDK：`composer require qcloud/cos-sdk-v5`
2. 配置 COS 环境变量
3. 创建 Entity 文件
4. 生成数据库迁移：`php bin/console make:migration`
5. 执行迁移：`php bin/console doctrine:migrations:migrate`
6. 创建 Repository 类
7. 创建 DTO 验证类
8. 实现 Service 层
9. 实现 Controller
10. 更新 security.yaml
11. 测试 API 端点