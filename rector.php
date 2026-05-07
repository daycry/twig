<?php

use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\EarlyReturn\Rector\Foreach_\ChangeNestedForeachIfsToEarlyContinueRector;
use Rector\EarlyReturn\Rector\If_\ChangeIfElseValueAssignToEarlyReturnRector;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;
use Rector\EarlyReturn\Rector\Return_\PreparedValueToEarlyReturnRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::DEAD_CODE,
        LevelSetList::UP_TO_PHP_82,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_100,
    ]);
    $rectorConfig->parallel();

    $rectorConfig->paths([
        __DIR__ . '/src/',
        __DIR__ . '/tests/',
    ]);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ]);

    $rectorConfig->bootstrapFiles([
        realpath(getcwd()) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php',
    ]);

    if (is_file(__DIR__ . '/phpstan.neon.dist')) {
        $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon.dist');
    }

    $rectorConfig->phpVersion(PhpVersion::PHP_82);
    $rectorConfig->importNames();

    // Targeted skips: rules we don't want applied repo-wide.
    $rectorConfig->skip([
        __DIR__ . '/src/Views',

        // Promoted properties may stay even if currently unused (public contract / DI shape).
        RemoveUnusedPromotedPropertyRector::class,

        // String class names may legitimately point to runtime-only views.
        StringClassNameToClassConstantRector::class,
    ]);

    // Selective rules on top of the sets above.
    $rectorConfig->rule(RemoveAlwaysElseRector::class);
    $rectorConfig->rule(ChangeNestedForeachIfsToEarlyContinueRector::class);
    $rectorConfig->rule(ChangeIfElseValueAssignToEarlyReturnRector::class);
    $rectorConfig->rule(PreparedValueToEarlyReturnRector::class);
    $rectorConfig->rule(MakeInheritedMethodVisibilitySameAsParentRector::class);
};
