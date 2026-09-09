<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

class ServiceProviderTest extends TestCase
{
    public function test_config_defaults_resolve_without_publishing()
    {
        $this->assertTrue(config('data-mapper.normalise_properties'));
        $this->assertSame('array', config('data-mapper.map_arrays_through'));
    }

    public function test_types_generation_config_no_longer_exists()
    {
        $this->assertNull(config('data-mapper.types_generation'));
    }
}
