<?php

namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Database\mysqly;
use Eyika\Atom\Framework\Support\Str;

class Validator {
    private static array $req_data;
    public static array $errors;
    private static array $validated;

    public function __construct(Request|array $_req_obj = [])
    {
        self::$req_data = $_req_obj instanceof Request ? $_req_obj->input() : $_req_obj;
        self::$errors = [];
        self::$validated = [];
    }

    public static function validate(Request|array $req_obj, array $params, string $separator = '|'): bool|array
    {
        $me = new self($req_obj);

        foreach ($params as $paramKey => $paramValue) {
            $validations = explode($separator, $paramValue);
            $resp = $me->validateValue($paramKey, $validations);
            if ($resp) {
                static::$errors[$paramKey] = $resp;  //Correction needded, it should be key value pair
                continue;
            }
            if (Arr::keyExists(static::$req_data, $paramKey))
                static::$validated[$paramKey] = static::$req_data[$paramKey];
        }

        if (count(static::$errors) > 0) {
            return false;
        }

        return static::$validated;
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

    private function getError(string $param, string $type): string 
    {
        $paramval = $this->getParamValue($param);
        if ($paramval === false && $type != 'required') {
            return '';
        }
        switch ($type) {
            case 'required':
                if (gettype($paramval) !== 'boolean' || $paramval != false)
                    $resp = '';
                else
                    $resp = "{$param} is required";
                break;
            case 'forbidden':
                    $resp = "{$param} is forbidden in this request";
                break;
            case 'string':
                $stat = is_string($paramval);
                $resp = !$stat ? "{$param} should be a string" : '';
                break;
            case 'bool':
                $stat = !is_bool($paramval);
                $resp = $stat ? "{$param} should be a boolean" : '';
                break;
            case 'boolean':
                $stat = !is_bool($paramval);
                $resp = $stat ? "{$param} should be a boolean" : '';
                break;
            case 'float':
                $stat = is_float($paramval) || is_int($paramval) || is_numeric($paramval);
                $resp = !$stat ? "{$param} should be a float" : '';
                break;
            case 'double':
                $stat = is_double($paramval) || is_int($paramval) || is_numeric($paramval);
                $resp = !$stat ? "{$param} should be a double" : '';
                break;
            case 'integer':
                $stat = is_integer($paramval) || is_int($paramval) || is_numeric($paramval) && !stripos($paramval,'.');
                $resp = !$stat ? "{$param} should be an integer" : '';
                break;
            case 'int':
                $stat = is_integer($paramval) || is_int($paramval) || is_numeric($paramval) && !stripos($paramval,'.');
                $resp = !$stat ? "{$param} should be an integer" : '';
                break;
            case 'numeric':
                $stat = is_numeric($paramval);
                $resp = !$stat ? "{$param} should be a numeric" : '';
                break;
            case 'url':
                $stat = is_link($paramval);
                $resp = !$stat ? "{$param} should be an url" : '';
                break;
            case 'file':
                $stat = is_file($paramval);
                $resp = !$stat ? "{$param} should be a file" : '';
                break;
            case 'array':
                $stat = is_array($paramval);
                $resp = !$stat ? "{$param} should be an array" : '';
                break;
            case 'json':
                $stat = Str::isJson($paramval);
                $resp = !$stat ? "{$param} should be a valid json string" : '';
                break;
            default:
                $resp = $this->performAdvanceValidation($type, $param, $paramval);
        }
        return $resp;
    }

    private function performAdvanceValidation(string $type, $param, $paramval)
    {
        if (Str::contains($type, ":")) {
            $items = explode(':', $type);

            // print_r($items);

            switch ($items[0]) {
                case 'max':
                    $resp = $paramval > (int)$items[1] ? "{$param} should not be greater than {$items[1]}" : '';
                    break;
                case 'min':
                    $resp = $paramval < (int)$items[1] ? "{$param} should not be less than {$items[1]}" : '';
                    break;
                case 'in':
                    $resp = !Arr::exists(explode(', ', $items[1]), $paramval, true) ? "{$param} should contain one of {$items[1]}" : '';
                    break;
                case 'not_in':
                    $resp = Arr::exists(explode(', ', $items[1]), $paramval, true) ? "{$param} should not contain any of {$items[1]}" : '';
                    break;
                case 'exist':
                    $_items = explode(',', $items[1]);
                    $resp = mysqly::count($_items[0], [$_items[1] => $paramval]) > 0 ? "" : "{$param} should exist in {$_items[1]} column of table {$_items[0]}";
                    break;
                case 'not_exist':
                    $_items = explode(',', $items[1]);
                    $resp = mysqly::count($_items[0], [$_items[1] => $paramval]) < 1 ? "" : "{$param} should not exist in {$_items[1]} column of table {$_items[0]}";
                    break;
                case 'contains':
                    $stat = Str::contains($items[0], $items[1]);
                    $resp = !$stat ? "{$param} should be a string that contains $items[1]" : '';
                    break;
                case 'includes':
                    $stat = Arr::has($items[0], $items[1]);
                    $resp = !$stat ? "{$param} should be an array that has $items[1]" : '';
                    break;
                default:
                    $resp = '';
            }
        } else { $resp = ''; }

        return $resp;
    }

    private function getParamValue(string $param): int|bool|float|string|array
    {
        if (!array_key_exists($param, self::$req_data)) {
            return false;
        }
        return self::$req_data[$param] ?? '';
    }
}
