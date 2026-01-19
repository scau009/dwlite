# DWLite 业务流程测试文档

本文档覆盖 DWLite 球鞋供应链平台的所有核心业务流程，明确各角色的交互流程和需要关注的数据。

---

## 第一部分：系统角色与权限

### 1.1 平台管理员 (Admin)

**职责**：

- 管理商品基础数据（品牌、分类、标签、SPU/SKU）
- 审核商户入驻申请
- 管理仓库和销售渠道
- 查看全局数据和报表
- 处理系统异常

**权限范围**：

| 模块 | 权限 |
|------|------|
| 商品管理 | 创建、编辑、删除品牌/分类/标签/商品 |
| 商户管理 | 审核、禁用商户；查看商户列表 |
| 仓库管理 | 创建、编辑仓库；配置仓库与渠道关系 |
| 渠道管理 | 创建、配置销售渠道 |
| 订单管理 | 查看所有订单；处理订单异常 |
| 结算管理 | 查看结算报表；审核提现申请 |

**认证方式**：JWT Token（`ROLE_ADMIN`）

### 1.2 商户 (Merchant)

**职责**：

- 管理自己的库存（送仓、定价、上架）
- 处理履约分配（自履约模式）
- 管理渠道对接设置
- 查看订单和结算数据

**权限范围**：

| 模块 | 权限 |
|------|------|
| 库存管理 | 创建入库单；查看自己的库存 |
| 上架管理 | 创建/编辑 Listing；设置价格策略 |
| 履约管理 | 接受/拒绝履约分配（自履约模式） |
| 订单管理 | 查看与自己相关的订单 |
| 结算管理 | 查看结算明细；申请提现 |
| 设置 | 管理 Webhook、API Key |

**认证方式**：JWT Token（`ROLE_MERCHANT`）

**数据隔离**：商户只能访问与自己 `merchant_id` 关联的数据

### 1.3 仓库人员 (Warehouse)

**职责**：

- 入库作业（收货、清点、上架）
- 出库作业（拣货、打包、发货）
- 库存盘点

**权限范围**：

| 模块 | 权限 |
|------|------|
| 入库作业 | 确认到货、清点收货、处理异常 |
| 出库作业 | 拣货、打包、确认发货 |
| 库存盘点 | 查询库存、执行盘点 |

**认证方式**：JWT Token（`ROLE_WAREHOUSE`）

**数据隔离**：仓库人员只能操作自己所属仓库的数据

### 1.4 外部系统 (OpenAPI)

**接入方式**：API Key 认证

**权限控制**：

```
Header: X-API-Key: <api_key>
```

**权限粒度**：

| 权限标识 | 说明 |
|----------|------|
| `fulfillment:read` | 读取履约单 |
| `fulfillment:write` | 操作履约单（接单、发货） |
| `inbound:read` | 读取入库单 |
| `inbound:write` | 操作入库单 |
| `inventory:read` | 读取库存 |
| `listing:read` | 读取上架商品 |
| `listing:write` | 操作上架商品 |
| `settlement:read` | 读取结算数据 |

---

## 第二部分：入库流程测试

### 2.1 入库单状态流转

```
draft → pending → shipped → arrived → receiving → completed
                                  ↓              ↘ partial_completed
                                  ↓
                              cancelled
```

| 状态 | 说明 | 触发条件 |
|------|------|----------|
| `draft` | 草稿 | 商户创建入库单 |
| `pending` | 待发货 | 商户提交入库单 |
| `shipped` | 已发货 | 商户填写物流信息 |
| `arrived` | 已到达 | 仓库确认到货 |
| `receiving` | 收货中 | 仓库开始清点 |
| `completed` | 已完成 | 全部商品入库完成（数量一致） |
| `partial_completed` | 部分完成 | 有数量差异但已完成处理 |
| `cancelled` | 已取消 | 商户或系统取消 |

### 2.2 正常流程测试用例

#### TC-IB-001: 完整入库流程（数量一致）

**前置条件**：
- 商户已审核通过（status = approved）
- 目标仓库状态正常（status = active）
- 商品 SKU 已存在

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1 | Merchant | 创建入库单（draft） | 返回入库单号 `IB{YYYYMMDD}{SEQ}` |
| 2 | Merchant | 添加入库明细（SKU + 数量 + 成本） | 明细项状态 = `pending` |
| 3 | Merchant | 提交入库单 | 状态变更 `draft → pending` |
| 4 | Merchant | 填写物流信息并标记发货 | 状态变更 `pending → shipped` |
| 5 | Warehouse | 确认到货 | 状态变更 `shipped → arrived` |
| 6 | Warehouse | 开始收货清点 | 状态变更 `arrived → receiving` |
| 7 | Warehouse | 提交收货结果（实收=预报） | 状态变更 `receiving → completed` |

