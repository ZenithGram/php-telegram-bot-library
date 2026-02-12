<?php
declare(strict_types=1);

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
     * @see https://zenithgram.github.io/classes/storage#%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B8%D1%81%D0%B0
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
     * @see https://zenithgram.github.io/classes/storage#%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B8%D1%81%D0%B0
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
     * @see https://zenithgram.github.io/classes/storage#%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B8%D1%81%D0%B0
     */
    public function clearState(int|string $user_id): void;

    /**
     * Получает сохраненные данные сессии пользователя
     *
     * @param int|string $user_id ID пользователя
     *
     * @return array
     *
     * @see https://zenithgram.github.io/classes/storage#%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B8%D1%81%D0%B0
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
     * @see https://zenithgram.github.io/classes/storage#%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B8%D1%81%D0%B0
     */
    public function setSessionData(int|string $user_id, array $data): void;

    /**
     * Полностью очищает данные сессии пользователя
     *
     * @param int|string $user_id
     *
     * @return void
     *
     * @see https://zenithgram.github.io/classes/storage#%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B8%D1%81%D0%B0
     */

    public function clearSessionData(int|string $user_id): void;
}