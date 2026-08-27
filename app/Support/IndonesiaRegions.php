<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class IndonesiaRegions
{
    /**
     * @var array<int, array{code: string, name: string, regencies: array<int, array{code: string, name: string}>}>|null
     */
    private static ?array $regions = null;

    /**
     * @return array<int, array{code: string, name: string, regencies: array<int, array{code: string, name: string}>}>
     */
    public static function all(): array
    {
        return self::$regions ??= File::json(resource_path('data/indonesia-regions.json'));
    }

    /** @return list<string> */
    public static function provinceCodes(): array
    {
        return array_column(self::all(), 'code');
    }

    /** @return list<string> */
    public static function regencyCodesFor(?string $provinceCode): array
    {
        foreach (self::all() as $province) {
            if ($province['code'] === $provinceCode) {
                return array_column($province['regencies'], 'code');
            }
        }

        return [];
    }
}
