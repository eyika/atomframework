<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Grammars\Grammar;
use Eyika\Atom\Framework\Support\Database\Grammars\MySqlGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\PostgresGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\SqliteGrammar;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class SearchableProduct extends Model
{
    public $table = 'products';
    public $softdeletes = false;

    protected const fillable = ['id', 'title'];
    protected const guarded  = [];
}

/**
 * Reported by a downstream consumer: the query builder could not express `LIKE … ESCAPE`, so a
 * shopper-typed `%` or `_` had no portable fix — `?q=%` meant "every product in the shop" and
 * `?q=t_e` matched "tee" and "the" alike.
 *
 * Their key insight is why this belongs in the framework rather than the call site: **MySQL's LIKE
 * has a default escape character and SQLite's has none**, so hand-escaping in PHP produces a
 * predicate that means one thing in production and another in a suite running on SQLite. A
 * predicate that behaves differently in test and in production is worse than the bug it fixes.
 *
 * The MySQL half of this is asserted in tests/Feature/LikeEscapeMysqlTest.php, against a real
 * MySQL — the portability claim is worth nothing if only one driver is ever exercised.
 */
class LikeEscapeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not enabled.');
        }

        $conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['database' => ':memory:']],
        ]);
        $conn->connect();
        DatabaseConnection::swap($conn);

        DatabaseConnection::exec('CREATE TABLE products (id INTEGER PRIMARY KEY, title TEXT)');

        foreach ([
            [1, 'Blue Tee'],
            [2, 'The Book'],
            [3, '50% Off Mug'],
            [4, 'under_score'],
            [5, 'back\\slash'],
        ] as $row) {
            DatabaseConnection::exec('INSERT INTO products (id, title) VALUES (?, ?)', $row);
        }
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
        return $this->ids((new SearchableProduct())->whereLike('title', $term, $escape)->get());
    }

    // ---------------------------------------------------------------- the reported hazard

    /** `?q=%` used to mean "the whole catalogue". */
    public function test_a_percent_is_matched_literally_when_escaped(): void
    {
        $this->assertSame([3], $this->search('%'), 'a typed % should find the row containing one');
    }

    /** `?q=t_e` used to match "tee" and "the" alike. */
    public function test_an_underscore_is_matched_literally_when_escaped(): void
    {
        $this->assertSame([], $this->search('t_e'), 'no title contains a literal "t_e"');
        $this->assertSame([4], $this->search('_score'));
    }

    /** The escape character itself must be escaped first, or terms containing it corrupt. */
    public function test_a_literal_backslash_in_the_term_is_handled(): void
    {
        $this->assertSame([5], $this->search('back\\slash'));
    }

    public function test_an_ordinary_search_still_works(): void
    {
        $this->assertSame([1], $this->search('Tee'));
        $this->assertSame([1, 2], $this->search('e '));
    }

    public function test_matching_stays_case_insensitive(): void
    {
        $this->assertSame([1], $this->search('BLUE'));
        $this->assertSame([1], $this->search('blue'));
    }

    public function test_escaping_composes_with_other_conditions(): void
    {
        $rows = (new SearchableProduct())
            ->where('id', 3)
            ->whereLike('title', '%', true)
            ->get();

        $this->assertSame([3], $this->ids($rows));
    }

    public function test_where_not_like_escapes_too(): void
    {
        $rows = (new SearchableProduct())->whereNotLike('title', '%', true)->get();

        $this->assertSame([1, 2, 4, 5], $this->ids($rows));
    }

    // ---------------------------------------------------------------- opt-in, by design

    /**
     * Escaping is opt-in because the documented three-argument idiom passes its OWN wildcards:
     * `where('title', 'LIKE', "%$term%")` must keep meaning what it says.
     */
    public function test_wildcards_still_work_when_escaping_is_not_requested(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $this->search('%', false));
    }

    public function test_the_three_argument_form_is_unaffected(): void
    {
        $rows = (new SearchableProduct())->where('title', 'LIKE', 'Tee')->get();

        $this->assertSame([1], $this->ids($rows));
    }

    // ---------------------------------------------------------------- the portability difference

    /**
     * The one line that makes this portable: MySQL treats a backslash as an escape INSIDE a string
     * literal too, so the escape character must be written doubled there and singly elsewhere.
     */
    public function test_the_escape_clause_differs_per_grammar(): void
    {
        $this->assertSame(" ESCAPE '\\'", (new SqliteGrammar())->compileLikeEscape());
        $this->assertSame(" ESCAPE '\\'", (new PostgresGrammar())->compileLikeEscape());
        $this->assertSame(" ESCAPE '\\\\'", (new MySqlGrammar())->compileLikeEscape());
    }

    public function test_the_escaper_covers_both_wildcards_and_itself(): void
    {
        $this->assertSame('100\\%', Grammar::escapeLikeWildcards('100%'));
        $this->assertSame('a\\_b', Grammar::escapeLikeWildcards('a_b'));
        $this->assertSame('a\\\\b', Grammar::escapeLikeWildcards('a\\b'));
        $this->assertSame('plain', Grammar::escapeLikeWildcards('plain'));
    }
}
