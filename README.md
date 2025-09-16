# Email Parser Module для Bitrix24

Модуль автоматически парсит Excel файлы из email и создает элементы смарт-процесса в CRM Bitrix24.

## Описание

Модуль подключается к почтовому серверу по IMAP, обрабатывает входящие письма с Excel вложениями (.xlsx), извлекает данные и создает соответствующие записи в смарт-процессе CRM.

## Системные требования

- PHP 8.2 или выше
- Bitrix24 (коммерческая версия)
- CentOS или другая Linux система
- Доступ к почтовому серверу по IMAP
- Composer для установки зависимостей

## Установка модуля на сервер

### 1. Установка IMAP расширения на CentOS

```bash
# Установка IMAP расширения PHP
sudo yum install php-imap

# Или если используется PHP 8.2+
sudo dnf install php-imap

# Перезапуск веб-сервера
sudo systemctl restart httpd
# или для nginx
sudo systemctl restart nginx php-fpm
```

Проверьте, что расширение загружено:
```bash
php -m | grep imap
```

### 2. Установка модуля в Bitrix

1. Скопируйте папку модуля в директорию:
   ```
   /path/to/bitrix/local/modules/bg.routing/
   ```

2. Установите зависимости через Composer:
   ```bash
   cd /path/to/bitrix/local/modules/bg.routing/
   composer install --no-dev --optimize-autoloader
   ```

3. Создайте директорию для логов:
   ```bash
   mkdir logs
   chmod 755 logs
   ```

## Настройки подключения

Все настройки подключения захардкожены в классе `EmailProcessor` (`lib/rout.php`):

```php
$imapServer = ''
$login = ''
$password = ''
```

Если нужно изменить настройки - отредактируйте эти значения в файле `lib/rout.php`.

## Установка через административную панель Bitrix

1. Войдите в административную панель Bitrix24
2. Перейдите в **Настройки → Управление структурой → Модули**
3. Найдите модуль **"Маршрутизация задач (bg.routing)"**
4. Нажмите **"Установить"**

## Настройка смарт-процесса

Модуль работает со смарт-процессом ID 1100. Убедитесь, что в вашем CRM настроены следующие поля:

### Стандартные поля:
- `TITLE` - заголовок (тема письма)
- `XML_ID` - уникальный ID сообщения
- `ASSIGNED_BY_ID` - ответственный
- `STAGE_ID` - стадия процесса

### Пользовательские поля:
- `UF_CRM_37_1750937812549` - Email отправителя
- `UF_CRM_37_DIVISION` - Имя отправителя  
- `UF_CRM_37_DATE_OF_ORDER` - Дата письма
- `UF_CRM_37_1749619917` - Имя файла вложения
- `SOURCE_DESCRIPTION` - JSON данные из Excel
- `UF_CRM_37_1749620161` - Приоритет (по умолчанию: 84)
- `UF_CRM_37_CATEGORY` - Категория (по умолчанию: 89)

## Использование

### Автоматическая обработка

После установки модуль автоматически запускается через агент Bitrix каждые 5 минут (300 секунд).

### Ручной запуск

Для тестирования можно запустить обработку вручную:

```bash
# Через браузер
http://yoursite.com/local/modules/bg.routing/lib/rout_test.php

# Или через CLI
php /path/to/bitrix/local/modules/bg.routing/lib/main.php
```

### Проверка агентов

Для проверки работы агентов используйте:
```
http://yoursite.com/local/modules/bg.routing/lib/getagents.php
```

## Логирование и отладка

### Файлы логов

- `lib/classes/debug_log.txt` - лог агента и основной обработки
- `lib/parsed_output.txt` - результаты парсинга Excel файлов

### Включение режима отладки

Для включения детального логирования установите в конструкторе `EmailProcessor`:

```php
$this->enableDebugLog = true; // вместо false
```

### Настройки обработки

В конструкторе `EmailProcessor` можно изменить:

```php
$this->maxExecutionTime = 300;     // Время выполнения скрипта
$this->messagesLimit = 10;         // Лимит обрабатываемых сообщений
$this->enableDebugLog = false;     // Режим отладки
```

