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

    public function paymentRequired(string $message = "", array $errors = []): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_PAYMENT_REQUIRED);
    }

    public function forbidden(string $message = "", array $errors = []): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_FORBIDDEN);
    }

    public function unauthorized(string $message = "Unauthorized"): self
    {
        return $this->create(['message' => $message], self::STATUS_UNAUTHORIZED);
    }

    /**
     * 429 — the caller has sent too many requests.
     *
     * `$retryAfter`, in seconds, is emitted as the `Retry-After` header. Send it whenever you
     * know when the limit resets: without it a client has no way to back off correctly, and
     * well-behaved ones fall back to guessing or retrying immediately.
     */
    public function tooManyRequests(string $message = "Too many requests", int|null $retryAfter = null, array $errors = []): self
    {
        if ($retryAfter !== null) {
            $this->setHeader('Retry-After', (string) max(0, $retryAfter));
        }

        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_TOO_MANY_REQUESTS);
    }

    /**
     * 503 — the service is unavailable, e.g. maintenance or a dependency being down.
     *
     * The status constant already existed; this is the missing helper for it. `Retry-After` is
     * accepted here for the same reason as on 429.
     */
    public function serviceUnavailable(string $message = "Service unavailable", int|null $retryAfter = null, array $errors = []): self
    {
        if ($retryAfter !== null) {
            $this->setHeader('Retry-After', (string) max(0, $retryAfter));
        }

        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_SERVICE_NOT_AVAILABLE);
    }
}
