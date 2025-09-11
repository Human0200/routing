<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Улучшенная функция поиска вложений с подробным логированием
function findAttachments($structure, $prefix = '', $debugFile) {
    $list = [];
    $partNum = $prefix ?: '1';

    file_put_contents($debugFile, "\nChecking part $partNum\n", FILE_APPEND);
    file_put_contents($debugFile, "Structure type: {$structure->type}\n", FILE_APPEND);
    
    $isAttachment = false;
    $filename = '';

    // 1) Проверка dparameters
    if (!empty($structure->ifdparameters)) {
        file_put_contents($debugFile, "Checking dparameters...\n", FILE_APPEND);
        foreach ($structure->dparameters as $p) {
            file_put_contents($debugFile, "dparameter: {$p->attribute} = {$p->value}\n", FILE_APPEND);
            $attr = strtolower($p->attribute);
            if (in_array($attr, ['filename', 'name'], true)) {
                $decoded = iconv_mime_decode($p->value, 0, 'UTF-8');
                $isAttachment = true;
                $filename = $decoded;
                file_put_contents($debugFile, "Found filename in dparameters: $filename\n", FILE_APPEND);
            }
        }
    }

    // 2) Проверка parameters
    if (!$isAttachment && !empty($structure->ifparameters)) {
        file_put_contents($debugFile, "Checking parameters...\n", FILE_APPEND);
        foreach ($structure->parameters as $p) {
            file_put_contents($debugFile, "parameter: {$p->attribute} = {$p->value}\n", FILE_APPEND);
            $attr = strtolower($p->attribute);
            if (in_array($attr, ['filename', 'name'], true)) {
                $decoded = iconv_mime_decode($p->value, 0, 'UTF-8');
                $isAttachment = true;
                $filename = $decoded;
                file_put_contents($debugFile, "Found filename in parameters: $filename\n", FILE_APPEND);
            }
        }
    }

    // 3) Проверка disposition
    if (!$isAttachment && !empty($structure->ifdisposition)) {
        file_put_contents($debugFile, "Disposition: {$structure->disposition}\n", FILE_APPEND);
        if (strtolower($structure->disposition) === 'attachment' && !empty($structure->dparameters)) {
            file_put_contents($debugFile, "Checking attachment disposition...\n", FILE_APPEND);
            foreach ($structure->dparameters as $p) {
                file_put_contents($debugFile, "disposition parameter: {$p->attribute} = {$p->value}\n", FILE_APPEND);
                $attr = strtolower($p->attribute);
                if (in_array($attr, ['filename', 'name'], true)) {
                    $decoded = iconv_mime_decode($p->value, 0, 'UTF-8');
                    $isAttachment = true;
                    $filename = $decoded;
                    file_put_contents($debugFile, "Found filename in disposition: $filename\n", FILE_APPEND);
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
        file_put_contents($debugFile, "Added attachment: " . print_r(end($list), true) . "\n", FILE_APPEND);
    } else {
        file_put_contents($debugFile, "No attachment found in this part\n", FILE_APPEND);
    }

    // Рекурсия по вложенным частям
    if (!empty($structure->parts)) {
        file_put_contents($debugFile, "Checking subparts (count: " . count($structure->parts) . ")\n", FILE_APPEND);
        foreach ($structure->parts as $i => $sub) {
            $newPrefix = $prefix ? $prefix . '.' . ($i + 1) : (string)($i + 1);
            $list = array_merge($list, findAttachments($sub, $newPrefix, $debugFile));
        }
    }

    return $list;
}

// Основной код
$imapServer = '{10.81.65.12:993/imap/ssl/novalidate-cert}INBOX';
$login = 'bitrix_test@gardiask.ru';
$password = '3fsg7wKXJoDsrQCpMyEE';

$outputFile = __DIR__ . '/parsed_output.txt';
$debugFile = __DIR__ . '/debug_log.txt';
file_put_contents($outputFile, "");
file_put_contents($debugFile, "Starting email processing...\n");

$imap = imap_open($imapServer, $login, $password) or die('Ошибка подключения: ' . imap_last_error());

$total = imap_num_msg($imap);
file_put_contents($debugFile, "Total messages: $total\n", FILE_APPEND);

if ($total === 0) {
    file_put_contents($debugFile, "No messages in mailbox\n", FILE_APPEND);
    exit;
}

// Обрабатываем все письма, начиная с самого нового
for ($i = $total; $i >= 1; $i--) {
    file_put_contents($debugFile, "\nProcessing message #$i\n", FILE_APPEND);
    
    $header = imap_headerinfo($imap, $i);
    $subject = !empty($header->subject) ? mb_decode_mimeheader($header->subject) : 'Без темы';
    file_put_contents($debugFile, "Subject: $subject\n", FILE_APPEND);
    file_put_contents($debugFile, "Date: " . $header->date . "\n", FILE_APPEND);

    // Получаем структуру письма
    $struc = imap_fetchstructure($imap, $i);
    file_put_contents($debugFile, "Full structure:\n" . print_r($struc, true) . "\n", FILE_APPEND);

    // Ищем все вложения
    $atts = findAttachments($struc, '', $debugFile);
    file_put_contents($debugFile, "All attachments found: " . print_r($atts, true) . "\n", FILE_APPEND);

    // Фильтруем только .xlsx
    $xlsx = array_filter($atts, function ($a) use ($debugFile) {
        $isXlsx = preg_match('/\.xlsx$/i', $a['filename']);
        file_put_contents($debugFile, "Checking file {$a['filename']} - is XLSX: " . ($isXlsx ? 'yes' : 'no') . "\n", FILE_APPEND);
        return $isXlsx;
    });

    if (empty($xlsx)) {
        $note = "Письмо #{$i}, тема: «{$subject}» — вложений .xlsx не обнаружено.\n";
        file_put_contents($outputFile, $note, FILE_APPEND);
        file_put_contents($debugFile, $note, FILE_APPEND);
        continue;
    }

    // Обрабатываем каждое .xlsx вложение
    foreach ($xlsx as $att) {
        file_put_contents($debugFile, "Processing XLSX attachment: " . print_r($att, true) . "\n", FILE_APPEND);
        
        try {
            $data = imap_fetchbody($imap, $i, $att['partNum']);
            file_put_contents($debugFile, "Raw data size: " . strlen($data) . " bytes\n", FILE_APPEND);

            // Декодируем
            if ($att['encoding'] == 3) {
                $data = base64_decode($data);
                file_put_contents($debugFile, "After base64 decode: " . strlen($data) . " bytes\n", FILE_APPEND);
            } elseif ($att['encoding'] == 4) {
                $data = quoted_printable_decode($data);
                file_put_contents($debugFile, "After quoted-printable decode: " . strlen($data) . " bytes\n", FILE_APPEND);
            }

            // Сохраняем временный файл
            $tmpPath = __DIR__ . '/tmp_' . uniqid() . '.xlsx';
            file_put_contents($tmpPath, $data);
            file_put_contents($debugFile, "Saved to temp file: $tmpPath (" . filesize($tmpPath) . " bytes)\n", FILE_APPEND);

            // Парсим XLSX
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $maxRow = $sheet->getHighestRow();
            $maxCol = $sheet->getHighestColumn();
            file_put_contents($debugFile, "Spreadsheet dimensions: rows=$maxRow, cols=$maxCol\n", FILE_APPEND);

            // Формируем вывод
            $txt = "Письмо #{$i}, тема: «{$subject}», файл: {$att['filename']}\n";
            for ($r = 1; $r <= $maxRow; ++$r) {
                $cells = [];
                for ($c = 'A'; $c <= $maxCol; ++$c) {
                    $cells[] = $sheet->getCell("{$c}{$r}")->getValue();
                }
                $txt .= implode(";", $cells) . "\n";
            }
            $txt .= str_repeat("—", 40) . "\n";

            file_put_contents($outputFile, $txt, FILE_APPEND);
            file_put_contents($debugFile, "Successfully parsed and saved data\n", FILE_APPEND);
            
            unlink($tmpPath);
        } catch (Exception $e) {
            $error = "Error processing attachment: " . $e->getMessage() . "\n";
            file_put_contents($debugFile, $error, FILE_APPEND);
            file_put_contents($outputFile, $error, FILE_APPEND);
        }
    }
}

imap_close($imap);
file_put_contents($debugFile, "Processing complete\n", FILE_APPEND);
echo "Готово! Проверьте файлы:\n- parsed_output.txt\n- debug_log.txt\n";