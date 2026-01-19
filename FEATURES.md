# DWLite 功能清单

> 球鞋供应链资源撮合与渠道分销平台 (B2B)
>
> 最后更新：2026-01-18

---

## 一、用户与认证模块

### 1.1 用户认证

| 功能 | 说明 | API 端点 |
|------|------|----------|
| 用户注册 | 邮箱注册，密码强度验证（8位+大小写+数字） | `POST /api/auth/register` |
| 邮箱验证 | 注册后发送验证邮件，Token 验证 | `POST /api/auth/verify-email` |
| 用户登录 | JWT 认证，返回 access_token 和 refresh_token | `POST /api/auth/login` |
| Token 刷新 | 自动刷新过期 Token | `POST /api/auth/refresh` |
| 用户登出 | Token 黑名单机制 | `POST /api/auth/logout` |
| 忘记密码 | 邮件发送重置链接 | `POST /api/auth/forgot-password` |
| 重置密码 | Token 验证后重置 | `POST /api/auth/reset-password` |
| 修改密码 | 登录状态下修改 | `PUT /api/auth/change-password` |
| 获取当前用户 | 获取登录用户信息 | `GET /api/auth/me` |

### 1.2 账户类型与权限

| 账户类型 | 说明 | 访问范围 |
|----------|------|----------|
| admin | 平台管理员 | 全功能访问 |
| merchant | 商户账号 | 访问自有数据 |
| warehouse | 仓库人员 | 仓库作业权限 |

---

## 二、商品管理模块（Admin）

### 2.1 品牌管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 品牌列表 | 分页、搜索、排序 | `/products/brands` |
| 新增品牌 | 名称、Logo、Slug | Modal |
| 编辑品牌 | 修改品牌信息 | Modal |
| 删除品牌 | 软删除/停用 | - |
| 品牌状态管理 | 启用/禁用 | - |

### 2.2 分类管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 分类列表 | 树形结构展示 | `/products/categories` |
| 新增分类 | 支持父级分类 | Modal |
| 编辑分类 | 修改分类信息 | Modal |
| 删除分类 | 级联处理 | - |
| 分类排序 | 拖拽排序 | - |

### 2.3 标签管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 标签列表 | 分页展示 | `/products/tags` |
| 新增标签 | 名称、颜色、排序 | Modal |
| 编辑标签 | 修改标签信息 | Modal |
| 删除标签 | 解除商品关联 | - |

### 2.4 商品管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 商品列表 | 卡片式展示，高级筛选 | `/products/list` |
| 新增商品 | SPU 创建，关联品牌/分类/标签 | Modal |
| 编辑商品 | 修改商品基本信息 | `/products/detail/:id` |
| 商品详情 | 完整信息展示 | `/products/detail/:id` |
| 商品状态管理 | draft/active/inactive/discontinued | - |
| 批量状态更新 | 批量选择更新状态 | Modal |
| 商品图片管理 | 多图上传、主图设置、排序 | - |

### 2.5 SKU 管理

| 功能 | 说明 |
|------|------|
| SKU 列表 | 商品下的 SKU 列表 |
| 新增 SKU | 尺码单位、尺码值、价格、条码 |
| 编辑 SKU | 修改 SKU 信息 |
| 删除 SKU | 删除 SKU |
| 快速添加尺码 | 批量添加尺码 SKU |
| 批量编辑 SKU | 批量修改价格等信息 |
| 货币切换 | 支持多币种价格 |

---

## 三、商户管理模块（Admin）

### 3.1 商户账户管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 商户列表 | 分页、筛选、搜索 | `/merchants/list` |
| 商户详情 | 完整商户信息 | Modal |
| 商户审核 | pending → approved/rejected | Modal |
| 商户状态管理 | 启用/禁用商户 | - |
| 商户交易记录 | 查看钱包交易流水 | Modal |
| 商户充值 | 管理员为商户充值保证金 | Modal |

