<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Support\View\BasicView;
use Eyika\Atom\Framework\Support\View\View;

class Response
{
    public const STATUS_OK = 200;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_CREATED = 201;
    public const NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_INTERNAL_SERVER_ERROR = 500;

    private const methodToFunc = [
        self::STATUS_OK => 'ok',
        self::STATUS_BAD_REQUEST => 'badRequest'
    ];

    public function __construct(int $status_code = 200)
    {

    }

    public static function json(string $message, array|int $data_or_method = 200, $method = null): bool
    {
        if (empty($method) && gettype($data_or_method) === 'integer') {
            $data = null;
        }
        $method = $data_or_method;
        if (!method_exists(JsonResponse::class, self::methodToFunc[$method])) {
            ///TODO throw an exception
        }
        return JsonResponse::{self::methodToFunc[$method]}($message, $data);
    }

    public static function view(string $file_name, $data = [])
    {
        $path = resource_path('views');
        try {
            if (config('view.use_advance_engine')) {
                $view = new View("$path");
                $code = $view->run("$file_name", $data);
            } else {
                $code = BasicView::make("$file_name.blade.php", "$path/", $data, true);
            }
        } catch (Exception $e) {
            header("Content-Type: text/html; charset=utf-8", self::STATUS_INTERNAL_SERVER_ERROR);
            echo "Server Error ". $e->getMessage();
            return true;
        }
        header("Content-Type: text/html; charset=utf-8", self::STATUS_OK);
        echo $code;
        return true;
    }

    public static function redirect(string $to, $code = 301, int $delay = null): bool
    {
        $delay ? header('Refresh: 5; URL=' . $to, true, $code) : header('Location: ' . $to, true, $code);
        return true;
    }

    public static function download(string $file_path, string $file_name = null): bool
    {
        if (!file_exists($file_path)) {
            die('File not found.');
        }
    
        // Set the file name for the download
        if (!$file_name) {
            $file_name = basename($file_path);
        }
    
        // Set headers to prompt the browser to download the file
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $file_name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
    
        // Clear the output buffer
        ob_clean();
        flush();
    
        // Read the file and write it to the output buffer
        readfile($file_path);
        exit;
        return true;
    }
}