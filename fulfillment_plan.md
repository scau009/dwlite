# 履约单生成与商户分配流程实现计划

## 需求概述

平台订单确认后触发履约单生成流程：
1. 根据 platform_rules 规则引擎确定将履约订单分发给哪个商户
2. 商户可以看到自己的履约单
3. 送仓寄售（consignment）：商户无操作，平台自动履约
4. 自履约（self_fulfillment）：商户需要自己发货
5. 自履约商户可以拒绝，系统自动分配给下一个商户
6. 遍历所有可用商户后仍无法履约，创建异常工单
7. 自履约响应时间：24小时，超时自动拒绝

---

## 流程图

```
订单确认 (Order.status: pending -> allocating)
          │
          ▼
┌─────────────────────────────────┐
│  AllocateOrderMessage (异步)    │
└─────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────┐
│  FulfillmentAllocationService   │
│  ┌───────────────────────────┐  │
│  │ 1. 获取所有有库存的来源   │  │
│  │    ChannelProductSource   │  │
│  │ 2. 应用 platform_rules    │  │
│  │    计算商户优先级评分     │  │
│  │ 3. 按评分排序选择商户     │  │
│  │ 4. 锁定库存创建履约单     │  │
│  └───────────────────────────┘  │
└─────────────────────────────────┘
          │
          ├── 成功 ─────────────────────┐
          │   Order: allocated          │
          │   Fulfillment: pending      │
          │                             │
          │   寄售模式: 直接创建出库单  │
          │   自履约: 等待商户响应      │
          │   (24小时超时自动拒绝)      │
          └─────────────────────────────┘
          │
          └── 失败 ─────────────────────┐
              Order: allocation_failed  │
              创建 OrderException       │
              (no_merchant_available)   │
              └─────────────────────────┘

自履约拒绝流程:
┌─────────────────────────────────┐
│  商户拒绝履约 / 超时自动拒绝   │
│  Fulfillment: rejected          │
└─────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────┐
│  ReallocationMessage (异步)     │
│  - 释放已锁定库存              │
│  - 将该商户加入排除列表        │
│  - 重新执行分配                │
└─────────────────────────────────┘
          │
          ├── 还有可用商户 ─────────────┐
          │   创建新的履约单            │
          │   分配给下一个商户          │
          └─────────────────────────────┘
          │
          └── 所有商户已尝试 ───────────┐
              创建 OrderException       │
              (allocation_exhausted)    │
              └─────────────────────────┘
```

---

## 核心实现

### 1. 商户选择算法

**流程**：
1. 根据 OrderItem.channelProduct 获取所有活跃的 ChannelProductSource
2. 过滤已被排除的商户和库存不足的来源
3. 应用 `platform_rules`（type=fulfillment_allocation）计算每个来源的评分
4. 按评分排序，选择最优来源
5. 验证价格：商户价格 + 平台佣金 <= 平台售价

**规则引擎上下文变量**：
```php
$context = [
    'source' => [
        'merchantId' => string,
        'priority' => int,           // ChannelProductSource.priority
        'price' => string,           // 商户报价
        'availableQuantity' => int,
        'soldQuantity' => int,
    ],
    'product' => [
        'platformPrice' => string,
        'skuCode' => string,
        'categorySlug' => string,
    ],
    'fulfillmentType' => string,     // consignment|self_fulfillment
    'order' => [
        'totalAmount' => string,
        'itemCount' => int,
    ],
];
```

### 2. 平台规则扩展

**新增规则类型**：
- `TYPE_FULFILLMENT_ALLOCATION = 'fulfillment_allocation'`

**新增规则分类**：
- `CATEGORY_ALLOCATION_SCORE = 'allocation_score'` - 分配评分规则

**示例规则表达式**：
```
// 基础评分：priority 越小分数越高
expression: "100 - source['priority']"

// 加入价格因素：价格越低分数越高
expression: "(100 - source['priority']) + (product['platformPrice'] - source['price']) * 10"

// 优先自履约商户
expression: "fulfillmentType == 'self_fulfillment' ? (100 - source['priority']) * 1.2 : (100 - source['priority'])"
```

---

## 文件变更清单

### 实体修改

**1. Fulfillment.php** - 新增字段和状态
```
路径: backend/src/Entity/Fulfillment.php

新增状态:
- STATUS_REJECTED = 'rejected'  // 商户拒绝
- STATUS_EXPIRED = 'expired'    // 超时未响应

新增字段:
- allocationSource: string      // 'auto'|'manual'
- allocationAttempt: int        // 当前是第几次分配尝试
- rejectedAt: DateTimeImmutable
- rejectionReason: string
- deadlineAt: DateTimeImmutable // 响应截止时间
- excludedMerchantIds: json     // 已排除的商户ID列表
```

**2. FulfillmentItem.php** - 新增关联
```
路径: backend/src/Entity/FulfillmentItem.php

新增字段:
- channelProductSource: ChannelProductSource
- inventoryListing: InventoryListing
- merchantInventory: MerchantInventory
```

