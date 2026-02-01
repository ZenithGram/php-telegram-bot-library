<?php

namespace ZenithGram\ZenithGram\Interfaces;

interface ApiClientInterface
{

    /**
     * @param string $method  Метод API
     * @param array  $params  Параметры
     * @param int    $timeout Клиентский таймаут <br> По умолчанию: 10.
     *
     * @return array
     */
    public function callAPI(string $method, array $params = [],
        int $timeout = 10,
    ): array;

    /**
     * @param string $url
     * @param string $destinationPath
     *
     * @return void
     */
    public function downloadFile(string $url, string $destinationPath): void;

    /**
     * @internal
     * Возвращает токен
     */
    public function getToken(): string;

    /**
     * @internal
     * Возвращает ссылку для скачивания файлов </br>
     * Пр: https://api.telegram.org/file/bot/{TOKEN}/
     */
    public function getApiFileUrl(): string;
}
