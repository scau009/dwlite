# 商户出价模块设计文档

## 1. 概述

商户出价模块允许商户对已入库的 SKU 进行定价，并选择上架到指定销售渠道。支持两种出价模式：

- **自主出价 (self_pricing)**：商户完全控制价格和上架状态
- **全托管 (auto_pricing)**：商户设置底价，平台自动调价和管理上架

## 2. 业务需求

### 2.1 核心场景

| 维度       | 决策                                       |
|------------|------------------------------------------|
| 出价时机   | 入库完成后独立设置（与入库单解耦）           |
| 出价粒度   | SKU + 渠道组合（同一SKU不同渠道可设不同价格） |
| 出价模式   | 自主出价 / 全托管（平台自动调价）            |
| 托管层级   | SKU + 渠道组合级别                          |
| 审批流程   | 无需审批，全托管模式下锁定商户操作权限       |
| 页面入口   | 独立出价管理页面                            |

### 2.2 出价模式对比

| 特性       | 自主出价 (self_pricing) | 全托管 (auto_pricing)     |
|------------|------------------------|---------------------------|
| 价格设置   | 商户自行设置           | 平台自动调整（规则待定）   |
| 底价       | 可选设置               | **必须设置**              |
| 修改权限   | 商户可随时调整         | **商户无法修改**          |
| 上架状态   | 商户控制               | **商户无法控制**          |
| 库存分配   | 商户指定               | **平台自动分配**          |

### 2.3 价格预警规则

| 预警类型   | 触发条件                                | 级别             |
|------------|----------------------------------------|------------------|
| 低于成本价 | `price < MerchantInventory.averageCost` | 警告（允许保存） |
| 低于底价   | `price < floorPrice`                    | 错误（阻止保存） |

## 3. 数据模型设计

### 3.1 InventoryListing 实体扩展

在现有 `InventoryListing` 实体基础上新增字段：

```php
// src/Entity/InventoryListing.php

// 新增常量
public const PRICING_MODE_SELF = 'self_pricing';      // 自主出价
public const PRICING_MODE_AUTO = 'auto_pricing';      // 全托管

// 新增字段
#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
private ?string $floorPrice = null;  // 底价

#[ORM\Column(type: 'string', length: 20, options: ['default' => 'self_pricing'])]
private string $pricingMode = self::PRICING_MODE_SELF;  // 出价模式

#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $autoPricingLockedAt = null;  // 全托管锁定时间
```

### 3.2 新增 PriceChangeLog 实体

记录价格变更历史：

```php
// src/Entity/PriceChangeLog.php

#[ORM\Entity(repositoryClass: PriceChangeLogRepository::class)]
#[ORM\Table(name: 'price_change_logs')]
#[ORM\Index(columns: ['inventory_listing_id', 'created_at'])]
class PriceChangeLog
{
    public const TYPE_MANUAL = 'manual';           // 手动修改
    public const TYPE_AUTO_PRICING = 'auto_pricing'; // 系统自动调价
    public const TYPE_BATCH = 'batch';             // 批量操作

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: InventoryListing::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InventoryListing $inventoryListing;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $oldPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $newPrice;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $oldFloorPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $newFloorPrice = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $changeType;  // manual, auto_pricing, batch

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $changeReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $changedBy = null;  // null 表示系统自动

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

### 3.3 数据库 Schema

```sql
-- doc/inventory_listings.sql 新增字段
ALTER TABLE inventory_listings
ADD COLUMN floor_price DECIMAL(10,2) DEFAULT NULL COMMENT '底价',
ADD COLUMN pricing_mode VARCHAR(20) NOT NULL DEFAULT 'self_pricing' COMMENT '出价模式: self_pricing, auto_pricing',
ADD COLUMN auto_pricing_locked_at DATETIME DEFAULT NULL COMMENT '全托管锁定时间';