### 3.2 商户 API Key 管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| API Key 列表 | 所有商户的 API Key | `/merchants/api-keys` |
| 创建 API Key | 为商户创建 API Key | Modal |
| 权限配置 | 配置 API 权限范围 | - |
| IP 白名单 | 配置访问 IP 限制 | - |
| 状态管理 | active/suspended/revoked | - |

---

## 四、仓库管理模块（Admin）

### 4.1 仓库管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 仓库列表 | 平台仓库和商户仓库 | `/warehouses/list` |
| 新增仓库 | 仓库类型、地址、联系方式 | Modal |
| 编辑仓库 | 修改仓库信息 | Modal |
| 仓库状态管理 | active/maintenance/disabled | - |
| 仓库类型 | self/third_party/bonded/overseas | - |
| 仓库分类 | platform/merchant | - |

### 4.2 仓库用户管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 仓库用户列表 | 仓库作业人员 | `/warehouses/users` |
| 分配用户 | 将用户分配到仓库 | Modal |
| 移除用户 | 解除用户仓库绑定 | - |

---

## 五、库存管理模块（Merchant）

### 5.1 入库单管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 入库单列表 | 分页、状态筛选 | `/inventory/inbound` |
| 创建入库单 | 选择仓库，添加商品 | Modal |
| 入库单详情 | 完整入库信息 | `/inventory/inbound/detail/:id` |
| 添加入库商品 | 选择 SKU，设置数量和成本 | Modal |
| 批量更新数量 | 批量修改预期数量 | Modal |
| 批量更新成本 | 批量修改单位成本 | Modal |
| 提交入库单 | draft → pending | - |
| 发货登记 | 填写物流信息 | Modal |
| 取消入库单 | 仅 draft/pending 可取消 | Modal |
| 异常处理 | 处理入库异常 | Modal |

### 5.2 库存查询

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 库存列表 | 按仓库/SKU 查询 | `/inventory/stock` |
| 库存详情 | 在途/可用/预留/损坏等 | - |
| 添加库存 | 手动添加库存记录 | `/inventory/stock/add` |
| 导入库存 | Excel 批量导入 | `/inventory/stock/import` |
| 库存调整 | 盘点调整（附原因） | Modal |
| 安全库存设置 | 设置安全库存阈值 | - |

### 5.3 出库单管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 出库单列表 | 分页、状态筛选 | `/inventory/outbound` |
| 创建出库单 | 选择库存，指定数量 | Modal |
| 出库单详情 | 完整出库信息 | `/inventory/outbound/detail/:id` |
| 出库单状态 | draft/pending/picking/packing/shipped | - |

### 5.4 入库异常管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 异常列表 | 数量差异、损坏等 | `/inventory/exceptions` |
| 异常详情 | 异常明细和处理记录 | `/inventory/exceptions/detail/:id` |
| 处理异常 | 确认/调整/退货 | Modal |

### 5.5 商机发现

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 商机列表 | 低库存预警、热销缺货 | `/opportunities` |
| 创建入库单 | 从商机直接创建入库单 | Modal |

### 5.6 商户仓库管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 仓库列表 | 商户可用仓库 | `/inventory/warehouses` |
| 仓库详情 | 仓库信息查看 | Modal |

---

## 六、仓库作业模块（Warehouse）

### 6.1 入库作业

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 待收货列表 | 等待收货的入库单 | `/warehouse/inbound` |
| 入库单详情 | 入库作业详情 | `/warehouse/inbound/:id` |
| 收货确认 | 确认到货，记录实收数量 | - |
| 异常登记 | 记录数量差异、损坏 | - |
| 入库统计 | 入库作业统计数据 | - |