**关键验证点**：

```php
// 1. 入库单状态
$inboundOrder->getStatus() === 'completed';

// 2. 明细项状态
foreach ($inboundOrder->getItems() as $item) {
    $item->getStatus() === 'received';
    $item->getReceivedQuantity() === $item->getExpectedQuantity();
}

// 3. 库存变化
$inventory = $merchantInventoryRepo->findByMerchantWarehouseSku($merchant, $warehouse, $sku);
// 可用库存增加
$inventory->getQuantityAvailable() === $previousAvailable + $receivedQuantity;

// 4. 库存交易记录
$transaction = $inventoryTransactionRepo->findByInboundItem($item);
$transaction->getType() === 'inbound';
$transaction->getQuantityChange() === $receivedQuantity;

// 5. 成本更新（加权平均）
$newAverageCost = ($oldAvailable * $oldCost + $received * $itemCost) / ($oldAvailable + $received);
```

#### TC-IB-002: 入库单取消

**前置条件**：入库单状态为 `draft`、`pending` 或 `shipped`

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1 | Merchant | 取消入库单 | 状态变更为 `cancelled` |

**关键验证点**：

```php
$inboundOrder->getStatus() === 'cancelled';
$inboundOrder->getCancelledAt() !== null;
$inboundOrder->getCancelReason() !== null;
// 库存无变化
```

### 2.3 异常处理测试用例

#### TC-IB-101: 数量不足（短装）

**测试场景**：预报 10 件，实收 8 件

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1-6 | - | 同 TC-IB-001 步骤 1-6 | - |
| 7 | Warehouse | 提交收货（实收 8 件） | 创建入库异常 |
| 8 | Warehouse | 处理异常（确认短装） | 异常状态 = `resolved` |
| 9 | - | 系统完成入库 | 状态 = `partial_completed` |

**关键验证点**：

```php
// 1. 明细项状态
$item->getStatus() === 'partial';
$item->getReceivedQuantity() === 8;
$item->getExpectedQuantity() === 10;

// 2. 异常记录
$exception = $inboundExceptionRepo->findByItem($item);
$exception->getType() === 'quantity_short';
$exception->getExpectedQuantity() === 10;
$exception->getActualQuantity() === 8;
$exception->getStatus() === 'resolved';

// 3. 库存只入账实收数量
$inventory->getQuantityAvailable() === $previous + 8;
```

#### TC-IB-102: 货损处理

**测试场景**：预报 10 件，实收 10 件但 2 件损坏

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1-6 | - | 同 TC-IB-001 步骤 1-6 | - |
| 7 | Warehouse | 提交收货（10 件，2 件损坏） | 创建货损异常 |
| 8 | Warehouse | 上传货损照片 | 异常附件更新 |
| 9 | Warehouse | 处理异常 | 异常状态 = `resolved` |

**关键验证点**：

```php
// 1. 可用库存
$inventory->getQuantityAvailable() === $previous + 8;

// 2. 损坏库存
$inventory->getQuantityDamaged() === $previousDamaged + 2;

// 3. 异常记录
$exception->getType() === 'damaged';
$exception->getQuantityDamaged() === 2;
```

#### TC-IB-103: 超量入库

**测试场景**：预报 10 件，实收 12 件

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1-6 | - | 同 TC-IB-001 步骤 1-6 | - |
| 7 | Warehouse | 提交收货（实收 12 件） | 创建超量异常 |
| 8 | Warehouse | 确认超量（商户补录） | 异常状态 = `resolved` |

**关键验证点**：

```php
$item->getStatus() === 'over';
$item->getReceivedQuantity() === 12;
$exception->getType() === 'quantity_over';
// 库存入账全部实收
$inventory->getQuantityAvailable() === $previous + 12;
```

---

## 第三部分：订单同步流程测试

### 3.1 订单状态流转

```
pending → allocating → allocated → fulfilling → shipped → delivered → completed
              ↓
       allocation_failed
              ↓
          cancelled
```