## Структура проекта

```
bg.routing/
├── .gitignore             # Git ignore правила
├── README.md              # Эта инструкция
├── composer.json          # Зависимости PHP
├── include.php            # Автозагрузка классов
├── options.php            # Опции модуля
├── install/               # Файлы установки
│   ├── index.php          # Установщик модуля
│   ├── step1.php          # Страница успешной установки
│   ├── unstep1.php        # Страница удаления
│   └── version.php        # Версия модуля
├── lang/ru/               # Языковые файлы
├── lib/                   # Основная логика
│   ├── classes/           # Вспомогательные классы
│   │   ├── agent.php      # Агент для автозапуска
│   │   ├── settings.php   # Настройки модуля
│   │   └── debug_log.txt  # Лог файл агента
│   ├── rout.php          # Основной класс EmailProcessor
│   ├── rout_test.php     # Тестовый запуск
│   ├── main.php          # CLI точка входа
│   ├── getagents.php     # Проверка агентов
│   └── parsed_output.txt # Результаты парсинга
├── src/App/              # Современная архитектура (альтернативная)
└── vendor/               # Composer зависимости
```

## Принцип работы

1. **Агент** запускается каждые 5 минут через планировщик Bitrix
2. **EmailProcessor** подключается к IMAP серверу
3. Обрабатываются последние письма (по умолчанию 10 штук)
4. Ищются вложения с расширением `.xlsx`
5. Excel файлы парсятся с помощью PhpSpreadsheet
6. Данные сохраняются в смарт-процесс CRM
7. Результаты записываются в лог файлы

## Устранение неполадок

### Проблемы с IMAP

1. **Ошибка подключения к IMAP:**
   ```bash
   # Проверьте установку расширения
   php -m | grep imap
   
   # Проверьте доступность сервера
   telnet 10.81.65.12 993
   ```

2. **SSL ошибки:**
   - В настройках уже указан `/novalidate-cert`
   - Проверьте файрвол и доступность порта 993

3. **Таймауты подключения:**
   - Модуль имеет встроенную проверку доступности сервера
   - При недоступности сервера обработка прерывается

### Проблемы с правами доступа

```bash
# Установите правильные права на директории
chmod -R 755 /path/to/bitrix/local/modules/bg.routing/
chmod -R 777 /path/to/bitrix/local/modules/bg.routing/lib/classes/
```

### Проблемы с Composer

```bash
# Очистка и переустановка зависимостей
rm -rf vendor/ composer.lock
composer install --no-dev --optimize-autoloader
```

### Отладка работы агента

1. Проверьте лог агента: `lib/classes/debug_log.txt`
2. Запустите тест: `lib/rout_test.php`
3. Проверьте список агентов: `lib/getagents.php`
4. Посмотрите результаты парсинга: `lib/parsed_output.txt`

## Настройка других почтовых серверов

Если нужно подключиться к другому серверу, измените в `lib/rout.php`:

**Gmail:**
```php
$this->imapServer = '{imap.gmail.com:993/imap/ssl}INBOX';
```

**Yandex:**
```php
$this->imapServer = '{imap.yandex.ru:993/imap/ssl}INBOX';
```

**Mail.ru:**
```php
$this->imapServer = '{imap.mail.ru:993/imap/ssl}INBOX';
```

**Exchange/Outlook:**
```php
$this->imapServer = '{outlook.office365.com:993/imap/ssl}INBOX';
```

## Часто задаваемые вопросы

**Q: Модуль не обрабатывает письма**
A: Проверьте доступность IMAP сервера, работу агента через `getagents.php` и лог файл `debug_log.txt`

**Q: Не создаются элементы в CRM**
A: Убедитесь, что смарт-процесс с ID 1100 существует и содержит необходимые поля

**Q: Ошибки при парсинге Excel**
A: Проверьте, что файлы действительно в формате `.xlsx` и не повреждены

**Q: Агент не запускается**
A: Проверьте, что модуль установлен и агент создан через `getagents.php`
