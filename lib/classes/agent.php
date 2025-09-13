<?php

namespace Background\AgentFunctions;

use Exception;
use Bitrix\Main\Loader;

class Agent
{
    public static function run()
    {
        try {
            if (!Loader::includeModule('bg.routing')) {
                return __METHOD__ . '(1);';
            }
            include_once __DIR__.'/../rout.php';


            return "\\Background\\AgentFunctions\\Agent::run();";

        } catch (Exception $e) {
            return "\\Background\\AgentFunctions\\Agent::run();"; 
        }
    }
}
