<?php

declare(strict_types=1);

namespace VkApi\Model;

use VkApi\Exception\InvalidResponseException;

final class Dialog
{
    public function __construct(
        public int $peerId,
        public DialogType $type,
        public ?string $title,
        public int $unreadCount,
        public ?int $lastMessageId,
        public ?string $lastMessageText,
        public ?int $lastMessageDate
    ) {
        if ($this->peerId <= 0) {
            throw new \InvalidArgumentException('peerId должен быть положительным числом.');
        }
    }

    /**
     * Преобразует элемент messages.getDialogs в типизированную модель.
     *
     * @param array<string, mixed> $item
     */
    public static function fromApi(array $item): self
    {
        $conversation = is_array($item['conversation'] ?? null)
            ? $item['conversation']
            : $item;
        $peer = is_array($conversation['peer'] ?? null) ? $conversation['peer'] : [];
        $chatSettings = is_array($conversation['chat_settings'] ?? null)
            ? $conversation['chat_settings']
            : [];
        $lastMessage = is_array($item['last_message'] ?? null) ? $item['last_message'] : [];

        $peerId = $peer['id'] ?? $conversation['peer_id'] ?? $item['peer_id'] ?? null;
        if (!is_numeric($peerId) || (int) $peerId <= 0) {
            throw new InvalidResponseException('В элементе диалога отсутствует корректный peer ID.');
        }

        $title = $chatSettings['title'] ?? $conversation['title'] ?? $item['title'] ?? null;
        $lastMessageId = $lastMessage['id'] ?? null;
        $lastMessageText = $lastMessage['text'] ?? null;
        $lastMessageDate = $lastMessage['date'] ?? null;
        $unreadCount = $conversation['unread_count'] ?? 0;

        return new self(
            (int) $peerId,
            DialogType::fromApiValue($peer['type'] ?? null),
            is_string($title) && $title !== '' ? $title : null,
            is_numeric($unreadCount) ? max(0, (int) $unreadCount) : 0,
            is_numeric($lastMessageId) ? (int) $lastMessageId : null,
            is_string($lastMessageText) ? $lastMessageText : null,
            is_numeric($lastMessageDate) ? (int) $lastMessageDate : null
        );
    }
}
