<?php

/**
 * Intelephense Stubs за OpenCart 4.x
 * Този файл съдържа дефиниции на основните OpenCart класове за Intelephense
 * За да избегнем грешки за липсващи класове и методи
 */

namespace {
    /**
     * VERSION - Константа за версията на OpenCart (глобален namespace)
     */
    if (!defined('VERSION')) {
        define('VERSION', '4.0.0.0');
    }

    /**
     * DB_PREFIX - Префикс за таблиците в базата данни
     */
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'oc_');
    }

    /**
     * DIR_SYSTEM - Път до системната директория
     */
    if (!defined('DIR_SYSTEM')) {
        define('DIR_SYSTEM', '/var/www/open40.avalonbg.com/system/');
    }

    /**
     * DIR_EXTENSION - Път до директорията с разширения
     */
    if (!defined('DIR_EXTENSION')) {
        define('DIR_EXTENSION', '/var/www/open40.avalonbg.com/extension/');
    }

    /**
     * DIR_APPLICATION - Път до основната директория на приложението (catalog или admin)
     */
    if (!defined('DIR_APPLICATION')) {
        define('DIR_APPLICATION', '/var/www/open40.avalonbg.com/catalog/');
    }

    /**
     * Registry - Основният клас за достъп до всички OpenCart компоненти
     */
    class Registry
    {
        private array $data = [];

        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value): void {}
        public function has(string $key): bool
        {
            return false;
        }
    }

    /**
     * Loader - Клас за зареждане на модели, библиотеки, езици и т.н.
     */
    class Loader
    {
        protected Registry $registry;

        public function __construct(Registry $registry) {}
        public function model(string $route, mixed &$data = []): mixed
        {
            return null;
        }
        public function view(string $route, array $data = []): string
        {
            return '';
        }
        public function controller(string $route, array $data = []): mixed
        {
            return null;
        }
        public function library(string $route, array &$data = []): void {}
        public function helper(string $route): void {}
        public function config(string $route): array
        {
            return [];
        }
        public function language(string $route, string $key = ''): array|string
        {
            return '';
        }
    }

    /**
     * Controller - Базов клас за всички контролери
     */
    class Controller
    {
        protected Registry $registry;
        protected Loader $load;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function getRegistry(): Registry
        {
            return new Registry();
        }
    }

    /**
     * Model - Базов клас за всички модели
     */
    class Model
    {
        protected Registry $registry;
        /** @var Loader */
        public Loader $load;
        /** @var Config */
        public Config $config;
        /** @var Language */
        public Language $language;

        public function __construct(Registry $registry) {}
    }

    /**
     * Language - Клас за работа с езикови файлове
     */
    class Language
    {
        protected Registry $registry;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function get(string $key): string
        {
            return '';
        }
        public function set(string $key, string $value): void {}
        public function all(): array
        {
            return [];
        }
        public function load(string $filename, string $key = ''): array
        {
            return [];
        }
    }

    /**
     * Config - Клас за работа с конфигурация
     */
    class Config
    {
        protected Registry $registry;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value): void {}
        public function has(string $key): bool
        {
            return false;
        }
    }

    /**
     * Request - Клас за работа с HTTP заявки
     */
    class Request
    {
        public array $get = [];
        public array $post = [];
        public array $request = [];
        public array $cookie = [];
        public array $files = [];
        public array $server = [];

        public function clean(mixed $data, string $type = ''): mixed
        {
            return null;
        }
        public function getServer(string $key): mixed
        {
            return null;
        }
    }

    /**
     * Response - Клас за работа с HTTP отговори
     */
    class Response
    {
        protected array $headers = [];
        protected int $level = 0;
        protected string $output = '';

        /**
         * @param string $header Header name or "Header: Value" format
         * @param string $value Optional value if header is provided separately
         */
        public function addHeader(string $header, string $value = ''): void {}
        public function redirect(string $url, int $status = 302): void {}
        public function setCompression(int $level): void {}
        public function setOutput(string $output): void {}
        public function getOutput(): string
        {
            return '';
        }
        public function output(): void {}
    }

    /**
     * Session - Клас за работа с сесии
     */
    class Session
    {
        protected Registry $registry;
        protected string $session_id = '';
        protected array $data = [];

        public function __construct(Registry $registry, string $session_id = '') {}
        public function getId(): string
        {
            return '';
        }
        public function start(string $key = 'default', bool $value = true): void {}
        public function has(string $key): bool
        {
            return false;
        }
        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value): void {}
        public function remove(string $key): void {}
    }

    /**
     * DB - Клас за работа с база данни
     */
    class DB
    {
        protected object $connection;
        protected string $hostname;
        protected string $username;
        protected string $password;
        protected string $database;
        protected string $port;
        protected string $prefix;

        public function __construct(string $hostname, string $username, string $password, string $database, string $port = '', string $prefix = '') {}
        public function query(string $sql): object|bool
        {
            return false;
        }
        public function escape(string $value): string
        {
            return '';
        }
        public function countAffected(): int
        {
            return 0;
        }
        public function getLastId(): int
        {
            return 0;
        }
        public function connected(): bool
        {
            return false;
        }
    }

    /**
     * Url - Клас за генериране на URL адреси
     */
    class Url
    {
        protected Registry $registry;
        protected string $url;
        protected array $rewrite = [];

        public function __construct(Registry $registry) {}
        public function link(string $route, string|array $args = '', bool $secure = false): string
        {
            return '';
        }
        public function addRewrite(string $key, string $value): void {}
    }

    /**
     * Document - Клас за управление на документ (мета данни, скриптове, стилове)
     */
    class Document
    {
        protected Registry $registry;
        protected string $title = '';
        protected string $description = '';
        protected string $keywords = '';
        protected array $links = [];
        protected array $styles = [];
        protected array $scripts = [];
        protected string $og_image = '';

        public function __construct(Registry $registry) {}
        public function setTitle(string $title): void {}
        public function getTitle(): string
        {
            return '';
        }
        public function setDescription(string $description): void {}
        public function getDescription(): string
        {
            return '';
        }
        public function setKeywords(string $keywords): void {}
        public function getKeywords(): string
        {
            return '';
        }
        public function addLink(string $href, string $rel): void {}
        public function addStyle(string $href, string $rel = 'stylesheet', string $media = 'screen'): void {}
        public function addScript(string $href, string $position = 'header'): void {}
        public function setOgImage(string $image): void {}
        public function getOgImage(): string
        {
            return '';
        }
    }

    /**
     * Log - Клас за писане в логове
     */
    class Log
    {
        protected string $file;

        public function __construct(string $filename) {}
        public function write(string $message): void {}
        public function getFile(): string
        {
            return '';
        }
    }

    /**
     * Cache - Клас за работа с кеш
     */
    class Cache
    {
        protected Registry $registry;
        protected object $adapter;
        protected int $expire;

        public function __construct(Registry $registry, int $expire = 3600) {}
        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value, int $expire = 0): void {}
        public function delete(string $key): void {}
    }

    /**
     * Event - Клас за работа с събития (events)
     */
    class Event
    {
        protected Registry $registry;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function register(string $trigger, string $action, int $priority = 0): void {}
        public function unregister(string $trigger, string $route): void {}
        public function trigger(string $event, array $args = []): mixed
        {
            return null;
        }
    }

    /**
     * Cart - Клас за работа с количката
     */
    class Cart
    {
        protected Registry $registry;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function getProducts(): array
        {
            return [];
        }
        public function add(int $product_id, int $quantity = 1, array $option = [], int $recurring_id = 0): void {}
        public function remove(string $key): void {}
        public function clear(): void {}
        public function getWeight(): float
        {
            return 0.0;
        }
        public function getSubTotal(): float
        {
            return 0.0;
        }
        public function getTaxes(): array
        {
            return [];
        }
        public function getTotal(): float
        {
            return 0.0;
        }
        public function countProducts(): int
        {
            return 0;
        }
        public function hasProducts(): bool
        {
            return false;
        }
        public function hasRecurringProducts(): bool
        {
            return false;
        }
        public function hasStock(): bool
        {
            return false;
        }
        public function hasShipping(): bool
        {
            return false;
        }
        public function hasDownload(): bool
        {
            return false;
        }
    }

    /**
     * Customer - Клас за работа с клиенти
     */
    class Customer
    {
        protected Registry $registry;
        protected int $customer_id = 0;
        protected string $customer_group_id = '';
        protected string $firstname = '';
        protected string $lastname = '';
        protected string $email = '';
        protected string $telephone = '';
        protected string $newsletter = '';
        protected string $address_id = '';

        public function __construct(Registry $registry) {}
        public function login(string $email, string $password, bool $override = false): bool
        {
            return false;
        }
        public function logout(): void {}
        public function isLogged(): bool
        {
            return false;
        }
        public function getId(): int
        {
            return 0;
        }
        public function getGroupId(): int
        {
            return 0;
        }
        public function getFirstName(): string
        {
            return '';
        }
        public function getLastName(): string
        {
            return '';
        }
        public function getEmail(): string
        {
            return '';
        }
        public function getTelephone(): string
        {
            return '';
        }
        public function getNewsletter(): bool
        {
            return false;
        }
        public function getAddressId(): int
        {
            return 0;
        }
        public function getBalance(): float
        {
            return 0.0;
        }
        public function getRewardPoints(): int
        {
            return 0;
        }
    }

    /**
     * Currency - Клас за работа с валути
     */
    class Currency
    {
        protected Registry $registry;
        protected string $code = '';
        protected array $currencies = [];

        public function __construct(Registry $registry) {}
        public function set(string $currency): void {}
        public function format(float $number, string $currency = '', float $value = 0, bool $format = true): string
        {
            return '';
        }
        public function convert(float $value, string $from, string $to): float
        {
            return 0.0;
        }
        public function getId(string $currency = ''): int
        {
            return 0;
        }
        public function getSymbolLeft(string $currency = ''): string
        {
            return '';
        }
        public function getSymbolRight(string $currency = ''): string
        {
            return '';
        }
        public function getDecimalPlace(string $currency = ''): int
        {
            return 0;
        }
        public function getCode(): string
        {
            return '';
        }
        public function getValue(string $currency = ''): float
        {
            return 0.0;
        }
        public function has(string $currency): bool
        {
            return false;
        }
    }

    /**
     * Tax - Клас за работа с данъци
     */
    class Tax
    {
        protected Registry $registry;
        protected array $tax_rates = [];

        public function __construct(Registry $registry) {}
        public function setShippingAddress(int $country_id, int $zone_id): void {}
        public function setPaymentAddress(int $country_id, int $zone_id): void {}
        public function setStoreAddress(int $country_id, int $zone_id): void {}
        public function calculate(float $value, int $tax_class_id, bool $calculate = true): float
        {
            return 0.0;
        }
        public function getTax(float $value, int $tax_class_id): float
        {
            return 0.0;
        }
        public function getRateName(int $tax_rate_id): string
        {
            return '';
        }
        public function getRate(int $tax_rate_id): float
        {
            return 0.0;
        }
        public function getRates(float $value, int $tax_class_id): array
        {
            return [];
        }
    }

    /**
     * Weight - Клас за работа с тегла
     */
    class Weight
    {
        protected Registry $registry;
        protected array $weights = [];

        public function __construct(Registry $registry) {}
        public function convert(float $value, string $from, string $to): float
        {
            return 0.0;
        }
        public function format(float $value, string $weight_class_id, string $decimal_point = '.', string $thousand_point = ','): string
        {
            return '';
        }
        public function getUnit(string $weight_class_id): string
        {
            return '';
        }
    }

    /**
     * Length - Клас за работа с дължини
     */
    class Length
    {
        protected Registry $registry;
        protected array $lengths = [];

        public function __construct(Registry $registry) {}
        public function convert(float $value, string $from, string $to): float
        {
            return 0.0;
        }
        public function format(float $value, string $length_class_id, string $decimal_point = '.', string $thousand_point = ','): string
        {
            return '';
        }
        public function getUnit(string $length_class_id): string
        {
            return '';
        }
    }
}

