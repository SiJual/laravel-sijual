<?php

namespace App\Helpers;

class GeoHelper
{
    /**
     * Calculate a new coordinate given a starting coordinate, distance, and bearing.
     * Uses the Haversine formula (Earth radius = 6371km).
     *
     * @param float $lat Starting latitude
     * @param float $lng Starting longitude
     * @param float $distanceMeters Distance to move in meters
     * @param float $bearingDegrees Direction in degrees (0-360, 0 = North)
     * @return array{lat: float, lng: float}
     */
    public static function calculateOffsetCoordinates(float $lat, float $lng, float $distanceMeters, float $bearingDegrees): array
    {
        $R = 6371000; // Earth radius in meters
        $d = $distanceMeters;

        $lat1 = deg2rad($lat);
        $lon1 = deg2rad($lng);
        $brng = deg2rad($bearingDegrees);

        $lat2 = asin(sin($lat1) * cos($d / $R) + cos($lat1) * sin($d / $R) * cos($brng));
        $lon2 = $lon1 + atan2(sin($brng) * sin($d / $R) * cos($lat1), cos($d / $R) - sin($lat1) * sin($lat2));

        return [
            'lat' => rad2deg($lat2),
            'lng' => rad2deg($lon2),
        ];
    }
}
