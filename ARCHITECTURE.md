# DWLite 后端架构设计与实体关系

## 后端分层架构

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            Controller 层                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │   Admin/*   │  │  Merchant/* │  │ Warehouse/* │  │      Auth           │ │
│  │ (平台管理)   │  │ (商户自助)   │  │ (仓库作业)   │  │   (认证/鉴权)        │ │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────────────┘ │
├─────────────────────────────────────────────────────────────────────────────┤
│                            Service 层                                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────────┐   │
│  │   Auth/*         │  │   Fulfillment/*  │  │   ChannelGateway/*       │   │
│  │  认证/授权服务     │  │   履约服务        │  │   渠道网关 (策略模式)     │   │
│  └──────────────────┘  └──────────────────┘  └──────────────────────────┘   │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────────┐   │
│  │  InventoryService│  │  OrderSyncService│  │   RuleEngine/*           │   │
│  │   库存服务        │  │   订单同步服务    │  │   规则引擎               │   │
│  └──────────────────┘  └──────────────────┘  └──────────────────────────┘   │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────────┐   │
│  │  WalletService   │  │  PayoutService   │  │   ProductSyncService     │   │
│  │   钱包服务        │  │   提现服务        │  │   商品同步服务            │   │
│  └──────────────────┘  └──────────────────┘  └──────────────────────────┘   │
├─────────────────────────────────────────────────────────────────────────────┤
│                          Message/Handler 层 (异步任务)                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────────┐   │
│  │ OrderSyncMessage │  │SettlementMessage │  │ FulfillmentMessage       │   │
│  │   订单同步        │  │   结算处理        │  │   履约处理               │   │
│  └──────────────────┘  └──────────────────┘  └──────────────────────────┘   │
├─────────────────────────────────────────────────────────────────────────────┤
│                          Repository/Entity 层                                 │
│                      (Doctrine ORM + MySQL)                                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 核心实体关系图 (ER Diagram)

### 商品域 (Product Domain)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│   ┌─────────┐       ┌───────────────┐       ┌─────────────┐                 │
│   │  Brand  │◄──N:1─│    Product    │─1:N──►│ ProductSku  │                 │
│   │  品牌   │       │   商品(SPU)    │       │  规格(SKU)   │                 │
│   └─────────┘       └───────┬───────┘       └─────────────┘                 │
│                             │                      ▲                         │
│   ┌─────────┐               │N:1                   │                         │
│   │Category │◄──────────────┘                      │                         │
│   │  分类   │                                      │                         │
│   └─────────┘       ┌───────────────┐              │                         │
│                     │  ProductImage │◄─1:N─────────┤                         │
│   ┌─────────┐       │   商品图片     │              │                         │
│   │   Tag   │◄─N:N──┴───────────────┘              │                         │
│   │  标签   │                                      │                         │
│   └─────────┘                                      │                         │
└────────────────────────────────────────────────────┼─────────────────────────┘
                                                     │
```

### 用户与商户域

```
┌────────────────────────────────────────────────────┼─────────────────────────┐
│                                                    │                         │
│   ┌─────────┐       ┌───────────────┐              │                         │
│   │  User   │─1:1──►│   Merchant    │──────────────┼─────────────┐           │
│   │  用户   │       │     商户      │              │             │           │
│   └─────────┘       └───────┬───────┘              │             │           │
│                             │                      │             │           │
│                    ┌────────┼────────┐             │             │           │
│                    │        │        │             │             │           │
│                   1:N      1:N      1:N            │             │           │
│                    ▼        ▼        ▼             │             │           │
│              ┌─────────┐ ┌──────┐ ┌────────────────┴──┐          │           │
│              │ Wallet  │ │Payout│ │MerchantInventory  │          │           │
│              │  钱包   │ │ 提现 │ │    商户库存        │◄─────────┤           │
│              └────┬────┘ └──────┘ └─────────┬─────────┘          │           │
│                   │                         │                    │           │
│                  1:N                       1:N                   │           │
│                   ▼                         ▼                    │           │
│          ┌────────────────┐      ┌──────────────────┐            │           │
│          │WalletTransaction│      │InventoryTransaction│          │           │
│          │   钱包流水      │      │    库存流水        │           │           │
│          └────────────────┘      └──────────────────┘            │           │
└──────────────────────────────────────────────────────────────────┼───────────┘
                                                                   │
```

### 渠道与仓库域

```
┌──────────────────────────────────────────────────────────────────┼───────────┐
│                                                                  │           │
│   ┌─────────────────┐                    ┌─────────────────┐     │           │
│   │  SalesChannel   │────────────────────│   Warehouse     │◄────┘           │
│   │   销售渠道       │        N:N        │     仓库         │                 │
│   └────────┬────────┘  (via SalesChannel └────────┬────────┘                 │
│            │            Warehouse)                │                          │
│           1:N                                    N:1                          │
│            ▼                                      │                          │
│   ┌────────────────────┐                          │                          │
│   │MerchantSalesChannel│◄─────────────────────────┘                          │
│   │   商户渠道关系      │                                                     │
│   └────────┬───────────┘                                                     │
│            │                                                                 │
│           1:N                                                                │
│            ▼                                                                 │
│   ┌────────────────────┐       ┌──────────────────────────┐                  │
│   │  InventoryListing  │◄─N:1──│   MerchantInventory      │                  │
│   │   库存上架配置      │       │      (商户库存)           │                  │
│   │  - allocationMode  │       └──────────────────────────┘                  │
│   │  - fulfillmentType │                                                     │
│   │  - pricingModel    │                                                     │
│   └────────────────────┘                                                     │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 入库域 (Inbound Domain)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│   ┌──────────────────┐                                                       │
│   │   InboundOrder   │─1:N──►┌──────────────────┐                            │
│   │     入库单        │       │ InboundOrderItem │                            │
│   │ (Merchant→仓库)   │       │    入库明细       │                            │
│   └────────┬─────────┘       └──────────────────┘                            │
│            │                                                                 │
│       ┌────┴────┐                                                            │
│      1:1       1:N                                                           │
│       ▼         ▼                                                            │
│ ┌───────────────┐  ┌───────────────────┐                                     │
│ │InboundShipment│  │ InboundException  │─1:N──►┌──────────────────────┐      │
│ │   物流信息     │  │    入库异常        │       │InboundExceptionItem  │      │
│ └───────────────┘  └───────────────────┘       │    异常明细           │      │
│                                                └──────────────────────┘      │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 订单与履约域 (Order & Fulfillment)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│  ┌───────────────┐        ┌───────────────┐        ┌───────────────────┐     │
│  │SalesChannel   │◄─N:1───│    Order      │─1:N───►│   OrderItem       │     │
│  │  销售渠道      │        │  平台订单      │        │    订单明细        │     │
│  └───────────────┘        └───────┬───────┘        └───────────────────┘     │
│                                   │                                          │
│                                  1:N                                         │
│                                   ▼                                          │
│                           ┌───────────────┐                                  │
│                           │  Fulfillment  │─1:N──►┌─────────────────┐        │
│                           │    履约单      │       │ FulfillmentItem │        │
│                           │ TYPE:          │       │   履约明细       │        │
│                           │ - platform_wh  │       └─────────────────┘        │
│                           │ - merchant_wh  │                                  │
│                           └───────┬───────┘                                  │
│                                   │                                          │
│                              ┌────┴────┐                                     │
│                             1:1       1:1                                    │
│                              ▼         ▼                                     │
│                  ┌───────────────┐  ┌───────────────┐                        │
│                  │ OutboundOrder │  │  Settlement   │                        │
│                  │    出库单      │  │    结算单      │                        │
│                  │  (平台仓发货)   │  │  (T+N延迟结算) │                        │
│                  └───────┬───────┘  └───────┬───────┘                        │
│                          │                  │                                │
│                         1:N                1:N                               │
│                          ▼                  ▼                                │
│              ┌───────────────────┐  ┌───────────────┐                        │
│              │ OutboundOrderItem │  │SettlementItem │                        │
│              │    出库明细        │  │   结算明细     │                        │
│              └───────────────────┘  └───────────────┘                        │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 核心业务流程

```
                              ┌─────────────────────────────────────┐
                              │           销售渠道 (KicksCrew等)      │
                              └──────────────────┬──────────────────┘
                                                 │ 订单同步
                                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                                订单处理流程                                   │
│                                                                              │
│   Order(pending) ──► Order(allocating) ──► Order(allocated) ──► Order(...)  │
│        │                    │                     │                          │
│        └────────────────────┴─────────────────────┘                          │
│                             │                                                │
│                    库存分配服务 (FulfillmentAllocationService)                 │
│                             │                                                │
│              ┌──────────────┴──────────────┐                                 │
│              ▼                             ▼                                 │
│   ┌─────────────────────┐       ┌─────────────────────┐                      │
│   │ Fulfillment         │       │ Fulfillment         │                      │
│   │ TYPE: platform_wh   │       │ TYPE: merchant_wh   │                      │
│   │ (寄售-平台仓发货)    │       │ (自履约-商家发货)    │                      │
│   └──────────┬──────────┘       └──────────┬──────────┘                      │
│              │                             │                                 │
│              ▼                             ▼                                 │
│   ┌─────────────────────┐       ┌─────────────────────┐                      │
│   │   OutboundOrder     │       │  商户确认/发货       │                      │
│   │   (出库单 → WMS)    │       │  (24h 响应期限)     │                      │
│   └──────────┬──────────┘       └──────────┬──────────┘                      │
│              │                             │                                 │
│              └──────────────┬──────────────┘                                 │
│                             │                                                │
│                             ▼                                                │
│                  ┌─────────────────────┐                                     │
│                  │ Fulfillment(shipped)│                                     │
│                  │ → completed         │                                     │
│                  └──────────┬──────────┘                                     │
│                             │                                                │
│                             ▼                                                │
│                  ┌─────────────────────┐                                     │
│                  │    Settlement       │     T+N 天后                        │
│                  │ (结算单-待结算)      │────────────►  Wallet 入账            │
│                  └─────────────────────┘                                     │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 库存模式对比

| 模式 | 库存位置 | 履约方式 | 库存分配 | 定价 |
|------|---------|---------|---------|------|
| **寄售 (Consignment)** | 平台仓 (实物) | 平台履约 (OutboundOrder) | shared/dedicated | self/platform_managed |
| **自履约 (Self-Fulfillment)** | 商家仓 (虚拟) | 商户发货 | shared | self |

---

## 关键实体状态机

### Order 状态流转

```
pending → allocating → allocated → fulfilling → shipped → delivered → completed
                    ↘ allocation_failed
                                                                    ↘ cancelled
```

### Fulfillment 状态流转

```
pending → processing → shipped → delivered → completed
       ↘ rejected/expired → (重新分配)
                                           ↘ cancelled
```

### Settlement 状态流转

```
pending → settled (T+N天后自动)
       ↘ cancelled (订单退款)
```

---

## 实体清单

### 商品域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| Product | 商品 SPU | `src/Entity/Product.php` |
| ProductSku | 商品 SKU | `src/Entity/ProductSku.php` |
| ProductImage | 商品图片 | `src/Entity/ProductImage.php` |
| Brand | 品牌 | `src/Entity/Brand.php` |
| Category | 分类 | `src/Entity/Category.php` |
| Tag | 标签 | `src/Entity/Tag.php` |

### 用户与商户域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| User | 用户 | `src/Entity/User.php` |
| Merchant | 商户 | `src/Entity/Merchant.php` |
| Wallet | 商户钱包 | `src/Entity/Wallet.php` |
| WalletTransaction | 钱包流水 | `src/Entity/WalletTransaction.php` |
| Payout | 提现申请 | `src/Entity/Payout.php` |
| MerchantBankAccount | 商户银行账户 | `src/Entity/MerchantBankAccount.php` |

### 渠道与仓库域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| SalesChannel | 销售渠道 | `src/Entity/SalesChannel.php` |
| Warehouse | 仓库 | `src/Entity/Warehouse.php` |
| SalesChannelWarehouse | 渠道仓库关系 | `src/Entity/SalesChannelWarehouse.php` |
| MerchantSalesChannel | 商户渠道关系 | `src/Entity/MerchantSalesChannel.php` |

### 库存域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| MerchantInventory | 商户库存 | `src/Entity/MerchantInventory.php` |
| InventoryTransaction | 库存流水 | `src/Entity/InventoryTransaction.php` |
| InventoryListing | 库存上架配置 | `src/Entity/InventoryListing.php` |

### 入库域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| InboundOrder | 入库单 | `src/Entity/InboundOrder.php` |
| InboundOrderItem | 入库明细 | `src/Entity/InboundOrderItem.php` |
| InboundShipment | 入库物流 | `src/Entity/InboundShipment.php` |
| InboundException | 入库异常 | `src/Entity/InboundException.php` |
| InboundExceptionItem | 异常明细 | `src/Entity/InboundExceptionItem.php` |

### 订单与履约域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| Order | 平台订单 | `src/Entity/Order.php` |
| OrderItem | 订单明细 | `src/Entity/OrderItem.php` |
| Fulfillment | 履约单 | `src/Entity/Fulfillment.php` |
| FulfillmentItem | 履约明细 | `src/Entity/FulfillmentItem.php` |
| OutboundOrder | 出库单 | `src/Entity/OutboundOrder.php` |
| OutboundOrderItem | 出库明细 | `src/Entity/OutboundOrderItem.php` |

### 结算域
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| Settlement | 结算单 | `src/Entity/Settlement.php` |
| SettlementItem | 结算明细 | `src/Entity/SettlementItem.php` |

### 渠道商品同步
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| ChannelProduct | 渠道商品 | `src/Entity/ChannelProduct.php` |
| ChannelProductSource | 渠道商品来源 | `src/Entity/ChannelProductSource.php` |
| ChannelProductSyncLog | 同步日志 | `src/Entity/ChannelProductSyncLog.php` |

### 规则引擎
| 实体 | 说明 | 文件路径 |
|------|------|---------|
| PlatformRule | 平台规则 | `src/Entity/PlatformRule.php` |
| MerchantRule | 商户规则 | `src/Entity/MerchantRule.php` |
| RuleExecutionLog | 规则执行日志 | `src/Entity/RuleExecutionLog.php` |