| 状态 | 说明 |
|------|------|
| `pending` | 待处理（刚同步） |
| `allocating` | 分配中 |
| `allocated` | 已分配（库存分配成功） |
| `allocation_failed` | 分配失败（库存不足） |
| `fulfilling` | 履约中 |
| `shipped` | 已发货 |
| `delivered` | 已签收 |
| `completed` | 已完成 |
| `cancelled` | 已取消 |

### 3.2 订单同步正常流程

#### TC-OS-001: 订单拉取与创建

**前置条件**：
- 销售渠道已配置且状态正常
- 渠道 API 凭证有效

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | Scheduler | 触发 `PullOrdersMessage` | 消息入队 |
| 2 | Worker | 处理消息，调用渠道 API | 获取新订单列表 |
| 3 | Worker | 创建订单记录 | 订单状态 = `pending` |
| 4 | Worker | 记录同步日志 | `OrderSyncLog.status = success` |

**关键验证点**：

```php
// 1. 订单创建
$order = $orderRepo->findByExternalOrderId($externalId);
$order !== null;
$order->getStatus() === 'pending';
$order->getOrderNo() matches '/^ORD\d{8}\d{6}$/';

// 2. 订单明细
foreach ($order->getItems() as $item) {
    $item->getProductSku() !== null;
    $item->getQuantity() > 0;
    $item->getUnitPrice() > 0;
}

// 3. 同步状态
$syncState = $order->getSalesChannel()->getSyncState();
$syncState->getLastSyncedAt() !== null;
$syncState->getLastOrderId() === $externalId;
```

#### TC-OS-002: 订单库存分配

**前置条件**：
- 订单状态为 `pending`
- 相关 SKU 有足够库存

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | System | 触发 `AllocateOrderMessage` | 开始分配 |
| 2 | Worker | 订单状态变更 | `pending → allocating` |
| 3 | Worker | 查找匹配的 ChannelProduct | 获取可用库存源 |
| 4 | Worker | 按优先级分配库存 | 创建 Fulfillment |
| 5 | Worker | 订单状态变更 | `allocating → allocated` |

**关键验证点**：

```php
// 1. 订单状态
$order->getStatus() === 'allocated';

// 2. 履约单创建
$fulfillments = $order->getFulfillments();
count($fulfillments) >= 1;

// 3. 库存预留
foreach ($fulfillments as $fulfillment) {
    foreach ($fulfillment->getItems() as $item) {
        $reservation = $item->getInventoryReservation();
        $reservation->getStatus() === 'allocated';
    }
}

// 4. 库存数量变化
// quantityPendingReserve 增加（软锁定）
$inventory->getQuantityPendingReserve() >= $allocatedQuantity;
```

#### TC-OS-003: 订单确认

**前置条件**：
- 订单状态为 `allocated`
- 渠道支持订单确认

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | System | 触发 `ConfirmOrderMessage` | 开始确认 |
| 2 | Worker | 调用渠道 API 确认订单 | API 返回成功 |
| 3 | Worker | 更新订单确认状态 | `confirmedAt` 更新 |
| 4 | Worker | 库存预留转为锁定 | `reserved → locked` |

**关键验证点**：

```php
// 1. 订单确认时间
$order->getConfirmedAt() !== null;

// 2. 库存状态变化
// quantityPendingReserve 减少
// quantityReserved 增加
$inventory->getQuantityReserved() >= $confirmedQuantity;
```

### 3.3 订单同步异常处理

#### TC-OS-101: 库存不足导致分配失败

**测试场景**：订单需要 5 件，但库存只有 3 件

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1-2 | - | 同 TC-OS-002 步骤 1-2 | - |
| 3 | Worker | 库存检查失败 | 创建订单异常 |
| 4 | Worker | 订单状态变更 | `allocation_failed` |

**关键验证点**：

```php
$order->getStatus() === 'allocation_failed';

$exception = $orderExceptionRepo->findByOrder($order);
$exception->getType() === 'allocation_failed';
$exception->getStatus() === 'pending';
```

#### TC-OS-102: 渠道 API 调用失败（重试机制）

**测试场景**：渠道 API 返回 500 错误

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | Worker | 调用渠道 API | 返回 500 错误 |
| 2 | Messenger | 标记消息失败 | 进入重试队列 |
| 3 | Messenger | 第 1 次重试（延迟 1s） | 重新投递 |
| 4 | Messenger | 第 2 次重试（延迟 4s） | 重新投递 |
| 5 | Messenger | 第 3 次重试（延迟 16s） | 重新投递 |
| 6 | Messenger | 超过最大重试次数 | 进入失败队列 |

**关键验证点**：

