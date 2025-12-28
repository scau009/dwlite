# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Working Style

- Follow existing architecture strictly
- Do NOT introduce new frameworks or dependencies unless explicitly requested
- Prefer modifying existing code over adding new abstractions
- Respect current naming and folder conventions
- Think step by step
- When uncertain, ask before implementing
- Output only what is requested
- Do not use doctrine migration
- Never use local datetime for database storage, use UTC instead
- When requested to implement a new Module, create a new plan markdown file in `docs/plan/` folder, and name it
  `{module_name}.md`
- Always keep UI & UX friendly , clean and same style

## Project Overview

DWLite is a full-stack application with a PHP backend and React frontend, plus an integrated observability stack.

### Business Context

| 维度       | 内容说明                          |
|----------|-------------------------------|
| 项目名称     | 球鞋供应链资源撮合与渠道分销平台 (B2B)        |
| 业务本质     | 整合供应链供给（货主）与销售渠道（平台），实现高效撮合交易 |
| 战略目标     | 解决供给侧销售难、平台侧招商难和稳定供给难的问题      |
| MVP 核心目标 | 成功对接并完成一次进口业务和一次出口业务的全流程交易    |

#### 进口线路：海外供应商 (实物库存)

| 业务步骤    | 角色        | 技术系统需支持的功能 (MVP)                                 |
|---------|-----------|--------------------------------------------------|
| 商品及库存导入 | 海外货主/平台运营 | Excel 批量导入 (SPU/SKC/SKU、数量、价格)。商品信息库功能需支持爬虫数据整合。 |
| 商品入库    | 仓储履约合作方   | 入库管理对接：平台系统发送预定入库通知给合作仓；仓库完成实际收货后，回传入库确认及实收数量。   |
| 价格制定与上架 | 海外货主/平台运营 | 出价模块：支持自主出价或最低价全托管模式；上架模块：指定国内销售渠道。              |
| 库存同步    | 平台系统/渠道方  | 库存同步 API：实时/准实时将已入库的实物库存数量同步至渠道。                 |
| 订单处理    | 渠道方/平台系统  | 渠道对接 API：接收订单；订单路由：确定履约仓库，库存分配                   |
| 履约发货    | 仓储履约合作方   | 出库对接：系统将订单推送给合作仓，并接收出库作业                         |
| 结算准备    | 平台系统      | 基础交易对账单生成：记录交易额、手续费等信息，与入库和出库数量对账。               |

#### 出口线路：国内供应商 (虚拟库存)

| 业务步骤    | 角色            | 技术系统需支持的功能 (MVP)                            |
|---------|---------------|---------------------------------------------|
| 商品及库存同步 | 国内货主/平台运营     | 对接国内货主 ERP 系统，同步虚拟库存和价格。若只有 Excel 文件，也支持导入。 |
| 价格制定与上架 | 国内货主/平台运营     | 出价模块：仅支持自主出价；上架模块：指定国外销售渠道。                 |
| 库存同步    | 平台系统/渠道方      | 库存同步 API：实时/准实时将货主的虚拟库存数量同步至渠道。             |
| 订单处理    | 渠道方/平台系统/国内货主 | 渠道对接 API：接收订单；订单路由：分发给货主                    |
| 履约发货    | 国内货主          | 出库对接：货主根据订单发往境内质检仓，由质检仓库发往境外客户              |
| 结算准备    | 平台系统          | 基础交易对账单生成：记录交易额、手续费等信息。                     |

### 技术设计原则 (MVP 阶段)

- **商品数据隔离原则**：实施商品信息 (SPU/SKC) 与库存/价格信息 (SKU) 的分离设计。
- **服务化解耦**：核心功能模块（商品服务、库存服务、订单服务、履约服务、结算服务）应独立设计。

## Architecture

```
dwlite/
├── backend/          # Symfony 6.4 PHP API (FrankenPHP)
│   └── src/
│       ├── Controller/       # Route handlers (attribute-based routing)
│       ├── Service/          # Business logic (RequestIdService, MetricsService)
│       ├── EventSubscriber/  # Request lifecycle hooks (tracing, metrics)
│       ├── Message/          # Async message classes
│       ├── MessageHandler/   # Async message handlers
│       ├── Messenger/        # Middleware (TraceIdMiddleware)
│       ├── Scheduler/        # Scheduled task definitions
│       ├── Entity/           # Doctrine entities (User, tokens)
│       ├── Repository/       # Doctrine repositories
│       ├── Dto/              # Data transfer objects
│       ├── Security/         # Security components (UserChecker)
│       └── Monolog/          # Custom log processors
├── frontend/         # React 19 + TypeScript + Vite
├── observability/    # Loki + Promtail + Tempo + Prometheus + Grafana
└── docker-compose.yml
```