-- doc/price_change_logs.sql 新建表
CREATE TABLE price_change_logs (
    id CHAR(26) NOT NULL COMMENT 'ULID',
    inventory_listing_id CHAR(26) NOT NULL,
    old_price DECIMAL(10,2) DEFAULT NULL COMMENT '原价格',
    new_price DECIMAL(10,2) NOT NULL COMMENT '新价格',
    old_floor_price DECIMAL(10,2) DEFAULT NULL COMMENT '原底价',
    new_floor_price DECIMAL(10,2) DEFAULT NULL COMMENT '新底价',
    change_type VARCHAR(20) NOT NULL COMMENT '变更类型: manual, auto_pricing, batch',
    change_reason VARCHAR(255) DEFAULT NULL COMMENT '变更原因',
    changed_by CHAR(26) DEFAULT NULL COMMENT '操作人ID，null表示系统',
    created_at DATETIME NOT NULL COMMENT '创建时间',
    PRIMARY KEY (id),
    INDEX idx_listing_time (inventory_listing_id, created_at),
    CONSTRAINT fk_pcl_listing FOREIGN KEY (inventory_listing_id)
        REFERENCES inventory_listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcl_user FOREIGN KEY (changed_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='价格变更日志';
```

## 4. API 设计

### 4.1 商户出价 API

| Method | Path                                    | 说明           |
|--------|----------------------------------------|----------------|
| GET    | /api/merchant/pricing                   | 获取出价列表    |
| GET    | /api/merchant/pricing/{id}              | 获取出价详情    |
| POST   | /api/merchant/pricing                   | 创建出价        |
| PUT    | /api/merchant/pricing/{id}              | 更新出价        |
| POST   | /api/merchant/pricing/batch             | 批量更新出价    |
| GET    | /api/merchant/pricing/{id}/history      | 获取价格历史    |
| GET    | /api/merchant/pricing/available-inventory | 可出价库存列表 |

### 4.2 DTO 设计

```php
// src/Dto/Merchant/Pricing/PricingQueryDto.php
class PricingQueryDto
{
    public ?string $keyword = null;           // SKU名称/款号搜索
    public ?string $channelId = null;         // 渠道筛选
    public ?string $pricingMode = null;       // 出价模式筛选
    public ?string $status = null;            // 上架状态筛选
    public int $page = 1;
    public int $pageSize = 20;
}

// src/Dto/Merchant/Pricing/CreatePricingDto.php
class CreatePricingDto
{
    #[Assert\NotBlank]
    public string $merchantInventoryId;       // 库存ID

    #[Assert\NotBlank]
    public string $merchantSalesChannelId;    // 渠道关联ID

    #[Assert\NotBlank]
    #[Assert\Positive]
    public string $price;                     // 售价

    public ?string $compareAtPrice = null;    // 划线价

    public ?string $floorPrice = null;        // 底价

    #[Assert\Choice(choices: ['self_pricing', 'auto_pricing'])]
    public string $pricingMode = 'self_pricing';

    #[Assert\Choice(choices: ['shared', 'dedicated'])]
    public string $allocationMode = 'shared';

    public ?int $allocatedQuantity = null;    // 专属模式分配数量
}

// src/Dto/Merchant/Pricing/UpdatePricingDto.php
class UpdatePricingDto
{
    #[Assert\Positive]
    public ?string $price = null;

    public ?string $compareAtPrice = null;

    public ?string $floorPrice = null;

    #[Assert\Choice(choices: ['self_pricing', 'auto_pricing'])]
    public ?string $pricingMode = null;

    #[Assert\Choice(choices: ['shared', 'dedicated'])]
    public ?string $allocationMode = null;

    public ?int $allocatedQuantity = null;

    #[Assert\Choice(choices: ['active', 'paused'])]
    public ?string $status = null;
}

// src/Dto/Merchant/Pricing/BatchUpdatePricingDto.php
class BatchUpdatePricingDto
{
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 100)]
    public array $ids;                        // 出价记录ID列表

    public ?string $price = null;             // 批量设置价格
    public ?string $floorPrice = null;        // 批量设置底价
    public ?string $pricingMode = null;       // 批量设置模式
    public ?string $status = null;            // 批量设置状态
}
```

### 4.3 响应格式

```php
// 出价列表项
{
    "id": "01JGXX...",
    "sku": {
        "id": "01JGXX...",
        "name": "Air Jordan 1 High OG",
        "styleNumber": "DZ5485-612",
        "size": "42",
        "imageUrl": "https://..."
    },
    "channel": {
        "id": "01JGXX...",
        "code": "poizon",
        "name": "得物"
    },
    "price": "1299.00",
    "compareAtPrice": "1599.00",
    "floorPrice": "1100.00",
    "pricingMode": "self_pricing",
    "allocationMode": "shared",
    "allocatedQuantity": null,
    "availableQuantity": 5,
    "soldQuantity": 2,
    "status": "active",
    "costPrice": "900.00",          // 来自 MerchantInventory.averageCost
    "profitRate": "44.33",          // 计算的利润率 (price - cost) / price * 100
    "createdAt": "2025-01-15T10:00:00+00:00",
    "updatedAt": "2025-01-20T14:30:00+00:00"
}
```

## 5. 服务层设计

### 5.1 PricingService

```php
// src/Service/Merchant/PricingService.php
class PricingService
{
    public function __construct(
        private InventoryListingRepository $listingRepo,
        private MerchantInventoryRepository $inventoryRepo,
        private PriceChangeLogRepository $logRepo,
        private EntityManagerInterface $em,
    ) {}

