# DWLite

球鞋供应链资源撮合与渠道分销平台 (B2B)

全栈应用：Symfony 6.4 后端 + React 19 前端 + 可观测性套件。

## 技术栈

| 层级 | 技术 |
|------|------|
| **后端** | Symfony 6.4 / FrankenPHP / PHP 8.2 |
| **前端** | React 19 / TypeScript 5.9 / Vite 7 / Ant Design 5 |
| **数据库** | MySQL 8.0 / Redis |
| **消息队列** | Symfony Messenger + Redis Stream |
| **认证** | JWT (lexik/jwt-authentication-bundle) |
| **文件存储** | 腾讯云 COS |
| **可观测性** | Grafana / Loki / Tempo / Prometheus |

## 快速启动

### 环境要求

- Docker & Docker Compose
- Node.js 20+
- PHP 8.2+ (本地开发可选)

### 本地开发

```bash
# 1. 克隆项目
git clone <repo-url> && cd dwlite

# 2. 构建基础镜像
cd backend
docker build -t dwlite-php-base:latest -f Dockerfile.base .

# 3. 启动所有服务
docker-compose up -d

# 4. 生成 JWT 密钥（首次）
docker-compose exec backend php bin/console lexik:jwt:generate-keypair

# 5. 前端开发
cd ../frontend && npm install && npm run dev
```

## 服务地址

| 服务 | 地址 | 说明 |
|------|------|------|
| Backend API | http://localhost:8000 | 后端 API |
| Frontend | http://localhost:5173 | 前端开发服务器 |
| Grafana | http://localhost:3000 | 监控面板 (admin/admin) |
| Prometheus | http://localhost:9090 | 指标收集 |
| MailHog | http://localhost:8025 | 邮件测试 |
| Messenger Monitor | http://localhost:8000/_debug/messenger | 队列监控 |

## 项目结构

```
dwlite/
├── backend/           # Symfony 6.4 API
│   ├── src/
│   │   ├── Controller/    # API 控制器
│   │   ├── Entity/        # Doctrine 实体
│   │   ├── Repository/    # 数据仓库
│   │   ├── Service/       # 业务逻辑
│   │   ├── Dto/           # 数据传输对象
│   │   ├── Message/       # 异步消息
│   │   └── MessageHandler/# 消息处理器
│   └── doc/               # 数据库 SQL 文件
├── frontend/          # React 19 SPA
│   └── src/
│       ├── pages/         # 页面组件
│       ├── components/    # 共享组件
│       ├── lib/           # API 客户端
│       └── i18n/          # 国际化
├── observability/     # 可观测性配置
└── docker-compose.yml
```

## 常用命令

### Docker

```bash
docker-compose up -d              # 启动所有服务
docker-compose restart backend    # 重启后端
docker-compose logs -f worker     # 查看 Worker 日志
```

### 后端 (backend/)

```bash
composer install                  # 安装依赖
vendor/bin/phpstan analyse        # 静态分析
vendor/bin/php-cs-fixer fix       # 代码格式化
php bin/console debug:router      # 查看路由
php bin/console messenger:monitor # 队列监控
```

### 前端 (frontend/)

```bash
npm install      # 安装依赖
npm run dev      # 开发服务器
npm run build    # 生产构建
npm run lint     # 代码检查
```

## 核心功能

- **商品管理**: SPU/SKC/SKU 三级商品体系
- **库存管理**: 入库单、出库单、库存盘点
- **商户管理**: 商户注册、审核、钱包
- **仓库作业**: 收货、上架、拣货、发货
- **渠道对接**: 库存同步、订单接收

## 文档

详细开发指南请参阅 [CLAUDE.md](./CLAUDE.md)。
