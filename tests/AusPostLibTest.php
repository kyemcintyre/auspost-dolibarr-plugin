<?php

namespace AusPost\Tests;

use PHPUnit\Framework\TestCase;

class AusPostLibTest extends TestCase
{
    public function testCalculateMarkupNone()
    {
        $this->assertEquals(15.50, auspost_calculate_markup(15.50, 'none', 5.0));
        $this->assertEquals(0.0, auspost_calculate_markup(0.0, 'none', 5.0));
    }

    public function testCalculateMarkupFlat()
    {
        $this->assertEquals(18.50, auspost_calculate_markup(15.50, 'flat', 3.00));
        $this->assertEquals(20.00, auspost_calculate_markup(15.50, 'flat', 4.50));
    }

    public function testCalculateMarkupPercentage()
    {
        // 10% on $20 = $22.00
        $this->assertEquals(22.00, auspost_calculate_markup(20.00, 'percent', 10.0));
        // 15% on $14.10 = $16.215 -> $16.22
        $this->assertEquals(16.22, auspost_calculate_markup(14.10, 'percent', 15.0));
    }

    public function testConvertWeightToKg()
    {
        // Already in kg (0)
        $this->assertEquals(2.5, auspost_convert_weight_to_kg(2.5, 0));

        // Grams (-3)
        $this->assertEquals(0.75, auspost_convert_weight_to_kg(750, -3));
        $this->assertEquals(1.5, auspost_convert_weight_to_kg(1500, -3));

        // Milligrams (-6)
        $this->assertEquals(0.005, auspost_convert_weight_to_kg(5000, -6));

        // Metric ton (3)
        $this->assertEquals(2000.0, auspost_convert_weight_to_kg(2, 3));

        // Pounds (99) - 1 lb = 0.453592 kg
        $this->assertEqualsWithDelta(0.4536, auspost_convert_weight_to_kg(1, 99), 0.001);
    }

    public function testConvertDimToCm()
    {
        // Already in cm (-2)
        $this->assertEquals(25.0, auspost_convert_dim_to_cm(25.0, -2));

        // Meters (0) -> cm
        $this->assertEquals(120.0, auspost_convert_dim_to_cm(1.2, 0));

        // Millimeters (-3) -> cm
        $this->assertEquals(15.0, auspost_convert_dim_to_cm(150, -3));

        // Inches (98) -> 1 inch = 2.54 cm
        $this->assertEqualsWithDelta(25.4, auspost_convert_dim_to_cm(10, 98), 0.01);
    }

    public function testCalculateCubicWeight()
    {
        // 40cm x 30cm x 20cm = 24,000 cm3
        // 24,000 / 4000 = 6.0 kg
        $cubic = auspost_calculate_cubic_weight(40, 30, 20);
        $this->assertEquals(6.0, $cubic);

        // Billable weight: actual 3kg vs cubic 6kg -> should be 6kg
        $billable = auspost_get_billable_weight(3.0, 40, 30, 20);
        $this->assertEquals(6.0, $billable);

        // Billable weight: actual 8kg vs cubic 6kg -> should be 8kg
        $billableHeavy = auspost_get_billable_weight(8.0, 40, 30, 20);
        $this->assertEquals(8.0, $billableHeavy);
    }
}
