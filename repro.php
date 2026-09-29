<?php

declare(strict_types=1);

/**
 * 调用方报的两个症状的最小复现。
 *
 *   php repro.php
 *
 * 期望：打印「没有复现到问题」并退 0；
 * 现状：打印三条症状并退 1。
 */

require __DIR__ . '/autoload.php';

use Arrdiff\ArrDiff;

$problems = [];

// 症状 1：'01' 与 1 被当成了同一个键。
$r1 = ArrDiff::diff(['1' => 'x', 'k' => 'p'], ['01' => 'y', 'k' => 'p']);
if (!isset($r1['removed']['/1']) || !isset($r1['added']['/01'])) {
    $problems[] = sprintf(
        "①'01' 与 1 混同：期望 removed['/1'] + added['/01']，实际 added=%s removed=%s changed=%s",
        json_encode($r1['added'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        json_encode($r1['removed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        json_encode($r1['changed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

// 症状 2：两边完全一样的子数组也被报成了 changed。
$r2 = ArrDiff::diff(['cfg' => ['a' => 1, 'b' => 2]], ['cfg' => ['a' => 1, 'b' => 2]]);
if ($r2['changed'] !== []) {
    $problems[] = sprintf(
        "②等价子数组被报成 changed：期望空，实际 %s",
        json_encode($r2['changed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

// 症状 3：路径不是升序。
$r3 = ArrDiff::diff(['zeta' => 1, 'alpha' => 2, 'mu' => 3, 'beta' => 4], []);
$got = array_keys($r3['removed']);
$want = $got;
sort($want, SORT_STRING);
if ($got !== $want) {
    $problems[] = sprintf(
        "③路径不是升序：期望 %s，实际 %s",
        json_encode($want, JSON_UNESCAPED_SLASHES),
        json_encode($got, JSON_UNESCAPED_SLASHES)
    );
}

if ($problems === []) {
    echo "没有复现到问题\n";
    exit(0);
}

foreach ($problems as $p) {
    echo $p . "\n";
}

exit(1);