```php
// 同步日志记录所有尝试
$logs = $orderSyncLogRepo->findByOrder($order);
count($logs) === 4; // 1 initial + 3 retries

foreach ($logs as $log) {
    $log->getErrorMessage() !== null;
}

// 最后一条日志状态
$lastLog->getStatus() === 'failed';
```

---

## 第四部分：履约分配流程测试

### 4.1 履约单状态流转

```
pending → processing → shipped → delivered → completed
   ↓           ↓
rejected   cancelled
   ↓
expired
```

| 状态 | 说明 |
|------|------|
| `pending` | 待处理 |
| `processing` | 处理中（平台仓：出库作业中；商家仓：已通知商家） |
| `shipped` | 已发货 |
| `delivered` | 已签收 |
| `completed` | 已完成 |
| `cancelled` | 已取消 |
| `rejected` | 商户拒绝（仅限自履约） |
| `expired` | 超时未响应 |

### 4.2 履约类型

| 类型 | 说明 | 发货方 |
|------|------|--------|
| `platform_warehouse` | 平台仓发货 | 仓库人员 |
| `merchant_warehouse` | 商家自有仓发货 | 商户 |

### 4.3 平台仓履约流程

#### TC-FF-001: 平台仓完整履约

**前置条件**：
- 订单已分配（status = allocated）
- 履约类型 = `platform_warehouse`
- 仓库有足够库存

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1 | System | 创建履约单 | 状态 = `pending` |
| 2 | System | 创建出库单 | OutboundOrder 状态 = `pending` |
| 3 | Warehouse | 开始拣货 | 出库单状态 = `picking` |
| 4 | Warehouse | 完成拣货 | 明细项标记已拣 |
| 5 | Warehouse | 开始打包 | 出库单状态 = `packing` |
| 6 | Warehouse | 完成打包 | 出库单状态 = `ready` |
| 7 | Warehouse | 填写物流信息并发货 | 出库单状态 = `shipped` |
| 8 | System | 更新履约单状态 | 履约单状态 = `shipped` |
| 9 | System | 推送发货信息到渠道 | 渠道 API 调用成功 |
| 10 | System | 更新订单状态 | 订单状态 = `shipped` |

**关键验证点**：

```php
// 1. 履约单状态
$fulfillment->getStatus() === 'shipped';
$fulfillment->getTrackingNumber() !== null;
$fulfillment->getShippingCarrier() !== null;
$fulfillment->getShippedAt() !== null;

// 2. 出库单状态
$outboundOrder = $fulfillment->getOutboundOrder();
$outboundOrder->getStatus() === 'shipped';

// 3. 库存变化
// quantityReserved 减少
// quantityAvailable 不变（之前已锁定）
$inventory->getQuantityReserved() === $previousReserved - $shippedQuantity;

// 4. 库存交易记录
$transaction = $inventoryTransactionRepo->findByOutboundItem($item);
$transaction->getType() === 'outbound';
$transaction->getQuantityChange() === -$shippedQuantity;

// 5. 预留状态
$reservation->getStatus() === 'completed';

// 6. 订单状态
$order->getStatus() === 'shipped';
```

### 4.4 商户自履约流程

#### TC-FF-002: 商户接单并发货

**前置条件**：
- 订单已分配（status = allocated）
- 履约类型 = `merchant_warehouse`
- 商户有足够库存

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1 | System | 创建履约单并通知商户 | 状态 = `pending` |
| 2 | Merchant | 接受履约分配 | 状态 = `processing` |
| 3 | Merchant | 填写物流信息并发货 | 状态 = `shipped` |
| 4 | System | 推送发货信息到渠道 | 渠道 API 调用成功 |

**关键验证点**：

```php
// 1. 履约单状态
$fulfillment->getStatus() === 'shipped';
$fulfillment->getAcceptedAt() !== null;
$fulfillment->getShippedAt() !== null;

// 2. 库存变化（商户自己的库存）
$inventory->getQuantityReserved() === $previousReserved - $shippedQuantity;
```

#### TC-FF-003: 商户拒单

**前置条件**：
- 履约单状态 = `pending`
- 履约类型 = `merchant_warehouse`

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1 | Merchant | 拒绝履约分配 | 状态 = `rejected` |
| 2 | System | 释放库存预留 | 预留状态 = `released` |
| 3 | System | 触发重新分配 | 新建 `AllocateOrderMessage` |

**关键验证点**：

