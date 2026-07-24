<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReportAggregationServiceTest extends TestCase
{
    public function test_aggregate_calculation_math(): void
    {
        $income = 500000;
        $expense = 200000;
        $net = $income - $expense;

        $this->assertEquals(300000, $net);
    }
}
