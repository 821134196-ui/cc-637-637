#!/usr/bin/env bash
# 一键启动：迁移 + 首次填充 + 构建前端（如需）+ 启动服务
set -euo pipefail
cd "$(dirname "$0")"

PHP="$PWD/.bin/php"
[ -x "$PHP" ] || PHP="$(command -v php)"
COMPOSER="$PWD/.bin/composer.phar"

echo "==> 安装后端依赖"
if [ ! -d server/vendor ]; then
  "$PHP" "$COMPOSER" install --no-interaction --working-dir=server
fi

echo "==> 准备环境配置"
[ -f server/.env ] || cp server/.env.example server/.env
mkdir -p server/database
[ -f server/database/database.sqlite ] || touch server/database/database.sqlite

if [ ! -s server/database/database.sqlite ]; then
  echo "==> 首次启动：迁移并填充演示数据"
  (cd server && "$PHP" artisan migrate:fresh --seed --force)
else
  echo "==> 数据库已存在：仅执行增量迁移"
  (cd server && "$PHP" artisan migrate --force)
fi
(cd server && "$PHP" artisan key:generate --force --quiet)

echo "==> 构建前端"
if [ ! -d web/node_modules ]; then
  (cd web && npm install)
fi
(cd web && npm run build)

PORT="${PORT:-8000}"
echo ""
echo "==> 启动完成: http://127.0.0.1:${PORT}/"
echo "    演示账号: researcher@example.edu / approver@example.edu （密码 password）"
echo ""
cd server && exec "$PHP" artisan serve --host=0.0.0.0 --port="$PORT"
