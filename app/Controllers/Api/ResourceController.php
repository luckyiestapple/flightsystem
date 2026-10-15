<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;
use JsonException;

abstract class ResourceController extends BaseController
{
    use ResponseTrait;

    protected string $modelClass;
    protected string $primaryKey;
    protected array $writableFields = [];
    protected array $requiredFields = [];
    protected array $putRequiredFields = [];
    protected array $nullableFields = [];
    protected array $validationRules = [];
    protected array $references = [];
    protected array $uniqueFields = [];
    protected array $referenceFields = [];
    protected array $searchableFields = [];
    protected array $filterableFields = [];
    protected array $sortableFields = [];

    public function index(): ResponseInterface
    {
        return $this->listing();
    }

    public function search(): ResponseInterface
    {
        return $this->listing();
    }

    protected function listing(): ResponseInterface
    {
        $builder = $this->model()->builder();
        $query = $this->request->getGet('q');

        if ($query !== null) {
            if (! is_string($query)) {
                return $this->errorResponse('Parameter q harus berupa teks.', 400);
            }

            if ($query !== '' && $this->searchableFields === []) {
                return $this->errorResponse('Pencarian teks tidak tersedia untuk resource ini.', 400);
            }

            if ($query !== '') {
                $builder->groupStart();

                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $builder->like($field, $query, 'both', null, true);
                    } else {
                        $builder->orLike($field, $query, 'both', null, true);
                    }
                }

                $builder->groupEnd();
            }
        }

        $filters = $this->request->getGet('filter') ?? [];

        if (! is_array($filters)) {
            return $this->errorResponse('Parameter filter harus berupa object field=nilai.', 400);
        }

        foreach ($filters as $field => $value) {
            if (! in_array($field, $this->filterableFields, true)) {
                return $this->errorResponse(sprintf('Field filter "%s" tidak didukung.', $field), 400);
            }

            if (! is_string($value) && ! is_numeric($value)) {
                return $this->errorResponse(sprintf('Nilai filter "%s" harus berupa scalar.', $field), 400);
            }

            $builder->where($field, $value);
        }

        $sort = $this->request->getGet('sort');
        $order = $this->request->getGet('order') ?? 'ASC';

        if ($sort !== null && (! is_string($sort) || ! in_array($sort, $this->sortableFields, true))) {
            return $this->errorResponse('Field sort tidak didukung.', 400);
        }

        if (! is_string($order) || ! in_array(strtoupper($order), ['ASC', 'DESC'], true)) {
            return $this->errorResponse('Parameter order hanya boleh ASC atau DESC.', 400);
        }

        $order = strtoupper($order);
        $builder->orderBy($sort ?? $this->primaryKey, $order);
        $records = $builder->get()->getResultArray();
        $records = $this->presentRecords($records);

        if ($records instanceof ResponseInterface) {
            return $records;
        }

        return $this->successResponse($records, 200, 'Data berhasil diambil.');
    }

    public function show(string $id): ResponseInterface
    {
        if (! $this->isUuid($id)) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $record = $this->model()->find($id);

        if ($record === null) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $record = $this->presentRecord($record);

        if ($record instanceof ResponseInterface) {
            return $record;
        }

        return $this->successResponse($record, 200, 'Data berhasil diambil.');
    }

    public function update(string $id): ResponseInterface
    {
        if (! $this->isUuid($id)) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $model = $this->model();
        $record = $model->find($id);

        if ($record === null) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $payload = $this->jsonPayload();

        if ($payload instanceof ResponseInterface) {
            return $payload;
        }

        $errors = $this->payloadErrors($payload, false, $id, $this->putRequiredFields);

        if ($errors !== []) {
            return $this->errorResponse($errors, 400);
        }

        $payload = array_merge(array_fill_keys($this->nullableFields, null), $payload);
        $payload = $this->withUpdatedTimestamp($payload);

        if (! $model->update($id, $payload)) {
            return $this->errorResponse('Gagal memperbarui data.', 500);
        }

        return $this->successResponse($model->find($id), 200, 'Data berhasil diperbarui.');
    }

    public function patch(string $id): ResponseInterface
    {
        if (! $this->isUuid($id)) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $model = $this->model();
        $record = $model->find($id);

        if ($record === null) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $payload = $this->jsonPayload();

        if ($payload instanceof ResponseInterface) {
            return $payload;
        }

        $errors = $this->payloadErrors($payload, true, $id, [], $record);

        if ($errors !== []) {
            return $this->errorResponse($errors, 400);
        }

        $payload = $this->withUpdatedTimestamp($payload);

        if (! $model->update($id, $payload)) {
            return $this->errorResponse('Gagal memperbarui data.', 500);
        }

        return $this->successResponse($model->find($id), 200, 'Data berhasil diperbarui.');
    }

    public function delete(string $id): ResponseInterface
    {
        if (! $this->isUuid($id)) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        $model = $this->model();

        if ($model->find($id) === null) {
            return $this->errorResponse('Data tidak ditemukan.', 404);
        }

        foreach ($this->references as $table => $fields) {
            foreach ($fields as $field) {
                if (db_connect()->table($table)->where($field, $id)->countAllResults() > 0) {
                    return $this->errorResponse(
                        'Data tidak dapat dihapus karena masih digunakan oleh data lain.',
                        409,
                    );
                }
            }
        }

        if (! $model->delete($id)) {
            return $this->errorResponse('Gagal menghapus data.', 500);
        }

        return $this->successResponse(null, 200, sprintf('Data dengan id %s berhasil dihapus.', $id));
    }

    protected function model(): Model
    {
        $modelClass = $this->modelClass;

        return new $modelClass();
    }

    public function create(): ResponseInterface
    {
        $payload = $this->jsonPayload();

        if ($payload instanceof ResponseInterface) {
            return $payload;
        }

        $errors = $this->payloadErrors($payload);

        if ($errors !== []) {
            return $this->errorResponse($errors, 400);
        }

        $payload[$this->primaryKey] = $this->uuid();
        $model = $this->model();

        if (! $model->insert($payload, false)) {
            return $this->errorResponse('Gagal menyimpan data.', 500);
        }

        $record = $model->find($payload[$this->primaryKey]);

        if ($record === null) {
            return $this->errorResponse('Data berhasil disimpan tetapi tidak dapat dibaca kembali.', 500);
        }

        return $this->successResponse($record, 201, 'Data berhasil ditambahkan.');
    }

    protected function jsonPayload(): array|ResponseInterface
    {
        $body = $this->request->getBody();

        if (! is_string($body) || $body === '') {
            return $this->errorResponse('Body harus berupa JSON yang valid.', 400);
        }

        try {
            $payload = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponse('Body harus berupa JSON yang valid.', 400);
        }

        if (! $payload instanceof \stdClass) {
            return $this->errorResponse('Body JSON harus berupa object.', 400);
        }

        return (array) $payload;
    }

    protected function successResponse(
        mixed $data = null,
        int $httpStatus = 200,
        string $message = 'Data berhasil diproses.',
    ): ResponseInterface {
        return $this->respond([
            'status' => $httpStatus,
            'message' => $message,
            'data' => $data,
        ], $httpStatus);
    }

    protected function errorResponse(string|array $messages, int $httpStatus): ResponseInterface
    {
        if (is_string($messages)) {
            $messages = ['_request' => $messages];
        }

        return $this->respond([
            'status' => $httpStatus,
            'error' => $httpStatus,
            'messages' => $messages,
        ], $httpStatus);
    }

    protected function payloadErrors(
        array $payload,
        bool $partial = false,
        ?string $id = null,
        ?array $requiredFields = null,
        ?array $existing = null,
    ): array
    {
        $errors = [];
        $unknownFields = array_diff(array_keys($payload), $this->writableFields);
        $requiredFields ??= $this->requiredFields;

        foreach ($unknownFields as $field) {
            $errors[$field] = 'Field tidak dikenal atau tidak dapat diubah.';
        }

        foreach ($payload as $field => $value) {
            if (! in_array($field, $this->writableFields, true)) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                $errors[$field] = 'Field tidak boleh kosong.';
            } elseif ($value === null && ! in_array($field, $this->nullableFields, true)) {
                $errors[$field] = 'Field tidak boleh null.';
            } elseif ($value !== null && ! is_scalar($value)) {
                $errors[$field] = 'Field harus berupa nilai scalar.';
            }
        }

        if ($payload === [] && $partial) {
            $errors['_request'] = 'Body PATCH harus memiliki setidaknya satu field.';
        }

        $rules = [];

        foreach ($this->writableFields as $field) {
            if ($partial && ! array_key_exists($field, $payload)) {
                continue;
            }

            $required = in_array($field, $requiredFields, true);
            $provided = array_key_exists($field, $payload);

            if (! $provided && ! $required) {
                continue;
            }

            $fieldRules = $this->validationRules[$field] ?? '';

            if (in_array($field, $this->nullableFields, true)) {
                $rule = 'permit_empty|' . $fieldRules;
            } else {
                $rule = 'required|' . $fieldRules;
            }

            $rules[$field] = $rule;
        }

        if ($rules !== [] && ! $this->validateData($payload, $rules)) {
            $errors = array_merge($this->validator->getErrors(), $errors);
        }

        foreach ($this->uniqueFields as $field) {
            if (
                ! array_key_exists($field, $payload)
                || $payload[$field] === null
                || ! is_scalar($payload[$field])
                || isset($errors[$field])
            ) {
                continue;
            }

            $builder = $this->model()->builder()->where($field, $payload[$field]);

            if ($id !== null) {
                $builder->where($this->primaryKey . ' !=', $id);
            }

            if ($builder->countAllResults() > 0) {
                $errors[$field] = sprintf('Kode %s sudah digunakan.', $payload[$field]);
            }
        }

        foreach ($this->referenceFields as $field => [$table, $column]) {
            if (
                ! array_key_exists($field, $payload)
                || $payload[$field] === null
                || ! is_scalar($payload[$field])
                || isset($errors[$field])
            ) {
                continue;
            }

            if (db_connect()->table($table)->where($column, $payload[$field])->countAllResults() === 0) {
                $errors[$field] = 'Data referensi tidak ditemukan.';
            }
        }

        return array_merge($errors, $this->recordErrors(array_merge($existing ?? [], $payload)));
    }

    protected function recordErrors(array $payload): array
    {
        return [];
    }

    /**
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>|ResponseInterface
     */
    protected function presentRecords(array $records): array|ResponseInterface
    {
        return $records;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>|ResponseInterface
     */
    protected function presentRecord(array $record): array|ResponseInterface
    {
        return $record;
    }

    protected function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    protected function withUpdatedTimestamp(array $payload): array
    {
        if (in_array($this->primaryKey, ['airport_id', 'flight_id'], true)) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        return $payload;
    }

    protected function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}$/', $value) === 1;
    }
}
