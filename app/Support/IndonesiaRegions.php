<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class IndonesiaRegions
{
    /**
     * @var array<int, array{code: string, name: string, regencies: array<int, array{code: string, name: string}>}>|null
     */
    private static ?array $regions = null;

    /** @var array<string, array<int, array{code: string, name: string}>> */
    private static array $districts = [];

    /** @var array<string, array<int, array{code: string, name: string}>> */
    private static array $villages = [];

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

    /** @return array<int, array{code: string, name: string}> */
    public static function districtsFor(string $regencyCode): array
    {
        if (preg_match('/^\d{2}\.\d{2}$/', $regencyCode) !== 1) {
            return [];
        }

        return self::$districts[$regencyCode] ??= self::loadChildren('indonesia-districts', $regencyCode);
    }

    /** @return array<int, array{code: string, name: string}> */
    public static function villagesFor(string $districtCode): array
    {
        if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $districtCode) !== 1) {
            return [];
        }

        $regencyCode = substr($districtCode, 0, 5);

        return self::$villages[$districtCode] ??= self::loadChildren(
            'indonesia-villages',
            $regencyCode,
            $districtCode,
        );
    }

    public static function regencyBelongsToProvince(?string $regencyCode, ?string $provinceCode): bool
    {
        return is_string($regencyCode)
            && in_array($regencyCode, self::regencyCodesFor($provinceCode), true);
    }

    public static function districtBelongsToRegency(?string $districtCode, ?string $regencyCode): bool
    {
        return is_string($districtCode) && is_string($regencyCode)
            && in_array($districtCode, array_column(self::districtsFor($regencyCode), 'code'), true);
    }

    public static function villageBelongsToDistrict(?string $villageCode, ?string $districtCode): bool
    {
        return is_string($villageCode) && is_string($districtCode)
            && in_array($villageCode, array_column(self::villagesFor($districtCode), 'code'), true);
    }

    /** @return array<int, array{code: string, name: string}> */
    private static function loadChildren(string $directory, string $fileCode, ?string $filterCode = null): array
    {
        $path = resource_path("data/{$directory}/{$fileCode}.json");

        if (! File::exists($path)) {
            return [];
        }

        $children = File::json($path);

        return $filterCode === null
            ? $children
            : collect($children)
                ->filter(fn (array $child) => str_starts_with($child['code'], $filterCode.'.'))
                ->values()
                ->all();
    }
}
