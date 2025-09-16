<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('bg.routing', [
    'Settings24\GlobalSettings' => 'lib/classes/settings.php',
    'Background\AgentFunctions\Agent' => 'lib/classes/agent.php',
    'Background\Main\EmailProcessor' => 'lib/rout.php',

]);
?>