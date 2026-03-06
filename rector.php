<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withSkip([
        __DIR__.'/src/Core/Activity',
        __DIR__.'/src/Core/Tests',
        __DIR__.'/src/Mod/Trees',
    ])
    ->withSets([
        // PHP version upgrades
        LevelSetList::UP_TO_PHP_82,

        // Code quality
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
    ])
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    );
