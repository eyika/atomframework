<?php

namespace Eyika\Atom\Framework\Http;

class JsonResponse extends BaseResponse
{
    public function create(mixed $data = null, int $statusCode = 200): self
    {
        if (is_array($data) && isset($data['data']) && is_object($data['data']) && method_exists($data['data'], 'toArray')) {
            $data['data'] = $data['data']->toArray(includeDynamicProperties: true);
        } else if (is_array($data) && isset($data['data']) && is_object($data['data']) && method_exists($data['data'], '__toArray')) {
            $data['data'] = $data['data']->__toArray();
        }

        $jsonData = json_encode($data);
        $this->body($jsonData)->status($statusCode)
                        ->setHeader('Content-Type', 'application/json; charset=utf-8');
        
        return $this;
    }

    public function ok($data = []): self
    {
        return $this->create($data, self::STATUS_OK);
    }

    public function noContent(): self
    {
        return $this->create(statusCode: self::STATUS_CREATED);
    }

    public function created(string $message = '', $data = []): self
    {
        return $this->create($data, self::STATUS_CREATED);
    }

    public function notFound(string $message, array|null $data = null): self
    {
        return $this->create(['message' => $message, 'errors' => $data], self::STATUS_NOT_FOUND);
    }

    public function unprocessableEntity(string $message = "unprocessable request", string|array $errors = ""): self
    {
        return $this->create(['message' => $message, 'errors' => $errors], self::STATUS_UNPROCESSABLE_ENTITY);
    }

    public function serverError(string $message=""): self
    {
        return $this->create(['message' => $message], self::STATUS_INTERNAL_SERVER_ERROR);
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

    // private function respond(int $statusCode, $body = null)
    // {
    //     try {
    //         return $this->json($body)->withStatus($statusCode);
    //     } catch (Exception $ex) {
    //     }

    // }
}