### 6.2 出库作业

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 待出库列表 | 等待出库的订单 | `/warehouse/outbound` |
| 出库单详情 | 出库作业详情 | `/warehouse/outbound/:id` |
| 拣货作业 | 按拣货单拣货 | - |
| 打包作业 | 商品打包确认 | - |
| 发货登记 | 填写物流单号，确认发货 | - |
| 出库统计 | 出库作业统计数据 | - |

### 6.3 库存盘点

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 库存查询 | 仓库库存列表 | `/warehouse/inventory` |
| 库存汇总 | 按 SKU 汇总统计 | - |

---

## 七、销售渠道模块

### 7.1 渠道管理（Admin）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 渠道列表 | 平台接入的销售渠道 | `/channels/list` |
| 新增渠道 | 渠道代码、名称、配置 | Modal |
| 编辑渠道 | 修改渠道配置 | Modal |
| 渠道状态管理 | active/maintenance/disabled | - |
| 渠道配置 | JSON Schema 配置项 | Modal |
| 渠道仓库配置 | 关联仓库和优先级 | Drawer |

### 7.2 渠道商品管理（Admin）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 渠道商品列表 | 已上架到渠道的商品 | `/channels/products` |
| 渠道商品详情 | 价格、库存、同步状态 | `/channels/products/:id` |
| 上架商品 | 推送商品到渠道 | - |
| 下架商品 | 从渠道下架商品 | - |
| 价格设置 | 平台统一定价 | - |
| 库存模式 | aggregate/lowest/fixed | - |
| 同步状态 | pending/syncing/synced/failed | - |

### 7.3 商户渠道管理（Admin）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 商户渠道列表 | 商户-渠道关联 | `/channels/merchants` |
| 审批申请 | 商户入驻渠道审批 | Modal |
| 拒绝申请 | 拒绝并填写原因 | Modal |
| 暂停合作 | 暂停商户渠道权限 | Modal |
| 商户配置 | 商户特有渠道配置 | Modal |

### 7.4 渠道申请（Merchant）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 可用渠道 | 可申请的渠道列表 | `/channels/available` |
| 申请入驻 | 提交渠道入驻申请 | - |
| 我的渠道 | 已入驻的渠道列表 | `/channels/my-channels` |
| 渠道详情 | 渠道配置和状态 | - |

### 7.5 商品上架（Merchant）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 上架列表 | 商户上架的商品 | `/channels/listings` |
| 创建上架 | 选择库存，设置价格 | `/channels/listings/create` |
| 编辑上架 | 修改价格和可售数量 | `/channels/listings/:id/edit` |
| 上架日志 | 上架操作历史 | `/channels/listings-logs` |

### 7.6 商户规则（Merchant）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 规则列表 | 商户定价/分配规则 | `/channels/rules` |
| 创建规则 | 规则类型和配置 | `/channels/rules/create` |
| 编辑规则 | 修改规则配置 | `/channels/rules/:id/edit` |
| 规则测试 | 测试规则执行结果 | - |

---

## 八、履约管理模块（Admin）

### 8.1 平台订单管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 订单列表 | 渠道同步的订单 | `/fulfillment/orders` |
| 订单详情 | 完整订单信息 | `/fulfillment/orders/:id` |
| 订单状态 | pending/allocating/allocated/... | - |
| 自动分配 | 规则引擎自动分配 | - |
| 手动分配 | 管理员手动指定 | - |

### 8.2 履约单管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 履约单列表 | 订单拆分后的履约单 | `/fulfillment/fulfillment-orders` |
| 履约单详情 | 履约信息和物流 | `/fulfillment/fulfillment-orders/:id` |
| 履约状态 | pending/processing/shipped/... | - |
| 分配来源 | auto/manual | - |

### 8.3 订单异常管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 异常列表 | 分配失败、库存不足等 | `/fulfillment/order-exceptions` |
| 异常详情 | 异常原因和处理 | `/fulfillment/order-exceptions/:id` |
| 异常处理 | 重新分配/取消/退款 | - |

