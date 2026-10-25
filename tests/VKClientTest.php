<?php

declare(strict_types=1);

namespace VkApi\Tests;

use PHPUnit\Framework\TestCase;
use VkApi\Auth\StaticTokenProvider;
use VkApi\Exception\AccessDeniedException;
use VkApi\Exception\NetworkException;
use VkApi\Exception\RateLimitException;
use VkApi\Http\HttpResponse;
use VkApi\Model\Dialog;
use VkApi\Model\DialogType;
use VkApi\Model\VKUser;
use VkApi\VKClient;

final class VKClientTest extends TestCase
{
    private function client(FakeHttpClient $httpClient, int $maxRetries = 0): VKClient
    {
        return new VKClient(
            $httpClient,
            new StaticTokenProvider('test-token-not-a-real-secret'),
            '5.199',
            $maxRetries,
            0
        );
    }

    public function testSendMessageReturnsMessageIdAndUsesRandomId(): void
    {
        $http = new FakeHttpClient([
            new HttpResponse(200, '{"response":12345}'),
        ]);
        $client = $this->client($http);

        $messageId = $client->sendMessage(42, 'Привет!');

        self::assertSame(12345, $messageId);
        self::assertSame('messages.send', basename(parse_url($http->requests[0]['url'], PHP_URL_PATH)));
        self::assertSame(42, $http->requests[0]['params']['user_id']);
        self::assertIsInt($http->requests[0]['params']['random_id']);
        self::assertSame('test-token-not-a-real-secret', $http->requests[0]['params']['access_token']);
    }

    public function testRetryAfterTemporaryVkErrorReusesSameRandomId(): void
    {
        $http = new FakeHttpClient([
            new HttpResponse(200, '{"error":{"error_code":6,"error_msg":"Too many requests per second"}}'),
            new HttpResponse(200, '{"response":777}'),
        ]);
        $client = $this->client($http, 1);

        $messageId = $client->sendMessage(42, 'Сообщение', ['random_id' => 456]);

        self::assertSame(777, $messageId);
        self::assertCount(2, $http->requests);
        self::assertSame(456, $http->requests[0]['params']['random_id']);
        self::assertSame(456, $http->requests[1]['params']['random_id']);
    }

    public function testNetworkErrorIsRetried(): void
    {
        $http = new FakeHttpClient([
            new NetworkException('Временная ошибка сети.'),
            new HttpResponse(200, '{"response":123}'),
        ]);
        $client = $this->client($http, 1);

        self::assertSame(123, $client->sendMessage(7, 'Тест'));
        self::assertCount(2, $http->requests);
    }

    public function testAccessDeniedCode188BecomesAccessDeniedException(): void
    {
        $http = new FakeHttpClient([
            new HttpResponse(200, '{"error":{"error_code":188,"error_msg":"Access denied"}}'),
        ]);
        $client = $this->client($http);

        $this->expectException(AccessDeniedException::class);
        $client->sendMessage(7, 'Тест');
    }

    public function testGetDialogsReturnsTypedDialogObjects(): void
    {
        $body = json_encode([
            'response' => [
                'count' => 1,
                'items' => [
                    [
                        'conversation' => [
                            'peer' => ['id' => 2000000001, 'type' => 'chat'],
                            'chat_settings' => ['title' => 'Команда'],
                            'unread_count' => 2,
                        ],
                        'last_message' => [
                            'id' => 91,
                            'text' => 'Всем привет',
                            'date' => 1_700_000_000,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $http = new FakeHttpClient([new HttpResponse(200, $body)]);
        $dialogs = $this->client($http)->getDialogs(0, 10);

        self::assertCount(1, $dialogs);
        self::assertInstanceOf(Dialog::class, $dialogs[0]);
        self::assertSame(2000000001, $dialogs[0]->peerId);
        self::assertSame(DialogType::CHAT, $dialogs[0]->type);
        self::assertSame('Команда', $dialogs[0]->title);
        self::assertSame(2, $dialogs[0]->unreadCount);
        self::assertSame('Всем привет', $dialogs[0]->lastMessageText);
    }

    public function testGetUserReturnsTypedUser(): void
    {
        $http = new FakeHttpClient([
            new HttpResponse(
                200,
                '{"response":[{"id":17,"first_name":"Анна","last_name":"Иванова","photo_200":"https://example.test/avatar.jpg"}]}'
            ),
        ]);

        $user = $this->client($http)->getUser(17);

        self::assertInstanceOf(VKUser::class, $user);
        self::assertSame(17, $user->id);
        self::assertSame('Анна Иванова', $user->fullName());
        self::assertSame('https://example.test/avatar.jpg', $user->avatarUrl);
    }

    public function testRateLimitIsRetriedAndEventuallyMapped(): void
    {
        $http = new FakeHttpClient([
            new HttpResponse(429, '{"error":{"error_code":6,"error_msg":"Too many requests"}}'),
            new HttpResponse(429, '{"error":{"error_code":6,"error_msg":"Too many requests"}}'),
        ]);
        $client = $this->client($http, 1);

        try {
            $client->getUser(17);
            self::fail('Ожидалось RateLimitException.');
        } catch (RateLimitException $exception) {
            self::assertSame(429, $exception->httpStatusCode);
        }

        self::assertCount(2, $http->requests);
    }

    public function testInvalidMessageIsRejectedBeforeHttpRequest(): void
    {
        $http = new FakeHttpClient([]);
        $client = $this->client($http);

        $this->expectException(\InvalidArgumentException::class);
        $client->sendMessage(42, '   ');
    }
}
