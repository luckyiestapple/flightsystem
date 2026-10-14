<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;
use JsonException;

abstract class ResourceController extends BaseController
{
    protected string $modelClass;
    protected string $primaryKey;
    protected array $writableFields = [];
    protected array $requiredFields = [];
    protected array $validationRules = [];

    public function index(): ResponseInterface
    {
        $pagination = $this->pagination();

        if ($pagination instanceof ResponseInterface) {
            return $pagination;
        }

        [$page, $perPage] = $pagination;
        $builder = $this->model()->builder();
        $total = $builder->countAllResults(false);
        $records = $builder
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return $this->respond([
            'data' => $records,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    protected function model(): Model
    {
        $modelClass = $this->modelClass;

        return new $modelClass();
    }

    protected function pagination(): array|ResponseInterface
    {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('per_page') ?? 20;

        if (
            filter_var($page, FILTER_VALIDATE_INT) === false
            || filter_var($perPage, FILTER_VALIDATE_INT) === false
            || (int) $page < 1
            || (int) $perPage < 1
            || (int) $perPage > 100
        ) {
            return $this->respond([
                'message' => 'Parameter page harus >= 1 dan per_page harus berada di antara 1 sampai 100.',
            ], 400);
        }

        return [(int) $page, (int) $perPage];
    }

    public function create(): ResponseInterface
    {
        $payload = $this->jsonPayload();

        if ($payload instanceof ResponseInterface) {
            return $payload;
        }

        $errors = $this->payloadErrors($payload);

        if ($errors !== []) {
            return $this->respond(['errors' => $errors], 422);
        }

        $payload[$this->primaryKey] = $this->uuid();

        if (! $this->model()->insert($payload)) {
            return $this->respond(['errors' => $this->model()->errors()], 422);
        }

        $record = $this->model()->find($payload[$this->primaryKey]);

        if ($record === null) {
            return $this->respond(['message' => 'Data berhasil disimpan tetapi tidak dapat dibaca kembali.'], 500);
        }

        return $this->respond([
            'data' => $record,
            'message' => 'Data berhasil dibuat.',
        ], 201);
    }

    protected function jsonPayload(): array|ResponseInterface
    {
        try {
            $payload = json_decode($this->request->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->respond(['message' => 'Body harus berupa JSON yang valid.'], 400);
        }

        if (! $payload instanceof \stdClass) {
            return $this->respond(['message' => 'Body JSON harus berupa object.'], 400);
        }

        return (array) $payload;
    }

    protected function payloadErrors(array $payload, bool $partial = false): array
    {
        $errors = [];
        $unknownFields = array_diff(array_keys($payload), $this->writableFields);

        foreach ($unknownFields as $field) {
            $errors[$field] = 'Field tidak dikenal atau tidak dapat diubah.';
        }

        if ($payload === [] && $partial) {
            $errors['_request'] = 'Body PATCH harus memiliki setidaknya satu field.';
        }

        $rules = [];

        foreach ($this->writableFields as $field) {
            if ($partial && ! array_key_exists($field, $payload)) {
                continue;
            }

            if (! $partial && in_array($field, $this->requiredFields, true)) {
                $rules[$field] = 'required|' . $this->validationRules[$field];
            } elseif (isset($this->validationRules[$field])) {
                $rules[$field] = 'permit_empty|' . $this->validationRules[$field];
            }
        }

        if ($rules !== [] && ! $this->validateData($payload, $rules)) {
            $errors = array_merge($errors, $this->validator->getErrors());
        }

        return array_merge($errors, $this->recordErrors($payload));
    }

    protected function recordErrors(array $payload): array
    {
        return [];
    }

    protected function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
