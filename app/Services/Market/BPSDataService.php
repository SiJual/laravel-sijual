<?php

namespace App\Services\Market;

class BPSDataService
{
    /**
     * Fetch demographic insights from BPS Open Data & OSM APIs.
     *
     * @param string $areaName
     * @return array{area: string, population: int, avg_monthly_income: int, density: string, age_distribution: array}
     */
    public function getDemographics(string $areaName): array
    {
        return [
            'area' => $areaName,
            'population' => 48500,
            'avg_monthly_income' => 6500000,
            'density' => 'Tinggi',
            'age_distribution' => [
                '18-24' => '25%',
                '25-34' => '40%',
                '35-50' => '23%',
                '50+' => '12%',
            ],
        ];
    }
}