    /**
     * 创建出价
     */
    public function create(Merchant $merchant, CreatePricingDto $dto): InventoryListing
    {
        // 1. 验证库存归属
        // 2. 验证渠道关联
        // 3. 验证价格（不能低于底价）
        // 4. 创建 InventoryListing
        // 5. 记录价格日志
    }

    /**
     * 更新出价
     */
    public function update(
        InventoryListing $listing,
        UpdatePricingDto $dto,
        User $operator
    ): InventoryListing
    {
        // 1. 检查是否全托管模式（全托管不允许商户修改）
        // 2. 验证价格
        // 3. 更新字段
        // 4. 记录价格日志
    }

    /**
     * 批量更新
     */
    public function batchUpdate(
        Merchant $merchant,
        BatchUpdatePricingDto $dto,
        User $operator
    ): array
    {
        // 返回成功/失败统计
    }

    /**
     * 验证价格
     */
    public function validatePrice(
        InventoryListing $listing,
        string $price,
        ?string $floorPrice
    ): array
    {
        $warnings = [];
        $errors = [];

        // 检查是否低于底价
        if ($floorPrice && bccomp($price, $floorPrice, 2) < 0) {
            $errors[] = 'price_below_floor';
        }

        // 检查是否低于成本
        $inventory = $listing->getMerchantInventory();
        if ($inventory->getAverageCost() &&
            bccomp($price, $inventory->getAverageCost(), 2) < 0) {
            $warnings[] = 'price_below_cost';
        }

        return ['warnings' => $warnings, 'errors' => $errors];
    }

    /**
     * 切换到全托管模式
     */
    public function enableAutoPricing(
        InventoryListing $listing,
        string $floorPrice
    ): void
    {
        $listing->setPricingMode(InventoryListing::PRICING_MODE_AUTO);
        $listing->setFloorPrice($floorPrice);
        $listing->setAutoPricingLockedAt(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        // 触发自动调价（后续实现）
    }

    /**
     * 获取价格历史
     */
    public function getPriceHistory(
        InventoryListing $listing,
        int $limit = 50
    ): array
    {
        return $this->logRepo->findByListing($listing, $limit);
    }
}
```

### 5.2 AutoPricingService（后续迭代）

```php
// src/Service/Merchant/AutoPricingService.php
class AutoPricingService
{
    /**
     * 执行自动调价（定时任务调用）
     */
    public function executeAutoPricing(): void
    {
        // 1. 获取所有全托管模式的 listing
        // 2. 根据规则计算新价格
        // 3. 更新价格并记录日志
    }

