<?php

namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Exceptions\ValidationException;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Str;

class Validator {
    private static array $req_data;
    private static array $req_files;
    public static array $errors;
    protected static string $errorMessage = '';
    protected static int $errorCode = 422;
    private static array $validated;
    private static array $confirms;

    public function __construct(Request|array $_req_obj = [])
    {
        self::$req_data = $_req_obj instanceof Request ? $_req_obj->input() : $_req_obj;
        self::$req_files = [];
        self::$errors = [];
        self::$validated = [];
        self::$confirms = [];
    }

    public static function validate(Request|array $req_obj, array $params, string $separator = '|', $throw = false): bool|array
    {
        $me = new self($req_obj);

        foreach ($params as $paramKey => $paramValue) {
            $validations = explode($separator, $paramValue);
            $resp = $me->validateValue($paramKey, $validations);
            if ($resp) {
                static::$errors[$paramKey] = $resp;
                continue;
            }
            $dat = array_merge(static::$req_data, static::$req_files);

            // Support dot notation for nested arrays
            if (strpos($paramKey, '.') !== false) {
                $keys = explode('.', $paramKey);
                $value = $dat;
                $exists = true;

                foreach ($keys as $key) {
                    if (is_array($value) && array_key_exists($key, $value)) {
                        $value = $value[$key];
                    } else {
                        $exists = false;
                        break;
                    }
                }

                if ($exists) {
                    // Build nested array structure in validated
                    $current = &static::$validated;
                    foreach ($keys as $i => $key) {
                        if ($i === count($keys) - 1) {
                            $current[$key] = $value;
                        } else {
                            if (!isset($current[$key]) || !is_array($current[$key])) {
                                $current[$key] = [];
                            }
                            $current = &$current[$key];
                        }
                    }
                }
            } elseif (Arr::keyExists($dat, $paramKey)) {
                static::$validated[$paramKey] = $dat[$paramKey];
            }
        }

        foreach (static::$confirms as $paramKey => $paramValue) {
            $validations = explode($separator, $params[$paramKey]);
            $prevValue = static::$req_data[$paramKey];
            $validations[] = "equals:$prevValue";
            $resp = $me->validateValue("{$paramKey}_confirm", $validations);
            if ($resp) {
                static::$errors[$paramKey] = $resp;
                continue;
            }
        }

        if (count(static::$errors) > 0 && $throw) {
            throw new ValidationException(static::$errors, static::$errorMessage, static::$errorCode);
        }

        if (count(static::$errors) > 0) {
            return false;
        }

        return static::$validated;
    }

    public static function setErrorMessage(string $errorMessage)
    {
        static::$errorMessage = $errorMessage;
    }

    public static function setErrorCode(string $errorCode)
    {
        static::$errorCode = $errorCode;
    }

    private function validateValue(string $param, array $validations): null|array
    {
        $errors = [];
        if ($param === null || $validations === null) {
            return null;
        }
        foreach ($validations as $validation) {
            $resp = $this->getError($param, $validation);
            if ($resp == '') {
                continue;
            }

            array_push($errors, $resp);
        }
        if (count($errors) < 1)
            return null;

        return $errors;
    }

    private function getError(string $param, string|ValidatorRule $type): string
    {
        $resp = '';
        $paramval = $this->getParamValue($param);
        if ($type instanceof ValidatorRule) {
            if (!$type->passes($paramval))
                $resp = $type->getError();

            return $resp;
        }
        if ($paramval === null && $type !== 'required' && $type !== 'sometimes' && $type !== 'forbidden') {
            return $resp;
        }
        switch ($type) {
            case 'required':
                if ($paramval === null || (is_string($paramval) && trim($paramval) === ''))
                    $resp = "$param is required";
                else
                    $resp = '';
                break;
            case 'sometimes':
                $resp = '';
                break;
            case 'forbidden':
                $resp = $paramval === null ? '' : "$param is forbidden in this request";
                break;
            case 'string':
                $stat = is_string($paramval);
                $resp = !$stat ? "$param should be a string" : '';
                break;
            case 'bool':
            case 'boolean':
                $stat = is_bool($paramval);
                $resp = !$stat ? "$param should be a boolean" : '';
                break;
            case 'float':
            case 'double':
                $stat = is_float($paramval) || is_int($paramval) || is_numeric($paramval);
                $resp = !$stat ? "$param should be a float" : '';
                break;
            case 'integer':
            case 'int':
                $stat = is_int($paramval) || (is_numeric($paramval) && strpos((string)$paramval, '.') === false);
                $resp = !$stat ? "$param should be an integer" : '';
                break;
            case 'numeric':
                $stat = is_numeric($paramval);
                $resp = !$stat ? "$param should be a numeric" : '';
                break;
            case 'url':
                $stat = is_link($paramval);
                $resp = !$stat ? "$param should be an url" : '';
                break;
            case 'file':
                $stat = is_file($paramval);
                $resp = !$stat ? "$param should be a file" : '';
                break;
            case 'array':
                $stat = is_array($paramval);
                $resp = !$stat ? "$param should be an array" : '';
                break;
            case 'json':
                $stat = is_string($paramval) && Str::isJson($paramval);
                $resp = !$stat ? "$param should be a valid json string" : '';
                break;
            case 'uuid':
                $stat = is_string($paramval) && Str::isUuid($paramval);
                $resp = !$stat ? "$param should be a valid uuid string" : '';
                break;
            case 'ascii':
                $stat = is_string($paramval) && Str::isAscii($paramval);
                $resp = !$stat ? "$param should be a valid ascii string" : '';
                break;
            case 'phone':
                $stat = is_string($paramval) && Str::isPhoneNumber($paramval);
                $resp = !$stat ? "$param should be a valid phone string" : '';
                break;
            case 'email':
                $stat = is_string($paramval) && Str::isEmail($paramval);
                $resp = !$stat ? "$param should be a valid email string" : '';
                break;
            case 'base64':
                $stat = is_string($paramval) && Str::isBase64($paramval);
                $resp = !$stat ? "$param should be a valid base64 string" : '';
                break;
            case 'confirm':
                static::$confirms[$param] = $paramval;
                $resp = '';
                break;
            case 'image':
                $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $is_image = $paramval instanceof File && Arr::exists($image_types, $paramval->uploadProperties()->type());
                $resp = $is_image ? '' : "$param should be a valid image file";
                break;
            default:
                $resp = $this->performAdvanceValidation($type, $param, $paramval);
        }
        return $resp;
    }

