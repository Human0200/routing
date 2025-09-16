<?php
namespace Background\App;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Engine\CurrentUser;

class SmartProcessCreator
{
    private $logger;
    private $factory;

    public function __construct()
    {
        $this->logger = Logger::get();

        if (!Loader::includeModule('crm')) {
            throw new \RuntimeException('CRM module not available');
        }
        $this->authorizeAsUser(1);
        $this->factory = Container::getInstance()->getFactory(1100);
        if (!$this->factory) {
            throw new \RuntimeException('Smart process not found');
        }
    }
    private function authorizeAsUser(int $userId): void
    {
        global $USER;
        if (!$USER->IsAuthorized()) {
            $USER->Authorize($userId, false, true);
        }
    }
    public function create(array $emailData, array $xlsxData): ?int
    {
        try {
            $item = $this->factory->createItem();
            // Основные поля
            $item->set(FieldMapper::getFieldCode('title'), $xlsxData[3][1]); //TITLE
            $item->set(FieldMapper::getFieldCode('message_id'), $emailData['message_id'] ?? uniqid());

            if (isset($xlsxData[4][1])) { //description
                $item->set(
                    FieldMapper::getFieldCode('description'),
                    $xlsxData[4][1]
                );
            }
            if (isset($xlsxData[9][1])) { //deadline_description
                $item->set(
                    FieldMapper::getFieldCode('deadline_description'),
                    $xlsxData[9][1]
                );
            }
            if (isset($xlsxData[5][1])) { //departament
                $item->set(
                    FieldMapper::getFieldCode('departament'),
                    $xlsxData[5][1]
                );
            }
            if (isset($xlsxData[8][1])) { //deadline
                $item->set(
                    FieldMapper::getFieldCode('deadline'),
                    $this->parseEmailDate($xlsxData[8][1])
                );
            }
            if (isset($xlsxData[13][1])) { //more_info
                $value = $xlsxData[13][1];


                if (isset($xlsxData[14][1])) {
                    $value .= ' | ' . $xlsxData[14][1];
                }

                $item->set(
                    FieldMapper::getFieldCode('more_info'),
                    $value
                );
            }


            // Данные отправителя
            $fromData = $emailData['from'] ?? [];
            $item->set(
                FieldMapper::getFieldCode('sender_email'),
                $this->extractEmail($fromData)
            );
            $item->set(
                FieldMapper::getFieldCode('sender_name'),
                $this->extractName($fromData)
            );

            // Обработка даты
            $item->set(
                FieldMapper::getFieldCode('date'),
                // $this->parseEmailDate($emailData['date'] ?? null)
                $this->parseEmailDate($xlsxData[2][1] ?? null)
            );

            // Вложение и данные
            $item->set(
                FieldMapper::getFieldCode('attachment_name'),
                $emailData['attachment']['filename'] ?? 'Без названия'
            );
            $item->set(
                FieldMapper::getFieldCode('xlsx_data'),
                json_encode($xlsxData, JSON_UNESCAPED_UNICODE)
            );

            // Системные настройки
            $item->set('ASSIGNED_BY_ID', FieldMapper::getDefaultValue('assigned_by_id'));
            // $item->set('STAGE_ID', FieldMapper::getDefaultValue('stage_id'));
            $item->set(
                FieldMapper::getFieldCode('priority'),
                FieldMapper::getDefaultValue('priority')
            );
            $item->set(
                FieldMapper::getFieldCode('category'),
                FieldMapper::getDefaultValue('category')
            );
            $this->logger->info('Элемент: ', ['item' => $item]);
            //$result = $item->save();
            $operation = $this->factory->getAddOperation($item);
            $result = $operation->launch();

            if ($result->isSuccess()) {
                $this->logger->info("Элемент создан успешно", [
                    'id' => $item->getId(),
                    'title' => $emailData['subject'] ?? '',
                ]);
                return $item->getId();
            } else {
                file_put_contents('error.txt', print_r($result->getErrorMessages(), true));
            }

            $this->logger->error("Ошибка создания элемента", [
                'errors' => $result->getErrorMessages(),
            ]);

        } catch (\Exception $e) {
            $this->logger->critical("Ошибка при создании элемента", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return null;
    }

    private function extractEmail(array $fromData): string
    {
        if (isset($fromData['email'])) {
            return filter_var($fromData['email'], FILTER_VALIDATE_EMAIL)
                ? $fromData['email']
                : 'unknown@example.com';
        }
        return 'unknown@example.com';
    }

    private function extractName(array $fromData): string
    {
        return $fromData['name'] ?? 'Не указано';
    }

    private function parseEmailDate(?string $dateString): DateTime
    {
        try {
            if (empty($dateString)) {
                return new DateTime();
            }

            // Сначала пробуем стандартный разбор
            try {
                return new DateTime($dateString);
            } catch (\Exception $e) {
                // Если не получилось, пробуем разобрать RFC2822 вручную
                $parsedDate = date_parse($dateString);
                if ($parsedDate['error_count'] === 0) {
                    $dateTime = sprintf(
                        '%04d-%02d-%02d %02d:%02d:%02d',
                        $parsedDate['year'],
                        $parsedDate['month'],
                        $parsedDate['day'],
                        $parsedDate['hour'],
                        $parsedDate['minute'],
                        $parsedDate['second']
                    );
                    return new DateTime($dateTime);
                }

                // Если всё ещё ошибка, пробуем через strtotime
                $timestamp = strtotime($dateString);
                if ($timestamp !== false) {
                    return new DateTime(date('Y-m-d H:i:s', $timestamp));
                }

                $this->logger->warning("Не удалось разобрать дату", [
                    'original_date' => $dateString,
                    'error' => $e->getMessage(),
                ]);
                return new DateTime();
            }
        } catch (\Exception $e) {
            $this->logger->error("Критическая ошибка обработки даты", [
                'date' => $dateString,
                'error' => $e->getMessage(),
            ]);
            return new DateTime();
        }
    }
}