### Backend (Symfony 6.4)

- **Runtime**: FrankenPHP (PHP 8.2) with worker mode enabled (应用常驻内存)
- **Web Server**: Caddy (via FrankenPHP)
- **Database**: MySQL 8.0 (external, connects via `host.docker.internal:3306`)
- **ORM**: Doctrine with migrations support
- **Routing**: Attribute-based routes in `src/Controller/`
- **Logging**: Monolog with JSON format, collected by Promtail
- **Metrics**: Prometheus client (APCu storage) exposed at `/metrics`
- **Async Tasks**: Symfony Messenger with Redis transport
- **Scheduled Tasks**: Symfony Scheduler (cron replacement)
- **Authentication**: JWT (lexik/jwt-authentication-bundle) with refresh tokens
- **File Storage**: Tencent COS (qcloud/cos-sdk-v5)
- **Rate Limiting**: symfony/rate-limiter for API throttling
- **Distributed Locks**: symfony/lock for concurrency control

### Frontend (React 19)

- **Build Tool**: Vite 7
- **Language**: TypeScript 5.9
- **UI Framework**: Ant Design 5 + Pro Components
- **Styling**: Tailwind CSS 4
- **Routing**: React Router 7
- **i18n**: i18next with browser language detection
- **Path Alias**: `@/` maps to `src/`

#### Frontend Structure

```
frontend/src/
├── components/      # Shared UI components
├── config/          # App configuration (menus, role-based access)
├── contexts/        # React contexts (auth, theme)
├── i18n/            # Internationalization (en, zh locales)
├── layouts/         # Page layouts (AppLayout, AuthLayout)
├── lib/             # API clients and utilities
├── pages/           # Route pages
│   ├── auth/        # Authentication pages
│   ├── products/    # Product management
│   ├── brands/      # Brand management
│   ├── categories/  # Category management
│   ├── tags/        # Tag management
│   ├── merchants/   # Merchant management (admin)
│   ├── warehouses/  # Warehouse management
│   ├── inventory/   # Inventory (inbound orders, shipments, exceptions)
│   └── settings/    # Merchant self-service settings
├── theme/           # Ant Design theme configuration
├── types/           # TypeScript type definitions
├── router.tsx       # Route definitions
└── main.tsx         # App entry point
```

### Observability Stack

- **Loki**: Log aggregation (port 3100)
- **Tempo**: Distributed tracing backend (port 3200, OTLP: 4317/4318)
- **Prometheus**: Metrics collection (port 9090), scrapes backend `/metrics`
- **Promtail**: Log shipping from backend and Docker containers
- **Grafana**: Visualization dashboards (port 3000, admin/admin)

## Common Commands

### Docker (from root)

```bash
docker compose up -d              # Start all services
docker compose up -d --build      # Rebuild and start
docker compose restart backend    # Restart backend only
docker compose logs -f backend    # Follow backend logs
docker compose logs -f worker     # Follow async worker logs
docker compose logs -f scheduler  # Follow scheduler logs
```

### Backend (from /backend)

```bash
composer install                  # Install dependencies
composer cache:clear              # Clear Symfony cache
php bin/console debug:router      # List all routes
php bin/console make:migration    # Generate migration from entity changes
php bin/console doctrine:migrations:migrate  # Run pending migrations
php bin/console lexik:jwt:generate-keypair   # Generate JWT keys (first-time setup)
php bin/console app:create-admin admin@example.com password  # Create admin user
```

### Frontend (from /frontend)

```bash
npm install                       # Install dependencies
npm run dev                       # Start dev server with HMR
npm run build                     # Type-check and build for production
npm run lint                      # Run ESLint
```

### Messenger / Async Tasks

```bash
# Run worker locally (in container)
docker compose exec backend php bin/console messenger:consume async -vv

# View failed messages
docker compose exec backend php bin/console messenger:failed:show

# Retry failed messages
docker compose exec backend php bin/console messenger:failed:retry

# Test dispatch (sends example message)
curl -X POST http://localhost:8000/async/dispatch
```

## Service Ports

- Backend API: http://localhost:8000
- Backend Health: http://localhost:8000/health
- Backend Metrics: http://localhost:8000/metrics
- Grafana: http://localhost:3000
- Prometheus: http://localhost:9090
- Loki API: http://localhost:3100
- Tempo API: http://localhost:3200
- Redis: localhost:6379
- MailHog UI: http://localhost:8025 (邮件测试)

## Tracing

