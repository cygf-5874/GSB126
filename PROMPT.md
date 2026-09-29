递归比较数组的时候，数字键和字符串键被当成了同一种，含 `/` 与 `~` 的路径也会撞车。
arrdiff 是 PHP 8 的递归数组差异库（php-cli，无 composer，无第三方依赖），构建不需要额外步骤，
自检走 `scripts/check.sh`（`check/` 是固定验收程序，别改），既有用例走 `php tests/run.php`。

既有用例只用字符串键、也只比一层，所以都是绿的 —— 问题位置要自己复现定位。
`repro.php` 是调用方给的最小复现；`ArrDiff::apply()` 目前还是空实现。

任务：按 README「对外保证」的 10 条修正 `diff()`，并实现 `apply()`，让固定件全过。

验收：
- php tests/run.php 全绿；
- php check/check.php 退出码 0，10 个场景全过
  （keys 2 + shape 2 + value 2 + order 1 + edge 1 + roundtrip 2）。

约束：
1. 不改 `check/`；可以新增类文件。
2. 对外 API 名与签名不许改；`tests/run.php` 里的既有用例一条都不许删或改。
3. 不许用 composer，不许引入任何第三方库；不许用 mbstring 扩展。
4. 路径必须按 JSON Pointer 转义；结果不许依赖内部哈希顺序，apply 不得修改 base。