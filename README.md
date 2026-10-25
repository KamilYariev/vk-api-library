# VK API Library (PHP 8.1+)

Типизированная PHP-библиотека для вызова методов VK API `messages.send`, `messages.getDialogs` и `users.get`.

## Возможности

- PHP 8.1+, типизированные свойства, `readonly`-модели и `DialogType` enum.
- PSR-12-ориентированный стиль и PSR-4 autoload.
- Dependency Injection: HTTP-клиент и поставщик токена передаются в `VKClient` через конструктор.
- Три требуемых метода: `sendMessage()`, `getDialogs()` и `getUser()`.
- `random_id` для защиты от повторной отправки сообщения при сетевом повторе.
- Типизированные `Dialog` и `VKUser`, а не сырые массивы в публичном API.
- Отдельные исключения для авторизации, доступа, лимитов, некорректных запросов, сетевых ошибок и ошибок API.
- Повтор запросов при сетевых сбоях, HTTP 408/425/429/5xx и временных ошибках VK API.
- PHPUnit-тесты без реальных запросов к VK API.
- GitHub Actions workflow для запуска тестов на PHP 8.1–8.3.

> Библиотека использует указанный в задании метод `messages.getDialogs`. Доступность методов и требуемые разрешения зависят от актуальной версии VK API и типа токена. Перед реальным использованием проверьте документацию VK API.

## Требования

- PHP 8.1 или новее.
- Composer.
- PHPUnit 10.5 для запуска тестов (устанавливается Composer).

## Установка и запуск

### Запуск из клонированного репозитория

```bash
git clone https://github.com/<YOUR_GITHUB_USERNAME>/vk-api-library.git
cd vk-api-library
composer install
composer test
```

Замените `<YOUR_GITHUB_USERNAME>` на имя своего аккаунта и используйте точное имя репозитория, который создадите на GitHub.

### Установка как Composer-пакета

Имя пакета в `composer.json`: `miem/vk-api-library`.

После публикации пакета в Packagist его можно будет установить командой:

```bash
composer require miem/vk-api-library
```

Пока пакет не опубликован в Packagist, эта команда не гарантирует установку. Для локальной проверки используйте клонирование репозитория и `composer install`. Установку непосредственно из Git можно настроить через VCS-репозиторий Composer, указав URL своего GitHub-репозитория.

## Получение токена

Получите токен через официальный механизм авторизации VK для нужного приложения и сценария. Не храните реальный токен в исходниках, README, тестах или Git-репозитории.

Перед запуском задайте переменную окружения:

**macOS / Linux**
```bash
export VK_ACCESS_TOKEN="ваш_токен"
```

**Windows PowerShell**
```powershell
$env:VK_ACCESS_TOKEN = "ваш_токен"
```

В production используйте защищённое хранилище секретов или переменные окружения. Не коммитьте `.env` с настоящим токеном.

## Примеры использования

### Создание клиента

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use VkApi\Auth\EnvironmentTokenProvider;
use VkApi\Http\NativeHttpClient;
use VkApi\VKClient;

$client = new VKClient(
    httpClient: new NativeHttpClient(timeoutSeconds: 10),
    tokenProvider: new EnvironmentTokenProvider('VK_ACCESS_TOKEN'),
    apiVersion: '5.199',
    maxRetries: 3,
    retryDelayMs: 200
);
```

### Отправка сообщения

```php
$messageId = $client->sendMessage(123456, 'Привет!');

echo "ID сообщения: {$messageId}";
```

Для повторной отправки того же логического сообщения при собственных повторах приложения передавайте один и тот же `random_id`:

```php
$messageId = $client->sendMessage(
    123456,
    'Напоминание о встрече',
    ['random_id' => 20261025]
);
```

Для диалога/чата можно передать peer ID:

```php
$messageId = $client->sendMessage(
    123456,
    'Сообщение в чат',
    ['peer_id' => 2000000001]
);
```

При наличии `peer_id` библиотека отправляет параметр `peer_id`, а не `user_id`. Первый аргумент по сигнатуре всё равно должен быть положительным целым числом.

Другие поддерживаемые опции: `attachment`, `reply_to`, `forward_messages`, `forward`, `keyboard`, `dont_parse_links`, `disable_mentions`, `payload`, `template`, `intent`, `subscribe_id`. Неизвестные опции отклоняются, чтобы избежать незаметных ошибок.

### Получение диалогов

```php
$dialogs = $client->getDialogs(offset: 0, count: 10);