```php
// 1. 履约单状态
$fulfillment->getStatus() === 'rejected';
$fulfillment->getRejectedAt() !== null;
$fulfillment->getRejectedReason() !== null;

// 2. 库存释放
$reservation->getStatus() === 'released';
$inventory->getQuantityPendingReserve() === $previous - $allocatedQuantity;

// 3. 订单需要重新分配
$order->getStatus() === 'pending'; // 或 'allocating'
```

#### TC-FF-004: 超时未响应

**前置条件**：
- 履约单状态 = `pending`
- 履约类型 = `merchant_warehouse`
- 超过响应时限（如 24 小时）

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | Scheduler | 检测超时履约单 | 发现超时履约单 |
| 2 | System | 标记履约单超时 | 状态 = `expired` |
| 3 | System | 释放库存预留 | 预留状态 = `released` |
| 4 | System | 触发重新分配 | 新建 `AllocateOrderMessage` |

**关键验证点**：

```php
$fulfillment->getStatus() === 'expired';
$fulfillment->getExpiredAt() !== null;
$reservation->getStatus() === 'released';
```

### 4.5 多源分配场景

#### TC-FF-005: 订单拆分多个履约单

**测试场景**：订单需要 2 个 SKU，分别来自不同仓库

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | System | 分配库存 | 创建 2 个履约单 |
| 2 | Warehouse A | 发货履约单 1 | 履约单 1 状态 = `shipped` |
| 3 | Warehouse B | 发货履约单 2 | 履约单 2 状态 = `shipped` |
| 4 | System | 检查订单状态 | 所有履约完成后订单 = `shipped` |

**关键验证点**：

```php
// 订单有多个履约单
$fulfillments = $order->getFulfillments();
count($fulfillments) === 2;

// 每个履约单关联不同仓库
$warehouses = array_unique(array_map(
    fn($f) => $f->getWarehouse()->getId(),
    $fulfillments->toArray()
));
count($warehouses) === 2;

// 只有全部履约完成，订单才变为 shipped
$allShipped = true;
foreach ($fulfillments as $f) {
    if ($f->getStatus() !== 'shipped') {
        $allShipped = false;
    }
}
if ($allShipped) {
    $order->getStatus() === 'shipped';
}
```

---

## 第五部分：结算流程测试

### 5.1 结算单状态

| 状态 | 说明 |
|------|------|
| `pending` | 待结算（T+N 期间） |
| `settled` | 已结算（已入账到钱包） |
| `cancelled` | 已取消（如订单退款） |

### 5.2 结算生成流程

#### TC-ST-001: 订单完成后生成结算单

**前置条件**：
- 订单状态变为 `completed`
- 商户已配置结算规则

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | System | 订单标记完成 | 触发 `CreateSettlementMessage` |
| 2 | Worker | 计算结算金额 | 创建 Settlement |
| 3 | Worker | 创建结算明细 | 创建 SettlementItem |

**关键验证点**：

```php
// 1. 结算单创建
$settlement = $settlementRepo->findByOrder($order);
$settlement !== null;
$settlement->getStatus() === 'pending';

// 2. 金额计算
$settlement->getOrderAmount() === $order->getTotalAmount();
$settlement->getCommissionAmount() === $order->getTotalAmount() * $commissionRate;
$settlement->getNetAmount() === $settlement->getOrderAmount() - $settlement->getCommissionAmount();

// 3. 结算周期
$settlement->getSettlementDate() === $completedAt->modify('+N days');
```

#### TC-ST-002: 定时结算入账

**前置条件**：
- 结算单状态 = `pending`
- 已到达结算日期

**测试步骤**：

| 步骤 | 触发方 | 操作 | 预期结果 |
|------|--------|------|----------|
| 1 | Scheduler | 触发 `ProcessSettlementMessage` | 开始处理 |
| 2 | Worker | 查找待结算单据 | 获取到期结算单 |
| 3 | Worker | 更新钱包余额 | 余额增加 |
| 4 | Worker | 创建钱包交易记录 | 记录入账 |
| 5 | Worker | 更新结算单状态 | 状态 = `settled` |

**关键验证点**：

```php
// 1. 结算单状态
$settlement->getStatus() === 'settled';
$settlement->getSettledAt() !== null;

// 2. 钱包余额
$wallet = $merchant->getWallet();
$wallet->getBalance() === $previousBalance + $settlement->getNetAmount();

// 3. 钱包交易记录
$transaction = $walletTransactionRepo->findBySettlement($settlement);
$transaction->getType() === 'settlement_credit';
$transaction->getAmount() === $settlement->getNetAmount();
$transaction->getBalanceAfter() === $wallet->getBalance();
```

