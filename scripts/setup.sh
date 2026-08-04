#!/bin/bash
#
# 项目初始化安装脚本
# 新环境 clone 后运行一次:  bash scripts/setup.sh
#
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

echo ""
echo "=== 项目初始化配置 ==="

# -------------------------------------------------------
# 1. 安装 pre-commit hook
# -------------------------------------------------------
if [ -d ".git" ]; then
    cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
    echo -e "${GREEN}✅ pre-commit hook 已安装${NC}"
else
    echo -e "${RED}⚠️  未检测到 .git 目录，跳过 hook 安装${NC}"
fi

# -------------------------------------------------------
# 2. 检查存储目录
# -------------------------------------------------------
for dir in storage storage/cache storage/logs storage/sessions storage/framework storage/uploads; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        chmod 0777 "$dir"
        echo "✅ 创建目录: $dir"
    fi
done

# -------------------------------------------------------
# 3. 检查 SQLite 数据库
# -------------------------------------------------------
if [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
    chmod 0666 database/database.sqlite
    echo "✅ 创建 SQLite 数据库文件"
fi

# -------------------------------------------------------
# 4. 检查 .env 文件
# -------------------------------------------------------
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}✅ 已从 .env.example 创建 .env${NC}"
        echo "⚠️  请编辑 .env 填入实际配置"
    else
        echo -e "${RED}⚠️  未找到 .env.example，请手动创建 .env${NC}"
    fi
fi

# -------------------------------------------------------
echo ""
echo -e "${GREEN}✅ 初始化完成${NC}"
echo "   下次 git commit 时将自动执行 Admin 模板代码检查"
echo "   如需手动运行检查: bash scripts/git-hooks/pre-commit"