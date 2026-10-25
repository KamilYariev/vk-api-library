<?php

declare(strict_types=1);

namespace VkApi;

use InvalidArgumentException;
use VkApi\Auth\TokenProviderInterface;
use VkApi\Exception\AccessDeniedException;
use VkApi\Exception\ApiException;
use VkApi\Exception\AuthenticationException;
use VkApi\Exception\InvalidRequestException;
use VkApi\Exception\InvalidResponseException;
use VkApi\Exception\NetworkException;
use VkApi\Exception\RateLimitException;
use VkApi\Exception\VkApiException;
use VkApi\Http\HttpClientInterface;
use VkApi\Model\Dialog;
use VkApi\Model\VKUser;
use VkApi\Response\ApiResponse;

final class VKClient
{
    private const API_BASE_URL = 'https://api.vk.com/method/';
    private const RETRYABLE_HTTP_CODES = [408, 425, 429, 500, 502, 503, 504];
    private const RETRYABLE_VK_CODES = [6, 9, 10, 29, 420];
    private const ALLOWED_MESSAGE_OPTIONS = [
        'peer_id',
        'random_id',
        'attachment',
        'reply_to',
        'forward_messages',
        'forward',
        'keyboard',
        'dont_parse_links',
        'disable_mentions',
        'payload',
        'template',
        'intent',
        'subscribe_id',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TokenProviderInterface $tokenProvider,
        private readonly string $apiVersion = '5.199',
        private readonly int $maxRetries = 3,
        private readonly int $retryDelayMs = 200
    ) {
        if ($this->apiVersion === '') {
            throw new InvalidArgumentException('Версия VK API не может быть пустой.');
        }

        if ($this->maxRetries < 0 || $this->maxRetries > 10) {
            throw new InvalidArgumentException('maxRetries должен быть в диапазоне 0–10.');
        }

        if ($this->retryDelayMs < 0) {
            throw new InvalidArgumentException('retryDelayMs не может быть отрицательным.');
        }
    }

