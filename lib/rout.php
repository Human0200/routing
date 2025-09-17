<?php


use Bitrix\Main\Loader;
use Background\Main\EmailProcessor;

// Подключаем пролог БЕЗ вывода (это важно для агентов)
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/www';
}

// Подключаем ядро Битрикс
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Проверяем модуль
if (!Loader::includeModule('bg.routing')) {
    die("Module bg.routing not found");
}

try {
    // Создаем и запускаем обработчик
    $processor = new EmailProcessor();

    // Обрабатываем 5 последних сообщений
    $processed = $processor->processEmails(5);

    // Использование класса
    //$processor = new EmailProcessor();

    // 1. Обработка только 5 последних сообщений
    //$processed = $processor->processEmails(5);
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


    echo "OK: Processed $processed emails";
} catch (Exception $e) {

    echo "ERROR: " . $e->getMessage();
}
