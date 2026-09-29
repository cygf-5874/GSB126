<?php

declare(strict_types=1);

/**
 * 固定验收入口：arrdiff 的「对外保证」。**别改这个文件。**
 *
 * 用法：
 *   php check/check.php                  跑全部 8 个场景，全过才退 0
 *   php check/check.php -list            列出全部场景
 *   php check/check.php --only keys      只跑一组
 *   php check/check.php --only keys,edge 只跑指定的几组
 *
 * 六组共 10 个场景：
 *   keys  2 —— 整数键与数字样式字符串键严格区分；列表对映射按键比较；
 *   shape 2 —— 数组对标量记 changed 且不深入；等价子数组不产生条目；
 *   value 2 —— changed 保留原始类型；added / removed 的路径与值正确；
 *   order 1 —— 三个映射的路径按字节序升序，且多次调用结果一致；
 *   edge  1 —— 空数组的三种情形；
   roundtrip 2 —— JSON Pointer 转义与 apply 往返。
 *
 * 只依赖 PHP 8 标准库。判据完全确定：不读时间、不用随机源、不依赖 ini、
 * 不依赖 PHP 数组 hash 顺序。失败不早退；每个场景用 try/catch(\Throwable) 兜住后继续。
 */

require __DIR__ . '/../autoload.php';

use Arrdiff\ArrDiff;

final class CheckFailure extends \Exception
{
    public string $expected;

    public string $actual;

    public function __construct(string $message, string $expected = '-', string $actual = '-')
    {
        parent::__construct($message);
        $this->expected = $expected;
        $this->actual = $actual;
    }
}

function tk(string $name, string $why, callable $fn): array
{
    return ['name' => $name, 'why' => $why, 'fn' => $fn];
}

/** 逐值严格比较（顺序无关）；失败时把期望 / 实际都 var_export 出来。 */
function expect_same(mixed $want, mixed $got, string $what): void
{
    $w = canon($want);
    $g = canon($got);

    if ($w === $g) {
        return;
    }

    throw new CheckFailure(
        $what,
        export($w),
        export($g)
    );
}

function expect_true(bool $cond, string $what, string $expected, string $actual): void
{
    if ($cond) {
        return;
    }

    throw new CheckFailure($what, $expected, $actual);
}

/**
 * 把结果或期望值归一化：三个映射按路径字节序排序，便于顺序无关比较。
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

function export(mixed $v): string
{
    $s = var_export($v, true);
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;

    return strlen($s) > 320 ? substr($s, 0, 317) . '...' : $s;
}

/** 只跑一次，后续场景复用（同一对输入必须每次结果相同）。 */
function paths_of(array $map): array
{
    return array_keys($map);
}

function sorted_paths(array $map): array
{
    $keys = array_keys($map);
    sort($keys, SORT_STRING);

    return $keys;
}

function ascending(array $map): bool
{
    return paths_of($map) === sorted_paths($map);
}

// ---------------------------------------------------------------------------
// keys
// ---------------------------------------------------------------------------

$groups = [];

$groups['keys'] = [
    tk(
        'keys-numeric-forms-distinct',
        '整数键 1 与字符串键 \'01\' 必须不同（保证 2）',
        static function (): void {
            $r = ArrDiff::diff(['1' => 'x', 'k' => 'p'], ['01' => 'y', 'k' => 'p']);

            if (isset($r['changed']['/1']) && !isset($r['removed']['/1'])) {
                throw new CheckFailure(
                    '\'01\' 与 1 被当成了同一个键',
                    "added['/01']='y'、removed['/1']='x'",
                    'changed[\'/1\'] = ' . export($r['changed']['/1'] ?? null)
                );
            }

            expect_same(
                ['added' => ['/01' => 'y'], 'removed' => ['/1' => 'x'], 'changed' => []],
                $r,
                '两个键应当分别报 added / removed'
            );
        }
    ),
    tk(
        'list-vs-map-compared-by-key',
        '一侧不是列表时按键比较（保证 4）',
        static function (): void {
            $r = ArrDiff::diff([0 => 'a', 1 => 'b'], ['x' => 'a', 'y' => 'b']);

            expect_same(
                [
                    'added' => ['/x' => 'a', '/y' => 'b'],
                    'removed' => ['/0' => 'a', '/1' => 'b'],
                    'changed' => [],
                ],
                $r,
                '列表对映射应当按键比较，两处 added + 两处 removed'
            );
        }
    ),
];