    private function isGreaterThanMax($param, $paramval, $max): string
    {
        if ($paramval instanceof File) {
            return ($paramval->uploadProperties()->size() / 1024) > $max ? "{$param} should not be more than $max kb" : '';
        } elseif (is_string($paramval)) {
            return strlen($paramval) > $max ? "{$param} should not contain more than $max characters" : '';
        } elseif (is_array($paramval)) {
            return count($paramval) > $max ? "{$param} should not contain more than $max items" : '';
        } elseif (is_numeric($paramval)) {
            return (float)$paramval > $max ? "{$param} should not be greater than $max" : '';
        }

        return '';
    }

    private function isLessThanMin($param, $paramval, $min): string
    {
        if ($paramval instanceof File) {
            return ($paramval->uploadProperties()->size() / 1024) < $min ? "{$param} should not be less than $min kb" : '';
        } elseif (is_string($paramval)) {
            return strlen($paramval) < $min ? "{$param} should not contain less than $min characters" : '';
        } elseif (is_array($paramval)) {
            return count($paramval) < $min ? "{$param} should not contain less than $min items" : '';
        } elseif (is_numeric($paramval)) {
            return (float)$paramval < $min ? "{$param} should not be less than $min" : '';
        }

        return '';
    } 

    private function performAdvanceValidation(string $type, $param, $paramval)
    {
        if (Str::contains($type, ":")) {
            $items = explode(':', $type);

            // print_r($items);

            switch ($items[0]) {
                case 'max':
                    $resp = $this->isGreaterThanMax($param, $paramval, $items[1]);
                    break;
                case 'min':
                    $resp = $this->isLessThanMin($param, $paramval, $items[1]);
                    break;
                case 'equals':
                    $resp = $paramval === $items[1] ? '' : "{$param} should be same as " . str_replace('_confirm', '', $param);
                    break;
                case 'not_equals':
                    $resp = $paramval === $items[1] ? "{$param} should not be same as " . str_replace('_confirm', '', $param) : '';
                    break;
                case 'in':
                    $resp = !Arr::exists(array_map('trim', explode(',', $items[1])), $paramval, true) ? "{$param} should contain one of {$items[1]}" : '';
                    break;
                case 'not_in':
                    $resp = Arr::exists(array_map('trim', explode(',', $items[1])), $paramval, true) ? "{$param} should not contain any of {$items[1]}" : '';
                    break;
                case 'exist':
                    $_items = array_map('trim', explode(',', $items[1]));
                    $resp = DatabaseConnection::count($_items[0], [$_items[1] => $paramval]) > 0 ? "" : "{$param} should exist in {$_items[1]} column of table {$_items[0]}";
                    break;
                case 'not_exist':
                    $_items = array_map('trim', explode(',', $items[1]));
                    $resp = DatabaseConnection::count($_items[0], [$_items[1] => $paramval]) < 1 ? "" : "{$param} should not exist in {$_items[1]} column of table {$_items[0]}";
                    break;
                case 'contains':
                    $stat = is_string($paramval) && Str::contains($paramval, $items[1]);
                    $resp = !$stat ? "{$param} should be a string that contains $items[1]" : '';
                    break;
                case 'includes':
                    $stat = is_array($paramval) && Arr::has($paramval, $items[1]);
                    $resp = !$stat ? "{$param} should be an array that has $items[1]" : '';
                    break;
                case 'mimes':
                    $mimes = array_map('trim', explode(',', $items[1]));
                    $mimeParts = explode('/', mime_content_type($paramval->uploadProperties()->tmpName()));
                    $mime = $mimeParts[1] ?? '';
                    $is_valid_mime = $paramval instanceof File && Arr::exists($mimes, $mime);
                    $resp = $is_valid_mime ? '' : "$param should be a file with one of " . $items[1] ."mime";
                    break;
                case 'mimetypes':
                    $mimes = array_map('trim', explode(',', $items[1]));
                    $mimeType = mime_content_type($paramval->uploadProperties()->tmpName());
                    $is_valid_mime = $paramval instanceof File && Arr::exists($mimes, $mimeType);
                    $resp = $is_valid_mime ? '' : "$param should be a file with one of " . $items[1] ."mime type";
                    break;
                default:
                    $resp = '';
            }
        } else { $resp = ''; }

        return $resp;
    }

    private function getParamValue(string $param): int|bool|float|string|array|File|null
    {
        $dat = array_merge(self::$req_data, self::$req_files);

        // Support dot notation for nested arrays (e.g., 'analytics.totalTrades')
        if (strpos($param, '.') !== false) {
            $keys = explode('.', $param);
            $value = $dat;

            foreach ($keys as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    return null;
                }
            }

            return $value;
        }

        if (!array_key_exists($param, $dat)) {
            return null;
        }
        return $dat[$param] ?? '';
    }
}
