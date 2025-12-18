# Decisions

- 采用JWT 作为认证机制，不使用session
- 所有API 都必须是无状态的
- 绝对不要使用全局状态（如session、全局变量等）
- 绝对不要使用Migration 管理数据库 schema
- 调试API时，使用IDE里临时文件的功能例如：/Users/barry/Library/Application Support/JetBrains/PhpStorm2025.1/scratches/rest-api.http
- 修改了Entity后，必须修改对应的doc里的sql文件
