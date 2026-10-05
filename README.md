# 科研数据集访问申请与引用追踪系统

学校公开数据集目录；研究人员按用途申请原始数据访问，获批后在授权范围内下载；发表成果后登记引用编号并关联实际使用的数据版本。

## 技术栈

- 后端: PHP 8.3 + Laravel 13（Eloquent + SQLite，数据文件存本地 `server/storage/app/private/datasets/`）
- 前端: React 18 + Vite（构建产物由 Laravel 托管，单端口访问）
- 邮件: 模拟发送（`MAIL_MAILER=log`，写入 `server/storage/logs/laravel.log`）

## 一键启动

```bash
./start.sh
```

完成依赖安装、数据库迁移与演示数据填充、前端构建，然后在 `http://127.0.0.1:8000/` 启动服务（`PORT=8080 ./start.sh` 可换端口）。重复启动不会清空已有数据。

演示账号（密码均为 `password`）：

| 角色 | 账号 |
|---|---|
| 研究人员 | researcher@example.edu |
| 研究人员 | researcher2@example.edu |
| 审批人 | approver@example.edu |

## 核心规则

- **公开目录不泄露受限字段**：`/api/catalog` 只返回公开字段名与公开列预览；受限字段清单需登录后在申请表单中查看。
- **申请要素**：数据集版本 + 字段范围 + 使用期限 + 用途；审批通过后生成授权（Grant），快照锁定版本、字段、有效期。
- **数据集更新不扩大已有授权**：新版本发布后，旧授权仍下载旧版本、旧字段范围。
- **被拒申请不可改用途**：被拒申请不可编辑、不可再审批，改变用途必须重新提交申请。
- **撤回/到期立即失效**：下载链接（token）每次请求都校验授权状态，撤回或到期后返回 410；已登记的成果引用保留。
- **下载参数不可越权**：`?fields=` 只能缩小获批字段范围，请求未获准字段返回 403。

## 页面

- `/` 公开目录与数据集详情（公开字段预览）
- `/apply/:id` 提交访问申请（选版本、字段、期限、用途）
- `/applications` 我的申请（状态跟踪，被拒可一键重新申请）
- `/grants` 已获批范围与可用下载（生成下载链接）
- `/citations` 成果引用登记册（公开）与登记入口
- `/admin` 审批管理（审批申请、撤回授权、发布新版本、新建数据集）

## 验证

```bash
# 单元/特性测试（20 个，含字段权限、版本锁定、链接失效等安全规则）
cd server && ../.bin/php artisan test

# 端到端验证（需服务已启动）：完整走通申请→审批→下载→更新→撤回流程
./verify-e2e.sh
```

## 目录结构

```
server/            Laravel 后端（API + SPA 托管）
  app/Models/      Dataset, DatasetVersion, DatasetField, AccessApplication, Grant, DownloadToken, Citation
  app/Http/Controllers/  Catalog / Application / Approval / Grant / Download / Citation / DatasetAdmin
  tests/Feature/   AccessControlTest, AuthTest
web/               React 前端（Vite，构建输出到 server/public/app）
start.sh           一键启动
verify-e2e.sh      端到端验证脚本
```
