<?php

namespace Tests\Unit;

use App\Services\Market\GeospatialService;
use PHPUnit\Framework\TestCase;

class GeospatialServiceTest extends TestCase
{
    public function test_haversine_distance_calculation(): void
    {
        $geo = new GeospatialService();
        // Coords approx 0.35 km apart
        $dist = $geo->calculateDistance(-6.2444, 106.8006, -6.2460, 106.8030);

        $this->assertGreaterThan(0, $dist);
        $this->assertLessThan(2.0, $dist);
    }
}
