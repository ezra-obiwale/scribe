<?php

namespace Knuckles\Scribe\Tests\Strategies\Metadata;

use Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class GetFromDocBlocksTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_metadata_from_method_docblock()
    {
        $strategy = new GetFromDocBlocks(new DocumentationConfig([]));
        $methodDocblock = <<<DOCBLOCK
/**
  * Endpoint title.
  * Endpoint description.
  * Multiline.
  */
DOCBLOCK;
        $classDocblock = '';
        $results = $strategy->getMetadataFromDocBlock(new DocBlock($methodDocblock), new DocBlock($classDocblock));

        $this->assertArrayNotHasKey('authenticated', $results);
        $this->assertNull($results['subgroup']);
        $this->assertSame('Endpoint title.', $results['title']);
        $this->assertSame("Endpoint description.\nMultiline.", $results['description']);
    }

    /** @test */
    public function can_fetch_metadata_from_method_and_class()
    {
        $strategy = new GetFromDocBlocks(new DocumentationConfig([]));
        $methodDocblock = <<<DOCBLOCK
/**
  * Endpoint title.
  * Endpoint description.
  * Multiline.
  */
DOCBLOCK;
        $classDocblock = <<<DOCBLOCK
/**
  * @group Group A
  * Group description.
  */
DOCBLOCK;
        $results = $strategy->getMetadataFromDocBlock(new DocBlock($methodDocblock), new DocBlock($classDocblock));

        $this->assertArrayNotHasKey('authenticated', $results);
        $this->assertArrayNotHasKey('tryOut', $results);
        $this->assertNull($results['subgroup']);
        $this->assertSame('Group A', $results['groupName']);
        $this->assertSame('Group description.', $results['groupDescription']);
        $this->assertSame('Endpoint title.', $results['title']);
        $this->assertSame("Endpoint description.\nMultiline.", $results['description']);

        $methodDocblock = <<<DOCBLOCK
/**
  * Endpoint title.
  * @authenticated
  * @noTryOut
  */
DOCBLOCK;
        $classDocblock = <<<DOCBLOCK
/**
  * @authenticated
  * @noTryOut
  * @subgroup Scheiße
  * @subgroupDescription Heilige Scheiße
  */
DOCBLOCK;
        $results = $strategy->getMetadataFromDocBlock(new DocBlock($methodDocblock), new DocBlock($classDocblock));

        $this->assertTrue($results['authenticated']);
        $this->assertFalse($results['tryOut']);
        $this->assertSame(null, $results['groupName']);
        $this->assertSame('Scheiße', $results['subgroup']);
        $this->assertSame('Heilige Scheiße', $results['subgroupDescription']);
        $this->assertSame('', $results['groupDescription']);
        $this->assertSame('Endpoint title.', $results['title']);
        $this->assertSame("", $results['description']);
    }

    /** @test */
    public function can_override_group_name_group_description_auth_and_try_out_status_from_method()
    {
        $strategy = new GetFromDocBlocks(new DocumentationConfig([]));
        $methodDocblock = <<<DOCBLOCK
/**
  * Endpoint title.
  * This is the endpoint description.
  * @authenticated
  * @noTryOut
  * @group Group from method
  */
DOCBLOCK;
        $classDocblock = <<<DOCBLOCK
/**
  * @group Group from controller
  * @tryOut
  * This is the group description.
  */
DOCBLOCK;
        $results = $strategy->getMetadataFromDocBlock(new DocBlock($methodDocblock), new DocBlock($classDocblock));

        $this->assertTrue($results['authenticated']);
        $this->assertFalse($results['tryOut']);
        $this->assertSame('Group from method', $results['groupName']);
        $this->assertSame("", $results['groupDescription']);
        $this->assertSame("This is the endpoint description.", $results['description']);
        $this->assertSame("Endpoint title.", $results['title']);

    }
}
