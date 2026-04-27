<?php

namespace Eyika\Atom\Framework\Http;

class JsonResponse extends BaseResponse
{
    public function ok(string $message, mixed $data = []): self
    {
        return $this->create(['message' => $message, 'data' => $data], self::STATUS_OK);
    }

    public function noContent(): self
    {
        return $this->create(statusCode: self::STATUS_NO_CONTENT);
    }

    public function created(string $message = '', mixed $data = []): self
    {
        return $this->create(['message' => $message, 'data' => $data], self::STATUS_CREATED);
    }

    public function notFound(string $message, mixed $data = null): self
    {
        return $this->create(['message' => $message, 'errors' => $data], self::STATUS_NOT_FOUND);
    }

    public function conflict(string $message = "", array $errors = []): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_CONFLICT);
    }

    public function unprocessableEntity(string $message = "unprocessable request", string $errors = ""): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_UNPROCESSABLE_ENTITY);
    }

    public function serverError(string $message=""): self
    {
        return $this->create(['message' => $message], self::STATUS_INTERNAL_SERVER_ERROR);
    }

    public function badGateway(string $message = ""): self
    {
        return $this->create(['message' => $message], self::STATUS_BAD_GATEWAY);
    }

    public function badRequest(string $message = "", array $errors = []): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_BAD_REQUEST);
    }

    public function forbidden(string $message = "", array $errors = []): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_FORBIDDEN);
    }

    public function unauthorized(string $message = "Unauthorized"): self
    {
        return $this->create(['message' => $message], self::STATUS_UNAUTHORIZED);
    }
}
