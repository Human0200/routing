<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('bg.routing', [
    'Settings24\GlobalSettings' => 'lib/classes/settings.php',
    'Background\AgentFunctions\Agent' => 'lib/classes/agent.php',
    'Background\Main\EmailProcessor' => 'lib/main.php',
    'Background\App\EmailParser' => 'src/App/EmailParser.php',
    'Background\App\SmartProcessCreator' => 'src/App/SmartProcessCreator.php',
    'Background\App\DebugHelper' => 'src/App/DebugHelper.php',
    'Background\App\DuplicateChecker' => 'src/App/DuplicateChecker.php',
    'Background\App\XlsxProcessor' => 'src/App/XlsxProcessor.php',
    'Background\App\Config' => 'src/App/Config.php',
    'Background\App\Logger' => 'src/App/Logger.php',
    'Background\App\FieldMapper' => 'src/App/FieldMapper.php',


]);
?>