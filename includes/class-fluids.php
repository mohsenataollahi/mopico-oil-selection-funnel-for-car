<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class Fluids
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'engine_oil' => 'روغن موتور',
            'at_gear' => 'گیربکس اتوماتیک',
            'cvt' => 'CVT',
            'mt_gear' => 'روغن گیربکس دستی',
            'coolant' => 'مایع خنک‌کننده',
            'brake' => 'روغن ترمز',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function specs(): array
    {
        return [
            'displacement' => 'حجم موتور (لیتر)',
            'power' => 'حداکثر قدرت موتور (اسب بخار)',
            'valves' => 'تعداد سوپاپ',
            'year_start' => 'شروع دوره ساخت',
            'year_end' => 'پایان دوره ساخت',
        ];
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, array{volume: string, products: list<int>}>
     */
    public static function readStored(array $stored): array
    {
        $read = [];
        foreach (self::all() as $key => $label) {
            $row = $stored[$key] ?? [];
            $volume = '';
            $products = [];
            if (is_array($row)) {
                $volume = isset($row['volume']) && is_string($row['volume']) ? $row['volume'] : '';
                $rawProducts = $row['products'] ?? [];
                if (is_array($rawProducts)) {
                    foreach ($rawProducts as $id) {
                        $id = absint($id);
                        if ($id > 0) {
                            $products[] = $id;
                        }
                    }
                }
            }
            $read[$key] = [
                'volume' => $volume,
                'products' => $products,
            ];
        }

        return $read;
    }

    /**
     * @param array<string, mixed> $volumes
     * @param array<string, mixed> $picked
     * @param array<int, true> $allowedIds
     * @return array<string, array{volume: string, products: list<int>}>
     */
    public static function sanitizeRecommendations(array $volumes, array $picked, array $allowedIds): array
    {
        $clean = [];
        foreach (self::all() as $key => $label) {
            $volume = '';
            if (isset($volumes[$key]) && is_string($volumes[$key])) {
                $volume = sanitize_text_field($volumes[$key]);
            }

            $ids = [];
            $raw = $picked[$key] ?? [];
            if (is_array($raw)) {
                foreach ($raw as $id) {
                    $id = absint($id);
                    if ($id > 0 && isset($allowedIds[$id])) {
                        $ids[$id] = $id;
                    }
                }
            }

            $clean[$key] = [
                'volume' => $volume,
                'products' => array_values($ids),
            ];
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, string>
     */
    public static function readSpecs(array $stored): array
    {
        $read = [];
        foreach (self::specs() as $key => $label) {
            $value = $stored[$key] ?? '';
            $read[$key] = is_string($value) ? $value : '';
        }

        return $read;
    }

    /**
     * @param array<string, mixed> $posted
     * @return array<string, string>
     */
    public static function sanitizeSpecs(array $posted): array
    {
        $clean = [];
        foreach (self::specs() as $key => $label) {
            $value = '';
            if (isset($posted[$key]) && is_string($posted[$key])) {
                $value = sanitize_text_field($posted[$key]);
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
