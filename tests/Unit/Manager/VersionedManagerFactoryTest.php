<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit\Manager;

use Illuminate\Redis\Connections\Connection;
use Mockery;
use Monospice\LaravelRedisSentinel\Configuration\Loader as ConfigurationLoader;
use Monospice\LaravelRedisSentinel\Manager\VersionedManagerFactory;
use Monospice\LaravelRedisSentinel\Contracts\Factory as ManagerContract;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use PHPUnit_Framework_TestCase as TestCase;

class VersionedManagerFactoryTest extends TestCase
{
    /**
     * The instance of the connection factory service under test.
     *
     * @var VersionedManagerFactory
     */
    protected $subject;

    /**
     * A mock instance of the package's configuration loader to inject as a
     * dependency.
     *
     * @var ConfigurationLoader
     */
    protected $configLoaderMock;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->configLoaderMock = Mockery::mock(ConfigurationLoader::class);
        $this->configLoaderMock->isLumen = ApplicationFactory::isLumen();
        $this->configLoaderMock->shouldReceive('getApplicationVersion')
            ->andReturn(ApplicationFactory::getApplicationVersion());
        $this->configLoaderMock->shouldReceive('get')
            ->andReturn([ ])->byDefault();


        $this->subject = new VersionedManagerFactory($this->configLoaderMock);
    }

    /**
     * Run this cleanup after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        Mockery::close();
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(VersionedManagerFactory::class, $this->subject);
    }

    public function testBuildsAConnectionFactoryInstance()
    {
        // Test $manager->connection() to verify that we loaded the config.
        $this->configLoaderMock->shouldReceive('get')
            ->with('database.redis-sentinel', [ ])
            ->andReturn([ 'connection' => [ ] ]);

        $manager = $this->subject->makeInstance();
        $connection = $manager->connection('connection');

        $this->assertInstanceOf(ManagerContract::class, $manager);
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testBuildsAConnectionFactoryWithFactoryMethod()
    {
        // Test $manager->connection() to verify that we loaded the config.
        $this->configLoaderMock->shouldReceive('get')
            ->with('database.redis-sentinel', [ ])
            ->andReturn([ 'connection' => [ ] ]);

        $manager = VersionedManagerFactory::make($this->configLoaderMock);
        $connection = $manager->connection('connection');

        $this->assertInstanceOf(ManagerContract::class, $manager);
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testBuildsWithManagerForFrameworkVersion()
    {
        $manager = $this->subject->makeInstance();
        $version = ApplicationFactory::getVersionedRedisSentinelManagerClass();

        $this->assertInstanceOf($version, $manager->getVersionedManager());
    }
}
