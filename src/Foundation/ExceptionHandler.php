<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Exceptions\Db\ModelNotFoundException;
use Eyika\Atom\Framework\Exceptions\Http\AccessDeniedHttpException;
use Eyika\Atom\Framework\Exceptions\Http\NotFoundHttpException;
use Eyika\Atom\Framework\Exceptions\Http\UnauthorizedHttpException;
use Eyika\Atom\Framework\Exceptions\ValidationException;
use Eyika\Atom\Framework\Foundation\Contracts\ExceptionHandler as ContractExceptionHandler;
use Eyika\Atom\Framework\Http\JsonResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;

class ExceptionHandler implements ContractExceptionHandler
{
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function report(\Throwable $exception)
    {
        
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return bool
     *
     * @throws \Exception
     */
    public function render($request, \Throwable $exception): bool
    {
        logger()->info($exception->getMessage(), $exception->getTrace());
        if ($request->wantsJson()) {
            $code = $exception->getCode();
            $message = $exception->getMessage();
            if ($code < 100 || $code >= 600) {
                $code = JsonResponse::STATUS_INTERNAL_SERVER_ERROR;
            }

            if ($exception instanceof ModelNotFoundException) {
                $message = $exception->getMessage();
                $code = JsonResponse::STATUS_NOT_FOUND;

                if (preg_match('@\\\\(\w+)\]@', $message, $matches)) {
                    $model = $matches[1];
                    $model = preg_replace('/Table/i', '', $model);
                    $message = "{$model} not found.";
                }
            }

            if ($exception instanceof NotFoundHttpException) {
                $message = $exception->getMessage();

                return response()->json('Not Found', [
                    'success' => false,
                    'message' => $message,
                ], JsonResponse::STATUS_NOT_FOUND);
            }

            if ($exception instanceof ValidationException) {
                $firstError = $exception->errors();

                return response()->json('Validation Error', [
                    'success' => false,
                    'message' => $firstError[0],
                ], JsonResponse::STATUS_UNPROCESSABLE_ENTITY);
            }

            if ($exception instanceof AccessDeniedHttpException) {
                return response()->json('Access Denied', [
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], JsonResponse::STATUS_UNAUTHORIZED);
            }

            if ($exception instanceof UnauthorizedHttpException) {
                return response()->json('Unauthorized Request', [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], $exception->getStatusCode());
            }

            if ($request->wantsJson() or $request->isXmlHttpRequest()) {
                return Response::json('An error occured', [
                    'success' => false,
                    'message' => str_contains($message, 'SQLSTATE') || str_contains($message, 'Illuminate') ? 'something happened try again' : $message,
                ], $code);
            }
        } else {
            echo "got here";
            $code = $exception->getCode();
            $message = $exception->getMessage();
            if ($code < 100 || $code >= 600) {
                $code = JsonResponse::STATUS_INTERNAL_SERVER_ERROR;
            }

            if ($exception instanceof ModelNotFoundException) {
                $message = $exception->getMessage();
                $code = JsonResponse::STATUS_NOT_FOUND;

                if (preg_match('@\\\\(\w+)\]@', $message, $matches)) {
                    $model = $matches[1];
                    $model = preg_replace('/Table/i', '', $model);
                    $message = "{$model} not found.";
                }
            }

            if ($exception instanceof ValidationException) {
                $message = $exception->errors()[0];
                $code = JsonResponse::STATUS_UNPROCESSABLE_ENTITY;

                if (! $request->expectsJson() and ! $request->isXmlHttpRequest()) {
                    return false;
                    // return Response::redirectBack()::back()->withInput()->withErrors($message);
                }
            }

            if ($request->expectsJson() or $request->isXmlHttpRequest()) {
                return Response::json('Error Occured', [
                    'success' => false,
                    'message' => $message,
                ], $code);
            }
        }
    }

     /**
     * @param  Request  $request
     * @return JsonResponse|void
     */
    protected function unauthenticated($request, AccessDeniedHttpException $exception)
    {
        // if ($request->isXmlHttpRequest() || $request->expectsJson()) {
        //     return response()->json('Unauthenticated Request', ['error' => 'Unauthenticated.'], JsonResponse::STATUS_UNAUTHORIZED);
        // } elseif (! empty($request->all()['expires']) && ! empty($request->all()['signature'])) {

        //     if (! empty($request->route('hash'))) {

        //         $user = User::find($request->route('id'));
        //         $user->update(['email_verified_at' => Carbon::now()]);

        //         return redirect()->guest(route('login'));

        //     }
        // } else {

        //     return redirect()->guest(route('login'));
        // }
    }
}
