<?
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
use Background\AgentFunctions\Agent;

if (!Loader::includeModule('bg.routing')) {
    echo "Module bg.routing not found\n";
    file_put_contents(__DIR__ . '/debug_log.txt', "Module bg.routing not found\n", FILE_APPEND);
    die();
}
echo 'run';
Agent::run();
