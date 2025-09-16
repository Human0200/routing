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


            // Использование класса
            $processor = new EmailProcessor();

            // 1. Обработка только 10 последних сообщений
            $processed = $processor->processEmails(10);
            //echo "Обработано: $processed сообщений";

            // 2. Обработка только новых сообщений (не дубликатов)
            // $processed = $processor->processNewEmails(5);
            // echo "Обработано новых: $processed сообщений";

            // 3. Обработка сообщений начиная с определенного номера
            // $processed = $processor->processEmailsFrom(100, 3);
            // echo "Обработано: $processed сообщений начиная с №100";

            // 4. Получение количества непрочитанных сообщений
            // $unprocessed = $processor->getUnprocessedCount();
            // echo "Непрочитанных сообщений: $unprocessed";

            // 5. Обработка без ограничений (как раньше)
            // $processed = $processor->processEmails();
            // echo "Обработано всех сообщений: $processed";

            return __METHOD__ . '(3);';
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/debug_log.txt', $e->getMessage() . "\n", FILE_APPEND);
            return __METHOD__ . '(4);';
        }
    }
}
