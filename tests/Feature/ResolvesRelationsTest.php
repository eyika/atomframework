<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Auth\User as AuthUser;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Database\Relation;

class RRTeam extends Model
{
    public $table = 'atomtest_rr_teams';
    public $softdeletes = false;
    const fillable = ['id', 'name'];
}

class RRLogin extends Model
{
    public $table = 'atomtest_rr_logins';
    public $softdeletes = false;
    const fillable = ['id', 'rr_user_id', 'ip'];
}

/**
 * An Auth\User subclass. Auth\User uses ResolvesRelations, so a direct relation
 * call ($user->team()) must return resolved DATA, not a Relation descriptor —
 * matching the app convention (App\Models\User extends this base).
 */
class RRUser extends AuthUser
{
    public $table = 'atomtest_rr_users';
    public $softdeletes = false;
    const fillable = ['id', 'name', 'team_id'];

    public function team()
    {
        return $this->belongsTo(RRTeam::class, 'team_id', 'id');
    }

    public function logins()
    {
        return $this->hasMany(RRLogin::class, 'rr_user_id', 'id');
    }
}

/**
 * Covers the ResolvesRelations trait (applied to Auth\User): hasOne/belongsTo/
 * hasMany resolve to DATA on a direct call, while the base Model keeps returning
 * Relation descriptors for with() batching (EagerLoadTest).
 */
class ResolvesRelationsTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_rr_logins');
        $this->raw('DROP TABLE IF EXISTS atomtest_rr_users');
        $this->raw('DROP TABLE IF EXISTS atomtest_rr_teams');
        $this->raw('CREATE TABLE atomtest_rr_teams (id INT PRIMARY KEY, name VARCHAR(50))');
        $this->raw('CREATE TABLE atomtest_rr_users (id INT PRIMARY KEY, name VARCHAR(50), team_id INT NULL)');
        $this->raw('CREATE TABLE atomtest_rr_logins (id INT PRIMARY KEY, rr_user_id INT, ip VARCHAR(40))');
        $this->raw("INSERT INTO atomtest_rr_teams (id, name) VALUES (10, 'Alpha')");
        $this->raw("INSERT INTO atomtest_rr_users (id, name, team_id) VALUES (1, 'Ada', 10), (2, 'Bob', NULL)");
        $this->raw("INSERT INTO atomtest_rr_logins (id, rr_user_id, ip) VALUES (1,1,'1.1.1.1'),(2,1,'2.2.2.2')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_rr_logins');
        $this->raw('DROP TABLE IF EXISTS atomtest_rr_users');
        $this->raw('DROP TABLE IF EXISTS atomtest_rr_teams');
    }

    public function test_belongs_to_resolves_to_data_not_a_relation(): void
    {
        $user = (new RRUser())->where('id', 1)->first(true);

        $team = $user->team();

        $this->assertNotInstanceOf(Relation::class, $team);
        $this->assertInstanceOf(RRTeam::class, $team);
        $this->assertSame('Alpha', $team->name);
    }

    public function test_belongs_to_returns_null_when_fk_is_null(): void
    {
        $user = (new RRUser())->where('id', 2)->first(true);

        $this->assertNull($user->team());
    }

    public function test_has_many_resolves_to_an_array_of_models(): void
    {
        $user = (new RRUser())->where('id', 1)->first(true);

        $logins = $user->logins();

        $this->assertIsArray($logins);
        $this->assertCount(2, $logins);
        $this->assertInstanceOf(RRLogin::class, $logins[0]);
    }

    public function test_get_relation_still_works(): void
    {
        $user = (new RRUser())->where('id', 1)->first(true);

        $this->assertSame('Alpha', $user->getRelation('team')->name);
    }
}