每个请求自动生成 `trace_id` 和 `span_id`：

- 响应头返回 `X-Trace-Id` 和 `X-Span-Id`
- 日志的 `extra` 字段包含这些 ID
- 支持传入 `X-Trace-Id` 或 W3C `traceparent` 头实现跨服务追踪

在 Grafana 中：

- Loki 日志可点击 TraceID 跳转到 Tempo
- 按 trace_id 查询：`{job="symfony", trace_id="xxx"}`

## Logging

Backend logs are written to `/app/var/log/dev.log` in JSON format and automatically collected by Promtail. Query logs in
Grafana with:

- `{job="symfony"}` - All Symfony logs
- `{job="symfony", channel="app"}` - Application logs
- `{job="symfony", level="ERROR"}` - Error logs only

## Async Tasks (Symfony Messenger)

使用 Redis Stream 作为消息队列，`worker` 服务自动消费消息。

**创建新的异步任务：**

1. 创建 Message 类（实现 `AsyncMessageInterface`）：

```php
// src/Message/MyTaskMessage.php
class MyTaskMessage implements AsyncMessageInterface {
    public function __construct(public readonly string $data) {}
}
```

2. 创建 Handler：

```php
// src/MessageHandler/MyTaskMessageHandler.php
#[AsMessageHandler]
class MyTaskMessageHandler {
    public function __invoke(MyTaskMessage $message): void {
        // 处理任务...
    }
}
```

3. 派发消息：

```php
$bus->dispatch(new MyTaskMessage('some data'));
```

**配置：**

- Transport: `config/packages/messenger.yaml`
- Redis Stream: `dwlite_messages`
- 失败消息: `dwlite_failed`
- 重试策略: 最多 3 次，指数退避
- Trace Context: 自动通过 `TraceIdMiddleware` 传递到异步任务

## Scheduled Tasks (Symfony Scheduler)

使用 Symfony Scheduler 替代传统 cron，`scheduler` 服务自动触发定时任务。

**添加定时任务：**

1. 创建 Message 类：

```php
// src/Message/MyScheduledTask.php
class MyScheduledTask {
    public function __construct(public readonly \DateTimeImmutable $scheduledAt) {}
}
```

2. 创建 Handler：

```php
// src/MessageHandler/MyScheduledTaskHandler.php
#[AsMessageHandler]
class MyScheduledTaskHandler {
    public function __invoke(MyScheduledTask $message): void {
        // 执行定时任务...
    }
}
```

3. 在 `MainSchedule` 中注册：

```php
// src/Scheduler/MainSchedule.php
public function getSchedule(): Schedule
{
    return (new Schedule())->with(
        RecurringMessage::every('1 hour', new MyScheduledTask(new \DateTimeImmutable())),
        // 或使用 cron 表达式
        RecurringMessage::cron('0 0 * * *', new MidnightTask()),  // 每天午夜
    )->stateful($this->cache);
}
```

**常用时间表达式：**

- `RecurringMessage::every('1 minute', $msg)` - 每分钟
- `RecurringMessage::every('1 hour', $msg)` - 每小时
- `RecurringMessage::every('1 day', $msg)` - 每天
- `RecurringMessage::cron('*/5 * * * *', $msg)` - 每 5 分钟
- `RecurringMessage::cron('0 9 * * 1-5', $msg)` - 工作日早 9 点

## Authentication (JWT)

基于 JWT 的用户认证系统，支持邮箱验证、密码重置等功能。

### API 端点

| Method | Path                      | 说明       | 认证 |
|--------|---------------------------|----------|----|
| POST   | /api/auth/register        | 用户注册     | ✗  |
| POST   | /api/auth/verify-email    | 验证邮箱     | ✗  |
| POST   | /api/auth/login           | 用户登录     | ✗  |
| POST   | /api/auth/refresh         | 刷新 Token | ✗  |
| POST   | /api/auth/logout          | 用户登出     | ✓  |
| POST   | /api/auth/forgot-password | 忘记密码     | ✗  |
| POST   | /api/auth/reset-password  | 重置密码     | ✗  |
| PUT    | /api/auth/change-password | 修改密码     | ✓  |
| GET    | /api/auth/me              | 获取当前用户   | ✓  |

### 使用示例

```bash
# 注册
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"Test123!"}'

# 登录
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"Test123!"}'

# 获取当前用户（需要 JWT）
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer <token>"

# 刷新 Token
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"<refresh_token>"}'
```

### 配置

- JWT 密钥: `config/jwt/` (private.pem, public.pem)
- Token 有效期: 1 小时 (access), 30 天 (refresh)
- 邮件模板: `templates/emails/`
- 数据库 Schema: `doc/schema.sql`