### 8.4 管理员入库/出库查看

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 全平台入库单 | 查看所有商户入库单 | `/admin/inbound/orders` |
| 入库单详情 | 入库单完整信息 | `/admin/inbound/orders/:id` |
| 全平台出库单 | 查看所有履约出库单 | `/admin/outbound/orders` |
| 出库单详情 | 出库单完整信息 | `/admin/outbound/orders/:id` |

---

## 九、结算管理模块

### 9.1 平台结算管理（Admin）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 结算单列表 | 平台所有结算单 | `/settlements/list` |
| 结算单详情 | 结算明细和费率 | `/settlements/detail/:id` |
| 手动结算 | 触发结算处理 | - |
| 结算状态 | pending/settled/cancelled | - |
| 佣金计算 | 按比例计算平台佣金 | - |

### 9.2 提现管理（Admin）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 提现申请列表 | 商户提现申请 | `/settlements/payouts` |
| 提现详情 | 提现信息和银行账户 | `/settlements/payouts/:id` |
| 审批提现 | 同意提现申请 | Modal |
| 拒绝提现 | 拒绝并填写原因 | Modal |
| 提现状态 | pending/approved/processing/completed/... | - |

### 9.3 商户结算中心（Merchant）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 我的结算 | 商户结算单列表 | `/merchant/settlements` |
| 结算详情 | 查看结算明细 | `/merchant/settlements/:id` |
| 我的提现 | 商户提现记录 | `/merchant/payouts` |
| 申请提现 | 发起提现申请 | Modal |
| 银行账户管理 | 添加/编辑银行账户 | `/merchant/bank-accounts` |

---

## 十、钱包与财务模块

### 10.1 商户钱包（Merchant）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 钱包概览 | 保证金/余额/冻结金额 | `/settings/wallet` |
| 交易记录 | 充值/提现/佣金/结算流水 | - |
| 钱包类型 | deposit(保证金)/balance(余额) | - |

---

## 十一、规则引擎模块

### 11.1 平台规则（Admin）

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 规则列表 | 平台级业务规则 | `/platform-rules` |
| 创建规则 | 规则类型/配置/表达式 | `/platform-rules/create` |
| 编辑规则 | 修改规则配置 | `/platform-rules/:id/edit` |
| 规则测试 | 测试面板验证规则 | - |
| 规则分配 | 分配规则到商户 | Drawer |
| 表达式编辑器 | 可视化表达式编辑 | - |

---

## 十二、设置模块（Merchant）

### 12.1 商户资料

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| 基本信息 | 商户名称、Logo、联系方式 | `/settings/info` |
| 营业执照 | 上传营业执照 | - |
| 地址信息 | 国家/地区/地址 | - |

### 12.2 API Key 管理

| 功能 | 说明 | 前端路由 |
|------|------|----------|
| API Key 列表 | 商户自己的 API Key | `/settings/api-keys` |
| 创建 API Key | 创建新的 API Key | Modal |
| 权限配置 | 配置接口权限 | - |
| 删除 API Key | 废弃 API Key | - |

---

## 十三、外部集成模块（OpenAPI）

### 13.1 商户系统对接

| 接口 | 方法 | 路径 | 说明 |
|------|------|------|------|
| 库存查询 | GET | `/api/v1/open/merchant/inventory` | 查询商户库存 |
| 履约接单 | POST | `/api/v1/open/merchant/fulfillments/{id}/accept` | 接受履约订单 |
| 履约拒单 | POST | `/api/v1/open/merchant/fulfillments/{id}/reject` | 拒绝履约订单 |
| 履约发货 | POST | `/api/v1/open/merchant/fulfillments/{id}/ship` | 提交发货信息 |
| 上架管理 | POST | `/api/v1/open/merchant/listings` | 商品上架接口 |
| 结算查询 | GET | `/api/v1/open/merchant/settlements` | 查询结算信息 |

### 13.2 仓库 WMS 对接