**3. PlatformRule.php** - 新增类型常量
```
路径: backend/src/Entity/PlatformRule.php

新增:
- TYPE_FULFILLMENT_ALLOCATION = 'fulfillment_allocation'
- CATEGORY_ALLOCATION_SCORE = 'allocation_score'
```

**4. OrderException.php** - 新增类型
```
路径: backend/src/Entity/OrderException.php

新增:
- TYPE_NO_MERCHANT_AVAILABLE = 'no_merchant_available'
- TYPE_ALLOCATION_EXHAUSTED = 'allocation_exhausted'
```

### 新增实体

**5. FulfillmentAllocationLog.php** - 分配日志
```
路径: backend/src/Entity/FulfillmentAllocationLog.php

字段:
- id: ULID
- order: Order
- orderItem: OrderItem
- attemptNumber: int
- selectedMerchantId: string|null
- selectedSourceId: string|null
- result: string (success|no_stock|price_invalid|rejected|expired)
- candidateSources: json    // 所有候选来源及评分
- failureReason: string|null
- createdAt: DateTimeImmutable
```

### 新增服务

**6. FulfillmentAllocationService.php** - 核心服务
```
路径: backend/src/Service/Fulfillment/FulfillmentAllocationService.php

方法:
- allocateOrder(Order, array $excludedMerchantIds): AllocationResult
- selectSourcesForOrder(Order, array $excludedMerchantIds): array
- evaluateSourceScore(ChannelProductSource, OrderItem): float
- createFulfillmentsForOrder(Order, array $itemAllocations): array
- handleRejection(Fulfillment, string $reason): void
- handleExpiration(Fulfillment): void
```

**7. FulfillmentService.php** - 履约单业务服务
```
路径: backend/src/Service/Fulfillment/FulfillmentService.php

方法:
- getMerchantFulfillments(Merchant, array $filters): array
- acceptFulfillment(Fulfillment, User): void
- rejectFulfillment(Fulfillment, string $reason, User): void
- shipFulfillment(Fulfillment, ShipRequest): void
- getAdminFulfillments(array $filters): array
- manualReassign(Fulfillment, Merchant, string $reason): void
```

### 新增消息和处理器

**8. AllocateOrderMessage.php**
```
路径: backend/src/Message/AllocateOrderMessage.php

属性:
- orderId: string
- excludedMerchantIds: array
- attemptNumber: int
```

**9. AllocateOrderMessageHandler.php**
```
路径: backend/src/MessageHandler/AllocateOrderMessageHandler.php
```

**10. FulfillmentRejectionMessage.php**
```
路径: backend/src/Message/FulfillmentRejectionMessage.php

属性:
- fulfillmentId: string
- reason: string
- rejectedBy: string|null (商户手动拒绝时)
```

**11. FulfillmentRejectionMessageHandler.php**
```
路径: backend/src/MessageHandler/FulfillmentRejectionMessageHandler.php
```

**12. CheckFulfillmentDeadlinesMessage.php**
```
路径: backend/src/Message/CheckFulfillmentDeadlinesMessage.php
```

**13. CheckFulfillmentDeadlinesMessageHandler.php**
```
路径: backend/src/MessageHandler/CheckFulfillmentDeadlinesMessageHandler.php
```

### 新增 DTO

**14. AllocationResult.php**
```
路径: backend/src/Service/Fulfillment/Dto/AllocationResult.php
```

**15. SourceSelectionResult.php**
```
路径: backend/src/Service/Fulfillment/Dto/SourceSelectionResult.php
```

**16. RejectFulfillmentRequest.php**
```
路径: backend/src/Dto/Merchant/RejectFulfillmentRequest.php
```

**17. ShipFulfillmentRequest.php**
```
路径: backend/src/Dto/Merchant/ShipFulfillmentRequest.php
```

**18. FulfillmentListQuery.php**
```
路径: backend/src/Dto/Merchant/Query/FulfillmentListQuery.php
```

**19. AdminFulfillmentListQuery.php**
```
路径: backend/src/Dto/Admin/Query/AdminFulfillmentListQuery.php
```

### 新增控制器

**20. MerchantFulfillmentController.php** - 商户端 API
```
路径: backend/src/Controller/MerchantFulfillmentController.php

路由:
- GET  /api/fulfillments              # 获取履约单列表
- GET  /api/fulfillments/{id}         # 获取履约单详情
- POST /api/fulfillments/{id}/accept  # 接受履约（自履约）
- POST /api/fulfillments/{id}/reject  # 拒绝履约（自履约）
- POST /api/fulfillments/{id}/ship    # 发货（自履约）
```

**21. Admin/FulfillmentController.php** - 管理端 API
```
路径: backend/src/Controller/Admin/FulfillmentController.php

路由:
- GET  /api/admin/fulfillments              # 获取履约单列表
- GET  /api/admin/fulfillments/{id}         # 获取履约单详情
- POST /api/admin/fulfillments/{id}/reassign # 手动重新分配
- POST /api/admin/fulfillments/{id}/cancel  # 取消履约单
```

### 新增 Repository 方法