### 密码要求

- 最少 8 个字符
- 至少包含一个大写字母
- 至少包含一个小写字母
- 至少包含一个数字

## Role-Based Access Control

系统有两种账户类型：

- **admin**: 平台管理员，可访问所有管理功能
- **merchant**: 商户账号，只能访问自己的数据

### Admin-Only 端点

使用 `#[AdminOnly]` 属性标记仅限管理员访问的控制器或方法：

```php
// 整个控制器仅限管理员
#[AdminOnly]
class BrandController extends AbstractController { }

// 单个方法仅限管理员
#[AdminOnly]
public function delete(int $id): JsonResponse { }
```

Admin 控制器位于 `src/Controller/Admin/`，处理：

- 品牌管理 (BrandController)
- 分类管理 (CategoryController)
- 标签管理 (TagController)
- 商品管理 (ProductController)
- 仓库管理 (WarehouseController)
- 商户管理 (MerchantController)

### 商户自助服务

商户可通过以下端点管理自己的数据：

- `MerchantProfileController`: 商户资料和钱包
- `InboundOrderController`: 入库单管理

## Architecture Principles

1. Backend language: 以 PHP 为主，Go 为辅
2. Auth: JWT only, no session
3. Database:
    - MySQL 作为主数据库，存储核心业务数据
    - MongoDB 用于存储事件日志、操作审计等非核心数据
4. 所有服务都必须是无状态的
5. 前端：React + Vite + Ant Design

## Key Decisions

- 采用 JWT 作为认证机制，不使用 session
- 所有 API 都必须是无状态的
- 绝对不要使用全局状态（如 session、全局变量等）
- **绝对不要使用 Migration 管理数据库 schema**
- 调试 API 时，使用 IDE 里临时文件的功能
- **修改了 Entity 后，必须修改对应的 doc 里的 sql 文件**

## Code Conventions

### Backend (PHP)

- 遵循 PSR-12 编码规范
- 采用 MVC 架构模式
- 采用依赖注入模式
- 尽可能使用类型，避免直接用数组
- 不要在控制器中直接使用数据库查询等复杂逻辑，保持控制器简洁
- 接口参数的验证，采用 Symfony Validator 组件
- 接口传入的参数通过 Serializer 组件进行自动转换 DTO
- 查询数据时，使用 Repository 模式
- 服务层中，使用 DTO 传递数据
- 服务层等代码，都要编写单元测试
- 数据库查询时，注意避免 N+1 查询问题
- 保持代码风格一致
- 接口返回的文本需要考虑 i18n
- 永远不要用 Doctrine 的 migration 来修改数据库结构
- 每次修改数据库结构时，都要更新对应的 doc 目录下的 sql 文件

## UI Design Guidelines

### 设计目标

- **高信息效率**：减少点击、减少页面跳转
- **数据优先**：表格与筛选为核心
- **操作安全**：批量操作清晰、可确认
- **克制美学**：极简、干净、不干扰操作

### 全局布局

```
[ TopBar ]
[ Sidebar | Main Content ]
```

- 顶部：全局工具栏（高度 ≤ 56px），仅包含 Logo、语言切换、主题切换、用户菜单
- 左侧：主导航（固定，可折叠）
- 主内容区：页面内容

### 列表页规范

```
[ 页面标题 ]
[ 页面说明（可选） ]
[ 筛选区（可折叠） ]
[ 操作区 ]
[ 数据表格 ]
[ 分页器 ]
```

**筛选区**：默认展示 3~4 个高频筛选条件，其余放入「更多筛选」

**操作区**：

- 未选中行：新增、批量导入、批量导出
- 选中行：显示「已选择 X 项」+ 批量操作按钮
- 危险操作必须二次确认

**表格**：

- 中等密度表格
- 第一列固定为 Checkbox
- 最后一列为操作列
- 表头支持排序

### 详情页规范

```
[ 返回列表 ]    [ 主操作按钮 ]
[ 核心信息卡片 ]
[ Tabs: 基本信息 | 库存信息 | 价格信息 | 操作记录 ]
```

### 表单规范

- 单列布局为主
- 字段分组（Section）
- 必填字段清晰标识
- 错误提示紧贴字段
- 主按钮：保存，次按钮：取消/返回

### 状态与反馈

- 状态 = 文本 + 轻量色彩，禁止使用强对比大色块
- 成功：轻量 Toast
- 失败：明确错误原因
- 批量操作：展示处理结果摘要

### 国际化

- 所有文本必须支持 i18n
- 不允许硬编码文案