| 接口 | 方法 | 路径 | 说明 |
|------|------|------|------|
| 入库通知 | POST | `/api/v1/open/warehouse/inbound/notify` | 接收入库预告 |
| 入库确认 | POST | `/api/v1/open/warehouse/inbound/{id}/confirm` | 回传入库结果 |
| 出库通知 | POST | `/api/v1/open/warehouse/outbound/notify` | 接收出库指令 |
| 出库确认 | POST | `/api/v1/open/warehouse/outbound/{id}/confirm` | 回传出库结果 |
| 库存盘点 | POST | `/api/v1/open/warehouse/inventory/sync` | 库存数据同步 |

### 13.3 Webhook 管理

| 功能 | 说明 |
|------|------|
| Webhook 配置 | 配置回调地址 |
| 事件订阅 | 订阅业务事件 |
| 投递记录 | Webhook 投递历史 |
| 重试机制 | 失败自动重试 |

---

## 十四、渠道网关模块

### 14.1 已对接渠道

| 渠道 | 代码 | 功能 |
|------|------|------|
| KicksCrew | `KICKSCREW` | 商品推送、库存同步、订单拉取、发货确认、商品下架 |

### 14.2 网关接口规范

| 接口 | 说明 |
|------|------|
| `pushProduct` | 推送商品到渠道 |
| `updateStockPrice` | 更新库存和价格 |
| `pullOrders` | 拉取渠道订单 |
| `confirmOrder` | 确认订单 |
| `shipOrder` | 推送发货信息 |
| `delistProduct` | 商品下架 |

---

## 十五、异步任务与定时任务

### 15.1 异步任务（Symfony Messenger）

| 任务 | 说明 |
|------|------|
| `PullOrdersMessage` | 从渠道拉取订单 |
| `AllocateOrderMessage` | 订单自动分配 |
| `SyncChannelProductMessage` | 同步渠道商品 |
| `ProcessSettlementMessage` | 处理结算 |
| `DispatchWebhookMessage` | 分发 Webhook |
| `ExpireReservationsMessage` | 过期库存预留 |
| `HandleExpiredFulfillmentsMessage` | 处理超时履约 |
| `CreateSettlementMessage` | 创建结算记录 |
| `ProcessConsignmentFulfillmentMessage` | 处理寄售履约 |
| `FulfillmentRejectionMessage` | 处理履约拒绝 |

### 15.2 定时任务（Symfony Scheduler）

| 任务 | 周期 | 说明 |
|------|------|------|
| 商品同步 | 每日 22:40 UTC | 同步 KicksDB 商品数据 |
| 订单拉取 | 每 5 分钟 | 从活跃渠道拉取订单 |
| 待同步扫描 | 每 5 分钟 | 补偿失败的同步任务 |
| 失败订单扫描 | 每 10 分钟 | 补偿失败的订单同步 |
| 超时履约处理 | 每 5 分钟 | 处理超时的履约单 |
| 待结算扫描 | 每小时 | 扫描待结算订单 |
| 预留过期 | 每分钟 | 释放过期的库存预留 |

---

## 十六、可观测性

### 16.1 日志与追踪

| 功能 | 说明 | 工具 |
|------|------|------|
| 结构化日志 | JSON 格式日志输出 | Loki + Promtail |
| 分布式追踪 | trace_id/span_id 关联 | Tempo (OTLP) |
| 跨服务追踪 | W3C traceparent 头支持 | - |
| 日志查询 | `{job="symfony"}` | Grafana |

### 16.2 监控指标

| 功能 | 说明 | 端点 |
|------|------|------|
| Prometheus 指标 | 应用指标暴露 | `/metrics` |
| Grafana 仪表盘 | 可视化监控 | `http://localhost:3000` |
| 队列监控 | Messenger Monitor | `/_debug/messenger` |

---

## 十七、Dashboard

### 17.1 管理员 Dashboard

