<?php
namespace App;

use PhpOffice\PhpSpreadsheet\IOFactory;

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
            $sheet       = $spreadsheet->getActiveSheet();

            $data = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = [];
                foreach ($row->getCellIterator() as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $data[] = $rowData;
            }

            $this->logger->debug("Parsed XLSX file", [
                'rows' => count($data),
                'cols' => count($data[0] ?? [])
            ]);

            return $data;

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
