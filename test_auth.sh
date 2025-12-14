#!/bin/bash

TEST_EMAIL="testuser$(date +%s)@example.com"
echo "=========================================="
echo "DWLite 认证模块完整测试"
echo "=========================================="
echo ""
echo "=== 1. 用户注册 ==="
echo "Email: $TEST_EMAIL"
echo "Password: Test123!"
echo ""
REGISTER_RESULT=$(curl -s -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"Test123!\"}")
echo "$REGISTER_RESULT" | python3 -m json.tool 2>/dev/null || echo "$REGISTER_RESULT"

echo ""
echo "=== 2. 从 MailHog 获取验证 Token ==="
sleep 1
TOKEN=$(curl -s http://localhost:8025/api/v2/messages | python3 -c "
import sys,json,re
msgs=json.load(sys.stdin)
if msgs['items']:
    body=msgs['items'][0]['Content']['Body']
    # Remove quoted-printable soft line breaks and decode
    body = body.replace('=\r\n', '').replace('=\n', '').replace('=3D', '=')
    match=re.search(r'token=([a-fA-F0-9]{64})', body)
    if match:
        print(match.group(1))
    else:
        # Fallback: try to extract any token-like string
        match=re.search(r'token=([a-fA-F0-9]+)', body)
        if match:
            print(match.group(1))
")
echo "Token: $TOKEN"

echo ""
echo "=== 3. 验证邮箱 ==="
VERIFY_RESULT=$(curl -s -X POST "http://localhost:8000/api/auth/verify-email?token=$TOKEN" \
  -H "Content-Type: application/json")
echo "$VERIFY_RESULT" | python3 -m json.tool 2>/dev/null || echo "$VERIFY_RESULT"

echo ""
echo "=== 4. 用户登录 ==="
LOGIN_RESULT=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"Test123!\"}")
echo "$LOGIN_RESULT" | python3 -m json.tool 2>/dev/null || echo "$LOGIN_RESULT"
ACCESS_TOKEN=$(echo "$LOGIN_RESULT" | python3 -c "import sys,json; data=json.load(sys.stdin); print(data.get('token',''))" 2>/dev/null)
REFRESH_TOKEN=$(echo "$LOGIN_RESULT" | python3 -c "import sys,json; data=json.load(sys.stdin); print(data.get('refresh_token',''))" 2>/dev/null)

echo ""
echo "=== 5. 获取当前用户信息 (需要 JWT) ==="
ME_RESULT=$(curl -s http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer $ACCESS_TOKEN")
echo "$ME_RESULT" | python3 -m json.tool 2>/dev/null || echo "$ME_RESULT"

echo ""
echo "=== 6. 刷新 Token ==="
REFRESH_RESULT=$(curl -s -X POST http://localhost:8000/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}")
echo "$REFRESH_RESULT" | python3 -m json.tool 2>/dev/null || echo "$REFRESH_RESULT"
NEW_TOKEN=$(echo "$REFRESH_RESULT" | python3 -c "import sys,json; data=json.load(sys.stdin); print(data.get('token',''))" 2>/dev/null)

echo ""
echo "=== 7. 修改密码 ==="
CHANGE_PW_RESULT=$(curl -s -X PUT http://localhost:8000/api/auth/change-password \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -d '{"current_password":"Test123!","new_password":"NewPass456!"}')
echo "$CHANGE_PW_RESULT" | python3 -m json.tool 2>/dev/null || echo "$CHANGE_PW_RESULT"

echo ""
echo "=== 8. 使用新密码登录 ==="
NEW_LOGIN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"NewPass456!\"}")
echo "$NEW_LOGIN" | python3 -m json.tool 2>/dev/null || echo "$NEW_LOGIN"
FINAL_TOKEN=$(echo "$NEW_LOGIN" | python3 -c "import sys,json; data=json.load(sys.stdin); print(data.get('token',''))" 2>/dev/null)

echo ""
echo "=== 9. 用户登出 ==="
LOGOUT_RESULT=$(curl -s -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer $FINAL_TOKEN")
echo "$LOGOUT_RESULT" | python3 -m json.tool 2>/dev/null || echo "$LOGOUT_RESULT"

echo ""
echo "=== 10. 忘记密码 (发送重置邮件) ==="
FORGOT_RESULT=$(curl -s -X POST http://localhost:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\"}")
echo "$FORGOT_RESULT" | python3 -m json.tool 2>/dev/null || echo "$FORGOT_RESULT"

echo ""
echo "=========================================="
echo "测试完成!"
echo "=========================================="