foreach ($dialogs as $dialog) {
    echo $dialog->peerId . ': ' . ($dialog->title ?? '(без названия)') . PHP_EOL;
    echo 'Тип: ' . $dialog->type->value . PHP_EOL;
    echo 'Непрочитано: ' . $dialog->unreadCount . PHP_EOL;
}
```

Метод возвращает `list<VkApi\Model\Dialog>`. Если API не вернул корректный список диалогов, библиотека выбрасывает `InvalidResponseException`.

### Получение пользователя

```php
$user = $client->getUser(123456);

echo $user->fullName() . PHP_EOL;
echo $user->avatarUrl ?? 'Аватар не указан';
```

`getUser()` возвращает `VkApi\Model\VKUser` с полями `id`, `firstName`, `lastName`, `avatarUrl`.

## Публичный API

### `VkApi\VKClient`

| Метод | Результат | Назначение |
|---|---|---|
| `sendMessage(int $userId, string $message, array $options = []): int` | ID сообщения | Отправка сообщения пользователю или peer ID диалога |
| `getDialogs(int $offset = 0, int $count = 10): array` | `list<Dialog>` | Получение типизированного списка диалогов |
| `getUser(int $userId): VKUser` | `VKUser` | Получение имени, фамилии и аватарки пользователя |

### Публичные модели

- `VkApi\Model\Dialog`: `peerId`, `type`, `title`, `unreadCount`, `lastMessageId`, `lastMessageText`, `lastMessageDate`.
- `VkApi\Model\DialogType`: `USER`, `CHAT`, `GROUP`, `EMAIL`, `UNKNOWN`.
- `VkApi\Model\VKUser`: `id`, `firstName`, `lastName`, `avatarUrl`, метод `fullName()`.

### Поставщики токена и HTTP-клиент

- `TokenProviderInterface`: контракт получения токена.
- `EnvironmentTokenProvider`: читает токен из переменной окружения.
- `StaticTokenProvider`: фиксированный токен для тестов/специальных случаев; не рекомендуется для токенов, зашитых в приложение.
- `HttpClientInterface`: контракт HTTP-клиента.
- `NativeHttpClient`: реализация на потоках PHP без обязательной сторонней HTTP-библиотеки.

### Исключения

- `AuthenticationException` — ошибка токена/авторизации.
- `AccessDeniedException` — нет прав на действие, включая VK error code `188`.
- `RateLimitException` — ограничение частоты запросов.
- `InvalidRequestException` — некорректные параметры запроса.
- `InvalidResponseException` — ответ API имеет неожиданный формат.
- `NetworkException` — сетевой сбой.
- `ApiException` — прочая ошибка API.
- `VkApiException` — общий базовый класс исключений VK API.

Все исключения API, наследующие `VkApiException`, могут содержать `vkErrorCode` и `httpStatusCode`.

## Retry и защита от дубликатов

По умолчанию клиент делает до трёх повторов после первоначального запроса. Используется экспоненциальная задержка с ограничением в 10 секунд. Повторяются сетевые ошибки и временные HTTP/VK ошибки. `random_id` создаётся один раз до цикла повторов, поэтому не меняется между попытками одного вызова `sendMessage()`.

Retry не применяется к ошибкам доступа или некорректным параметрам. Повторные вызовы `sendMessage()` из кода приложения считаются новыми вызовами; чтобы VK мог распознать повтор логического сообщения, передавайте тот же `random_id`.

## Тесты

```bash
composer install
composer test
```

Тесты используют `FakeHttpClient` и не отправляют реальные сообщения. Проверяются успешная отправка, сохранение `random_id` при повторе, сетевая ошибка, ошибка доступа `188`, типизация диалогов и пользователя, ограничение частоты запросов и валидация входных данных.

## Ограничения

- Это базовая библиотека, а не официальный SDK VK.
- Вызовы зависят от разрешений токена, версии VK API и доступности методов для конкретного типа токена.
- Тесты не проверяют живое подключение к VK и не подтверждают наличие прав у реального токена.
- `getDialogs()` следует сверить с актуальной документацией VK API перед эксплуатацией, поскольку в разных версиях API форматы и доступность методов могут отличаться.
