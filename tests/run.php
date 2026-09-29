<?php

declare(strict_types=1);

/**
 * arrdiff 回归用例：12 个。
 *
 * 覆盖既有能力：只用字符串键、只比一层、值都是字符串或整数。
 * 跑法：php tests/run.php —— 全过打印「通过 12/12」并退 0，否则退 1。
 */

require __DIR__ . '/../autoload.php';

use Arrdiff\ArrDiff;

$cases = [];

$cases[] = ['完全相同', ArrDiff::diff(['a' => 'x'], ['a' => 'x']), ['added' => [], 'removed' => [], 'changed' => []]];
$cases[] = ['空对空', ArrDiff::diff([], []), ['added' => [], 'removed' => [], 'changed' => []]];
$cases[] = ['$a 为空全部进 added', ArrDiff::diff([], ['u' => 1, 'v' => 2]), ['added' => ['/u' => 1, '/v' => 2], 'removed' => [], 'changed' => []]];
$cases[] = ['$b 为空全部进 removed', ArrDiff::diff(['u' => 1, 'v' => 2], []), ['added' => [], 'removed' => ['/u' => 1, '/v' => 2], 'changed' => []]];
$cases[] = ['只在 b 里的键', ArrDiff::diff(['a' => 'x'], ['a' => 'x', 'b' => 'y']), ['added' => ['/b' => 'y'], 'removed' => [], 'changed' => []]];
$cases[] = ['只在 a 里的键', ArrDiff::diff(['a' => 'x', 'b' => 'y'], ['a' => 'x']), ['added' => [], 'removed' => ['/b' => 'y'], 'changed' => []]];
$cases[] = ['同键不同值记 changed', ArrDiff::diff(['name' => 'old'], ['name' => 'new']), ['added' => [], 'removed' => [], 'changed' => ['/name' => ['from' => 'old', 'to' => 'new']]]];
$cases[] = ['三种变化混合', ArrDiff::diff(['a' => 'x', 'gone' => 'g'], ['a' => 'y', 'fresh' => 'f']), ['added' => ['/fresh' => 'f'], 'removed' => ['/gone' => 'g'], 'changed' => ['/a' => ['from' => 'x', 'to' => 'y']]]];
$cases[] = ['键顺序不同但内容相同', ArrDiff::diff(['b' => 2, 'a' => 1], ['a' => 1, 'b' => 2]), ['added' => [], 'removed' => [], 'changed' => []]];
$cases[] = ['值为空字符串也算变化', ArrDiff::diff(['a' => 'x'], ['a' => '']), ['added' => [], 'removed' => [], 'changed' => ['/a' => ['from' => 'x', 'to' => '']]]];
$cases[] = ['字母键的顺序无关紧要', ArrDiff::diff(['m' => 'p', 's' => 'x'], ['m' => 'q', 's' => 'x']), ['added' => [], 'removed' => [], 'changed' => ['/m' => ['from' => 'p', 'to' => 'q']]]];
$cases[] = ['多键全部只在一侧', ArrDiff::diff(['p' => 'p', 'q' => 'q'], ['r' => 'r']), ['added' => ['/r' => 'r'], 'removed' => ['/p' => 'p', '/q' => 'q'], 'changed' => []]];

/**
 * 归一化：把三个映射按路径排序后逐值严格比较（顺序无关、类型敏感）。
 *
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function canon(array $r): array
{
    $out = [];

    foreach (['added', 'removed', 'changed'] as $bucket) {
        $map = $r[$bucket] ?? [];
        ksort($map, SORT_STRING);
        $out[$bucket] = $map;
    }

    return $out;
}

$passed = 0;
$failed = 0;

foreach ($cases as [$name, $got, $want]) {
    if (canon($got) === canon($want)) {
        $passed++;
        continue;
    }

    $failed++;
    printf(
        "FAIL %s\n  期望=%s\n  实际=%s\n",
        $name,
        var_export(canon($want), true),
        var_export(canon($got), true)
    );
}

$total = count($cases);
printf("通过 %d/%d\n", $passed, $total);

exit($failed === 0 ? 0 : 1);
