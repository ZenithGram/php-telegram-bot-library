<?php

namespace ZenithGram\ZenithGram\Storage;

interface StorageInterface
{
    /**
     * Получает текущее состояние пользователя
     *
     * @param int|string $user_id ID пользователя
     *
     * @return string|null
     *
     * @see https://zenithgram.github.io/classes/storage/getState
     */
    public function getState(int|string $user_id): ?string;

    /**
     * Устанавливает новое состояние для пользователя
     *
     * @param int|string $user_id ID пользователя
     * @param string     $state   Название состояния (шага)
     *
     * @return void
     *
     * @throws \JsonException
     * @see https://zenithgram.github.io/classes/storage/setState
     */
    public function setState(int|string $user_id, string $state): void;

    /**
     * Сбрасывает состояние пользователя (выход из диалога)
     *
     * @param int|string $user_id ID пользователя
     *
     * @return void
     *
     * @throws \JsonException
     * @see https://zenithgram.github.io/classes/storage/clearState
     */
    public function clearState(int|string $user_id): void;

    /**
     * Получает сохраненные данные сессии пользователя
     *
     * @param int|string $user_id ID пользователя
     *
     * @return array
     *
     * @see https://zenithgram.github.io/classes/storage/getSessionData
     */
    public function getSessionData(int|string $user_id): array;

    /**
     * Сохраняет данные сессии пользователя (дополняет существующие)
     *
     * @param int|string $user_id ID пользователя
     * @param array $data   Массив данных для сохранения
     *
     * @return void
     *
     * @see https://zenithgram.github.io/classes/storage/setSessionData
     */
    public function setSessionData(int|string $user_id, array $data): void;

    /**
     * Полностью очищает данные сессии пользователя
     *
     * @param int $userId ID пользователя
     *
     * @return void
     *
     * @see https://zenithgram.github.io/classes/storage/clearSessionData
     */

    public function clearSessionData(int|string $user_id): void;
}