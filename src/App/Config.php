<?php
namespace Background\App;

class Config
{
    public static function get(string $key, $default = null)
    {
        static $env;

        if (! $env) {
            $env = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $env->load();
        }

        return $_ENV[$key] ?? $default;
    }

    public static function getImapServer(): string
    {
        return self::get('IMAP_SERVER');
    }

    public static function getIblockId(): int
    {
        return (int) self::get('IBLOCK_ID');
    }

    public static function getLogFile(): string
    {
        return self::get('LOG_FILE', 'logs/process.log');
    }

    public static function isDebugMode(): bool
    {
        return (bool) self::get('DEBUG_MODE', false);
    }

    public static function getImapLogin(): string
    {
        return self::get('IMAP_LOGIN');
    }

    public static function getImapPassword(): string
    {
        return self::get('IMAP_PASSWORD');
    }

    public static function getSmartProcessId(): int
    {
        return (int) self::get('SMART_PROCESS_ID', 1100);
    }

}
