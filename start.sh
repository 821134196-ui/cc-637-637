#!/usr/bin/env bash
# 科研数据集访问申请与引用追踪系统 —— 一键启动
# 用法: bash start.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS="$ROOT/.tools"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"
PHP_VERSION="8.4.23"

mkdir -p "$TOOLS"

# ---- 1. 准备 PHP 与 Composer（优先使用系统 php，否则下载静态构建） ----------
if command -v php >/dev/null 2>&1 && php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' 2>/dev/null; then
  PHP="$(command -v php)"
  echo "[start] 使用系统 PHP: $($PHP -r 'echo PHP_VERSION;')"
elif [ -x "$TOOLS/php" ]; then
  PHP="$TOOLS/php"
  echo "[start] 使用本地静态 PHP: $($PHP -r 'echo PHP_VERSION;')"
else
  echo "[start] 未检测到带 pdo_sqlite 的 PHP，下载静态 PHP $PHP_VERSION ..."
  curl -fsSL -o "$TOOLS/php.tar.gz" "https://dl.static-php.dev/static-php-cli/bulk/php-${PHP_VERSION}-cli-linux-x86_64.tar.gz"
  tar xzf "$TOOLS/php.tar.gz" -C "$TOOLS"
  rm "$TOOLS/php.tar.gz"
  PHP="$TOOLS/php"
fi

if command -v composer >/dev/null 2>&1; then
  COMPOSER="composer"
else
  if [ ! -f "$TOOLS/composer" ]; then
    echo "[start] 下载 Composer ..."
    curl -fsSL -o "$TOOLS/composer" "https://getcomposer.org/download/latest-stable/composer.phar"
    chmod +x "$TOOLS/composer"
  fi
  COMPOSER="$PHP $TOOLS/composer"
fi

# ---- 2. 后端依赖与初始化 ----------------------------------------------------
cd "$BACKEND"
if [ ! -d vendor ]; then
  echo "[start] 安装后端依赖 ..."
  $COMPOSER install --no-interaction --prefer-dist
fi
if [ ! -f .env ]; then
  cp .env.example .env
  $PHP artisan key:generate --no-interaction
fi
touch database/database.sqlite
echo "[start] 数据库迁移与种子 ..."
$PHP artisan migrate --seed --no-interaction --force

# ---- 3. 前端依赖 ------------------------------------------------------------
cd "$FRONTEND"
if [ ! -d node_modules ]; then
  echo "[start] 安装前端依赖 ..."
  npm install
fi

# ---- 4. 启动两个进程 --------------------------------------------------------
echo ""
echo "  后端 API:  http://127.0.0.1:8000"
echo "  前端页面:  http://127.0.0.1:5173"
echo ""
echo "  演示账号:"
echo "    研究员  researcher@school.edu / research123"
echo "    管理员  admin@school.edu      / admin123"
echo ""
echo "  邮件通知为模拟发送，写入 backend/storage/logs/laravel.log"
echo "  按 Ctrl+C 停止"
echo ""

PIDS=()
cleanup() {
  echo ""
  echo "[start] 正在停止服务 ..."
  kill "${PIDS[@]}" 2>/dev/null || true
  wait "${PIDS[@]}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

cd "$BACKEND" && $PHP artisan serve --host=127.0.0.1 --port=8000 &
PIDS+=($!)

cd "$FRONTEND" && npm run dev -- --host 127.0.0.1 --port 5173 &
PIDS+=($!)

wait "${PIDS[@]}"
