<?php
namespace Background\Main;
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class EmailProcessor
{
    private $imapServer;
    private $login;
    private $password;
    private $imap;
    private $debugFile;
    private $outputFile;
    private $maxExecutionTime;
    private $messagesLimit;
    private $enableDebugLog;

    public function __construct(array $config = [])
    {
        // Настройки по умолчанию
        $this->imapServer = $config['imap_server'] ?? '{10.81.65.12:993/imap/ssl/novalidate-cert}INBOX';
        $this->login = $config['login'] ?? 'bitrix_test@gardiask.ru';
        $this->password = $config['password'] ?? '3fsg7wKXJoDsrQCpMyEE';
        $this->debugFile = $config['debug_file'] ?? __DIR__ . '/debug_log.txt';
        $this->outputFile = $config['output_file'] ?? __DIR__ . '/parsed_output.txt';
        $this->maxExecutionTime = $config['max_execution_time'] ?? 300;
        $this->messagesLimit = $config['messages_limit'] ?? 10;
		//$this->enableDebugLog = $config['enable_debug_log'] ?? false;
		$this->enableDebugLog = true;

        // Настройка PHP
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('max_execution_time', $this->maxExecutionTime);

        // Инициализация файлов
        $this->initializeFiles();
    }
    

    private function initializeFiles()
    {
        file_put_contents($this->outputFile, "");
        file_put_contents($this->debugFile, "");
    }

    private function logMessage($message)
    {
        if (!$this->enableDebugLog) {
            return;
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->debugFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    private function findAttachments($structure, $prefix = '')
    {
        $list = [];
        $partNum = $prefix ?: '1';

        $isAttachment = false;
        $filename = '';

        // 1) Проверка dparameters
        if (!empty($structure->ifdparameters)) {
            foreach ($structure->dparameters as $p) {
                $attr = strtolower($p->attribute);
                if (in_array($attr, ['filename', 'name'], true)) {
                    $decoded = iconv_mime_decode($p->value, 0, 'UTF-8');
                    $isAttachment = true;
                    $filename = $decoded;
                }
            }
        }

        // 2) Проверка parameters
        if (!$isAttachment && !empty($structure->ifparameters)) {
            foreach ($structure->parameters as $p) {
                $attr = strtolower($p->attribute);
                if (in_array($attr, ['filename', 'name'], true)) {
                    $decoded = iconv_mime_decode($p->value, 0, 'UTF-8');
                    $isAttachment = true;
                    $filename = $decoded;
                }
            }
        }

        // 3) Проверка disposition
        if (!$isAttachment && !empty($structure->ifdisposition)) {
            if (strtolower($structure->disposition) === 'attachment' && !empty($structure->dparameters)) {
                foreach ($structure->dparameters as $p) {
                    $attr = strtolower($p->attribute);
                    if (in_array($attr, ['filename', 'name'], true)) {
                        $decoded = iconv_mime_decode($p->value, 0, 'UTF-8');
                        $isAttachment = true;
                        $filename = $decoded;
                    }
                }
            }
        }

        if ($isAttachment) {
            $list[] = [
                'partNum' => $partNum,
                'filename' => $filename,
                'encoding' => $structure->encoding,
            ];
        }

        // Рекурсия по вложенным частям
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $i => $sub) {
                $newPrefix = $prefix ? $prefix . '.' . ($i + 1) : (string)($i + 1);
                $list = array_merge($list, $this->findAttachments($sub, $newPrefix));
            }
        }

        return $list;
    }

    /**
 * Быстрая проверка доступности IMAP сервера перед подключением
 */
public function checkServerAvailability()
{
    // Извлекаем хост и порт из IMAP строки подключения
    preg_match('/\{([^:]+):(\d+)/', $this->imapServer, $matches);
    
    if (count($matches) < 3) {
        $this->logMessage("Cannot parse IMAP server string: {$this->imapServer}");
        return false;
    }
    
    $host = $matches[1];
    $port = (int)$matches[2];
    
    $this->logMessage("Checking availability of $host:$port");
    
    $startTime = microtime(true);
    
    // Быстрая проверка через fsockopen с коротким таймаутом
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    $checkTime = round(microtime(true) - $startTime, 2);
    
    if ($fp) {
        fclose($fp);
        $this->logMessage("Server $host:$port is available ({$checkTime}s)");
        return true;
    }
    
    $this->logMessage("Server $host:$port is NOT available ({$checkTime}s): $errno - $errstr");
    return false;
}

public function connect()
{
    $this->logMessage("Starting connection process to: {$this->imapServer}");
    
    $connectionStart = microtime(true);
    
    // Используйте 0 вместо OP_HALFOPEN
    $this->imap = @imap_open(
        $this->imapServer,
        $this->login,
        $this->password,
        0 // Стандартный режим чтения/записи
    );
    
    $connectionTime = round(microtime(true) - $connectionStart, 2);
    $this->logMessage("IMAP connection attempt took {$connectionTime}s");
    
    if (!$this->imap) {
        $error = "IMAP connection failed: " . imap_last_error();
        $this->logMessage($error);
        throw new Exception($error);
    }

    $this->logMessage("Successfully connected to IMAP server");
    return true;
}

    public function disconnect()
    {
        if ($this->imap) {
            imap_close($this->imap);
            $this->imap = null;
            $this->logMessage("Disconnected from IMAP server");
        }
    }

    public function getTotalMessages()
    {
        if (!$this->imap) {
            throw new Exception("Not connected to IMAP server");
        }
        return imap_num_msg($this->imap);
    }

    public function processEmails($limit = null)
    {
        if (!$this->imap) {
            throw new Exception("Not connected to IMAP server. Call connect() first.");
        }

        $total = $this->getTotalMessages();
        $this->logMessage("Total messages: $total");

        if ($total === 0) {
            $this->logMessage("No messages in mailbox");
            return [];
        }

        $processLimit = $limit ?? $this->messagesLimit;
        $start = max(1, $total - $processLimit + 1);
        $end = $total;

        $this->logMessage("Processing messages from $start to $end (last $processLimit messages)");

        $results = [];

        for ($i = $end; $i >= $start; $i--) {
            $result = $this->processMessage($i);
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    public function processMessage($messageNum)
    {
        $this->logMessage("Processing message #$messageNum");

        // Проверяем соединение
        if (!imap_ping($this->imap)) {
            throw new Exception("IMAP connection lost during processing");
        }

        $header = imap_headerinfo($this->imap, $messageNum);
        $subject = !empty($header->subject) ? mb_decode_mimeheader($header->subject) : 'Без темы';
        $this->logMessage("Subject: $subject");

        // Получаем структуру письма
        $struc = imap_fetchstructure($this->imap, $messageNum);
        if (!$struc) {
            $this->logMessage("Failed to get message structure");
            return null;
        }

        // Ищем все вложения
        $atts = $this->findAttachments($struc);

        // Фильтруем только .xlsx
        $xlsx = array_filter($atts, function ($a) {
            return preg_match('/\.xlsx$/i', $a['filename']);
        });

        if (empty($xlsx)) {
            $note = "Письмо #{$messageNum}, тема: «{$subject}» — вложений .xlsx не обнаружено.";
            $this->logMessage($note);
            return null;
        }

        $messageResult = [
            'message_num' => $messageNum,
            'subject' => $subject,
            'date' => $header->date,
            'attachments' => []
        ];

        // Обрабатываем каждое .xlsx вложение
        foreach ($xlsx as $att) {
            $attachmentData = $this->processAttachment($messageNum, $att, $subject);
            if ($attachmentData) {
                $messageResult['attachments'][] = $attachmentData;
            }
        }

        return $messageResult;
    }

    private function processAttachment($messageNum, $att, $subject)
    {
        $this->logMessage("Processing XLSX attachment: {$att['filename']}");

        try {
            $data = imap_fetchbody($this->imap, $messageNum, $att['partNum']);

            // Декодируем
            if ($att['encoding'] == 3) {
                $data = base64_decode($data);
            } elseif ($att['encoding'] == 4) {
                $data = quoted_printable_decode($data);
            }

            // Сохраняем временный файл
            $tmpPath = sys_get_temp_dir() . '/tmp_' . uniqid() . '.xlsx';
            file_put_contents($tmpPath, $data);

            // Парсим XLSX
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $maxRow = $sheet->getHighestRow();
            $maxCol = $sheet->getHighestColumn();

            // Извлекаем данные
            $sheetData = [];
            for ($r = 1; $r <= $maxRow; ++$r) {
                $rowData = [];
                for ($c = 'A'; $c <= $maxCol; ++$c) {
                    $rowData[] = $sheet->getCell("{$c}{$r}")->getValue();
                }
                $sheetData[] = $rowData;
            }

            // Формируем вывод для файла
            $txt = "Письмо #{$messageNum}, тема: «{$subject}», файл: {$att['filename']}\n";
            foreach ($sheetData as $row) {
                $txt .= implode(";", $row) . "\n";
            }
            $txt .= str_repeat("—", 40) . "\n";

            file_put_contents($this->outputFile, $txt, FILE_APPEND);

            unlink($tmpPath);

            $this->logMessage("Successfully processed attachment: {$att['filename']}");

            return [
                'filename' => $att['filename'],
                'rows' => $maxRow,
                'cols' => $maxCol,
                'data' => $sheetData
            ];

        } catch (Exception $e) {
            $error = "Error processing attachment {$att['filename']}: " . $e->getMessage();
            $this->logMessage($error);
            file_put_contents($this->outputFile, $error . "\n", FILE_APPEND);
            return null;
        }
    }

    public function getOutputFile()
    {
        return $this->outputFile;
    }

    public function getDebugFile()
    {
        return $this->debugFile;
    }

    public function setDebugMode($enabled)
    {
        $this->enableDebugLog = $enabled;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}