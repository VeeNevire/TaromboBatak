<?php

namespace App\Http\Controllers;

use App\Support\IndonesiaRegions;
use Illuminate\Http\JsonResponse;

class IndonesiaRegionController extends Controller
{
    public function districts(string $regencyCode): JsonResponse
    {
        abort_unless(
            collect(IndonesiaRegions::all())
                ->flatMap(fn (array $province) => $province['regencies'])
                ->contains('code', $regencyCode),
            404,
        );

        return response()->json(['data' => IndonesiaRegions::districtsFor($regencyCode)]);
    }

    public function villages(string $districtCode): JsonResponse
    {
        return response()->json(['data' => IndonesiaRegions::villagesFor($districtCode)]);
    }
}