### 5.3 对账验证

#### TC-ST-003: 对账报表验证

**测试场景**：验证一段时间内的结算数据准确性

**验证点**：

```php
// 1. 订单金额汇总
$orderTotal = $orderRepo->sumTotalAmountByPeriod($startDate, $endDate);

// 2. 结算金额汇总
$settlementTotal = $settlementRepo->sumOrderAmountByPeriod($startDate, $endDate);

// 3. 佣金汇总
$commissionTotal = $settlementRepo->sumCommissionByPeriod($startDate, $endDate);

// 4. 入账金额汇总
$creditTotal = $walletTransactionRepo->sumCreditByPeriod($startDate, $endDate);

// 验证等式
$orderTotal === $settlementTotal;
$settlementTotal - $commissionTotal === $creditTotal;
```

### 5.4 提现流程

#### TC-ST-004: 商户提现申请

**前置条件**：
- 商户钱包余额充足
- 商户已绑定银行账户

**测试步骤**：

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|----------|
| 1 | Merchant | 提交提现申请 | 创建 Payout（status = pending） |
| 2 | System | 冻结钱包余额 | 可用余额减少 |
| 3 | Admin | 审核通过 | 状态 = `approved` |
| 4 | System | 处理转账 | 状态 = `processing` |
| 5 | System | 确认到账 | 状态 = `completed` |

**关键验证点**：

```php
// 1. 提现申请
$payout->getStatus() === 'pending';
$payout->getAmount() > 0;
$payout->getBankAccount() !== null;

// 2. 余额冻结
$wallet->getBalance() === $previousBalance;
$wallet->getAvailableBalance() === $previousBalance - $payout->getAmount();

// 3. 完成后
$payout->getStatus() === 'completed';
$wallet->getBalance() === $previousBalance - $payout->getAmount();

// 4. 交易记录
$transaction = $walletTransactionRepo->findByPayout($payout);
$transaction->getType() === 'payout';
$transaction->getAmount() === -$payout->getAmount();
```

---

## 第六部分：端到端场景测试

### 6.1 进口业务完整流程

**业务场景**：海外货主将实物库存送入平台仓，通过国内渠道销售

```
┌─────────────────────────────────────────────────────────────────┐
│                        进口业务流程                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  海外货主          平台仓库          平台系统          国内渠道  │
│     │                 │                 │                 │     │
│     │  创建入库单     │                 │                 │     │
│     │─────────────────>                 │                 │     │
│     │                 │                 │                 │     │
│     │  发货           │                 │                 │     │
│     │─────────────────>                 │                 │     │
│     │                 │                 │                 │     │
│     │                 │  收货入库       │                 │     │
│     │                 │────────────────>│                 │     │
│     │                 │                 │                 │     │
│     │                 │                 │  上架商品       │     │
│     │                 │                 │─────────────────>     │
│     │                 │                 │                 │     │
│     │                 │                 │  同步库存       │     │
│     │                 │                 │─────────────────>     │
│     │                 │                 │                 │     │
│     │                 │                 │  <── 拉取订单   │     │
│     │                 │                 │<────────────────│     │
│     │                 │                 │                 │     │
│     │                 │  <── 创建出库单 │                 │     │
│     │                 │<────────────────│                 │     │
│     │                 │                 │                 │     │
│     │                 │  发货           │                 │     │
│     │                 │────────────────>│                 │     │
│     │                 │                 │                 │     │
│     │                 │                 │  推送物流       │     │
│     │                 │                 │─────────────────>     │
│     │                 │                 │                 │     │
│     │  结算入账       │                 │                 │     │
│     │<────────────────────────────────── │                 │     │
│     │                 │                 │                 │     │
└─────────────────────────────────────────────────────────────────┘
```

#### TC-E2E-001: 进口业务完整流程

**测试步骤**：

