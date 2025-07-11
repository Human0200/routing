<?php
namespace App;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;

class DuplicateChecker
{
    public static function isDuplicate(string $messageId): bool
    {
        if (! Loader::includeModule('crm')) {
            throw new \RuntimeException('CRM module not available');
        }

        $factory = Container::getInstance()->getFactory(1100);
        if (! $factory) {
            throw new \RuntimeException('Smart process type 1100 not found');
        }

        // Используем XML_ID вместо UF_MESSAGE_ID
        $items = $factory->getItems([
            'filter' => ['=XML_ID' => $messageId],
            'limit'  => 1,
        ]);

        return count($items) > 0;
    }
}
