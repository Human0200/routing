<?php

namespace Background\Main;

use Background\App\Config;
use Background\App\DebugHelper;
use Background\App\DuplicateChecker;
use Background\App\EmailParser;
use Background\App\Logger;
use Background\App\SmartProcessCreator;
use Background\App\XlsxProcessor;
use Bitrix\Main\Loader;


class EmailProcessor
{
    private $logger;
    private $parser;
    private $xlsxProcessor;
    private $creator;

    public function __construct()
    {
        $this->initializeEnvironment();
        $this->initializeComponents();
    }

    /**
     * Инициализация окружения и зависимостей
     */
private function initializeEnvironment()
{
    if (!Loader::includeModule('bg.routing')) {
        throw new \Exception("Module bg.routing not found");
    }

    // Проверяем, что Битрикс уже инициализирован
    if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
        throw new \Exception("Bitrix not initialized");
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    // Проверка загрузки autoload
    if (!class_exists('Composer\Autoload\ClassLoader')) {
        throw new \Exception('Composer autoloader not found! Run "composer install"');
    }
}

    /**
     * Инициализация компонентов приложения
     */
    private function initializeComponents()
    {
        $this->logger = Logger::get();
        $this->parser = new EmailParser();
        $this->xlsxProcessor = new XlsxProcessor();
        $this->creator = new SmartProcessCreator();
    }

    /**
     * Обработка email сообщений с ограничением по количеству
     * @param int $limit Максимальное количество сообщений для обработки (0 - без ограничений)
     */
    public function processEmails(int $limit = 0): int
    {
        $this->logger->info("Starting email processing" . ($limit > 0 ? " with limit: {$limit}" : ""));

        try {
            // Получение сообщений
            $this->logger->info("Fetching email messages");
            $messages = $this->parser->getMessages();
            $this->logger->info(sprintf("Found %d messages", count($messages)));

            // Применяем лимит
            if ($limit > 0 && count($messages) > $limit) {
                $messages = array_slice($messages, 0, $limit);
                $this->logger->info(sprintf("Limited to %d messages", $limit));
            }

            $processedCount = 0;

            foreach ($messages as $message) {
                if ($this->processMessage($message)) {
                    $processedCount++;
                }
            }

            $this->logger->info("Email processing completed. Processed: {$processedCount} messages");
            return $processedCount;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return 0;
        }
    }

    /**
     * Обработка email сообщений начиная с определенного номера
     * @param int $startWith Номер сообщения, с которого начать обработку
     * @param int $limit Максимальное количество сообщений для обработки
     */
    public function processEmailsFrom(int $startWith, int $limit = 0): int
    {
        $this->logger->info("Starting email processing from message: {$startWith}" . ($limit > 0 ? " with limit: {$limit}" : ""));

        try {
            // Получение сообщений
            $this->logger->info("Fetching email messages");
            $messages = $this->parser->getMessages();
            $this->logger->info(sprintf("Found %d messages", count($messages)));

            // Фильтруем сообщения, начиная с указанного номера
            $filteredMessages = array_filter($messages, function ($message) use ($startWith) {
                return $message['number'] >= $startWith;
            });

            // Применяем лимит
            if ($limit > 0 && count($filteredMessages) > $limit) {
                $filteredMessages = array_slice($filteredMessages, 0, $limit);
            }

            $this->logger->info(sprintf("Processing %d messages starting from %d", count($filteredMessages), $startWith));

            $processedCount = 0;

            foreach ($filteredMessages as $message) {
                if ($this->processMessage($message)) {
                    $processedCount++;
                }
            }

            $this->logger->info("Email processing completed. Processed: {$processedCount} messages");
            return $processedCount;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return 0;
        }
    }