namespace Opencart\System\Engine {
    /**
     * VERSION - Константа за версията на OpenCart
     */
    const VERSION = '4.0.0.0';

    /**
     * Registry - Основният клас за достъп до всички OpenCart компоненти
     */
    class Registry
    {
        private array $data = [];

        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value): void {}
        public function has(string $key): bool
        {
            return false;
        }
    }

    /**
     * Loader - Клас за зареждане на модели, библиотеки, езици и т.н.
     */
    class Loader
    {
        protected Registry $registry;

        public function __construct(Registry $registry) {}
        public function model(string $route, mixed &$data = []): mixed
        {
            return null;
        }
        public function view(string $route, array $data = []): string
        {
            return '';
        }
        public function controller(string $route, array $data = []): mixed
        {
            return null;
        }
        public function library(string $route, array &$data = []): void {}
        public function helper(string $route): void {}
        public function config(string $route): array
        {
            return [];
        }
        public function language(string $route, string $key = ''): array|string
        {
            return '';
        }
    }

    /**
     * Language - Клас за работа с езикови файлове
     */
    class Language
    {
        protected Registry $registry;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function get(string $key): string
        {
            return '';
        }
        public function set(string $key, string $value): void {}
        public function all(): array
        {
            return [];
        }
        public function load(string $filename, string $key = ''): array
        {
            return [];
        }
    }

    /**
     * Document - Клас за управление на документ (мета данни, скриптове, стилове)
     */
    class Document
    {
        protected Registry $registry;
        protected string $title = '';
        protected string $description = '';
        protected string $keywords = '';
        protected array $links = [];
        protected array $styles = [];
        protected array $scripts = [];
        protected string $og_image = '';

        public function __construct(Registry $registry) {}
        public function setTitle(string $title): void {}
        public function getTitle(): string
        {
            return '';
        }
        public function setDescription(string $description): void {}
        public function getDescription(): string
        {
            return '';
        }
        public function setKeywords(string $keywords): void {}
        public function getKeywords(): string
        {
            return '';
        }
        public function addLink(string $href, string $rel): void {}
        public function addStyle(string $href, string $rel = 'stylesheet', string $media = 'screen'): void {}
        public function addScript(string $href, string $position = 'header'): void {}
        public function setOgImage(string $image): void {}
        public function getOgImage(): string
        {
            return '';
        }
    }

    /**
     * Url - Клас за генериране на URL адреси
     */
    class Url
    {
        protected Registry $registry;
        protected string $url;
        protected array $rewrite = [];

        public function __construct(Registry $registry) {}
        public function link(string $route, string|array $args = '', bool $secure = false): string
        {
            return '';
        }
        public function addRewrite(string $key, string $value): void {}
    }

    /**
     * Session - Клас за работа с сесии
     */
    class Session
    {
        protected Registry $registry;
        protected string $session_id = '';
        public array $data = [];

        public function __construct(Registry $registry, string $session_id = '') {}
        public function getId(): string
        {
            return '';
        }
        public function start(string $key = 'default', bool $value = true): void {}
        public function has(string $key): bool
        {
            return false;
        }
        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value): void {}
        public function remove(string $key): void {}
    }

    /**
     * Config - Клас за работа с конфигурация
     */
    class Config
    {
        protected Registry $registry;
        protected array $data = [];

        public function __construct(Registry $registry) {}
        public function get(string $key): mixed
        {
            return null;
        }
        public function set(string $key, mixed $value): void {}
        public function has(string $key): bool
        {
            return false;
        }
    }

    /**
     * Response - Клас за работа с HTTP отговори
     */
    class Response
    {
        protected array $headers = [];
        protected int $level = 0;
        protected string $output = '';

        /**
         * @param string $header Header name or "Header: Value" format
         * @param string $value Optional value if header is provided separately
         */
        public function addHeader(string $header, string $value = ''): void {}
        public function redirect(string $url, int $status = 302): void {}
        public function setCompression(int $level): void {}
        public function setOutput(string $output): void {}
        public function getOutput(): string
        {
            return '';
        }
        public function output(): void {}
    }

    /**
     * Request - Клас за работа с HTTP заявки
     */
    class Request
    {
        public array $get = [];
        public array $post = [];
        public array $request = [];
        public array $cookie = [];
        public array $files = [];
        public array $server = [];

        public function clean(mixed $data, string $type = ''): mixed
        {
            return null;
        }
        public function getServer(string $key): mixed
        {
            return null;
        }
    }

    /**
     * User - Клас за работа с потребители (администратори)
     */
    class User
    {
        protected Registry $registry;
        protected int $user_id = 0;
        protected string $username = '';
        protected string $user_group_id = '';
        protected array $permission = [];

        public function __construct(Registry $registry) {}
        public function login(string $username, string $password): bool
        {
            return false;
        }
        public function logout(): void {}
        public function hasPermission(string $type, string $route): bool
        {
            return false;
        }
        public function isLogged(): bool
        {
            return false;
        }
        public function getId(): int
        {
            return 0;
        }
        public function getUserName(): string
        {
            return '';
        }
        public function getGroupId(): int
        {
            return 0;
        }
    }

    /**
     * Controller - Базов клас за всички контролери в OpenCart 4
     * 
     * @property SettingSetting $model_setting_setting
     * @property SettingExtension $model_setting_extension
     */
    class Controller
    {
        protected Registry $registry;
        public Loader $load;
        protected array $data = [];
        public Language $language;
        public Document $document;
        public Url $url;
        public Session $session;
        public Config $config;
        public Response $response;
        public User $user;
        public Request $request;
        /** @var SettingSetting */
        public $model_setting_setting;
        /** @var SettingExtension */
        public $model_setting_extension;

        public function __construct(Registry $registry) {}
        public function getRegistry(): Registry
        {
            return new Registry();
        }

        /**
         * Магически метод за достъп до динамично заредени модели
         * @param string $name
         * @return mixed
         */
        public function __get(string $name): mixed
        {
            return null;
        }
    }

    /**
     * DB - Клас за работа с база данни
     */
    class DB
    {
        protected object $connection;
        protected string $hostname;
        protected string $username;
        protected string $password;
        protected string $database;
        protected string $port;
        protected string $prefix;

        public function __construct(string $hostname, string $username, string $password, string $database, string $port = '', string $prefix = '') {}
        public function query(string $sql): object|bool
        {
            return false;
        }
        public function escape(string $value): string
        {
            return '';
        }
        public function countAffected(): int
        {
            return 0;
        }
        public function getLastId(): int
        {
            return 0;
        }
        public function connected(): bool
        {
            return false;
        }
    }

    /**
     * Model - Базов клас за всички модели в OpenCart 4
     */
    class Model
    {
        protected Registry $registry;
        public DB $db;
        /** @var Loader */
        public Loader $load;
        /** @var Config */
        public Config $config;
        /** @var Language */
        public Language $language;
        /** @var Session */
        public Session $session;

        public function __construct(Registry $registry) {}
    }

    /**
     * Setting Model - Модел за работа с настройки
     */
    class SettingSetting extends Model
    {
        public function editSetting(string $code, array $data): void {}
        public function getSetting(string $code, string $key = ''): mixed
        {
            return null;
        }
        public function deleteSetting(string $code): void {}
    }

    /**
     * Extension Model - Модел за работа с разширения
     */
    class SettingExtension extends Model
    {
        public function getExtensionsByType(string $type): array
        {
            return [];
        }
        public function getExtensionByCode(string $type, string $code): array
        {
            return [];
        }
        public function install(string $type, string $code): void {}
        public function uninstall(string $type, string $code): void {}
    }
}

namespace Opencart\System\Library {
    class Mail
    {
        protected string $to = '';
        protected string $from = '';
        protected string $sender = '';
        protected string $reply_to = '';
        protected string $subject = '';
        protected string $text = '';
        protected string $html = '';
        protected array $attachments = [];
        protected string $parameter = '';

        public function __construct(string $engine, array $option = []) {}
        public function setTo(string $to): void {}
        public function setFrom(string $from): void {}
        public function setSender(string $sender): void {}
        public function setReplyTo(string $reply_to): void {}
        public function setSubject(string $subject): void {}
        public function setText(string $text): void {}
        public function setHtml(string $html): void {}
        public function addAttachment(string $filename): void {}
        public function send(): bool
        {
            return true;
        }
    }
}
