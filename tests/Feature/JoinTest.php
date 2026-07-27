<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;

class JoinTestPost extends Model
{
    public $table = 'atomtest_posts';
    public $softdeletes = false;
    protected $fillable = ['id', 'user_id', 'title'];
}

/**
 * Covers BUG-30: JOINs were entirely dead (Connection::fetch hardcoded [] joins),
 * so a query filtered by a joined-table column would fail. Now the builder threads
 * $this->joins through fetch and the JOIN executes.
 */
class JoinTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_posts');
        $this->raw('DROP TABLE IF EXISTS atomtest_users');
        $this->raw('CREATE TABLE atomtest_users (id INT PRIMARY KEY, name VARCHAR(50))');
        $this->raw('CREATE TABLE atomtest_posts (id INT PRIMARY KEY, user_id INT, title VARCHAR(80))');
        $this->raw("INSERT INTO atomtest_users (id, name) VALUES (1, 'Ada'), (2, 'Bob')");
        $this->raw("INSERT INTO atomtest_posts (id, user_id, title) VALUES (1, 1, 'Ada-post'), (2, 2, 'Bob-post')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_posts');
        $this->raw('DROP TABLE IF EXISTS atomtest_users');
    }

    public function test_inner_join_selects_a_joined_table_column(): void
    {
        $posts = (new JoinTestPost())
            ->join('atomtest_users', 'atomtest_posts.user_id', '=', 'atomtest_users.id')
            ->where('user_id', 1)
            ->_all(true, ['atomtest_posts.title', 'atomtest_users.name']);

        // Selecting atomtest_users.name only succeeds if the JOIN actually executed
        // (previously fetch hardcoded [] joins → "unknown column").
        $this->assertNotFalse($posts);
        $this->assertCount(1, $posts);
        $this->assertSame('Ada-post', $posts[0]->title);
        $this->assertSame('Ada', $posts[0]->name); // the joined author name
    }
}
