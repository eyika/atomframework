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

        // `items.*.name` becomes items.0.name, items.1.name, … against the actual payload, so
        // every rule below (and the dot-notation collection) runs unchanged.
        $params = $me->expandWildcardRules($params);

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

    /**
     * Reset the static validation state (WRK-06/BUG-41). validate() already re-inits
     * everything on each call (via `new self()`), so this only clears the residue the
     * LAST request left behind — the worker calls it between requests so a stray read
     * of Validator::$errors before the next validate() can't leak across users.
     */
    public static function flush(): void
    {
        self::$req_data = [];
        self::$req_files = [];
        self::$errors = [];
        self::$validated = [];
        self::$confirms = [];
        self::$errorMessage = '';
        self::$errorCode = 422;
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
                if (
                    $paramval === null
                    || (is_string($paramval) && trim($paramval) === '')
                    || (is_array($paramval) && count($paramval) === 0)
                ) {
                    $resp = "$param is required";
                } else {
                    $resp = '';
                }
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
                // FILTER_VALIDATE_INT rejects "1e3"/"0x1A"/"10.5" that the old
                // is_numeric + no-dot check let through as integers.
                $stat = is_int($paramval) || (is_string($paramval) && filter_var($paramval, FILTER_VALIDATE_INT) !== false);
                $resp = !$stat ? "$param should be an integer" : '';
                break;
            case 'numeric':
                $stat = is_numeric($paramval);
                $resp = !$stat ? "$param should be a numeric" : '';
                break;
            case 'url':
                // Was using is_link() — a filesystem syscall that checks for
                // symbolic links on disk. Replaced with FILTER_VALIDATE_URL.
                $stat = is_string($paramval) && filter_var($paramval, FILTER_VALIDATE_URL) !== false;
                $resp = !$stat ? "$param should be an url" : '';
                break;
            case 'file':
                // Was using is_file() — checks if the value is a path to a
                // regular file on disk. Uploaded files arrive as instances of
                // the framework's File class, so use the same shape as 'image'.
                $stat = $paramval instanceof File;
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
            // mb_strlen so multi-byte chars (emojis, accented letters,
            // non-Latin scripts) count as one character each, not 2-4 bytes.
            return mb_strlen($paramval, 'UTF-8') > $max ? "{$param} should not contain more than $max characters" : '';
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
            return mb_strlen($paramval, 'UTF-8') < $min ? "{$param} should not contain less than $min characters" : '';
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
                    // Loose compare: $items[1] is always the rule's RHS as a
                    // string, but $paramval can be int/bool/float, so strict
                    // === would always fail for non-strings.
                    $resp = $paramval == $items[1] ? '' : "{$param} should be same as " . str_replace('_confirm', '', $param);
                    break;
                case 'not_equals':
                    $resp = $paramval == $items[1] ? "{$param} should not be same as " . str_replace('_confirm', '', $param) : '';
                    break;
                case 'in':
                    // Scalar-guard + string-compare against the string list (an array
                    // value can't be "one of" the options; 5 must match "5").
                    $allowed = array_map('trim', explode(',', $items[1]));
                    $resp = (is_scalar($paramval) && Arr::exists($allowed, (string) $paramval, true)) ? '' : "{$param} should contain one of {$items[1]}";
                    break;
                case 'not_in':
                    $allowed = array_map('trim', explode(',', $items[1]));
                    $resp = (is_scalar($paramval) && Arr::exists($allowed, (string) $paramval, true)) ? "{$param} should not contain any of {$items[1]}" : '';
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
                    // Guard instanceof File FIRST — the old code called
                    // ->uploadProperties() on a non-File value (fatal).
                    if (!$paramval instanceof File) {
                        $resp = "$param should be a file";
                        break;
                    }
                    $mimes = array_map('trim', explode(',', $items[1]));
                    $mimeParts = explode('/', mime_content_type($paramval->uploadProperties()->tmpName()));
                    $mime = $mimeParts[1] ?? '';
                    $resp = Arr::exists($mimes, $mime) ? '' : "$param should be a file with one of " . $items[1] . " mime";
                    break;
                case 'mimetypes':
                    if (!$paramval instanceof File) {
                        $resp = "$param should be a file";
                        break;
                    }
                    $mimes = array_map('trim', explode(',', $items[1]));
                    $mimeType = mime_content_type($paramval->uploadProperties()->tmpName());
                    $resp = Arr::exists($mimes, $mimeType) ? '' : "$param should be a file with one of " . $items[1] . " mime type";
                    break;
                default:
                    $resp = '';
            }
        } else { $resp = ''; }

        return $resp;
    }

    /**
     * Expand wildcard rule keys against the payload, leaving every other key untouched.
     *
     * `['items.*.name' => 'required|string']` over three items yields
     * `['items.0.name' => …, 'items.1.name' => …, 'items.2.name' => …]`, so collections of
     * objects can be validated declaratively instead of by hand in the controller. Errors are
     * reported under the concrete key (`items.1.name`), which points at the offending element.
     *
     * A wildcard over an absent or empty array expands to NOTHING — that is deliberate: a
     * per-element rule cannot assert the container exists. Pair it with a rule on the container
     * itself (`'items' => 'required|array'`) when the collection is mandatory.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function expandWildcardRules(array $params): array
    {
        $expanded = [];

        foreach ($params as $key => $rules) {
            if (!is_string($key) || !str_contains($key, '*')) {
                $expanded[$key] = $rules;
                continue;
            }

            foreach ($this->expandWildcardKey($key) as $concrete) {
                $expanded[$concrete] = $rules;
            }
        }

        return $expanded;
    }

    /**
     * Resolve one wildcard key to the concrete keys present in the payload. Nested wildcards
     * (`items.*.tags.*`) resolve left to right, each pass replacing the leftmost `*`.
     *
     * @return list<string>
     */
    private function expandWildcardKey(string $key): array
    {
        $star = strpos($key, '*');

        if ($star === false) {
            return [$key];
        }

        $prefix = rtrim(substr($key, 0, $star), '.');
        $suffix = ltrim(substr($key, $star + 1), '.');

        $target = $prefix === ''
            ? array_merge(self::$req_data, self::$req_files)
            : $this->getParamValue($prefix);

        if (!is_array($target)) {
            return [];
        }

        $keys = [];

        foreach (array_keys($target) as $index) {
            $concrete = $prefix === '' ? (string) $index : "$prefix.$index";

            if ($suffix !== '') {
                $concrete .= ".$suffix";
            }

            foreach ($this->expandWildcardKey($concrete) as $resolved) {
                $keys[] = $resolved;
            }
        }

        return $keys;
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
        // Preserve explicit null values — previously `?? ''` coerced an
        // explicit null in the request into '', conflating "key absent"
        // and "key present with null". Callers using the 'sometimes' /
        // 'required' rules already handle null correctly.
        return $dat[$param];
    }
}
