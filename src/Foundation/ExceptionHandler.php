<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Exceptions\Db\ModelNotFoundException;
use Eyika\Atom\Framework\Exceptions\Http\AccessDeniedHttpException;
use Eyika\Atom\Framework\Exceptions\Http\NotFoundHttpException;
use Eyika\Atom\Framework\Exceptions\Http\RequestException;
use Eyika\Atom\Framework\Exceptions\Http\UnauthorizedHttpException;
use Eyika\Atom\Framework\Exceptions\ValidationException;
use Eyika\Atom\Framework\Foundation\Contracts\ExceptionHandler as ContractExceptionHandler;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Facade\Response;
use Throwable;

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
     * @return BaseResponse
     *
     * @throws \Exception
     */
    public function render(Request $request, \Throwable $exception): BaseResponse
    {
        if ($request->wantsJson()) {
            $code = $exception->getCode();
            $message = config('app.debug') ? $exception->getMessage() : 'An error occured, contact administrator';
            if ($code < 100 || $code >= 600) {
                $code = BaseResponse::STATUS_INTERNAL_SERVER_ERROR;
            }

            if ($exception instanceof RequestException) {
                $errors = $exception->errors();

                return response()->json([
                    'message' => 'Bad Request', [
                        'success' => false,
                        'message' => $message,
                        'errors' => $errors
                    ]
                ], $exception->getCode() ?? 400);
            }

            if ($exception instanceof ModelNotFoundException) {
                $message = $exception->getMessage();
                $code = BaseResponse::STATUS_NOT_FOUND;

                if (preg_match('@\\\\(\w+)\]@', $message, $matches)) {
                    $model = $matches[1];
                    $model = preg_replace('/Table/i', '', $model);
                    $message = "{$model} not found.";
                }
                return response()->json(['message' => 'Not Found', [
                    'success' => false,
                    'message' => $message,
                ]], BaseResponse::STATUS_NOT_FOUND);
            }

            if ($exception instanceof NotFoundHttpException) {
                $message = $exception->getMessage();

                return response()->json(['message' => 'Not Found', 'data' => [
                    'success' => false,
                    'message' => $message,
                ]], BaseResponse::STATUS_NOT_FOUND);
            }

            if ($exception instanceof ValidationException) {
                $errors = $exception->errors();

                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage() ?? 'Validation Error',
                    'errors' => $errors
                ], BaseResponse::STATUS_UNPROCESSABLE_ENTITY);
            }

            if ($exception instanceof AccessDeniedHttpException) {
                return response()->json(['message' => 'Access Denied', [
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ]], BaseResponse::STATUS_UNAUTHORIZED);
            }

            if ($exception instanceof UnauthorizedHttpException) {
                return response()->json(['message' => 'Unauthorized Request', [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ]], $exception->getStatusCode());
            }

            return Response::json(['message' => 'An error occured', [
                'success' => false,
                'message' => $message,
                'data' => config('app.debug', false) ? $exception->getTrace() : null,
            ]], is_numeric($code) ? intval($code) : 500);

            // if ($request->expectsJson() or $request->isXmlHttpRequest()) {
            //     return Response::json('Error Occured', [
            //         'success' => false,
            //         'message' => $message,
            //     ], is_numeric($code) ? intval($code) : 500);
            // }
        } else {
            $code = $exception->getCode();
            $message = $exception->getMessage();
            if ($code < 100 || $code >= 600) {
                $code = BaseResponse::STATUS_INTERNAL_SERVER_ERROR;
            }

            if ($exception instanceof RequestException) {
                return response()->back()->withErrors($exception->errors());
            }

            if ($exception instanceof ModelNotFoundException) {
                $message = $exception->getMessage();
                $code = BaseResponse::STATUS_NOT_FOUND;

                if (preg_match('@\\\\(\w+)\]@', $message, $matches)) {
                    $model = $matches[1];
                    $model = preg_replace('/Table/i', '', $model);
                    $message = "{$model} not found.";
                }

                return Response::back()->withErrors($exception->errors());
            }

            if ($exception instanceof ValidationException) {
                $code = BaseResponse::STATUS_UNPROCESSABLE_ENTITY;

                return Response::back()->withInputs()->withErrors($exception->errors());
            }

            if ($exception instanceof UnauthorizedHttpException || $exception instanceof AccessDeniedHttpException) {
                $code = BaseResponse::STATUS_UNPROCESSABLE_ENTITY;

                return Response::redirect(config('auth.login_page', '/auth/login'));
            }

            return $this->renderErrorPage($request, $exception);
        }
        
        return response()->html('An error occured, contact administrator', $exception->getCode() ?? BaseResponse::STATUS_INTERNAL_SERVER_ERROR);
    }

    protected function renderErrorPage(Request $request, Throwable $exception): BaseResponse
    {
        if (config('app.debug', false)) {
            $whoops = new \Whoops\Run;
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);

            return response()->html($whoops->handleException($exception));
        }

        $serverErrorPage = config('view.server_error.path', '');

        if (!empty($serverErrorPage)) {
            return response()->view($serverErrorPage)->withErrors(['message' => $exception->getMessage()]);
        }

        return response()->html('An error occured, contact administrator', $exception->getCode() ?? BaseResponse::STATUS_INTERNAL_SERVER_ERROR);
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
