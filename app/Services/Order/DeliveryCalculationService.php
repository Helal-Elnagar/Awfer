<?php

namespace App\Services\Order;

use App\Models\Branch;
use App\Models\City;
use App\Models\User;
use App\Repositories\AddressRepository;
use App\Repositories\BranchRepository;
use App\Services\CityBoundaryService;
use App\Services\PolygonService;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryCalculationService
 *
 * Handles delivery fee calculation, branch selection, and distance calculations
 * Uses repositories for all database operations
 */
class DeliveryCalculationService
{
    protected $addressRepository;
    protected $branchRepository;
    protected $polygonService;
    protected $cityBoundaryService;

    public function __construct(
        AddressRepository $addressRepository,
        BranchRepository $branchRepository,
        PolygonService $polygonService,
        CityBoundaryService $cityBoundaryService
    ) {
        $this->addressRepository = $addressRepository;
        $this->branchRepository = $branchRepository;
        $this->polygonService = $polygonService;
        $this->cityBoundaryService = $cityBoundaryService;
    }

    /**
     * Calculate delivery details including branch, distance, and fee
     * Branch selection is ALWAYS based on user coordinates (polygon check)
     * regardless of order_type or delivery_type
     *
     * @param User $user
     * @param array $data
     * @return array ['branch_id', 'distance', 'delivery_fee']
     * @throws \Exception
     */
public function calculateDeliveryDetails(User $user, array $data): array
{
    $deliveryType = $data['delivery_type'] ?? 'immediate';
    $address = $this->getUserAddress($user, $data);
    $city = $address ? $this->resolveCity($address) : null;

    // The address must fall inside the selected city's delivery boundary.
    if ($address && $city) {
        $boundaryError = $this->cityBoundaryService->boundaryError($city->id, $address->lat, $address->lng);
        if ($boundaryError) {
            throw new \Exception(__($boundaryError));
        }
    }

    // Default: no distance
    $distance = 0.0;
    $branchId = null;

    // The delivery fee is determined by the City of the selected address
    // (falling back to the site default when the city has no fee configured).
    $deliveryFee = $this->deliveryFeeFor($deliveryType, $city);

    if ($address && $address->lat !== null && $address->lng !== null) {
        $branch = $this->findBranchByLocation((float) $address->lat, (float) $address->lng);

        if ($branch) {
            $branchId = $branch->id;

            if ($branch->latitude && $branch->longitude) {
                $distance = $this->calculateDistance(
                    (float) $address->lat,
                    (float) $address->lng,
                    (float) $branch->latitude,
                    (float) $branch->longitude
                );
            }
        }
    }

    return [
        'branch_id' => $branchId,
        'distance' => round($distance, 2),
        'delivery_fee' => round($deliveryFee, 2),
        'city_id' => $city->id ?? null,
    ];
}

    /**
     * Expected delivery fee for the given user/order payload.
     * Used to sanity-check the calculated fee against the configured city/site fee.
     *
     * @param User $user
     * @param array $data
     * @return float
     */
    public function expectedDeliveryFee(User $user, array $data): float
    {
        $deliveryType = $data['delivery_type'] ?? 'immediate';
        $address = $this->getUserAddress($user, $data);
        $city = $address ? $this->resolveCity($address) : null;

        return $this->deliveryFeeFor($deliveryType, $city);
    }

    /**
     * Recompute the order total using an authoritative delivery fee.
     * Removes the cart's previously stored delivery fee and adds the given one.
     *
     * @param mixed $cart
     * @param float $deliveryFee
     * @return float
     */
    public function totalWithDeliveryFee($cart, float $deliveryFee): float
    {
        $oldDeliveryFee = (float) ($cart->delivery_fee ?? 0);
        $base = max(0.0, (float) ($cart->total ?? 0) - $oldDeliveryFee);

        return round($base + $deliveryFee, 2);
    }

    /**
     * Resolve the City linked to the address, if any.
     *
     * @param object $address
     * @return City|null
     */
    private function resolveCity($address): ?City
    {
        if (empty($address->city_id)) {
            return null;
        }

        return City::query()->find($address->city_id);
    }

    /**
     * Delivery fee for the given delivery type and city.
     * Uses the city's configured fee when available, otherwise the site default.
     *
     * @param string $deliveryType
     * @param City|null $city
     * @return float
     */
    private function deliveryFeeFor(string $deliveryType, ?City $city): float
    {
        if ($deliveryType === 'pickup') {
            return 0.0;
        }

        if ($city && $city->delivery_fee !== null && $city->delivery_fee !== '') {
            return (float) $city->delivery_fee;
        }

        return (float) getSiteSetting('delivery_fee', 0);
    }




    /**
     * Get the delivery fee of the City associated with the selected address.
     *
     * @param User $user
     * @param array $data
     * @return float
     */
    private function getCityDeliveryFee(User $user, array $data): float
    {
        $addressId = $data['address_id'] ?? null;
        if (!$addressId) {
            return 0.0;
        }

        $address = $this->addressRepository->getUserAddress($user, (int) $addressId);
        if (!$address || !$address->city_id) {
            return 0.0;
        }

        $city = $address->city;
        return $city ? (float) ($city->delivery_fee ?? 0) : 0.0;
    }

