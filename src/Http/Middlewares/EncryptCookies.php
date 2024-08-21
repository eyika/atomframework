<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Facade\Encrypter;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class EncryptCookies implements MiddlewareInterface
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        // Decrypt the cookies on the incoming request
        $this->decryptCookies($request);

        // Encrypt the cookies on the outgoing response
        $this->encryptCookies($request);

        return false;
    }

    /**
     * Decrypt the cookies in the request.
     *
     * @param Request $request
     * @return void
     */
    protected function decryptCookies(Request $request): void
    {
        foreach ($request->cookies ?? [] as $key => $value) {
            if ($this->isDisabled($key)) {
                continue;
            }

            $request->cookies->set($key, $this->decrypt($value));
        }
    }

    /**
     * Encrypt the cookies in the response.
     *
     * @return void
     */
    protected function encryptCookies(): void
    {
        logger()->info('this may still need some touch (EncryptCookies@encryptCookies)');
        foreach (BaseResponse::cookies() as $key => $cookie) {
            if ($this->isDisabled($key)) {
                continue;
            }

            $encryptedValue = $this->encrypt($cookie->getValue());

            BaseResponse::setCookie(
                $cookie->withValue($encryptedValue)
            );
        }
    }

    /**
     * Determine if the cookie should not be encrypted.
     *
     * @param string $name
     * @return bool
     */
    protected function isDisabled(string $name): bool
    {
        return in_array($name, $this->except);
    }

    /**
     * Encrypt a value.
     *
     * @param string $value
     * @return string
     */
    protected function encrypt(string $value): string
    {
        return Encrypter::encrypt($value);
        // return base64_encode(openssl_encrypt($value, 'AES-256-CBC', $this->getKey(), 0, $this->getIv()));
    }

    /**
     * Decrypt a value.
     *
     * @param string $value
     * @return mixed
     */
    protected function decrypt(string $value): mixed
    {
        return Encrypter::decrypt($value);
    }

    /**
     * Get the encryption key.
     *
     * @return string
     */
    protected function getKey(): string
    {
        return hash('sha256', 'your-secret-key'); // Replace with your actual secret key
    }

    /**
     * Get the initialization vector (IV) for encryption.
     *
     * @return string
     */
    protected function getIv(): string
    {
        return substr(hash('sha256', 'your-secret-iv'), 0, 16); // Replace with your actual IV
    }
}

// // Example usage:
// $request = Request::create('/some-route', 'GET');
// $response = new Response();

// $middleware = new EncryptCookies();
// $response = $middleware->handle($request, function ($req) {
//     $response = new Response('Hello World');
//     $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('test', 'cookie_value'));
//     return $response;
// });

// $response->send();
