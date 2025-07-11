<?php
namespace App;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;

class DebugHelper
{
    public static function printSmartProcessItems(int $smartProcessId): void
    {
        if (! Loader::includeModule('crm')) {
            echo "CRM module not available\n";
            return;
        }

        $factory = Container::getInstance()->getFactory($smartProcessId);
        if (! $factory) {
            echo "Smart process {$smartProcessId} not found\n";
            return;
        }

        echo "<h3>Debug: Items in Smart Process {$smartProcessId}</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Title</th><th>Fields</th></tr>";

        $items = $factory->getItems([
            'select' => ['*', 'UF_*'],
            'limit'  => 50,
        ]);

        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>{$item->getId()}</td>";
            echo "<td>" . htmlspecialchars($item->getTitle()) . "</td>";
            echo "<td><pre>" . print_r($item->getData(), true) . "</pre></td>";
            echo "</tr>";
        }

        echo "</table>";

        // Выводим информацию о полях
        echo "<h3>Available Fields:</h3>";
        echo "<pre>" . print_r($factory->getFieldsInfo(), true) . "</pre>";
    }

    public static function printSmartProcessStructure(int $smartProcessId): void
    {
        $factory = Container::getInstance()->getFactory($smartProcessId);
        echo '<h3>Smart Process Structure</h3>';
        echo '<pre>' . print_r($factory->getFieldsInfo(), true) . '</pre>';
    }

    public static function printRecentItems(int $smartProcessId, int $limit = 5): void
    {
        $factory = Container::getInstance()->getFactory($smartProcessId);
        $items   = $factory->getItems(['limit' => $limit]);

        echo '<h3>Recent Items</h3>';
        foreach ($items as $item) {
            echo '<pre>' . print_r($item->getData(), true) . '</pre>';
        }
    }

    // In App\DebugHelper class
    public static function printItemDetails($processId, $elementId)
    {
        if (! self::isDebugMode()) {
            return;
        }

        $element = \Bitrix\Crm\Service\Container::getInstance()
            ->getFactory($processId)
            ->getItem($elementId);

        echo '<h3>Created Item Details</h3>';
        echo '<pre>' . print_r($element->getData(), true) . '</pre>';
    }
}
