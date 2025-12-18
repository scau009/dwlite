# Architecture Principles

1. Backend language: 以PHP 为主，Go 为辅
2. Auth: JWT only, no session
3. Database:
    - MySQL 作为主数据库，存储核心业务数据
    - MongoDB 用于存储事件日志、操作审计等非核心数据
4. Never:
    - 使用migration 管理数据库 schema
    - 在代码中硬编码
   
5. 所有服务都必须是无状态的
6. 前端：
    - React + Vite 作为前端框架
    - Ant Design 作为组件库

