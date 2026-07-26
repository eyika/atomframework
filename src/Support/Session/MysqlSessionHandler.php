<?php

namespace Eyika\Atom\Framework\Support\Session;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;
use PDOException;
use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class MysqlSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private $table;
    private Connection $dbConnection;

    public function __construct()
    {
        // $dbname = env('DB_DATABASE');
        // $dbhost = env('DB_HOST');
        // $dbadapter = env('DB_ADAPTER');
        $this->table = config('session.table', 'sessions');
        $this->dbConnection = new Connection(config('database'));
    }

    public function open($sessionSavePath, $sessionName): bool
    {
        // Do NOT overwrite the configured table with the PHP session name
        // (e.g. "PHPSESSID") — that pointed writes/reads at the wrong table.
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function destroy($sessionId): bool
    {
        try {
            // $query = "DELETE FROM `{$this->table}` WHERE id = :id";
            // $statement = $this->dbConnection->prepare($query);
            // $statement->bindParam(':id', $sessionId);
            // $result = $statement->execute();
            // $statement->closeCursor();

            return DB::table($this->table)->delete($sessionId);
    
            // return $result;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->destroy($sessionId);
        }
    }

    public function gc($maximumLifetime): int|false
    {
        try {
            $query = "DELETE FROM `{$this->table}` WHERE session_last_updated < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
            // Bind :max_lifetime — it was unbound, so gc() always errored (and the
            // catch below retried it forever).
            $result = DB::table($this->table)->raw($query, ['max_lifetime' => (int) $maximumLifetime]);

            return $result ? $result->rowCount() : false;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->gc($maximumLifetime);
        }
    }
    public function read($sessionId): string
    {
        try {
            // $query = "SELECT session_data FROM `{$this->table}` WHERE id = :id";
            // $statement = $this->dbConnection->prepare($query);
            // $statement->bindParam(':id', $sessionId);
            // $statement->execute();
            // $sessionData = $statement->fetchColumn();
            // $statement->closeCursor();

            // first() returns the ROW (assoc array), not the scalar column value —
            // extract session_data or '' (read() must return a string).
            $row = DB::table($this->table)->where('id', $sessionId)->first('session_data');

            return is_array($row) ? (string) ($row['session_data'] ?? '') : '';
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->read($sessionId);
        }
    }
    public function write($sessionId, $sessionData): bool
    {
        try {
            $query = "REPLACE INTO `{$this->table}` (id, session_data, session_last_updated) VALUES (:id, :session_data, NOW())";
            // $statement = $this->dbConnection->prepare($query);
            // $statement->bindParam(':id', $sessionId);
            // $statement->bindParam(':session_data', $sessionData);
            // $result = $statement->execute();
            // $statement->closeCursor();

            // Bind BOTH :id and :session_data — :session_data was unbound, so the
            // session payload was never persisted.
            $result = DB::table($this->table)->raw($query, ['id' => $sessionId, 'session_data' => $sessionData]);

            return (bool) $result;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->write($sessionId, $sessionData);
        }
    }
    public function create_sid(): string
    {
        // Generate and return a new session ID
        return bin2hex(random_bytes(16));
    }
    public function validateId($sessionId): bool
    {
        try {
            // $query = "SELECT COUNT(*) FROM `{$this->table}` WHERE id = :id";
            // $statement = $this->dbConnection->prepare($query);
            // $statement->bindParam(':id', $sessionId);
            // $statement->execute();
            // $count = $statement->fetchColumn();
            // $statement->closeCursor();

            return DB::table($this->table)->where('id', $sessionId)->exists();

            // return $count > 0;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->validateId($sessionId);
        }
    }
    public function updateTimestamp($sessionId, $sessionData): bool
    {
        try {
            // $query = "UPDATE `{$this->table}` SET session_last_updated = NOW() WHERE id = :id";
            // $statement = $this->dbConnection->prepare($query);
            // $statement->bindParam(':id', $sessionId);
            // $result = $statement->execute();
            // $statement->closeCursor();

            return DB::table($this->table)->where('id', $sessionId)->update(['session_last_updated' => 'now']);
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->updateTimestamp($sessionId, $sessionData); // was retrying gc() with wrong args
        }
    }

    private function handle_exception(PDOException $exception) {        
        if ( strpos($exception->getMessage(), "doesn't exist") !== false ) {
        //   $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
        //     `id` CHAR(32) NOT NULL,
        //     `session_data` BLOB,
        //     `session_last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        //     PRIMARY KEY (`id`)
        //   ) Engine = INNODB DEFAULT CHARSET utf8";

        Schema::create($this->table, function (Blueprint $table) {
            $table->string('id', 32)->notNullable()->primary();
            $table->blob('session_data');
            $table->timestamp('session_last_updated')->notNullable()->useCurrent()->useCurrentOnUpdate();
        });

        //   $this->dbConnection->exec($sql);
        }
        else {
            throw $exception;
        }
      }
}