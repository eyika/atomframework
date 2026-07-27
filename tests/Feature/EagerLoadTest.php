<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;

class ELUser extends Model
{
    public $table = 'atomtest_el_users';
    public $softdeletes = false;
    const fillable = ['id', 'name'];

    public function posts()
    {
        return $this->hasMany(ELPost::class, 'el_user_id', 'id');
    }
}

class ELPost extends Model
{
    public $table = 'atomtest_el_posts';
    public $softdeletes = false;
    const fillable = ['id', 'el_user_id', 'title'];

    public function author()
    {
        return $this->belongsTo(ELUser::class, 'el_user_id', 'id');
    }
}

/**
 * Covers BUG-21: relationships were N+1 (executed per row). Now hasOne/belongsTo/
 * hasMany return a Relation descriptor and with() batch-loads every parent with a
 * single whereIn.
 */
class EagerLoadTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_el_posts');
        $this->raw('DROP TABLE IF EXISTS atomtest_el_users');
        $this->raw('CREATE TABLE atomtest_el_users (id INT PRIMARY KEY, name VARCHAR(50))');
        $this->raw('CREATE TABLE atomtest_el_posts (id INT PRIMARY KEY, el_user_id INT, title VARCHAR(80))');
        $this->raw("INSERT INTO atomtest_el_users (id, name) VALUES (1, 'Ada'), (2, 'Bob')");
        $this->raw("INSERT INTO atomtest_el_posts (id, el_user_id, title) VALUES (1,1,'A1'),(2,1,'A2'),(3,2,'B1')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_el_posts');
        $this->raw('DROP TABLE IF EXISTS atomtest_el_users');
    }

    public function test_eager_belongs_to(): void
    {
        $posts = (new ELPost())->with('author')->all(true);
        $this->assertCount(3, $posts);

        $byId = [];
        foreach ($posts as $post) {
            $byId[$post->id] = $post;
        }

        $this->assertSame('Ada', $byId[1]->author->name);
        $this->assertSame('Ada', $byId[2]->author->name);
        $this->assertSame('Bob', $byId[3]->author->name);
    }

    public function test_eager_has_many(): void
    {
        $users = (new ELUser())->with('posts')->all(true);

        $byId = [];
        foreach ($users as $user) {
            $byId[$user->id] = $user;
        }

        $this->assertCount(2, $byId[1]->posts); // Ada
        $this->assertCount(1, $byId[2]->posts); // Bob
    }

    public function test_lazy_get_relation(): void
    {
        $post = (new ELPost())->where('id', 1)->first(true);

        $this->assertSame('Ada', $post->getRelation('author')->name);
    }
}
