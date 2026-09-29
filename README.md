# arrdiff —— 递归数组差异

`arrdiff` 是 PHP 8 的递归数组差异库，只依赖标准库（php-cli，无 composer，无第三方依赖）。

跑法：

```bash
php tests/run.php      # 既有回归用例
php repro.php          # 调用方最小复现
bash scripts/check.sh  # 固定验收（check/ 别改）
```

## 对外保证

`ArrDiff::diff(array $a, array $b): array`，返回三个映射：

```php
[
    'added'   => [ '/path' => <新值>,            ... ],
    'removed' => [ '/path' => <原值>,            ... ],
    'changed' => [ '/path' => ['from' => <原值>, 'to' => <新值>], ... ],
]
```

1. **路径格式与顺序**：路径用 **JSON Pointer** 风格（`/a/b/0`）标识；三个映射的键都必须**按路径字符串升序**排列（`strcmp` 意义上的字节序）。
2. **键必须严格区分**：整数键与「数字样式的字符串键」是不同的键 —— `1`、`'01'`、`' 1'`、`'1.0'` 是**四个互不相同的键**，不得互相匹配。实现不得把键先转成数值再比较。
3. **数组与标量的边界**：同一路径上一侧是数组、另一侧是标量（或 `null`）时，记为一条 `changed`，**不再往里深入**，也不得抛异常。
4. **列表语义**：只有当**两侧都是**列表（连续整数键 `0..n-1`）时才按**位置**比较；只要有一侧不是列表，就按**键**比较。例如 `[0 => 'a', 1 => 'b']` 与 `['x' => 'a', 'y' => 'b']` 是两处 `removed`（`/0`、`/1`）加两处 `added`（`/x`、`/y`），不是「无变化」。
5. **不报无变化的变化**：两边结构等价的子数组不产生任何条目（`changed` 里不得出现它，也不得出现它的子路径）。
6. **保留原始类型**：`changed` 的 `from` / `to`、`added` / `removed` 的值都保留**原始的 PHP 类型**，不得字符串化。`true` 与 `1`、`1` 与 `'1'` 是不同的值。
7. **空数组**：空数组对空数组返回三个空数组；`$a` 为空时 `$b` 的内容全部进 `added`；`$b` 为空时 `$a` 的内容全部进 `removed`。
8. **确定性**：同一对输入多次调用，返回的数组必须**逐键、逐值、逐顺序**完全相同；结果不得依赖 PHP 数组的内部哈希顺序。

9. **JSON Pointer 转义**：路径 token 中的 `~` 必须写成 `~0`、`/` 必须写成 `~1`；
    键 `a/b` 与 `~x` 不能生成有歧义的路径。
10. **可应用性**：`ArrDiff::apply(array $base, array $diff): array` 必须在不修改 `$base`
    的前提下，把 `diff()` 的结果精确应用到基准数组；对列表、映射、标量和 `null` 都保持
    PHP 原始类型，满足 `apply($a, diff($a, $b)) === $b`。
## 公开接口

```php
namespace Arrdiff;

final class ArrDiff
{
    /** @return array{added: array<string,mixed>, removed: array<string,mixed>, changed: array<string,array{from:mixed,to:mixed}>} */
    public static function diff(array $a, array $b): array;
}
```

## 本次的目标

`src/ArrDiff.php` 里已经有一版能跑通既有用例的实现，但它在上面 8 条保证里**有若干条没做到**（既有用例只用字符串键、也只比一层，碰不到这些位置）。把 `src/ArrDiff.php` 修到满足全部 8 条，同时保持 `tests/run.php` 全绿。
