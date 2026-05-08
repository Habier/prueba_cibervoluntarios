<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        __DIR__.'/migrations',
    ]);

    $ecsConfig->skip([
        __DIR__.'/config/reference.php',
    ]);

    $ecsConfig->sets([
        SetList::COMMON,
        SetList::CLEAN_CODE,
        SetList::STRICT,
    ]);

    $ecsConfig->dynamicSets([
        '@Symfony',
    ]);
};
