<?php

namespace Eyika\Atom\Framework\Support\Database;

use Eyika\Atom\Framework\Http\Route;

class PaginatedData
{
    protected static array $data;
    protected static int $total_records;
    protected static int $records_per_page;
    protected static int $total_pages;
    protected static int $current_page;

    public function __construct(array $data, int $total_records, int $records_per_page, int $total_pages, int $current_page)
    {
        static::$data = $data;
        static::$total_records = $total_records;
        static::$records_per_page = $records_per_page;
        static::$total_pages = $total_pages;
        static::$current_page = $current_page;
    }

    public static function init(array $data, int $total_records, int $records_per_page, int $total_pages, int $current_page)
    {
        return new static($data, $total_records, $records_per_page, $total_pages, $current_page);
    }

    public static function toArray(string $route_name)
    {
        return [
            'data' => self::$data,
            'totalRecords' => self::$total_records,
            'totalPages' => self::$total_pages,
            'recordsPerPage' => self::$records_per_page,
            'currentPage' => self::$current_page,
            'previousPage' => self::$current_page > 1 ? self::baseUrl($route_name, false) : null,
            'nextPage' => self::$current_page < self::$total_pages ? self::baseUrl($route_name) : null,
        ];
    }

    private static function baseUrl(string $route_name, bool $incr = true)
    {
        return Route::route($route_name)."?page=". ($incr ? self::$current_page + 1 : self::$current_page - 1);
    }
}