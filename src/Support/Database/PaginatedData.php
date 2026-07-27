<?php

namespace Eyika\Atom\Framework\Support\Database;

use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Support\Collections\Collection;

/**
 * Holds paginated records and allows converting to array while computing the nextpage and previous page urls
 * 
 * This class can be improved further by implementing ArrayAccess or Iterator interface
 */
class PaginatedData
{
    public const recordsPerPage = 15;
    public const currentPage = 1;

    protected static array $data;
    protected static int $total_records;
    protected static int $records_per_page;
    protected static int $total_pages;
    protected static int $current_page;
    protected static string|null $route_name;

    public function __construct(array|Collection $data, int $total_records, int $records_per_page, int $total_pages, int $current_page, ?string $route_name = null)
    {
        // Accept a Collection (query/relation results are Collections now); store the
        // page items as a plain array so toArray()/JSON output is unchanged.
        static::$data = $data instanceof Collection ? $data->all() : $data;
        static::$total_records = $total_records;
        static::$records_per_page = $records_per_page;
        static::$total_pages = $total_pages;
        static::$current_page = $current_page;
        static::$route_name = $route_name;
    }

    public static function init(array|Collection $data, int $total_records, int $records_per_page, int $total_pages, int $current_page, ?string $route_name = null)
    {
        return new static($data, $total_records, $records_per_page, $total_pages, $current_page, $route_name);
    }

    /** The current page's items as a Collection (paginated collection). */
    public static function collection(): Collection
    {
        return collect(static::$data);
    }

    /**
     * Apply a callback to each item in the paginated data.
     *
     * @param callable(&$data, string|int $key): void $callback
     * @return void
     */
    public static function each(callable $callback)
    {
        array_walk(static::$data, $callback);
    }

    public static function toArray()
    {
        return [
            'data' => self::$data,
            'totalRecords' => self::$total_records,
            'totalPages' => self::$total_pages,
            'recordsPerPage' => self::$records_per_page,
            'currentPage' => self::$current_page,
            'previousPage' => self::$current_page > 1 ? self::getFullUrl(false) : null,
            'nextPage' => self::$current_page < self::$total_pages ? self::getFullUrl() : null,
        ];
    }

    private static function getFullUrl(bool $incr = true)
    {
        if (self::$route_name === null)
            self::$route_name = '/';

        return Route::route(self::$route_name)."?page=". ($incr ? self::$current_page + 1 : self::$current_page - 1);
    }
}
