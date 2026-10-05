#!/usr/bin/env bash
# 端到端验证：字段权限、版本切换、旧链接失效、更新不扩大授权
set -euo pipefail
BASE="${BASE:-http://127.0.0.1:8000/api}"
J() { python3 -c "import sys,json;d=json.load(sys.stdin);print(eval('d'+sys.argv[1]))" "$1" 2>/dev/null; }

echo "--- 1. 公开目录不泄露受限字段"
CATALOG=$(curl -s "$BASE/catalog/1")
echo "$CATALOG" | grep -q student_id && { echo "FAIL: 目录泄露 student_id"; exit 1; }
echo "$CATALOG" | grep -q health_condition && { echo "FAIL: 目录泄露 health_condition"; exit 1; }
echo "$CATALOG" | grep -q S2024001 && { echo "FAIL: 目录泄露学号数据"; exit 1; }
echo "$CATALOG" | grep -q age_group || { echo "FAIL: 公开字段缺失"; exit 1; }
echo "OK"

echo "--- 2. 研究人员登录并提交申请（v1, 含受限字段 student_id）"
RTOKEN=$(curl -s -X POST "$BASE/login" -H 'Content-Type: application/json' \
  -d '{"email":"researcher@example.edu","password":"password"}' | J "['token']")
V1=$(curl -s "$BASE/datasets/1/fields" -H "Authorization: Bearer $RTOKEN" | J "['versions'][-1]['id']")
curl -s -X POST "$BASE/applications" -H "Authorization: Bearer $RTOKEN" -H 'Content-Type: application/json' \
  -d "{\"dataset_id\":1,\"dataset_version_id\":$V1,\"purpose\":\"体质与运动关系统计研究\",\"fields\":[\"age_group\",\"bmi\",\"student_id\"],\"access_start\":\"2026-01-01\",\"access_end\":\"2027-12-31\"}" \
  | grep -q '"status":"pending"' || { echo "FAIL: 申请未创建"; exit 1; }
echo "OK"

echo "--- 3. 未批准前无授权、无下载"
curl -s "$BASE/grants" -H "Authorization: Bearer $RTOKEN" | grep -q '\[\]' || { echo "FAIL: 未批准即有授权"; exit 1; }
echo "OK"

echo "--- 4. 审批人批准"
ATOKEN=$(curl -s -X POST "$BASE/login" -H 'Content-Type: application/json' \
  -d '{"email":"approver@example.edu","password":"password"}' | J "['token']")
APP_ID=$(curl -s "$BASE/admin/applications" -H "Authorization: Bearer $ATOKEN" | J "['applications'][-1]['id']")
curl -s -X POST "$BASE/admin/applications/$APP_ID/approve" -H "Authorization: Bearer $ATOKEN" \
  -H 'Content-Type: application/json' -d '{}' | grep -q '"status":"approved"' || { echo "FAIL: 批准失败"; exit 1; }
echo "OK"

echo "--- 5. 生成下载链接，下载内容仅含获批字段"
GRANT_ID=$(curl -s "$BASE/grants" -H "Authorization: Bearer $RTOKEN" | J "['grants'][0]['id']")
DL=$(curl -s -X POST "$BASE/grants/$GRANT_ID/token" -H "Authorization: Bearer $RTOKEN" | J "['token']")
CSV=$(curl -s "$BASE/download/$DL")
HEADER=$(echo "$CSV" | head -1)
[ "$HEADER" = "age_group,bmi,student_id" ] || { echo "FAIL: 表头=$HEADER"; exit 1; }
echo "$CSV" | grep -q health_condition && { echo "FAIL: 下载含未获批字段"; exit 1; }
echo "OK"

echo "--- 6. 篡改下载参数请求未获准字段被拒"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/download/$DL?fields=age_group,health_condition")
[ "$CODE" = "403" ] || { echo "FAIL: 篡改参数返回 $CODE"; exit 1; }
SUB=$(curl -s "$BASE/download/$DL?fields=bmi" | head -1)
[ "$SUB" = "bmi" ] || { echo "FAIL: 子集下载异常: $SUB"; exit 1; }
echo "OK"

