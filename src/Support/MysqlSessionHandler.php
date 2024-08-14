<?php

namespace Basttyy\FxDataServer\libs;

use Basttyy\FxDataServer\libs\Helpers\Stringable;
use PDO;
use PDOException;
use SessionHandler;
use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class MysqlSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private $table;
    private $dbConnection;

    public function __construct()
    {
        $this->table = "session";
        $dbname = env('DB_NAME');
        $dbhost = env('DB_HOST');
        $dbadapter = env('DB_ADAPTER');
        $this->dbConnection = new PDO("$dbadapter:host=$dbhost;dbname=$dbname", env('DB_USER'), env('DB_PASS'));
    }
    public function open($sessionSavePath, $sessionName): bool
    {
        $this->table = $sessionName;
        return true;
    }
    public function close(): bool{
        $this->table = 'session';
        return true;
    }
    public function destroy($sessionId): bool
    {
        try {
            $query = "DELETE FROM `{$this->table}` WHERE id = :id";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $sessionId);
            $result = $statement->execute();
            $statement->closeCursor();
    
            return $result;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->destroy($sessionId);
        }
    }
    public function gc($maximumLifetime): int|false
    {
        try {
            $query = "DELETE FROM `{$this->table}` WHERE session_last_updated < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':max_lifetime', $maxLifetime, PDO::PARAM_INT);
            $result = $statement->execute();
            $statement->closeCursor();

            return $result;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->gc($maximumLifetime);
        }
    }
    public function read($sessionId): string
    {
        try {
            $query = "SELECT session_data FROM `{$this->table}` WHERE id = :id";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $sessionId);
            $statement->execute();
            $sessionData = $statement->fetchColumn();
            $statement->closeCursor();

            return $sessionData ?: '';
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->read($sessionId);
        }
    }
    public function write($sessionId, $sessionData): bool
    {
        try {
            $query = "REPLACE INTO `{$this->table}` (id, session_data, session_last_updated) VALUES (:id, :session_data, NOW())";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $sessionId);
            $statement->bindParam(':session_data', $sessionData);
            $result = $statement->execute();
            $statement->closeCursor();

            return $result;
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
            $query = "SELECT COUNT(*) FROM `{$this->table}` WHERE id = :id";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $sessionId);
            $statement->execute();
            $count = $statement->fetchColumn();
            $statement->closeCursor();

            return $count > 0;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->validateId($sessionId);
        }
    }
    public function updateTimestamp($sessionId, $sessionData): bool
    {
        try {
            $query = "UPDATE `{$this->table}` SET session_last_updated = NOW() WHERE id = :id";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $sessionId);
            $result = $statement->execute();
            $statement->closeCursor();

            return $result;
        } catch (PDOException $ex) {
            $this->handle_exception($ex);
            return $this->gc($sessionId, $sessionData);
        }
    }

    private function handle_exception(PDOException $exception) {        
        if ( strpos($exception->getMessage(), "doesn't exist") !== false ) {
          $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` CHAR(32) NOT NULL,
            `session_data` BLOB,
            `session_last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
          ) Engine = INNODB DEFAULT CHARSET utf8";

          $this->dbConnection->exec($sql);
        }
        else {
            throw $exception;
        }
      }
}