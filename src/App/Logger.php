<?php
namespace Background\App;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

class Logger
{
    private static $instance;

    public static function get(): MonologLogger
    {
        if (! self::$instance) {
            $logger = new MonologLogger('email-processor');

            $logFile = Config::getLogFile();
            $dir     = dirname($logFile);

            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $logger->pushHandler(new RotatingFileHandler($logFile, 7, MonologLogger::DEBUG));

            if (Config::isDebugMode()) {
                $logger->pushHandler(new StreamHandler('php://stdout', MonologLogger::DEBUG));
            }

            self::$instance = $logger;
        }

        return self::$instance;
    }
}
