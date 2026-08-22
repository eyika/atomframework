<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;

class MysqlSearchableProduct extends Model
{
    public $table = 'atomtest_like_products';
    public $softdeletes = false;

    protected const fillable = ['id', 'title'];
    protected const guarded  = [];
}

/**
 * The MySQL half of the LIKE-escape fix. The whole reason that fix lives in the framework rather
 * than at the call site is that **MySQL's LIKE has a default escape character and SQLite's has
 * none** — so a predicate hand-escaped in PHP means one thing in production and another in a suite
 * running on SQLite.
 *
 * Asserting it on SQLite alone would therefore prove nothing about the claim being made. These are
 * the same assertions as the SQLite test, run against a real MySQL: identical results on both is
 * the property under test.
 *
 * MySQL also treats a backslash as an escape INSIDE a string literal, so the escape character has
 * to be written doubled here — that difference is what `MySqlGrammar::compileLikeEscape()` exists
 * for, and it is only observable against MySQL itself.
 */
class LikeEscapeMysqlTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_like_products');
        $this->raw('CREATE TABLE atomtest_like_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100) NULL
        )');
        $this->raw("INSERT INTO atomtest_like_products (id, title) VALUES
            (1, 'Blue Tee'),
            (2, 'The Book'),
            (3, '50% Off Mug'),
            (4, 'under_score')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_like_products');
    }

    private function ids($rows): array
    {
        $out = [];
        foreach (($rows ?: []) as $row) {
            $out[] = (int) (is_array($row) ? $row['id'] : $row->id);
        }
        sort($out);

        return $out;
    }

    private function search(string $term, bool $escape = true): array
    {
        return $this->ids((new MysqlSearchableProduct())->whereLike('title', $term, $escape)->get());
    }

    public function test_a_percent_is_matched_literally_when_escaped(): void
    {
        $this->assertSame([3], $this->search('%'));
    }

    public function test_an_underscore_is_matched_literally_when_escaped(): void
    {
        $this->assertSame([], $this->search('t_e'));
        $this->assertSame([4], $this->search('_score'));
    }

    public function test_an_ordinary_search_still_works(): void
    {
        $this->assertSame([1], $this->search('Tee'));
    }

    /** Opt-out keeps the wildcard behaviour the three-argument idiom depends on. */
    public function test_wildcards_still_work_when_escaping_is_not_requested(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->search('%', false));
    }

    /**
     * The property the whole change rests on: the same term, escaped, selects the same rows on
     * MySQL as it does on SQLite. If this ever diverges, the predicate has stopped being portable
     * and a search box will behave differently in production than in the suite.
     */
    public function test_results_match_the_sqlite_expectations_exactly(): void
    {
        $this->assertSame([3], $this->search('%'), 'percent');
        $this->assertSame([], $this->search('t_e'), 'underscore');
        $this->assertSame([4], $this->search('_score'), 'leading underscore');
        $this->assertSame([1], $this->search('Tee'), 'plain term');
        $this->assertSame([1], $this->search('BLUE'), 'case-insensitivity');
    }
}
