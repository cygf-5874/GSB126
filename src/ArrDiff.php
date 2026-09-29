<?php

declare(strict_types=1);

namespace Arrdiff;

/**
 * 递归数组差异。
 *
 * 返回 ['added' => …, 'removed' => …, 'changed' => …]，路径用 JSON Pointer 风格。
 */
final class ArrDiff
{
    /**
     * @return array{
     *     added: array<string, mixed>,
     *     removed: array<string, mixed>,
     *     changed: array<string, array{from: mixed, to: mixed}>
     * }
     */
    public static function diff(array $a, array $b): array
    {
        $added = [];
        $removed = [];
        $changed = [];

        self::walk('', $a, $b, $added, $removed, $changed);

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    public static function apply(array $base, array $diff): array
    {
        throw new \LogicException('not implemented');
    }

    private static function walk(
        string $prefix,
        array $a,
        array $b,
        array &$added,
        array &$removed,
        array &$changed
    ): void {
        $na = self::normalizeKeys($a);
        $nb = self::normalizeKeys($b);

        $keys = [];
        foreach (array_keys($na) as $k) {
            $keys[$k] = true;
        }
        foreach (array_keys($nb) as $k) {
            $keys[$k] = true;
        }

        if ($a !== [] && array_is_list($a)) {
            $keys = [];
            $n = max(count($a), count($b));
            for ($i = 0; $i < $n; $i++) {
                $keys[$i] = true;
            }
        }

        foreach (array_keys($keys) as $k) {
            $path = $prefix . '/' . $k;
            $inA = array_key_exists($k, $na);
            $inB = array_key_exists($k, $nb);

            if ($inA && !$inB) {
                $removed[$path] = $na[$k];
                continue;
            }
            if (!$inA && $inB) {
                $added[$path] = $nb[$k];
                continue;
            }

            $va = $na[$k];
            $vb = $nb[$k];

            if (is_array($va) && is_array($vb)) {
                self::walk($path, $va, $vb, $added, $removed, $changed);
                $changed[$path] = ['from' => $va, 'to' => $vb];
                continue;
            }

            if (is_array($va) || is_array($vb)) {
                self::walk($path, $va, $vb, $added, $removed, $changed);
                continue;
            }

            if ($va === $vb) {
                continue;
            }

            $changed[$path] = ['from' => (string) $va, 'to' => (string) $vb];
        }
    }

    /**
     * @param array<array-key, mixed> $arr
     * @return array<array-key, mixed>
     */
    private static function normalizeKeys(array $arr): array
    {
        $out = [];

        foreach ($arr as $k => $v) {
            $key = is_numeric((string) $k) ? 0 + $k : $k;
            $out[$key] = $v;
        }

        return $out;
    }
}