    /**
     * 计算建议价格（规则待定）
     */
    public function calculateSuggestedPrice(InventoryListing $listing): string
    {
        // TODO: 实现自动调价规则
        // 可能的规则：
        // - 跟随平台最低价
        // - 基于市场供需
        // - 基于历史销量
    }
}
```

## 6. 前端页面设计

### 6.1 页面结构

```
/merchant/pricing                    # 出价管理列表页
/merchant/pricing/:id                # 出价详情/编辑页
/merchant/pricing/:id/history        # 价格历史页
```

### 6.2 出价管理列表页

```
[ 页面标题：出价管理 ]
[ 筛选区 ]
  - 搜索：SKU名称/款号
  - 渠道：下拉选择
  - 出价模式：自主出价/全托管
  - 状态：草稿/已上架/已暂停

[ 操作区 ]
  - 批量设置价格
  - 批量设置底价
  - 批量切换模式
  - 批量上架/下架

[ 数据表格 ]
  | 商品信息 | 渠道 | 售价 | 底价 | 成本 | 利润率 | 模式 | 库存 | 状态 | 操作 |

[ 分页器 ]
```

### 6.3 出价编辑弹窗/页面

```
[ 商品信息卡片 ]
  - 图片、名称、款号、尺码

[ 价格设置 ]
  - 售价 *（必填）
  - 划线价（选填）
  - 底价（全托管必填）
  - 成本价（只读，来自库存）
  - 预估利润率（自动计算）

[ 出价模式 ]
  - 自主出价
  - 全托管（选中后显示提示：开启后将无法手动修改价格和上架状态）

[ 库存分配 ]
  - 共享模式（显示可用库存）
  - 专属模式（输入分配数量）

[ 价格预警 ]
  - 低于成本价警告（黄色提示）
  - 低于底价错误（红色，阻止提交）

[ 操作按钮 ]
  - 保存
  - 保存并上架
  - 取消
```

### 6.4 国际化 Key

```typescript
// i18n/locales/zh.ts
pricing: {
  title: '出价管理',
  createPricing: '创建出价',
  editPricing: '编辑出价',
  batchUpdate: '批量更新',

  // 字段
  price: '售价',
  compareAtPrice: '划线价',
  floorPrice: '底价',
  costPrice: '成本价',
  profitRate: '利润率',
  pricingMode: '出价模式',

  // 出价模式
  selfPricing: '自主出价',
  autoPricing: '全托管',
  autoPricingTip: '开启后将无法手动修改价格和上架状态，平台将自动调价',

  // 预警
  warningBelowCost: '当前售价低于成本价，可能产生亏损',
  errorBelowFloor: '售价不能低于底价',

  // 状态
  draft: '草稿',
  active: '已上架',
  paused: '已暂停',

  // 操作
  activate: '上架',
  pause: '暂停',
  viewHistory: '查看历史',

  // 历史
  priceHistory: '价格历史',
  changeType: '变更类型',
  changeManual: '手动修改',
  changeAutoPricing: '系统调价',
  changeBatch: '批量操作',
}
```

## 7. 实现步骤

### Phase 1：基础出价功能

1. **后端**
   - [ ] 扩展 `InventoryListing` 实体（新增 floorPrice, pricingMode, autoPricingLockedAt）
   - [ ] 创建 `PriceChangeLog` 实体
   - [ ] 更新数据库 schema（doc/inventory_listings.sql, doc/price_change_logs.sql）
   - [ ] 创建 DTO（CreatePricingDto, UpdatePricingDto, BatchUpdatePricingDto）
   - [ ] 实现 `PricingService`
   - [ ] 创建 `MerchantPricingController`

2. **前端**
   - [ ] 创建出价管理列表页 `/merchant/pricing`
   - [ ] 创建出价编辑弹窗/页面
   - [ ] 添加 API 客户端方法
   - [ ] 添加国际化文本

### Phase 2：批量操作与历史

1. **后端**
   - [ ] 实现批量更新 API
   - [ ] 实现价格历史查询 API

2. **前端**
   - [ ] 实现批量选择和操作
   - [ ] 创建价格历史页面/弹窗

### Phase 3：全托管模式（后续迭代）

1. **后端**
   - [ ] 实现 `AutoPricingService`
   - [ ] 添加自动调价定时任务
   - [ ] 实现调价规则引擎

2. **前端**
   - [ ] 全托管模式 UI 优化
   - [ ] 调价规则配置界面（如需要）

## 8. 注意事项

1. **权限控制**
   - 全托管模式下，商户无法修改 price、status
   - 后端必须校验 pricingMode，拒绝非法操作

2. **数据一致性**
   - 价格变更必须记录日志
   - 切换模式时需要验证底价

3. **性能考虑**
   - 批量操作限制每次最多 100 条
   - 价格历史查询需要分页

4. **国际化**
   - 所有前端文本使用 i18n key
   - 后端错误信息返回 key，前端翻译