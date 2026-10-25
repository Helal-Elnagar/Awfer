<?php

namespace App\Services;

use App\Models\City;

/**
 * CityBoundaryService
 *
 * Handles validation of coordinates against a city's delivery boundary.
 * Used at address save time and before order creation.
 */
class CityBoundaryService
{
    public function __construct(
        private readonly PolygonService $polygonService
    ) {}

    /**
     * Check whether the given coordinates fall inside the city's delivery boundary.
     * Returns null when the coordinates are valid (or no boundary is configured),
     * otherwise returns a translation key describing the problem.
     *
     * @param int|string|null $cityId
     * @param mixed $latitude
     * @param mixed $longitude
     * @return string|null
     */
    public function boundaryError($cityId, $latitude, $longitude): ?string
    {
        if (!$cityId || $latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        $city = City::find($cityId);
        if (!$city) {
            return 'apis.city_not_found';
        }

        $points = $city->boundaryPoints();
        if (empty($points)) {
            return null; // Legacy city without a boundary: allow
        }

        $inside = $this->polygonService->pointInPolygon(
            [(float) $latitude, (float) $longitude],
            $points
        );

        return $inside ? null : 'apis.address_outside_city_boundary';
    }
}
