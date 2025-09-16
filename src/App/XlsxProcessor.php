<?php
namespace Background\App;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class XlsxProcessor
{
    private $logger;

    public function __construct()
    {
        $this->logger = Logger::get();
    }

    public function parse(string $content): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tempFile, $content);

        try {
            $spreadsheet = IOFactory::load($tempFile);
            $sheet = $spreadsheet->getActiveSheet();

            $data = [];
            // foreach ($sheet->getRowIterator() as $row) {
            //     $rowData = [];
            //     foreach ($row->getCellIterator() as $cell) {
            //         $rowData[] = $cell->getValue();

            //     }
            //     $data[] = $rowData;
            // }


            foreach ($sheet->getRowIterator() as $row) {
                $rowData = [];
                foreach ($row->getCellIterator() as $cell) {
                    $value = $cell->getValue();

                    if ($cell->getDataType() === DataType::TYPE_NUMERIC) {
                        if (Date::isDateTime($cell)) {
                            $value = Date::excelToDateTimeObject($value)->format('d.m.Y'); // например, 24.07.2025
                        } else {
                            // Это просто число, не дата
                            $value = $value; // можно оставить как есть
                        }
                    }

                    $rowData[] = $value;

                }
                $data[] = $rowData;
            }

            $this->logger->debug("Parsed XLSX file", [
                'rows' => count($data),
                'cols' => count($data[0] ?? [])
            ]);
            // if (count($data) > 0) {
            //     $maxRowsToLog = 100; // Ограничение: не больше 100 строк
            //     $dataToLog = array_slice($data, 0, $maxRowsToLog);
            //     if (count($data) > $maxRowsToLog) {
            //         $dataToLog[] = ['... TRUNCATED ...', 'Total rows: ' . count($data)];
            //     }

            //     $this->logger->debug("XLSX content (first {$maxRowsToLog} rows)", [
            //         'content' => $dataToLog,
            //     ]);
            // }

            return $data;

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
