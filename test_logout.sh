#!/bin/bash

echo "=== 1. 登录获取 Token ==="
LOGIN_RESULT=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"GxR654321"}')
echo "$LOGIN_RESULT" | python3 -m json.tool 2>/dev/null || echo "$LOGIN_RESULT"

TOKEN=$(echo "$LOGIN_RESULT" | python3 -c "import sys,json; data=json.load(sys.stdin); print(data.get('token',''))" 2>/dev/null)

if [ -z "$TOKEN" ]; then
    echo "登录失败，无法获取 Token"
    exit 1
fi

echo ""
echo "=== 2. 使用 Token 访问 /me ==="
curl -s http://localhost:8000/api/auth/me -H "Authorization: Bearer $TOKEN" | python3 -m json.tool 2>/dev/null

echo ""
echo "=== 3. 执行 Logout ==="
curl -s -X POST http://localhost:8000/api/auth/logout -H "Authorization: Bearer $TOKEN" | python3 -m json.tool 2>/dev/null

echo ""
echo "=== 4. Logout 后再次访问 /me (应该返回 401) ==="
RESULT=$(curl -s -w "\nHTTP_CODE:%{http_code}" http://localhost:8000/api/auth/me -H "Authorization: Bearer $TOKEN")
echo "$RESULT"