$groups['shape'] = [
    tk(
        'array-vs-scalar-is-changed',
        '一侧标量一侧数组记 changed 且不深入（保证 3）',
        static function (): void {
            $r = ArrDiff::diff(['v' => ['x' => 1]], ['v' => 'plain']);

            expect_same(
                ['added' => [], 'removed' => [], 'changed' => ['/v' => ['from' => ['x' => 1], 'to' => 'plain']]],
                $r,
                '标量与数组的边界只记一条 changed'
            );
        }
    ),
    tk(
        'equal-subarray-produces-nothing',
        '结构等价的子数组不产生条目（保证 5）',
        static function (): void {
            $r = ArrDiff::diff(
                ['cfg' => ['a' => 1, 'b' => ['c' => 2]], 'n' => 1],
                ['cfg' => ['a' => 1, 'b' => ['c' => 2]], 'n' => 1]
            );

            expect_same(
                ['added' => [], 'removed' => [], 'changed' => []],
                $r,
                '完全相同的嵌套结构不得报出任何条目'
            );
        }
    ),
];

$groups['value'] = [
    tk(
        'changed-keeps-original-types',
        'changed 的 from / to 保留原始类型（保证 6）',
        static function (): void {
            $r = ArrDiff::diff(['flag' => true, 'n' => 1], ['flag' => 1, 'n' => '1']);

            $f = $r['changed']['/flag'] ?? null;
            $n = $r['changed']['/n'] ?? null;

            if (!is_array($f) || ($f['from'] ?? null) !== true || ($f['to'] ?? null) !== 1) {
                throw new CheckFailure(
                    'true → 1 的类型被抹平了',
                    "from=true(bool)、to=1(int)",
                    'from=' . export($f['from'] ?? null) . '、to=' . export($f['to'] ?? null)
                );
            }

            if (!is_array($n) || ($n['from'] ?? null) !== 1 || ($n['to'] ?? null) !== '1') {
                throw new CheckFailure(
                    '1 → \'1\' 的类型被抹平了',
                    "from=1(int)、to='1'(string)",
                    'from=' . export($n['from'] ?? null) . '、to=' . export($n['to'] ?? null)
                );
            }
        }
    ),
    tk(
        'added-removed-paths',
        'added / removed 的路径与值正确（保证 1 / 7）',
        static function (): void {
            $r = ArrDiff::diff(['a' => 1, 'b' => 2], ['b' => 2, 'c' => 3]);

            expect_same(
                ['added' => ['/c' => 3], 'removed' => ['/a' => 1], 'changed' => []],
                $r,
                '只在一侧出现的键应当分别进 added / removed'
            );
        }
    ),
];

$groups['order'] = [
    tk(
        'paths-ascending-and-stable',
        '路径按字节序升序且多次调用一致（保证 1 / 8）',
        static function (): void {
            $a = ['zeta' => 1, 'alpha' => 2, 'mu' => 3, 'beta' => 4];
            $r = ArrDiff::diff($a, []);

            foreach (['added', 'removed', 'changed'] as $bucket) {
                if (!ascending($r[$bucket])) {
                    throw new CheckFailure(
                        "{$bucket} 的路径不是升序",
                        implode(',', sorted_paths($r[$bucket])),
                        implode(',', paths_of($r[$bucket]))
                    );
                }
            }

            $again = ArrDiff::diff($a, []);

            if ($again !== $r) {
                throw new CheckFailure(
                    '同一输入两次调用结果不同',
                    '逐键逐值完全相同',
                    export($again)
                );
            }
        }
    ),
];

$groups['edge'] = [
    tk(
        'empty-cases',
        '空数组的三种情形（保证 7）',
        static function (): void {
            expect_same(
                ['added' => [], 'removed' => [], 'changed' => []],
                ArrDiff::diff([], []),
                '空对空'
            );

            expect_same(
                ['added' => ['/x' => 1, '/y' => 2], 'removed' => [], 'changed' => []],
                ArrDiff::diff([], ['x' => 1, 'y' => 2]),
                '$a 为空'
            );

            expect_same(
                ['added' => [], 'removed' => ['/x' => 1, '/y' => 2], 'changed' => []],
                ArrDiff::diff(['x' => 1, 'y' => 2], []),
                '$b 为空'
            );
        }
    ),
];

