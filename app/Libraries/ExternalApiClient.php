<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use JsonException;

final class ExternalApiClient
{
    /**
     * @return array{status: int, data: mixed}
     */
    public function getJson(string $serviceName, string $baseUrl, string $path): array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($baseUrl === '') {
            throw new ExternalApiException(
                sprintf('URL API %s belum dikonfigurasi.', $serviceName),
                503,
            );
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (
            filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || ! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || $host === ''
        ) {
            throw new ExternalApiException(
                sprintf('URL API %s tidak valid.', $serviceName),
                503,
            );
        }

        try {
            $response = service('curlrequest')->get(
                $baseUrl . '/' . ltrim($path, '/'),
                [
                    'http_errors' => false,
                    'timeout' => 5,
                    'connect_timeout' => 3,
                    'headers' => ['Accept' => 'application/json'],
                ],
            );
        } catch (HTTPException $exception) {
            throw new ExternalApiException(
                sprintf('Tidak dapat terhubung ke API %s.', $serviceName),
                502,
                $exception,
            );
        }

        try {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ExternalApiException(
                sprintf('API %s mengembalikan JSON yang tidak valid.', $serviceName),
                502,
                $exception,
            );
        }

        return [
            'status' => $response->getStatusCode(),
            'data' => $data,
        ];
    }
}
