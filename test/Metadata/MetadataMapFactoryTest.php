<?php

/**
 * @see       https://github.com/mezzio/mezzio-hal for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-hal/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-hal/blob/master/LICENSE.md New BSD License
 */

namespace MezzioTest\Hal\Metadata;

use Generator;
use Mezzio\Hal\Metadata;
use Mezzio\Hal\Metadata\Exception\InvalidConfigException;
use Mezzio\Hal\Metadata\MetadataMap;
use Mezzio\Hal\Metadata\MetadataMapFactory;
use Mezzio\Hal\Metadata\RouteBasedCollectionMetadata;
use Mezzio\Hal\Metadata\RouteBasedCollectionMetadataFactory;
use Mezzio\Hal\Metadata\RouteBasedResourceMetadata;
use Mezzio\Hal\Metadata\RouteBasedResourceMetadataFactory;
use Mezzio\Hal\Metadata\UrlBasedCollectionMetadata;
use Mezzio\Hal\Metadata\UrlBasedCollectionMetadataFactory;
use Mezzio\Hal\Metadata\UrlBasedResourceMetadata;
use Mezzio\Hal\Metadata\UrlBasedResourceMetadataFactory;
use MezzioTest\Hal\TestAsset;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use stdClass;

class MetadataMapFactoryTest extends TestCase
{
    /**
     * @var MetadataMapFactory
     */
    private $factory;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new MetadataMapFactory();
    }

    public function testFactoryReturnsEmptyMetadataMapWhenNoConfigServicePresent()
    {
        $this->container->has('config')->willReturn(false);
        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertAttributeSame([], 'map', $metadataMap);
    }

    public function testFactoryReturnsEmptyMetadataMapWhenConfigServiceHasNoMetadataMapEntries()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([]);
        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertAttributeSame([], 'map', $metadataMap);
    }

    public function testFactoryRaisesExceptionIfMetadataMapConfigIsNotAnArray()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([MetadataMap::class => 'nope']);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('expected an array');
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryRaisesExceptionIfMetadataMapItemIsNotAnArray()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([MetadataMap::class => ['nope']]);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('metadata item configuration');
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryRaisesExceptionIfAnyMetadataIsMissingAClassEntry()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([MetadataMap::class => [['nope']]]);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('missing "__class__"');
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryRaisesExceptionIfTheMetadataClassDoesNotExist()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([MetadataMap::class => [[
            '__class__' => 'not-a-class',
        ]]]);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid metadata class provided');
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryRaisesExceptionIfTheMetadataClassIsNotAnAbstractMetadataType()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([MetadataMap::class => [[
            '__class__' => __CLASS__,
        ]]]);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('does not extend ' . Metadata\AbstractMetadata::class);
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryRaisesExceptionIfMetadataClassDoesNotHaveACreationMethodInTheFactory()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([MetadataMap::class => [[
            '__class__' => TestAsset\TestMetadata::class,
        ]]]);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('please provide a factory in your configuration');
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryRaisesExceptionIfMetadataFactoryDoesNotImplementFactoryInterface()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    ['__class__' => TestAsset\TestMetadata::class]
                ],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        TestAsset\TestMetadata::class => stdClass::class,
                    ],
                ],
            ]
        );
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('is not a valid metadata factory class; does not implement');
        ($this->factory)($this->container->reveal());
    }

    public function invalidMetadata() : Generator
    {
        $types = [
            UrlBasedResourceMetadata::class,
            UrlBasedCollectionMetadata::class,
            RouteBasedResourceMetadata::class,
            RouteBasedCollectionMetadata::class,
        ];

        foreach ($types as $type) {
            yield $type => [['__class__' => $type], $type];
        }
    }

    /**
     * @dataProvider invalidMetadata
     */
    public function testFactoryRaisesExceptionIfMetadataIsMissingRequiredElements(
        array $metadata,
        string $expectExceptionString
    ) {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [$metadata],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        RouteBasedCollectionMetadata::class => RouteBasedCollectionMetadataFactory::class,
                        RouteBasedResourceMetadata::class   => RouteBasedResourceMetadataFactory::class,

                        UrlBasedCollectionMetadata::class   => UrlBasedCollectionMetadataFactory::class,
                        UrlBasedResourceMetadata::class     => UrlBasedResourceMetadataFactory::class,
                    ],
                ],
            ]
        );
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($expectExceptionString);
        ($this->factory)($this->container->reveal());
    }

    public function testFactoryCanMapUrlBasedResourceMetadata()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    [
                        '__class__'      => UrlBasedResourceMetadata::class,
                        'resource_class' => stdClass::class,
                        'url'            => '/test/foo',
                        'extractor'      => 'ObjectProperty',
                    ],
                ],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        UrlBasedResourceMetadata::class => UrlBasedResourceMetadataFactory::class,
                    ],
                ],
            ]
        );

        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertTrue($metadataMap->has(stdClass::class));
        $metadata = $metadataMap->get(stdClass::class);

        $this->assertInstanceOf(UrlBasedResourceMetadata::class, $metadata);
        $this->assertSame(stdClass::class, $metadata->getClass());
        $this->assertSame('ObjectProperty', $metadata->getExtractor());
        $this->assertSame('/test/foo', $metadata->getUrl());
    }

    public function testFactoryCanMapUrlBasedCollectionMetadata()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    [
                        '__class__'             => UrlBasedCollectionMetadata::class,
                        'collection_class'      => stdClass::class,
                        'collection_relation'   => 'foo',
                        'url'                   => '/test/foo',
                        'pagination_param'      => 'p',
                        'pagination_param_type' => Metadata\AbstractCollectionMetadata::TYPE_PLACEHOLDER,
                    ],
                ],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        UrlBasedCollectionMetadata::class => UrlBasedCollectionMetadataFactory::class,
                    ],
                ],
            ]
        );

        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertTrue($metadataMap->has(stdClass::class));
        $metadata = $metadataMap->get(stdClass::class);

        $this->assertInstanceOf(UrlBasedCollectionMetadata::class, $metadata);
        $this->assertSame(stdClass::class, $metadata->getClass());
        $this->assertSame('foo', $metadata->getCollectionRelation());
        $this->assertSame('/test/foo', $metadata->getUrl());
        $this->assertSame('p', $metadata->getPaginationParam());
        $this->assertSame(Metadata\AbstractCollectionMetadata::TYPE_PLACEHOLDER, $metadata->getPaginationParamType());
    }

    public function testFactoryCanMapRouteBasedResourceMetadata()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    [
                        '__class__'                    => RouteBasedResourceMetadata::class,
                        'resource_class'               => stdClass::class,
                        'route'                        => 'foo',
                        'extractor'                    => 'ObjectProperty',
                        'resource_identifier'          => 'foo_id',
                        'route_identifier_placeholder' => 'foo_id',
                        'route_params'                 => ['foo' => 'bar'],
                        'identifiers_to_placeholders_mapping' => [
                            'bar' => 'bar_value',
                            'baz' => 'baz_value',
                        ],
                    ],
                ],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        RouteBasedResourceMetadata::class => RouteBasedResourceMetadataFactory::class,
                    ],
                ],
            ]
        );

        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertTrue($metadataMap->has(stdClass::class));
        $metadata = $metadataMap->get(stdClass::class);

        $this->assertInstanceOf(RouteBasedResourceMetadata::class, $metadata);
        $this->assertSame(stdClass::class, $metadata->getClass());
        $this->assertSame('ObjectProperty', $metadata->getExtractor());
        $this->assertSame('foo', $metadata->getRoute());
        $this->assertSame('foo_id', $metadata->getResourceIdentifier());
        $this->assertSame('foo_id', $metadata->getRouteIdentifierPlaceholder());
        $this->assertSame(['foo' => 'bar'], $metadata->getRouteParams());
        $this->assertSame([
            'bar'    => 'bar_value',
            'baz'    => 'baz_value',
            'foo_id' => 'foo_id',
        ], $metadata->getIdentifiersToPlaceholdersMapping());
    }

    public function testFactoryCanMapRouteBasedCollectionMetadata()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    [
                        '__class__'              => RouteBasedCollectionMetadata::class,
                        'collection_class'       => stdClass::class,
                        'collection_relation'    => 'foo',
                        'route'                  => 'foo',
                        'pagination_param'       => 'p',
                        'pagination_param_type'  => Metadata\AbstractCollectionMetadata::TYPE_PLACEHOLDER,
                        'route_params'           => ['foo' => 'bar'],
                        'query_string_arguments' => ['baz' => 'bat'],
                    ],
                ],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        RouteBasedCollectionMetadata::class => RouteBasedCollectionMetadataFactory::class,
                    ],
                ],
            ]
        );

        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertTrue($metadataMap->has(stdClass::class));
        $metadata = $metadataMap->get(stdClass::class);

        $this->assertInstanceOf(RouteBasedCollectionMetadata::class, $metadata);
        $this->assertSame(stdClass::class, $metadata->getClass());
        $this->assertSame('foo', $metadata->getCollectionRelation());
        $this->assertSame('foo', $metadata->getRoute());
        $this->assertSame('p', $metadata->getPaginationParam());
        $this->assertSame(Metadata\AbstractCollectionMetadata::TYPE_PLACEHOLDER, $metadata->getPaginationParamType());
        $this->assertSame(['foo' => 'bar'], $metadata->getRouteParams());
        $this->assertSame(['baz' => 'bat'], $metadata->getQueryStringArguments());
    }

    public function testFactoryCanCreateMetadataViaFactoryMethod()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    ['__class__' => TestAsset\TestMetadata::class],
                ],
            ]
        );

        $this->factory = new TestAsset\TestMetadataMapFactory();

        $metadataMap = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(MetadataMap::class, $metadataMap);
        $this->assertTrue($metadataMap->has(stdClass::class));
        $metadata = $metadataMap->get(stdClass::class);

        $this->assertInstanceOf(TestAsset\TestMetadata::class, $metadata);
    }

    public function testFactoryRaisesExceptionWhenCreatingRouteBasedResourceMetadataContainingConfigConflicts()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(
            [
                MetadataMap::class => [
                    [
                        '__class__'                    => RouteBasedResourceMetadata::class,
                        'resource_class'               => stdClass::class,
                        'route'                        => 'foo',
                        'extractor'                    => 'ObjectProperty',
                        'resource_identifier'          => 'foo_id',
                        'route_identifier_placeholder' => 'foo_id',
                        'route_params'                 => ['foo' => 'bar'],
                        'identifiers_to_placeholders_mapping' => [
                            'foo_id' => 'id',
                            'bar'    => 'bar_value',
                            'baz'    => 'baz_value',
                        ],
                    ],
                ],
                'mezzio-hal' => [
                    'metadata-factories' => [
                        RouteBasedResourceMetadata::class => RouteBasedResourceMetadataFactory::class,
                    ],
                ],
            ]
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"$routeIdentifierPlaceholder"');
        ($this->factory)($this->container->reveal());
    }
}
