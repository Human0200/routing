<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверка окружения
echo '<h3>Environment Check</h3>';
echo 'PHP Version: ' . PHP_VERSION . '<br>';
echo 'Current dir: ' . __DIR__ . '<br>';
echo 'Document root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . '<br>';

require_once __DIR__ . '/../vendor/autoload.php';

// Проверка загрузки autoload
if (! class_exists('Composer\Autoload\ClassLoader')) {
    die('Composer autoloader not found! Run "composer install"');
}

// Подключение Битрикса
$bitrixRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..';
require_once $bitrixRoot . '/bitrix/modules/main/include/prolog_before.php';

// Проверка загрузки Битрикса
if (! defined('B_PROLOG_INCLUDED')) {
    die('Bitrix prolog not loaded correctly');
}

use App\Config;
use App\DebugHelper;
use App\DuplicateChecker;
use App\EmailParser;
use App\Logger;
use App\SmartProcessCreator;
use App\XlsxProcessor;

// Инициализация логгера
$logger = Logger::get();
$logger->info("Starting email processing");

// Отладочный вывод структуры смарт-процесса
if (Config::isDebugMode()) {
    DebugHelper::printSmartProcessStructure(Config::getSmartProcessId());
    DebugHelper::printRecentItems(Config::getSmartProcessId(), 5);
}

try {
    // Инициализация компонентов
    $logger->info("Initializing components");
    $parser        = new EmailParser();
    $xlsxProcessor = new XlsxProcessor();
    $creator       = new SmartProcessCreator();

    // Получение сообщений
    $logger->info("Fetching email messages");
    $messages = $parser->getMessages();
    $logger->info(sprintf("Found %d messages", count($messages)));

    foreach ($messages as $message) {
        $logger->debug("Processing message", [
            'number'     => $message['number'],
            'subject'    => $message['subject'],
            'message_id' => $message['message_id'],
        ]);

        // Проверка дубликатов
        if (DuplicateChecker::isDuplicate($message['message_id'])) {
            $logger->info("Skipping duplicate message", [
                'message_id' => $message['message_id'],
            ]);
            continue;
        }

        // Обработка вложений
        foreach ($message['attachments'] as $attachment) {
            $logger->debug("Checking attachment", [
                'filename' => $attachment['filename'],
            ]);

            // Фильтрация по расширению
            if (stripos($attachment['filename'], '.xlsx') === false) {
                $logger->debug("Skipping non-XLSX attachment");
                continue;
            }

            try {
                // Получение содержимого вложения
                $logger->info("Processing XLSX attachment", [
                    'filename' => $attachment['filename'],
                ]);

                $content = $parser->getAttachmentContent(
                    $message['number'],
                    $attachment['partNum'],
                    $attachment['encoding']
                );

                // Парсинг XLSX
                $parsedData            = $xlsxProcessor->parse($content);
                $message['attachment'] = $attachment;

                // Создание элемента смарт-процесса
                $elementId = $creator->create($message, $parsedData);

                if ($elementId) {
                    $logger->info("Successfully created CRM item", [
                        'element_id' => $elementId,
                        'filename'   => $attachment['filename'],
                    ]);

                    // Отладочный вывод созданного элемента
                    if (Config::isDebugMode()) {
                        DebugHelper::printItemDetails(Config::getSmartProcessId(), $elementId);
                    }
                }
            } catch (\Exception $e) {
                $logger->error("Failed to process attachment", [
                    'filename' => $attachment['filename'],
                    'error'    => $e->getMessage(),
                    'trace'    => $e->getTraceAsString(),
                ]);
            }
        }
    }

    $logger->info("Email processing completed");
} catch (\Throwable $e) {
    $logger->critical("Fatal error during processing", [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Дополнительный вывод для отладки
    if (Config::isDebugMode()) {
        echo '<h3>Fatal Error Details</h3>';
        echo '<pre>' . print_r([
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ], true) . '</pre>';
    }
}

// Закрытие соединений и завершение
$logger->info("Script execution finished");
