# DWLite

全栈应用：Symfony 6.4 后端 + React 19 前端 + 可观测性套件。

## 技术栈

- **后端**: Symfony 6.4 / FrankenPHP / MySQL 8.0 / Redis
- **前端**: React 19 / TypeScript / Vite
- **可观测性**: Grafana / Loki / Tempo / Prometheus

## 快速启动

### 本地开发
```bash
# 登录容器镜像仓库
docker login 

cd backend

# 构建基础镜像
docker build -t dwlite-php-base:latest -f Dockerfile.base .

# 启动所有服务
docker compose up -d

# 前端开发
cd frontend && npm install && npm run dev
```

## 服务地址

| 服务 | 地址 |
|------|------|
| Backend API | http://localhost:8000 |
| Grafana | http://localhost:3000 (admin/admin) |
| MailHog | http://localhost:8025 |

## 项目结构

```
dwlite/
├── backend/       # Symfony API
├── frontend/      # React SPA
├── observability/ # Grafana/Loki/Tempo/Prometheus 配置
└── docker-compose.yml
```

## 文档

详细开发指南请参阅 [CLAUDE.md](./CLAUDE.md)。
