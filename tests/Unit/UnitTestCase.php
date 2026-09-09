<?php

namespace OpenSoutheners\LaravelDataMapper\Tests\Unit;

use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mockery;
use OpenSoutheners\LaravelDataMapper\Mappers;
use OpenSoutheners\LaravelDataMapper\ServiceProvider;
use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ServiceProvider::registerMapper([
            Mappers\CollectionDataMapper::class,
            Mappers\ModelDataMapper::class,

            Mappers\CarbonDataMapper::class,
            Mappers\BackedEnumDataMapper::class,
            Mappers\GenericObjectDataMapper::class,
            Mappers\ObjectDataMapper::class,
        ]);

        $mockedConfig = Mockery::mock(Repository::class);

        $mockedConfig->shouldReceive('get')->andReturn(true);

        Container::getInstance()->bind('config', fn () => $mockedConfig);
    }

    public function actAsUser($user = null)
    {
        $mockedAuth = Mockery::mock(AuthManager::class);

        $mockedAuth->shouldReceive('check')->andReturn(false);
        $mockedAuth->shouldReceive('userResolver')->andReturn(fn () => $user);

        Container::getInstance()->bind('auth', fn () => $mockedAuth);
    }
}
