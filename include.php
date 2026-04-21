<?php

use Onpeak\Emulator1c\Importer;
use Onpeak\Emulator1c\Stats;
use Onpeak\Emulator1c\Logger;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'onpeak.emulator1c',
    [
        Importer::class => 'lib/Importer.php',
        Stats::class => 'lib/Stats.php',
        Logger::class => 'lib/Logger.php',
    ]
);