// ---------------------------------------------------------------------------
// roundtrip：JSON Pointer 转义与 apply 往返
// ---------------------------------------------------------------------------

$groups['roundtrip'] = [
    tk('pointer-escape', '含 / 与 ~ 的键必须使用 JSON Pointer 转义（保证 9）', static function (): void {
        $a = ['a/b' => 1, '~x' => 2, '01' => 3];
        $b = ['a/b' => 2, '~x' => 3, 'x' => 'new'];
        $d = ArrDiff::diff($a, $b);
        expect_true(isset($d['changed']['/a~1b']), '路径 /a~1b 存在', '/a~1b', implode(',', array_keys($d['changed'])));
        expect_true(isset($d['changed']['/~0x']), '路径 /~0x 存在', '/~0x', implode(',', array_keys($d['changed'])));
        $got = ArrDiff::apply($a, $d);
        if ($got !== $b) {
            throw new CheckFailure('apply 后等于目标', export($b), export($got));
        }
        if ($a !== ['a/b' => 1, '~x' => 2, '01' => 3]) {
            throw new CheckFailure('apply 不修改 base', export($a), export($a));
        }
    }),
    tk('list-roundtrip', '列表、标量与数组变化可由 apply 精确还原（保证 10）', static function (): void {
        $a = [0 => 'a', 1 => ['x' => 1], 2 => 3];
        $b = [0 => 'a', 1 => ['x' => 2], 3 => 4];
        $d = ArrDiff::diff($a, $b);
        $got = ArrDiff::apply($a, $d);
        if ($got !== $b) {
            throw new CheckFailure('列表 apply 往返', export($b), export($got));
        }
    }),
];
// ---------------------------------------------------------------------------
// 运行器
// ---------------------------------------------------------------------------

$flat = [];
foreach ($groups as $group => $entries) {
    foreach ($entries as $entry) {
        $flat[] = [$group, $entry['name'], $entry['why'], $entry['fn']];
    }
}

$argv = $argv ?? [];
$doList = false;
$only = null;

for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];

    if ($arg === '-list' || $arg === '--list') {
        $doList = true;
    } elseif ($arg === '--only' || $arg === '--group') {
        $i++;
        if ($i >= count($argv)) {
            fwrite(STDERR, "--only 需要一个组名（keys/shape/value/order/edge）\n");
            exit(2);
        }
        $only = [];
        foreach (explode(',', $argv[$i]) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $only[$part] = true;
            }
        }
    } elseif ($arg === '-h' || $arg === '--help') {
        echo "用法: php check/check.php [-list] [--only <组名>]\n";
        exit(0);
    } else {
        fwrite(STDERR, "未知参数: {$arg}\n");
        exit(2);
    }
}

if ($doList) {
    $lastGroup = null;
    foreach ($flat as [$group, $name, $why, $_fn]) {
        if ($group !== $lastGroup) {
            printf("== 组 %s ==\n", $group);
            $lastGroup = $group;
        }
        printf("[%-6s] %-32s %s\n", $group, $name, $why);
    }
    exit(0);
}

$selected = [];
foreach ($flat as $entry) {
    if ($only === null || isset($only[$entry[0]])) {
        $selected[] = $entry;
    }
}

if ($selected === []) {
    fwrite(STDERR, "没有匹配的场景\n");
    exit(2);
}

$passed = 0;
$failed = 0;
$lastGroup = null;

foreach ($selected as [$group, $name, $_why, $fn]) {
    if ($group !== $lastGroup) {
        printf("== 组 %s ==\n", $group);
        $lastGroup = $group;
    }

    try {
        $fn();
    } catch (CheckFailure $exc) {
        $failed++;
        printf("FAIL %s/%s  期望=%s 实际=%s（%s）\n", $group, $name, $exc->expected, $exc->actual, $exc->getMessage());
        continue;
    } catch (\Throwable $exc) {
        $failed++;
        printf("FAIL %s/%s  期望=场景正常结束 实际=%s: %s\n", $group, $name, get_class($exc), $exc->getMessage());
        continue;
    }

    $passed++;
    printf("PASS %s/%s\n", $group, $name);
}

$total = count($selected);
printf("结果：通过 %d/%d\n", $passed, $total);
exit($failed === 0 ? 0 : 1);
