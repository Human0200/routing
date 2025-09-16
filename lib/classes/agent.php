<?php

namespace Background\AgentFunctions;

use Exception;
use Bitrix\Main\Loader;
use Background\Main\EmailProcessor;

class Agent
{
    public static function run()
    {
        try {
            if (!Loader::includeModule('bg.routing')) {
                // echo "Module bg.routing not found\n";
                file_put_contents(__DIR__ . '/debug_log.txt', "Module bg.routing not found\n", FILE_APPEND);
                return __METHOD__ . '(1);';
            }
            
            $processor = new EmailProcessor();
            $connection = $processor->connect();
            if (!$connection) {
                // echo "Not connected to IMAP server. Call connect() first.";
                file_put_contents(__DIR__ . '/debug_log.txt', "Not connected to IMAP server. Call connect() first.\n", FILE_APPEND);
                return "\\Background\\AgentFunctions\\Agent::run();";
            }
            $results = $processor->processEmails(5);
            //echo "Processed " . count($results) . " emails.\n";
            return "\\Background\\AgentFunctions\\Agent::run();";
        } catch (Exception $e) {
            return "\\Background\\AgentFunctions\\Agent::run();";
        }
    }
}