| 阶段 | 步骤 | 角色 | 操作 | 验证点 |
|------|------|------|------|--------|
| 入库 | 1 | Merchant | 创建入库单 | 入库单号生成 |
| 入库 | 2 | Merchant | 添加 SKU 明细（含成本价） | 明细项创建 |
| 入库 | 3 | Merchant | 提交并发货 | 状态 = shipped |
| 入库 | 4 | Warehouse | 收货确认 | 库存 +N |
| 上架 | 5 | Merchant | 创建 InventoryListing | Listing 创建 |
| 上架 | 6 | Merchant | 设置价格策略 | 价格配置 |
| 上架 | 7 | Merchant | 上架到渠道 | ChannelProduct 创建 |
| 同步 | 8 | System | 同步库存到渠道 | 渠道库存更新 |
| 订单 | 9 | Channel | 产生订单 | - |
| 订单 | 10 | System | 拉取订单 | 订单创建 |
| 订单 | 11 | System | 分配库存 | Fulfillment 创建 |
| 订单 | 12 | System | 确认订单 | 库存锁定 |
| 履约 | 13 | Warehouse | 拣货打包 | 出库单流转 |
| 履约 | 14 | Warehouse | 发货 | 状态 = shipped |
| 履约 | 15 | System | 推送物流到渠道 | API 调用成功 |
| 结算 | 16 | System | 订单完成 | 结算单创建 |
| 结算 | 17 | System | T+N 后入账 | 钱包余额增加 |

**完整验证清单**：

```php
// === 入库阶段 ===
$inboundOrder->getStatus() === 'completed';
$inventory->getQuantityAvailable() === $inboundQuantity;

// === 上架阶段 ===
$listing->getStatus() === 'active';
$channelProduct->getStatus() === 'active';
$channelProduct->getSyncedAt() !== null;

// === 订单阶段 ===
$order->getStatus() === 'allocated';
$fulfillment->getStatus() === 'pending';
$inventory->getQuantityReserved() === $orderQuantity;

// === 履约阶段 ===
$fulfillment->getStatus() === 'shipped';
$order->getStatus() === 'shipped';
$inventory->getQuantityAvailable() === $inboundQuantity - $orderQuantity;

// === 结算阶段 ===
$settlement->getStatus() === 'settled';
$wallet->getBalance() > 0;

// === 利润计算验证 ===
$profit = $order->getTotalAmount() - $inventory->getAverageCost() * $orderQuantity - $settlement->getCommissionAmount();
```

### 6.2 出口业务完整流程

**业务场景**：国内货主的虚拟库存，通过海外渠道销售

```
┌─────────────────────────────────────────────────────────────────┐
│                        出口业务流程                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  国内货主          质检仓库          平台系统          海外渠道  │
│     │                 │                 │                 │     │
│     │  同步虚拟库存   │                 │                 │     │
│     │────────────────────────────────>  │                 │     │
│     │                 │                 │                 │     │
│     │                 │                 │  上架商品       │     │
│     │                 │                 │─────────────────>     │
│     │                 │                 │                 │     │
│     │                 │                 │  同步库存       │     │
│     │                 │                 │─────────────────>     │
│     │                 │                 │                 │     │
│     │                 │                 │  <── 拉取订单   │     │
│     │                 │                 │<────────────────│     │
│     │                 │                 │                 │     │
│     │  <── 履约分配   │                 │                 │     │
│     │<────────────────────────────────── │                 │     │
│     │                 │                 │                 │     │
│     │  接受并发货     │                 │                 │     │
│     │─────────────────>                 │                 │     │
│     │                 │                 │                 │     │
│     │                 │  收货质检       │                 │     │
│     │                 │────────────────>│                 │     │
│     │                 │                 │                 │     │
│     │                 │  转运发货       │                 │     │
│     │                 │────────────────────────────────>  │     │
│     │                 │                 │                 │     │
│     │  结算入账       │                 │                 │     │
│     │<────────────────────────────────── │                 │     │
│     │                 │                 │                 │     │
└─────────────────────────────────────────────────────────────────┘
```

#### TC-E2E-002: 出口业务完整流程

**测试步骤**：

| 阶段 | 步骤 | 角色 | 操作 | 验证点 |
|------|------|------|------|--------|
| 库存 | 1 | Merchant | 通过 API 同步虚拟库存 | MerchantInventory 创建 |
| 上架 | 2 | Merchant | 创建 InventoryListing | Listing 创建 |
| 上架 | 3 | Merchant | 上架到海外渠道 | ChannelProduct 创建 |
| 同步 | 4 | System | 同步库存到渠道 | 渠道库存更新 |
| 订单 | 5 | Channel | 产生订单 | - |
| 订单 | 6 | System | 拉取订单 | 订单创建 |
| 订单 | 7 | System | 分配库存 | Fulfillment(merchant_warehouse) |
| 履约 | 8 | Merchant | 接受履约分配 | 状态 = processing |
| 履约 | 9 | Merchant | 发货到质检仓 | 提供物流信息 |
| 履约 | 10 | Warehouse | 收货质检 | 质检通过 |
| 履约 | 11 | Warehouse | 转运发货 | 更新物流信息 |
| 履约 | 12 | System | 推送物流到渠道 | API 调用成功 |
| 结算 | 13 | System | 订单完成 | 结算单创建 |
| 结算 | 14 | System | T+N 后入账 | 钱包余额增加 |

