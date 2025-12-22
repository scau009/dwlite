# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

DWLite is a full-stack application with a PHP backend and React frontend, plus an integrated observability stack.

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

Backend logs are written to `/app/var/log/dev.log` in JSON format and automatically collected by Promtail. Query logs in Grafana with:
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

| Method | Path | 说明 | 认证 |
|--------|------|------|------|
| POST | /api/auth/register | 用户注册 | ✗ |
| POST | /api/auth/verify-email | 验证邮箱 | ✗ |
| POST | /api/auth/login | 用户登录 | ✗ |
| POST | /api/auth/refresh | 刷新 Token | ✗ |
| POST | /api/auth/logout | 用户登出 | ✓ |
| POST | /api/auth/forgot-password | 忘记密码 | ✗ |
| POST | /api/auth/reset-password | 重置密码 | ✗ |
| PUT | /api/auth/change-password | 修改密码 | ✓ |
| GET | /api/auth/me | 获取当前用户 | ✓ |

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