    /**
     * Get user address from data or database
     * Supports multiple coordinate formats: lat/lng, latitude/longitude, gift_lat/gift_lng, gift_latitude/gift_longitude
     *
     * @param User $user
     * @param array $data
     * @return \App\Models\Address|object|null
     */
    private function getUserAddress(User $user, array $data)
    {
        // Priority 1: Use address_id if provided
        if (isset($data['address_id'])) {
            $address = $this->addressRepository->getUserAddress($user, $data['address_id']);
            if ($address) {
                // Normalize field names to lat/lng
                return (object) [
                    'lat' => $address->latitude ?? $address->lat,
                    'lng' => $address->longitude ?? $address->lng,
                    'city_id' => $address->city_id ?? null,
                ];
            }
        }

        // Priority 2: Gift order coordinates (gift_latitude/gift_longitude or gift_lat/gift_lng)
        $orderType = $data['order_type'] ?? null;
        if ($orderType === 'gift') {
            $lat = $data['gift_latitude'] ?? $data['gift_lat'] ?? null;
            $lng = $data['gift_longitude'] ?? $data['gift_lng'] ?? null;

            if ($lat !== null && $lng !== null) {
                return (object) [
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }
        }

        // Priority 3: Direct coordinates (latitude/longitude or lat/lng)
        $lat = $data['latitude'] ?? $data['lat'] ?? null;
        $lng = $data['longitude'] ?? $data['lng'] ?? null;

        if ($lat !== null && $lng !== null) {
            return (object) [
                'lat' => $lat,
                'lng' => $lng,
                'city_id' => $data['city_id'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Find branch by location using polygon containment
     * Checks if user coordinates are within any branch's delivery polygon
     *
     * @param float $userLat
     * @param float $userLng
     * @return Branch|null
     * @throws \Exception
     */
    private function findBranchByLocation(float $userLat, float $userLng): ?Branch
    {
        $branches = $this->branchRepository->getActive();

        // إذا كان المشروع لا يدعم نظام الفروع أو لا توجد فروع مفعلة
        // فلا نرمي استثناءً ونسمح بإتمام العملية بدون ربط بفرع
        if ($branches->isEmpty()) {
            return null;
        }

        // إيجاد أول فرع يحتوي إحداثيات المستخدم ضمن مضلع التوصيل
        foreach ($branches as $branch) {
            if ($this->isPointInPolygon($userLat, $userLng, $branch)) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if point is within branch delivery polygon
     *
     * @param float $lat
     * @param float $lng
     * @param Branch $branch
     * @return bool
     */
    private function isPointInPolygon(float $lat, float $lng, Branch $branch): bool
    {
        if (empty($branch->polygon)) {
            return false; // No polygon defined, reject
        }

        $polygon = json_decode($branch->polygon, true);

        if (!is_array($polygon) || empty($polygon)) {
            return false; // Invalid polygon, reject
        }

        // Handle Leaflet polygon format (array of arrays)
        $points = $polygon[0] ?? $polygon;

        if (!is_array($points) || count($points) < 3) {
            return false; // Not enough points for a polygon, reject
        }

        // Ray casting algorithm for point-in-polygon test
        $intersections = 0;
        $vertices = count($points);

        for ($i = 0; $i < $vertices; $i++) {
            $vertex1 = $points[$i];
            $vertex2 = $points[($i + 1) % $vertices];

            // Handle both array formats: ['lat' => x, 'lng' => y] and [lat, lng]
            $v1Lat = is_array($vertex1) ? ($vertex1['lat'] ?? $vertex1[0] ?? null) : null;
            $v1Lng = is_array($vertex1) ? ($vertex1['lng'] ?? $vertex1[1] ?? null) : null;
            $v2Lat = is_array($vertex2) ? ($vertex2['lat'] ?? $vertex2[0] ?? null) : null;
            $v2Lng = is_array($vertex2) ? ($vertex2['lng'] ?? $vertex2[1] ?? null) : null;

            if ($v1Lat === null || $v1Lng === null || $v2Lat === null || $v2Lng === null) {
                continue; // Skip invalid vertices
            }

            if ($v1Lng == $v2Lng) {
                continue; // Vertical line, skip
            }

            if ($lng < min($v1Lng, $v2Lng) || $lng > max($v1Lng, $v2Lng)) {
                continue; // Point is outside the longitude range
            }

            // Calculate intersection point
            $xIntersection = ($lng - $v1Lng) * ($v2Lat - $v1Lat) / ($v2Lng - $v1Lng) + $v1Lat;

            if ($lat < $xIntersection) {
                $intersections++;
            }
        }

        // Odd number of intersections means point is inside polygon
        return ($intersections % 2) != 0;
    }

    /**
     * Calculate delivery fee based on distance and site settings
     *
     * @param float $distance Distance in kilometers
     * @return float
     */
    private function calculateDeliveryFee(float $distance): float
    {
        $fixedDeliveryFee = (float) getSiteSetting('delivery_fee', 0);
        $perKmDeliveryFee = (float) getSiteSetting('delivery_per_km_fee', 0);
        $deliveryDistanceThreshold = (float) getSiteSetting('delivery_distance_threshold', 0);

        // Fixed fee within threshold, per-km fee beyond threshold
        if ($distance <= $deliveryDistanceThreshold) {
            return $fixedDeliveryFee;
        }

        return $fixedDeliveryFee + ($distance * $perKmDeliveryFee);
    }
}

