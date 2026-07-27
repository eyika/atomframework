<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Covers BUG-01 (isJson honours charset), BUG-03 (hasFile no longer depends on a
 * PHPUnit function), BUG-04 (hasBody precedence), BUG-05 (is() actually matches),
 * BUG-06 (has() counts a present-null value).
 */
class RequestParsingTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['HTTP_CONTENT_TYPE', 'CONTENT_LENGTH', 'REQUEST_URI', 'REQUEST_METHOD'] as $k) {
            unset($_SERVER[$k]);
        }
        $_POST = $_GET = [];
        parent::tearDown();
    }

    public function test_is_json_matches_media_type_with_charset(): void
    {
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json; charset=utf-8';
        $this->assertTrue((new Request())->isJson());

        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $this->assertTrue((new Request())->isJson());

        $_SERVER['HTTP_CONTENT_TYPE'] = 'text/html';
        $this->assertFalse((new Request())->isJson());
    }

    public function test_has_file_does_not_fatal_and_reports_absence(): void
    {
        $this->assertFalse((new Request())->hasFile('avatar'));
    }

    public function test_has_body_compares_length_to_min(): void
    {
        $_SERVER['CONTENT_LENGTH'] = '100';
        $this->assertTrue((new Request())->hasBody());

        $_SERVER['CONTENT_LENGTH'] = '0';
        $this->assertFalse((new Request())->hasBody());
    }

    public function test_is_matches_path_with_wildcard(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/users?page=2';
        $request = new Request();

        $this->assertTrue($request->is('admin/*'));
        $this->assertTrue($request->is('admin/users'));
        $this->assertFalse($request->is('shop/*'));
    }

    public function test_has_counts_a_present_null_value(): void
    {
        $_POST = ['nickname' => null];
        $request = new Request();

        $this->assertTrue($request->has('nickname'));
        $this->assertFalse($request->has('missing'));
    }
}
