<?php

namespace Tests\Unit;

use App\Support\PlaceName;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_place_names_normalize_arabic_variants(): void
    {
        $this->assertSame(PlaceName::normalize('الإسكندرية'), PlaceName::normalize('الاسكندريه'));
        $this->assertSame(PlaceName::normalize('  ميت   أبو الكوم '), PlaceName::normalize('ميت ابو الكوم'));
        $this->assertSame('cairo', PlaceName::normalize(' Cairo '));
    }
}