**关键差异验证**：

```php
// === 库存来源 ===
// 虚拟库存通过 API 同步，无入库单
$inventory->getLastSyncedAt() !== null;
$inventory->getExternalSkuId() !== null;

// === 履约类型 ===
$fulfillment->getFulfillmentType() === 'merchant_warehouse';

// === 商户需要接单 ===
$fulfillment->getAcceptedAt() !== null;

// === 二次物流 ===
// 1. 商户到质检仓
// 2. 质检仓到海外客户
```

---

## 附录 A：测试数据准备

### A.1 测试工厂使用

```php
use Tests\Factory\MerchantFactory;
use Tests\Factory\WarehouseFactory;
use Tests\Factory\ProductSkuFactory;
use Tests\Factory\SalesChannelFactory;
use Tests\Factory\OrderFactory;
use Tests\Factory\InventoryFactory;

// 创建商户
$merchant = MerchantFactory::create($em, [
    'status' => Merchant::STATUS_APPROVED,
]);

// 创建仓库
$warehouse = WarehouseFactory::create($em, [
    'status' => Warehouse::STATUS_ACTIVE,
]);

// 创建商品 SKU
$sku = ProductSkuFactory::create($em);

// 创建库存
$inventory = InventoryFactory::create($em, $merchant, $sku, [
    'warehouse' => $warehouse,
    'quantityAvailable' => 100,
]);

// 创建渠道
$channel = SalesChannelFactory::create($em, [
    'status' => SalesChannel::STATUS_ACTIVE,
]);

// 创建订单
$order = OrderFactory::create($em, $merchant, $channel, [
    'items' => [
        ['sku' => $sku, 'quantity' => 2, 'unitPrice' => '199.00'],
    ],
]);
```

### A.2 测试环境配置

```yaml
# .env.test
DATABASE_URL="mysql://root:password@localhost:3306/dwlite_test"
REDIS_DSN="redis://localhost:6379/1"
MESSENGER_TRANSPORT_DSN="sync://"
```

---

## 附录 B：常见问题排查

### B.1 入库流程问题

| 问题 | 可能原因 | 排查方法 |
|------|----------|----------|
| 入库单提交失败 | 商户状态非 approved | 检查 merchant.status |
| 库存未增加 | 入库单状态未完成 | 检查 inbound_order.status |
| 成本计算错误 | 币种不一致 | 检查 currency 字段 |

### B.2 订单同步问题

| 问题 | 可能原因 | 排查方法 |
|------|----------|----------|
| 订单未同步 | 渠道 API 凭证失效 | 检查 sales_channel 配置 |
| 分配失败 | 无可用库存 | 检查 merchant_inventories |
| 确认失败 | 渠道 API 返回错误 | 检查 order_sync_logs |

### B.3 履约流程问题

| 问题 | 可能原因 | 排查方法 |
|------|----------|----------|
| 履约单未创建 | 订单未分配 | 检查 order.status |
| 商户未收到通知 | Webhook 配置错误 | 检查 webhooks 表 |
| 发货信息未同步 | 渠道 API 调用失败 | 检查 channel_product_sync_logs |

### B.4 结算流程问题

| 问题 | 可能原因 | 排查方法 |
|------|----------|----------|
| 结算单未创建 | 订单未完成 | 检查 order.status |
| 入账延迟 | T+N 未到期 | 检查 settlement.settlement_date |
| 金额不对 | 佣金率配置错误 | 检查 merchant 佣金配置 |

---

## 附录 C：相关代码位置

| 模块 | 代码路径 |
|------|----------|
| 入库服务 | `backend/src/Service/InboundService.php` |
| 订单服务 | `backend/src/Service/OrderService.php` |
| 履约服务 | `backend/src/Service/FulfillmentService.php` |
| 分配服务 | `backend/src/Service/AllocationService.php` |
| 结算服务 | `backend/src/Service/SettlementService.php` |
| 库存服务 | `backend/src/Service/InventoryService.php` |
| 渠道网关 | `backend/src/Service/ChannelGateway/` |
| E2E 测试 | `backend/tests/E2E/` |
| 测试工厂 | `backend/tests/Factory/` |