    /**
     * Отправляет сообщение пользователю либо peer ID диалога.
     *
     * Для группового диалога передайте его peer ID в options['peer_id'].
     * random_id создаётся автоматически и остаётся одинаковым при сетевых повторах.
     *
     * @param array<string, mixed> $options
     */
    public function sendMessage(int $userId, string $message, array $options = []): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('userId должен быть положительным числом.');
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('Текст сообщения не может быть пустым.');
        }

        $unknownOptions = array_diff(array_keys($options), self::ALLOWED_MESSAGE_OPTIONS);
        if ($unknownOptions !== []) {
            throw new InvalidArgumentException(
                'Неподдерживаемые параметры сообщения: ' . implode(', ', $unknownOptions)
            );
        }

        $randomId = $options['random_id'] ?? random_int(1, 2_147_483_647);
        if (!is_int($randomId)) {
            throw new InvalidArgumentException('Опция random_id должна быть целым числом.');
        }

        $params = [
            'message' => $message,
            'random_id' => $randomId,
        ];

        if (array_key_exists('peer_id', $options)) {
            if (!is_int($options['peer_id']) || $options['peer_id'] <= 0) {
                throw new InvalidArgumentException('Опция peer_id должна быть положительным целым числом.');
            }

            $params['peer_id'] = $options['peer_id'];
        } else {
            $params['user_id'] = $userId;
        }

        foreach ($options as $key => $value) {
            if (in_array($key, ['peer_id', 'random_id'], true)) {
                continue;
            }

            $params[$key] = $value;
        }

        $response = $this->request('messages.send', $params);

        if (is_int($response) || (is_numeric($response) && (int) $response >= 0)) {
            return (int) $response;
        }

        if (is_array($response) && isset($response['message_id']) && is_numeric($response['message_id'])) {
            return (int) $response['message_id'];
        }

        throw new InvalidResponseException('VK API вернул неожиданный результат для messages.send.');
    }

    /**
     * @return list<Dialog>
     */
    public function getDialogs(int $offset = 0, int $count = 10): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('offset не может быть отрицательным.');
        }

        if ($count < 1 || $count > 200) {
            throw new InvalidArgumentException('count должен быть в диапазоне 1–200.');
        }

        $response = $this->request('messages.getDialogs', [
            'offset' => $offset,
            'count' => $count,
        ]);

        if (!is_array($response)) {
            throw new InvalidResponseException('VK API вернул неожиданный результат для messages.getDialogs.');
        }

        // В ответе метода обычно используется объект с полями count и items.
        $items = $response['items'] ?? null;

        // Поддерживаем также массив элементов на случай отличий формата ответа.
        if ($items === null && array_is_list($response)) {
            $items = $response;
        }

        if (!is_array($items)) {
            throw new InvalidResponseException('В ответе messages.getDialogs отсутствует список items.');
        }

        $dialogs = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidResponseException('VK API вернул некорректный элемент диалога.');
            }

            $dialogs[] = Dialog::fromApi($item);
        }

        return $dialogs;
    }

    public function getUser(int $userId): VKUser
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('userId должен быть положительным числом.');
        }

        $response = $this->request('users.get', [
            'user_ids' => $userId,
            'fields' => 'photo_200',
        ]);

        if (!is_array($response) || !isset($response[0]) || !is_array($response[0])) {
            throw new InvalidResponseException('VK API не вернул данные пользователя.');
        }

        return VKUser::fromApi($response[0]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function request(string $method, array $params): mixed
    {
        $token = $this->tokenProvider->getToken();
        $formParams = $params + [
            'access_token' => $token,
            'v' => $this->apiVersion,
        ];

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $httpResponse = $this->httpClient->post(
                    self::API_BASE_URL . $method,
                    $formParams,
                    ['Accept' => 'application/json']
                );
            } catch (NetworkException $exception) {
                if ($attempt < $this->maxRetries) {
                    $this->pauseBeforeRetry($attempt);
                    continue;
                }

                throw $exception;
            }

            if (in_array($httpResponse->statusCode, self::RETRYABLE_HTTP_CODES, true)) {
                if ($attempt < $this->maxRetries) {
                    $this->pauseBeforeRetry($attempt);
                    continue;
                }

                if ($httpResponse->statusCode === 429) {
                    throw new RateLimitException(
                        'VK API временно ограничил частоту запросов (HTTP 429).',
                        null,
                        429
                    );
                }

                throw new ApiException(
                    sprintf('Временная HTTP-ошибка VK API: %d.', $httpResponse->statusCode),
                    null,
                    $httpResponse->statusCode
                );
            }

            $apiResponse = ApiResponse::fromHttpResponse($httpResponse);

            if ($apiResponse->hasError()) {
                $errorCode = $apiResponse->errorCode();

                if (
                    $errorCode !== null
                    && in_array($errorCode, self::RETRYABLE_VK_CODES, true)
                    && $attempt < $this->maxRetries
                ) {
                    $this->pauseBeforeRetry($attempt);
                    continue;
                }

                throw $this->mapApiError(
                    $apiResponse->errorMessage(),
                    $errorCode,
                    $httpResponse->statusCode
                );
            }

            if ($httpResponse->statusCode < 200 || $httpResponse->statusCode >= 300) {
                throw new ApiException(
                    sprintf('VK API вернул HTTP-статус %d.', $httpResponse->statusCode),
                    null,
                    $httpResponse->statusCode
                );
            }

            return $apiResponse->response();
        }

        // Цикл всегда завершается return или исключением; эта ветка нужна для анализа типов.
        throw new ApiException('Не удалось выполнить запрос к VK API.');
    }

    private function pauseBeforeRetry(int $attempt): void
    {
        $delayMs = min($this->retryDelayMs * (2 ** $attempt), 10_000);

        if ($delayMs > 0) {
            usleep((int) ($delayMs * 1_000));
        }
    }

    private function mapApiError(string $message, ?int $errorCode, int $httpStatusCode): VkApiException
    {
        if (in_array($errorCode, [5, 27, 28], true)) {
            return new AuthenticationException($message, $errorCode, $httpStatusCode);
        }

        if (in_array($errorCode, [7, 15, 188, 203, 212, 213, 214], true)) {
            return new AccessDeniedException($message, $errorCode, $httpStatusCode);
        }

        if (in_array($errorCode, [6, 9, 29, 420], true)) {
            return new RateLimitException($message, $errorCode, $httpStatusCode);
        }

        if (in_array($errorCode, [100, 111, 113], true)) {
            return new InvalidRequestException($message, $errorCode, $httpStatusCode);
        }

        return new ApiException($message, $errorCode, $httpStatusCode);
    }
}
