<?php

use Ps\Emulator1c\Importer;
use Ps\Emulator1c\Stats;
use Ps\Emulator1c\Logger;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'ps.emulator1c',
    [
        Importer::class => 'lib/Importer.php',
        Stats::class => 'lib/Stats.php',
        Logger::class => 'lib/Logger.php',
    ]
);
