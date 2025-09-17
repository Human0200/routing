<?php

namespace Background\AgentFunctions;

use Exception;
use Bitrix\Main\Loader;

class Agent
{
    public static function execute()
    {
        if (!Loader::includeModule('bg.routing')) {
            file_put_contents(__DIR__ . '/debug_log.txt', "Module bg.routing not found\n", FILE_APPEND);
            return __METHOD__ . '(1);';
        }
		$scriptPath = __DIR__ . '/../rout.php';
        try {

        if (file_exists($scriptPath)) {
            // Запускаем через командную строку
            $command = 'php -f ' . escapeshellarg($scriptPath) . ' 2>&1';
            $result = shell_exec($command);
            file_put_contents(
                __DIR__ . '/agent_exec_log.txt',
                date('Y-m-d H:i:s') . " - Command: $command\nResult: $result\n\n",
                FILE_APPEND
            );
        }

            return __METHOD__ . '(3);';
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/debug_log.txt', $e->getMessage() . "\n", FILE_APPEND);
            return __METHOD__ . '(4);';
        }
    }
}