| 指标 | 说明 |
|------|------|
| 今日订单数 | 当日订单统计 |
| 今日收入 | 当日交易额 |
| 待处理异常 | 未解决异常数 |
| 分配失败数 | 分配失败订单 |
| 订单趋势图 | 7日订单趋势 |
| 履约趋势图 | 7日履约趋势 |
| 最近订单 | 最新订单列表 |
| 最近异常 | 最新异常列表 |

### 17.2 商户 Dashboard

| 指标 | 说明 |
|------|------|
| 可用库存 | 当前可售库存 |
| 在途库存 | 运输中库存 |
| 预留库存 | 已分配库存 |
| 钱包余额 | 账户余额 |
| 保证金 | 已缴保证金 |
| 待结算 | 待结算金额 |
| 可提现 | 可提现金额 |
| 待处理任务 | 入库/出库待处理 |

---

## 十八、国际化支持

| 功能 | 说明 |
|------|------|
| 多语言支持 | 中文 (zh) / 英文 (en) |
| 自动检测 | 浏览器语言检测 |
| 手动切换 | 顶栏语言切换 |
| 后端消息 | 错误消息 i18n |

---

## 十九、数据实体

### 核心业务实体

| 实体 | 说明 |
|------|------|
| User | 用户账户 |
| Merchant | 商户 |
| Brand | 品牌 |
| Category | 分类 |
| Tag | 标签 |
| Product | 商品 (SPU) |
| ProductSku | 商品规格 (SKU) |
| ProductImage | 商品图片 |
| Warehouse | 仓库 |
| MerchantInventory | 商户库存 |
| InventoryTransaction | 库存流水 |
| InboundOrder | 入库单 |
| InboundOrderItem | 入库单明细 |
| InboundShipment | 入库物流 |
| InboundException | 入库异常 |
| OutboundOrder | 出库单 |
| OutboundOrderItem | 出库单明细 |
| SalesChannel | 销售渠道 |
| MerchantSalesChannel | 商户渠道关联 |
| SalesChannelWarehouse | 渠道仓库关联 |
| ChannelProduct | 渠道商品 |
| ChannelProductSource | 渠道商品来源 |
| InventoryListing | 库存上架 |
| Order | 平台订单 |
| OrderItem | 订单明细 |
| Fulfillment | 履约单 |
| FulfillmentItem | 履约明细 |
| Settlement | 结算单 |
| SettlementItem | 结算明细 |
| Wallet | 钱包 |
| WalletTransaction | 钱包流水 |
| Payout | 提现申请 |
| MerchantBankAccount | 银行账户 |
| ApiKey | API Key |
| Webhook | Webhook 配置 |
| WebhookDelivery | Webhook 投递记录 |
| PlatformRule | 平台规则 |
| MerchantRule | 商户规则 |

---

## 二十、技术栈

| 层级 | 技术 |
|------|------|
| 后端框架 | Symfony 6.4 (PHP 8.2) |
| 运行时 | FrankenPHP (Worker 模式) |
| 数据库 | MySQL 8.0 |
| 缓存/队列 | Redis |
| 前端框架 | React 19 + TypeScript 5.9 |
| UI 组件 | Ant Design 5 + Pro Components |
| 构建工具 | Vite 7 |
| 样式 | Tailwind CSS 4 |
| 路由 | React Router 7 |
| 国际化 | i18next |
| 文件存储 | 腾讯云 COS |
| 邮件服务 | 腾讯云 SES / Symfony Mailer |
| 日志 | Loki + Promtail |
| 追踪 | Tempo (OTLP) |
| 监控 | Prometheus + Grafana |

---

## 功能统计

| 类别 | 数量 |
|------|------|
| 功能模块 | 18 个 |
| 已实现功能点 | 200+ |
| API 端点 | 100+ |
| 前端页面 | 60+ |
| 数据实体 | 40+ |
| 异步任务 | 10+ |
| 定时任务 | 7 个 |
