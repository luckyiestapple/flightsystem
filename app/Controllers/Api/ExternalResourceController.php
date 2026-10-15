<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\ExternalApiClient;
use App\Libraries\ExternalApiException;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

abstract class ExternalResourceController extends BaseController
{
    use ResponseTrait;

    protected string $serviceName;
    protected string $baseUrl;
    protected string $collectionPath;
    protected string $detailPath;

    public function index(): ResponseInterface
    {
        return $this->proxy($this->collectionPath);
    }

    public function show(string $id): ResponseInterface
    {
        return $this->proxy($this->detailPath . rawurlencode($id));
    }

    protected function proxy(string $path): ResponseInterface
    {
        try {
            $result = (new ExternalApiClient())->getJson(
                $this->serviceName,
                $this->baseUrl,
                $path,
            );
        } catch (ExternalApiException $exception) {
            return $this->respond([
                'status' => $exception->httpStatus,
                'error' => $exception->httpStatus,
                'messages' => ['_request' => $exception->getMessage()],
            ], $exception->httpStatus);
        }

        return $this->respond($result['data'], $result['status']);
    }
}