    /**
     * Обработка только новых сообщений (тех, которые еще не были обработаны)
     * @param int $limit Максимальное количество новых сообщений для обработки
     */
    public function processNewEmails(int $limit = 0): int
    {
        $this->logger->info("Processing only new emails" . ($limit > 0 ? " with limit: {$limit}" : ""));

        try {
            // Получение сообщений
            $this->logger->info("Fetching email messages");
            $messages = $this->parser->getMessages();
            $this->logger->info(sprintf("Found %d messages", count($messages)));

            $processedCount = 0;
            $skippedCount = 0;

            foreach ($messages as $message) {
                // Проверяем лимит
                if ($limit > 0 && $processedCount >= $limit) {
                    $this->logger->info("Reached processing limit: {$limit}");
                    break;
                }

                // Проверка дубликатов
                if (DuplicateChecker::isDuplicate($message['message_id'])) {
                    $skippedCount++;
                    continue;
                }

                if ($this->processMessage($message)) {
                    $processedCount++;
                }
            }

            $this->logger->info("New email processing completed. Processed: {$processedCount}, Skipped duplicates: {$skippedCount}");
            return $processedCount;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return 0;
        }
    }

    /**
     * Обработка одного сообщения
     */
    public function processMessage(array $message): bool
    {
        $this->logger->debug("Processing message", [
            'number' => $message['number'],
            'subject' => $message['subject'],
            'message_id' => $message['message_id'],
        ]);

        // Проверка дубликатов
        if (DuplicateChecker::isDuplicate($message['message_id'])) {
            $this->logger->info("Skipping duplicate message", [
                'message_id' => $message['message_id'],
            ]);
            return false;
        }

        $processed = false;

        // Обработка вложений
        foreach ($message['attachments'] as $attachment) {
            if ($this->processAttachment($message, $attachment)) {
                $processed = true;
            }
        }

        return $processed;
    }

    /**
     * Обработка вложения
     */
    public function processAttachment(array $message, array $attachment): bool
    {
        $this->logger->debug("Checking attachment", [
            'filename' => $attachment['filename'],
        ]);

        // Фильтрация по расширению
        if (stripos($attachment['filename'], '.xlsx') === false) {
            $this->logger->debug("Skipping non-XLSX attachment");
            return false;
        }

        try {
            // Получение содержимого вложения
            $this->logger->info("Processing XLSX attachment", [
                'filename' => $attachment['filename'],
            ]);

            $content = $this->parser->getAttachmentContent(
                $message['number'],
                $attachment['partNum'],
                $attachment['encoding']
            );

            // Парсинг XLSX
            $parsedData = $this->xlsxProcessor->parse($content);
            $message['attachment'] = $attachment;

            // Создание элемента смарт-процесса
            $elementId = $this->creator->create($message, $parsedData);

            // Сохранение файла
            $this->saveAttachmentFile($message);

            if ($elementId) {
                $this->logger->info("Successfully created CRM item", [
                    'element_id' => $elementId,
                    'filename' => $attachment['filename'],
                ]);

                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to process attachment", [
                'filename' => $attachment['filename'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return false;
    }

    /**
     * Сохранение файла вложения
     */
    public function saveAttachmentFile(array $data): bool
    {
        if (isset($data['attachment'])) {
            $filename = $data['attachment']['filename'];
            $content = $this->parser->getAttachmentContent(
                $data['number'],
                $data['attachment']['partNum'],
                $data['attachment']['encoding']
            );

            // Сохраняем файл
            $result = file_put_contents($filename, $content);
            if ($result !== false) {
                //echo "Файл {$filename} успешно сохранен!";
                return true;
            }
        }

        //echo "Вложение не найдено или не удалось сохранить.";
        return false;
    }

    /**
     * Получение количества непрочитанных сообщений
     */
    public function getUnprocessedCount(): int
    {
        try {
            $messages = $this->parser->getMessages();
            $unprocessed = 0;

            foreach ($messages as $message) {
                if (!DuplicateChecker::isDuplicate($message['message_id'])) {
                    $unprocessed++;
                }
            }

            return $unprocessed;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get unprocessed count", [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Обработка ошибок
     */
    private function handleError(\Throwable $e)
    {
        $this->logger->critical("Fatal error during processing", [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Дополнительный вывод для отладки
        if (Config::isDebugMode()) {
            //echo '<h3>Fatal Error Details</h3>';
        }
    }

    /**
     * Получение логгера
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Получение парсера email
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * Получение процессора XLSX
     */
    public function getXlsxProcessor()
    {
        return $this->xlsxProcessor;
    }

    /**
     * Получение создателя смарт-процессов
     */
    public function getSmartProcessCreator()
    {
        return $this->creator;
    }

    /**
     * Завершение работы
     */
    public function __destruct()
    {
        $this->logger->info("Script execution finished");
    }
}
