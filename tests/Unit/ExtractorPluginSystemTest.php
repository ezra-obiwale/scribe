<?php

namespace Knuckles\Scribe\Tests\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;

class ExtractorPluginSystemTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @var \Knuckles\Scribe\Extracting\Extractor|null */
    protected $generator;

    protected function getPackageProviders($app)
    {
        $providers = [
            ScribeServiceProvider::class,
        ];
        if (class_exists(\Dingo\Api\Provider\LaravelServiceProvider::class)) {
            $providers[] = \Dingo\Api\Provider\LaravelServiceProvider::class;
        }
        return $providers;
    }

    protected function tearDown(): void
    {
        EmptyStrategy1::$called = false;
        EmptyStrategy2::$called = false;
        NotDummyMetadataStrategy::$called = false;

        parent::tearDown();
    }

    /** @test */
    public function only_specified_strategies_are_loaded()
    {
        $config = [
            'strategies' => [
                'metadata' => [NotDummyMetadataStrategy::class],
                'bodyParameters' => [
                    EmptyStrategy1::class,
                ],
                'responses' => [], // Making this empty so the Laravel-dependent strategies are not called
            ],
        ];
        $this->processRoute($config);

        $this->assertTrue(EmptyStrategy1::$called);
        $this->assertTrue(NotDummyMetadataStrategy::$called);
        $this->assertFalse(EmptyStrategy2::$called);
    }

    /** @test */
    public function responses_from_different_strategies_get_added()
    {
        $config = [
            'strategies' => [
                'bodyParameters' => [],
                'responses' => [DummyResponseStrategy200::class, DummyResponseStrategy400::class],
            ],
        ];
        $parsed = $this->processRoute($config);

        $this->assertCount(2, $parsed->responses->toArray());
        $responses = $parsed->responses->toArray();
        $first = array_shift($responses);
        $this->assertTrue(is_array($first));
        $this->assertEquals(200, $first['status']);
        $this->assertEquals('dummy', $first['content']);

        $second = array_shift($responses);
        $this->assertTrue(is_array($second));
        $this->assertEquals(400, $second['status']);
        $this->assertEquals('dummy2', $second['content']);
    }

    /**
     * @test
     * This is a generalized test, as opposed to the one above for responses only
     */
    public function combines_results_from_different_strategies_in_same_stage()
    {
        $config = [
            'strategies' => [
                'metadata' => [PartialDummyMetadataStrategy1::class, PartialDummyMetadataStrategy2::class],
                'bodyParameters' => [],
                'responses' => [],
            ],
        ];
        $parsed = $this->processRoute($config);

        $expectedMetadata = [
            'groupName' => 'dummy',
            'groupDescription' => 'dummy',
            'title' => 'dummy',
            'description' => 'dummy',
            'authenticated' => false,
            'tryOut' => true,
        ];
        $this->assertArraySubset($expectedMetadata, $parsed->metadata->toArray());
    }

    /** @test */
    public function missing_metadata_is_filled_in()
    {
        $config = [
            'strategies' => [
                'metadata' => [PartialDummyMetadataStrategy2::class],
                'bodyParameters' => [],
                'responses' => [],
            ],
        ];
        $parsed = $this->processRoute($config);

        $expectedMetadata = [
            'groupName' => '',
            'groupDescription' => 'dummy',
            'title' => '',
            'description' => 'dummy',
            'authenticated' => false,
            'tryOut' => true,
        ];
        $this->assertArraySubset($expectedMetadata, $parsed->metadata->toArray());
    }

    public function responsesToSort(): array
    {
        return [
            '400, 200, 201' => [[DummyResponseStrategy400::class, DummyResponseStrategy200::class, DummyResponseStrategy201::class]],
            '201, 400, 200' => [[DummyResponseStrategy201::class, DummyResponseStrategy400::class, DummyResponseStrategy200::class]],
            '400, 201, 200' => [[DummyResponseStrategy400::class, DummyResponseStrategy201::class, DummyResponseStrategy200::class]],
        ];
    }

    /**
     * @test
     * @dataProvider responsesToSort
     */
    public function sort_responses_by_status_code(array $responses)
    {
        $config = [
            'strategies' => [
                'bodyParameters' => [],
                'responses' => $responses,
            ],
        ];
        $parsed = $this->processRoute($config);

        [$first, $second, $third] = $parsed->responses;

        self::assertEquals(200, $first->status);
        self::assertEquals(201, $second->status);
        self::assertEquals(400, $third->status);
    }

    /** @test */
    public function overwrites_metadata_from_previous_strategies_in_same_stage()
    {
        $config = [
            'strategies' => [
                'metadata' => [NotDummyMetadataStrategy::class, PartialDummyMetadataStrategy1::class],
                'bodyParameters' => [],
                'responses' => [],
            ],
        ];
        $parsed = $this->processRoute($config);

        $expectedMetadata = [
            'groupName' => 'dummy',
            'groupDescription' => 'notdummy',
            'title' => 'dummy',
            'description' => 'dummy',
            'authenticated' => false,
            'tryOut' => true,
        ];
        $this->assertArraySubset($expectedMetadata, $parsed->metadata->toArray());
    }

    protected function processRoute(array $config): ExtractedEndpointData
    {
        $route = $this->createRoute('GET', '/api/test', 'dummy');
        $extractor = new Extractor(new DocumentationConfig($config));
        return $extractor->processRoute($route);
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => [$class, $controllerMethod]]);
    }
}


class EmptyStrategy1 extends Strategy
{
    public static $called = false;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        static::$called = true;
        return [];
    }
}

class EmptyStrategy2 extends Strategy
{
    public static $called = false;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        static::$called = true;
        return [];
    }
}

class NotDummyMetadataStrategy extends Strategy
{
    public static $called = false;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        static::$called = true;
        return [
            'groupName' => 'notdummy',
            'groupDescription' => 'notdummy',
            'title' => 'notdummy',
            'description' => 'notdummy',
            'authenticated' => true,
            'tryOut' => false,
        ];
    }
}

class PartialDummyMetadataStrategy1 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [
            'groupName' => 'dummy',
            'title' => 'dummy',
            'description' => 'dummy',
            'authenticated' => false,
            'tryOut' => true,
        ];
    }
}

class PartialDummyMetadataStrategy2 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [
            'description' => 'dummy',
            'groupDescription' => 'dummy',
        ];
    }
}

class DummyResponseStrategy200 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [['status' => 200, 'content' => 'dummy']];
    }
}

class DummyResponseStrategy201 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [['status' => 201, 'content' => 'dummy2']];
    }
}

class DummyResponseStrategy400 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [['status' => 400, 'content' => 'dummy2']];
    }
}
