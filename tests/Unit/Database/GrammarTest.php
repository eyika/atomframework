<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Grammars\Grammar;
use Eyika\Atom\Framework\Support\Database\Grammars\GrammarFactory;
use Eyika\Atom\Framework\Support\Database\Grammars\MySqlGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\PostgresGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\SqliteGrammar;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Pure (no-DB) checks that the MySQL and SQLite grammars compile the same portable Blueprint
 * into the right dialect DDL — MySQL preserving the historical output, SQLite producing valid
 * SQLite. This is the parity net for the driver split.
 */
class GrammarTest extends TestCase
{
    private function usersBlueprint(): Blueprint
    {
        $b = new Blueprint('users');
        $b->id();
        $b->string('name');
        $b->string('email', 120)->unique();
        $b->boolean('active')->default(true);
        $b->integer('age')->nullable();
        $b->enum('role', ['admin', 'user']);
        $b->timestamps();
        return $b;
    }

    public function test_mysql_create_table_matches_historical_ddl(): void
    {
        $sql = (new MySqlGrammar())->compileCreate($this->usersBlueprint());
        $this->assertCount(1, $sql); // MySQL inlines everything
        $ddl = $sql[0];

        $this->assertStringContainsString('`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $ddl);
        $this->assertStringContainsString('`name` VARCHAR(255)', $ddl);
        $this->assertStringContainsString('`email` VARCHAR(120) UNIQUE', $ddl);
        $this->assertStringContainsString('`active` TINYINT(1) DEFAULT 1', $ddl);
        $this->assertStringContainsString('`age` INT NULL', $ddl);
        $this->assertStringContainsString("`role` ENUM('admin', 'user')", $ddl);
        $this->assertStringContainsString('`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP', $ddl);
        $this->assertStringContainsString('`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $ddl);
    }

    public function test_sqlite_create_table_is_valid_sqlite(): void
    {
        $sql = (new SqliteGrammar())->compileCreate($this->usersBlueprint());

        // Two statements now: the table, then the column's unique constraint as a NAMED index.
        // Inline UNIQUE on SQLite produces an implicit `sqlite_autoindex_*` that the engine
        // refuses to drop, so dropUnique(['email']) could never work against it.
        $this->assertCount(2, $sql);
        $ddl = $sql[0];

        $this->assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $ddl);
        $this->assertStringContainsString('"name" VARCHAR(255)', $ddl);
        $this->assertStringContainsString('"email" VARCHAR(120)', $ddl);
        $this->assertStringNotContainsString('UNIQUE', $ddl, 'the constraint moves to its own index');
        $this->assertStringContainsString('CREATE UNIQUE INDEX "unique_email" ON "users" ("email")', $sql[1]);
        $this->assertStringContainsString('"active" INTEGER DEFAULT 1', $ddl);
        $this->assertStringContainsString('"age" INTEGER NULL', $ddl);
        $this->assertStringContainsString('"role" TEXT', $ddl);                 // no native ENUM
        $this->assertStringContainsString('"created_at" DATETIME DEFAULT CURRENT_TIMESTAMP', $ddl);
        // SQLite can't ON UPDATE — the modifier is dropped, not emitted as invalid SQL.
        $this->assertStringNotContainsString('ON UPDATE', $ddl);
        $this->assertStringNotContainsString('AUTO_INCREMENT PRIMARY', $ddl);   // MySQL spelling gone
    }

    public function test_mysql_create_table_declares_utf8mb4_charset(): void
    {
        // Without an explicit DEFAULT CHARSET, a table inherits the server default (latin1 on
        // many MariaDB installs) and rejects multi-byte UTF-8 — a σ dies with 1366. MySQL must
        // emit the charset; sqlite/pgsql have no such clause and must NOT.
        $mysql = (new MySqlGrammar())->compileCreate($this->usersBlueprint())[0];
        $this->assertStringContainsString('DEFAULT CHARSET=utf8mb4', $mysql);
        $this->assertStringContainsString('ENGINE=InnoDB', $mysql);
        $this->assertStringContainsString('COLLATE=utf8mb4_unicode_ci', $mysql);
        // The charset is a trailing table option, after the closing paren of the column list.
        $this->assertMatchesRegularExpression('/\)\s+ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$/', $mysql);

        $sqlite = (new SqliteGrammar())->compileCreate($this->usersBlueprint())[0];
        $this->assertStringNotContainsString('CHARSET', $sqlite);
        $this->assertStringNotContainsString('ENGINE', $sqlite);

        $pgsql = (new PostgresGrammar())->compileCreate($this->usersBlueprint())[0];
        $this->assertStringNotContainsString('CHARSET', $pgsql);
        $this->assertStringNotContainsString('ENGINE', $pgsql);
    }

    public function test_secondary_index_is_inline_on_mysql_but_separate_on_sqlite(): void
    {
        $make = function (): Blueprint {
            $b = new Blueprint('posts');
            $b->id();
            $b->string('slug');
            $b->unique(['slug']);
            return $b;
        };

        $mysql = (new MySqlGrammar())->compileCreate($make());
        $this->assertCount(1, $mysql);
        $this->assertStringContainsString('UNIQUE INDEX', $mysql[0]);

        $sqlite = (new SqliteGrammar())->compileCreate($make());
        $this->assertCount(2, $sqlite); // CREATE TABLE + CREATE UNIQUE INDEX
        $this->assertStringStartsWith('CREATE TABLE', $sqlite[0]);
        $this->assertStringContainsString('CREATE UNIQUE INDEX', $sqlite[1]);
        $this->assertStringContainsString('ON "posts"', $sqlite[1]);
    }

    public function test_unsigned_and_foreign_id_types(): void
    {
        $b = new Blueprint('orders');
        $b->unsignedBigInteger('user_id');
        $mysql = (new MySqlGrammar())->compileCreate($b)[0];
        $sqlite = (new SqliteGrammar())->compileCreate($b)[0];

        $this->assertStringContainsString('`user_id` BIGINT UNSIGNED', $mysql);
        $this->assertStringContainsString('"user_id" INTEGER', $sqlite); // no UNSIGNED in sqlite
        $this->assertStringNotContainsString('UNSIGNED', $sqlite);
    }

    public function test_char_type_compiles_with_length(): void
    {
        $b = new Blueprint('tokens');
        $b->char('hash', 64);
        $this->assertStringContainsString('`hash` CHAR(64)', (new MySqlGrammar())->compileCreate($b)[0]);
        $this->assertStringContainsString('"hash" CHAR(64)', (new SqliteGrammar())->compileCreate($b)[0]);
    }

    public function test_drop_if_exists_is_dialect_quoted(): void
    {
        $this->assertSame('DROP TABLE IF EXISTS `t`', (new MySqlGrammar())->compileDropIfExists('t'));
        $this->assertSame('DROP TABLE IF EXISTS "t"', (new SqliteGrammar())->compileDropIfExists('t'));
    }

    public function test_a_custom_grammar_can_be_registered_and_overrides_a_builtin(): void
    {
        $custom = new class extends SqliteGrammar {};
        try {
            // A brand-new driver…
            GrammarFactory::extend('firebird', fn () => $custom);
            $this->assertSame($custom, GrammarFactory::make('firebird'));
            $this->assertInstanceOf(Grammar::class, GrammarFactory::make('firebird'));

            // …and overriding a built-in driver.
            GrammarFactory::extend('pgsql', fn () => $custom);
            $this->assertSame($custom, GrammarFactory::make('pgsql'));
        } finally {
            GrammarFactory::flushExtensions();
        }

        // Built-ins resolve normally again once extensions are flushed.
        $this->assertInstanceOf(MySqlGrammar::class, GrammarFactory::make('mysql'));
        $this->expectException(\InvalidArgumentException::class);
        GrammarFactory::make('firebird');
    }

    public function test_begin_transaction_and_for_update_are_dialect_specific(): void
    {
        // MySQL keeps its historical spelling; SQLite uses BEGIN and has no row-lock suffix
        // (the transaction serializes writes on its own).
        $this->assertSame('START TRANSACTION', (new MySqlGrammar())->compileBeginTransaction());
        $this->assertSame(' FOR UPDATE', (new MySqlGrammar())->compileForUpdate());

        $this->assertSame('BEGIN', (new SqliteGrammar())->compileBeginTransaction());
        $this->assertSame('', (new SqliteGrammar())->compileForUpdate());
    }
}