**22. FulfillmentRepository.php** - 新增方法
```
路径: backend/src/Repository/FulfillmentRepository.php

新增方法:
- findByMerchantPaginated(Merchant, array $filters): array
- findPendingExpired(DateTimeImmutable $before): array
- countByMerchantAndStatus(Merchant, string $status): int
- findActiveByOrder(Order): array
```

**23. ChannelProductSourceRepository.php** - 新增方法
```
路径: backend/src/Repository/ChannelProductSourceRepository.php

新增方法:
- findAvailableSourcesExcluding(ChannelProduct, array $excludedMerchantIds): array
```

### 数据库变更

**24. doc/fulfillments.sql** - 更新
```sql
ALTER TABLE `fulfillments`
    ADD COLUMN `allocation_source` VARCHAR(20) NULL AFTER `status`,
    ADD COLUMN `allocation_attempt` INT NOT NULL DEFAULT 1 AFTER `allocation_source`,
    ADD COLUMN `rejected_at` DATETIME NULL AFTER `cancelled_at`,
    ADD COLUMN `rejection_reason` TEXT NULL AFTER `rejected_at`,
    ADD COLUMN `deadline_at` DATETIME NULL AFTER `rejection_reason`,
    ADD COLUMN `excluded_merchant_ids` JSON NULL AFTER `deadline_at`;
```

**25. doc/fulfillment_items.sql** - 更新
```sql
ALTER TABLE `fulfillment_items`
    ADD COLUMN `channel_product_source_id` VARCHAR(26) NULL,
    ADD COLUMN `inventory_listing_id` VARCHAR(26) NULL,
    ADD COLUMN `merchant_inventory_id` VARCHAR(26) NULL;
```

**26. doc/fulfillment_allocation_logs.sql** - 新增
```sql
CREATE TABLE `fulfillment_allocation_logs` (...);
```

**27. doc/platform_rules.sql** - 文档更新
```sql
-- 新增 type: fulfillment_allocation
-- 新增 category: allocation_score
```

### 定时任务

**28. Scheduler/MainSchedule.php** - 新增定时任务
```php
// 每15分钟检查超时履约单
RecurringMessage::every('15 minutes', new CheckFulfillmentDeadlinesMessage())
```

### 现有文件修改

**29. OrderSyncService.php** - 集成
```
路径: backend/src/Service/OrderSyncService.php

修改 confirmOrder() 方法:
- 确认成功后派发 AllocateOrderMessage
```

**30. RuleEngineService.php** - 扩展上下文
```
路径: backend/src/Service/RuleEngine/RuleEngineService.php

新增方法:
- evaluateFulfillmentAllocation(array $rules, array $context): float
```

---

## 前端变更 (可选，后续实现)

### 商户端页面
- `frontend/src/pages/fulfillment/list.tsx` - 履约单列表
- `frontend/src/pages/fulfillment/detail.tsx` - 履约单详情
- `frontend/src/lib/fulfillment-api.ts` - API 封装

### 管理端页面
- `frontend/src/pages/admin/fulfillment/list.tsx` - 管理端履约单列表
- `frontend/src/pages/admin/fulfillment/detail.tsx` - 管理端履约单详情

---

## 实现顺序

### 阶段 1：核心实体与数据库
1. 修改 `Fulfillment.php` 添加新字段和状态
2. 修改 `FulfillmentItem.php` 添加来源关联
3. 修改 `PlatformRule.php` 添加新类型
4. 修改 `OrderException.php` 添加新类型
5. 创建 `FulfillmentAllocationLog.php`
6. 更新所有 SQL 文件

### 阶段 2：服务层
1. 创建 DTO 类
2. 实现 `FulfillmentAllocationService`
3. 实现 `FulfillmentService`
4. 扩展 `RuleEngineService`
5. 更新 Repository 类

### 阶段 3：异步消息
1. 创建 `AllocateOrderMessage` 和 Handler
2. 创建 `FulfillmentRejectionMessage` 和 Handler
3. 创建 `CheckFulfillmentDeadlinesMessage` 和 Handler
4. 修改 `OrderSyncService` 集成

### 阶段 4：API 层
1. 实现 `MerchantFulfillmentController`
2. 实现 `Admin/FulfillmentController`
3. 添加 i18n 翻译

### 阶段 5：测试
1. 单元测试
2. 集成测试
3. 端到端测试

---

## 验证方案

1. **创建测试数据**：
   - 创建一个有多个商户供货来源的渠道商品
   - 创建 platform_rules 分配规则

2. **模拟订单确认**：
   - 创建订单并触发确认
   - 验证履约单正确创建
   - 验证商户选择符合规则

3. **测试拒绝流程**：
   - 商户拒绝自履约订单
   - 验证自动分配给下一个商户
   - 验证库存正确释放和重新锁定

4. **测试超时流程**：
   - 模拟超过24小时未响应
   - 验证自动标记为拒绝并重新分配

5. **测试异常情况**：
   - 所有商户库存不足
   - 所有商户都拒绝
   - 验证 OrderException 正确创建