echo "--- 7. 登记成果引用（关联实际使用版本 v1）"
curl -s -X POST "$BASE/citations" -H "Authorization: Bearer $RTOKEN" -H 'Content-Type: application/json' \
  -d "{\"grant_id\":$GRANT_ID,\"citation_number\":\"DOI-10.9999/E2E.1\",\"title\":\"体质与运动关系研究\",\"venue\":\"校刊\",\"published_at\":\"2026-09-01\"}" \
  | grep -q 'DOI-10.9999' || { echo "FAIL: 引用登记失败"; exit 1; }
echo "OK"

echo "--- 8. 发布 v2 后，旧授权仍只能下载 v1 获批字段"
V2CSV='age_group,grade,bmi,sport_hours,student_id,health_condition\n99+,研三,30.0,0,S999999,保密'
curl -s -X POST "$BASE/admin/datasets/1/versions" -H "Authorization: Bearer $ATOKEN" -H 'Content-Type: application/json' \
  -d "{\"csv\":\"$(echo "$V2CSV" | sed 's/$/\\n/' | tr -d '\n')\",\"changelog\":\"端到端测试新版本\"}" | grep -q '"version":3' || { echo "FAIL: 新版本发布失败"; exit 1; }
CSV2=$(curl -s "$BASE/download/$DL")
echo "$CSV2" | grep -q 'S999999' && { echo "FAIL: 旧授权拿到新版本数据"; exit 1; }
echo "$CSV2" | grep -q 'S2024001' || { echo "FAIL: 旧授权应仍返回 v1 数据"; exit 1; }
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/download/$DL?fields=sport_hours")
[ "$CODE" = "403" ] || { echo "FAIL: 更新后可用未获批字段 ($CODE)"; exit 1; }
echo "OK"

echo "--- 9. 撤回授权后旧链接立即失效，引用登记保留"
curl -s -X POST "$BASE/admin/grants/$GRANT_ID/revoke" -H "Authorization: Bearer $ATOKEN" > /dev/null
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/download/$DL")
[ "$CODE" = "410" ] || { echo "FAIL: 撤回后链接返回 $CODE"; exit 1; }
curl -s "$BASE/citations" | grep -q 'DOI-10.9999' || { echo "FAIL: 撤回后引用丢失"; exit 1; }
echo "OK"

echo "--- 10. 被拒申请改变用途需重新申请"
curl -s -X POST "$BASE/applications" -H "Authorization: Bearer $RTOKEN" -H 'Content-Type: application/json' \
  -d "{\"dataset_id\":1,\"dataset_version_id\":$V1,\"purpose\":\"原始用途文本内容\",\"fields\":[\"bmi\"],\"access_start\":\"2026-01-01\",\"access_end\":\"2027-01-01\"}" > /dev/null
APP2=$(curl -s "$BASE/admin/applications" -H "Authorization: Bearer $ATOKEN" | J "['applications'][-1]['id']")
curl -s -X POST "$BASE/admin/applications/$APP2/reject" -H "Authorization: Bearer $ATOKEN" -H 'Content-Type: application/json' -d '{"note":"用途不充分"}' > /dev/null
CODE=$(curl -s -o /dev/null -w '%{http_code}' -X PUT "$BASE/applications/$APP2" -H "Authorization: Bearer $RTOKEN" -H 'Content-Type: application/json' -d '{"purpose":"换个用途"}')
[ "$CODE" = "404" ] || [ "$CODE" = "405" ] || { echo "FAIL: 被拒申请可被修改 ($CODE)"; exit 1; }
CODE=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/applications/$APP2/approve" -H "Authorization: Bearer $ATOKEN" -H 'Content-Type: application/json' -d '{}')
[ "$CODE" = "422" ] || { echo "FAIL: 被拒申请可被再批准 ($CODE)"; exit 1; }
echo "OK"

echo ""
echo "全部端到端检查通过"
