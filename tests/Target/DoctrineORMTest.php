<?php

declare(strict_types=1);

namespace Tests\RulerZ\Target;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Entity\Player;
use PHPUnit\Framework\TestCase;
use RulerZ\Compiler\CompilationTarget;
use RulerZ\Compiler\Context;
use RulerZ\DoctrineORM\Target\DoctrineORM;
use RulerZ\Model\Executor;
use RulerZ\Model\Rule;
use RulerZ\Parser\Parser;

class DoctrineORMTest extends TestCase
{
    /** @var DoctrineORM */
    private $target;

    /** @var EntityManager */
    private $em;

    public function setUp()
    {
        // Dirty but easy way to have real entity metadata
        $paths = [__DIR__.'/../../../../examples/entities'];
        $isDevMode = true;

        // the connection configuration
        $dbParams = [
            'driver' => 'pdo_sqlite',
            'path' => 'sqlite::memory:',
        ];

        $config = Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);

        $this->em = EntityManager::create($dbParams, $config);

        $this->target = new DoctrineORM();
    }

    /**
     * @dataProvider supportedTargetsAndModes
     */
    public function testSupportedTargetsAndModes($target, string $mode): void
    {
        $this->assertTrue($this->target->supports($target, $mode));
    }

    public function supportedTargetsAndModes(): array
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        return [
            [$queryBuilder, CompilationTarget::MODE_APPLY_FILTER],
            [$queryBuilder, CompilationTarget::MODE_FILTER],
            [$queryBuilder, CompilationTarget::MODE_SATISFIES],
        ];
    }

    /**
     * @dataProvider unsupportedTargets
     */
    public function testItRejectsUnsupportedTargets($target)
    {
        $this->assertFalse($this->target->supports($target, CompilationTarget::MODE_FILTER));
    }

    public function unsupportedTargets(): array
    {
        return [
            ['string'],
            [42],
            [new \stdClass()],
            [[]],
        ];
    }

    public function testItReturnsAnExecutorModel()
    {
        $rule = '1 = 1';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertInstanceOf(Executor::class, $executorModel);

        $this->assertCount(2, $executorModel->getTraits());
        $this->assertSame('"1 = 1"', $executorModel->getCompiledRule());
    }

    public function testItPrefixesColumnAccessesWithTheRightEntityAlias()
    {
        $rule = 'points >= 1';
        $expectedRule = '"player.points >= 1"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertInstanceOf(Executor::class, $executorModel);

        $this->assertSame($expectedRule, $executorModel->getCompiledRule());
    }

    public function testItSupportsPositionalParameters()
    {
        $rule = 'points >= ?';
        $expectedRule = '"player.points >= ?0"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedRule, $executorModel->getCompiledRule());
    }

    public function testItSupportsNamedParameters()
    {
        $rule = 'points > :nb_points';
        $expectedRule = '"player.points > :nb_points"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedRule, $executorModel->getCompiledRule());
    }

    public function testItUsesMetadataToJoinTables()
    {
        $rule = 'group.name = "ADMIN"';
        $expectedRule = '"_0_group.name = \'ADMIN\'"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedRule, $executorModel->getCompiledRule());
        $this->assertSame([
            'detectedJoins' => [
                [
                    'root' => 'player',
                    'column' => 'group',
                    'as' => '_0_group',
                ],
            ],
        ], $executorModel->getCompiledData());
    }

    public function testItDoesNotDuplicateJoins()
    {
        $rule = 'group.name = "ADMIN" or group.name = "OWNER"';
        $expectedRule = '"(_0_group.name = \'ADMIN\' OR _0_group.name = \'OWNER\')"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedRule, $executorModel->getCompiledRule());
        $this->assertSame([
            'detectedJoins' => [
                [
                    'root' => 'player',
                    'column' => 'group',
                    'as' => '_0_group',
                ],
            ],
        ], $executorModel->getCompiledData());
    }

    public function testItReusesJoinedTables()
    {
        $context = new Context([
            'em' => $this->em,
            'root_entities' => [Player::class],
            'root_aliases' => ['player'],
            'joins' => [
                'player' => [
                    new Join(Join::INNER_JOIN, 'player.group', 'joined_group_alias'),
                ],
            ],
        ]);
        $rule = 'joined_group_alias.name = \'FOO\'';
        $expectedDql = '"joined_group_alias.name = \'FOO\'"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $context);

        $this->assertSame($expectedDql, $executorModel->getCompiledRule());
        $this->assertSame(['detectedJoins' => []], $executorModel->getCompiledData());
    }

    public function testItGeneratesADifferentIdentifierForContextsWithJoins()
    {
        $rule = 'group.name = "ADMIN" or group.name = "OWNER"';

        $joinLessContext = $this->createContext();
        $contextWithJoins = $this->createContext();

        $join = $this->createMock(Join::class);
        $join->method('getJoin')->willReturn('test.group');
        $join->method('getAlias')->willReturn('grp');

        $contextWithJoins['joins'] = ['some_root' => [$join]];

        $joinLessIdentifier = $this->target->getRuleIdentifierHint($rule, $joinLessContext);
        $joinIdentifier = $this->target->getRuleIdentifierHint($rule, $contextWithJoins);

        $this->assertNotSame($joinIdentifier, $joinLessIdentifier);
    }

    public function testItUsesTheMetadataToDetectInvalidAttributeAccess()
    {
        $rule = 'attr_does_not_exist = "ADMIN"';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"attr_does_not_exist" not found for entity "Entity\Player"');

        $this->target->compile($this->parseRule($rule), $this->createContext());
    }

    public function testItUsesTheMetadataToDetectInvalidJoins()
    {
        $rule = 'join_does_not_exist.name = "ADMIN"';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"join_does_not_exist" not found for entity "Entity\Player"');

        $this->target->compile($this->parseRule($rule), $this->createContext());
    }

    public function testItUsesTheMetadataToDetectInvalidAttributeAccessOnJoin()
    {
        $rule = 'group.attr_does_not_exist = "ADMIN"';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"attr_does_not_exist" not found for entity "Entity\Group"');

        $this->target->compile($this->parseRule($rule), $this->createContext());
    }

    public function testItHandlesEmbeddedClasses()
    {
        $rule = 'address.country = \'France\'';
        $expectedDql = '"player.address.country = \'France\'"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedDql, $executorModel->getCompiledRule());
        $this->assertSame(['detectedJoins' => []], $executorModel->getCompiledData());
    }

    public function testItSupportsInlineOperators()
    {
        $rule = 'points > 30 and always_true()';
        $expectedDql = '"(player.points > 30 AND 1 = 1)"';

        $this->target->defineInlineOperator('always_true', function () {
            return '1 = 1';
        });

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedDql, $executorModel->getCompiledRule());
    }

    public function testItSupportsInlineOperatorsWithParameters()
    {
        $rule = 'points >= 42 and always_true(42)';
        $expectedDql = '"(player.points >= 42 AND inline_always_true(42))"';

        $this->target->defineInlineOperator('always_true', function ($value) {
            return 'inline_always_true('.$value.')';
        });

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedDql, $executorModel->getCompiledRule());
    }

    public function testItImplicitlyConvertsUnknownOperators()
    {
        $rule = 'points > 30 and always_true()';
        $expectedDql = '"(player.points > 30 AND always_true())"';

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedDql, $executorModel->getCompiledRule());
    }

    public function testItSupportsCustomOperators()
    {
        $this->markTestSkipped('Not implemented yet.');

        $rule = 'points > 30 and always_true()';
        $expectedRule = '"(points > 30 AND ".call_user_func($operators["always_true"]).")"';

        $this->target->defineOperator('always_true', function () {
            throw new \LogicException('should not be called');
        });

        /** @var Executor $executorModel */
        $executorModel = $this->target->compile($this->parseRule($rule), $this->createContext());

        $this->assertSame($expectedRule, $executorModel->getCompiledRule());
    }

    private function parseRule(string $rule): Rule
    {
        return (new Parser())->parse($rule);
    }

    private function createContext(): Context
    {
        return new Context([
            'em' => $this->em,
            'root_entities' => [Player::class],
            'root_aliases' => ['player'],
            'joins' => [],
        ]);
    }
}
