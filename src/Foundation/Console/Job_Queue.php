<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use PDO;
use Exception;
use SQLite3;

class Job_Queue
{

	const QUEUE_TYPE_MYSQL = 'mysql';
	const QUEUE_TYPE_SQLITE = 'sqlite';
	const QUEUE_TYPE_PGSQL = 'pgsql';
	const QUEUE_TYPE_SQLSRV = 'sqlsrv';
	const QUEUE_TYPE_BEANSTALKD = 'beanstalkd';

	/** Every queue type backed by a SQL table, as opposed to a queue daemon. */
	const SQL_QUEUE_TYPES = [
		self::QUEUE_TYPE_MYSQL,
		self::QUEUE_TYPE_SQLITE,
		self::QUEUE_TYPE_PGSQL,
		self::QUEUE_TYPE_SQLSRV,
	];

	/**
	 * The type of job queue to use
	 *
	 * @var string
	 */
	protected $queue_type;

	/**
	 * Generic Connection Holder
	 *
	 * @var mixed
	 */
	protected PDO|SQLite3 $connection; 

	/**
	 * Name of the pipeline to work with
	 *
	 * @var string
	 */
	protected $pipeline; 

	/**
	 * Set of options to pass into the job queue
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Array to store various variables and checks
	 *
	 * @var array
	 */
	protected static $cache = [];

	/**
	 * The construct
	 *
	 * @param string $queue_type - self::QUEUE_TYPE_MYSQL is default
	 * @param array $options
	 */
	public function __construct(string $queue_type = self::QUEUE_TYPE_MYSQL, array $options = []) {

		if(empty($queue_type)) {
			throw new Exception('Queue Type not defined (or defined properly...)');
		}

		// An unrecognised type used to be accepted and then match no arm of any switch, so every
		// call was a silent no-op: jobs vanished on dispatch and the worker found an empty queue.
		$known = array_merge(self::SQL_QUEUE_TYPES, [self::QUEUE_TYPE_BEANSTALKD]);
		if(!in_array($queue_type, $known, true)) {
			throw new Exception("Unsupported queue type '{$queue_type}'; expected one of: ".implode(', ', $known).'.');
		}

		$this->queue_type = $queue_type;

		// set defaults
		$this->setOptions($options + [
			'mysql' => [
				'use_compression' => true
			]
		]);
	}

	/**
	 * Sets the options from the construct (or otherwise...)
	 *
	 * @param array $options
	 * @return void
	 */
	public function setOptions(array $options = []): void {
		$this->options = $options;
	}

	/**
	 * Returns the options set in the construct (or by set options)
	 *
	 * @return array
	 */
	public function getOptions(): array {
		return $this->options;
	}

	/**
	 * Sets the pipeline to use
	 *
	 * @param string $pipeline
	 * @return void
	 */
	public function setPipeline(string $pipeline): void {
		$this->pipeline = $pipeline;
	}

	/**
	 * Gets the currently used pipeline.
	 *
	 * @return string
	 */
	public function getPipeline(): string {
		return $this->pipeline;
	}

	/**
	 * Gets the cache
	 *
	 * @return array
	 */
	public function getCache(): array {
		return self::$cache;
	}

	/**
	 * Drains the internal cache
	 *
	 * @return void
	 */
	public function flushCache(): void {
		self::$cache = [];
	}

	/**
	 * Adds a generic connection for the queue type selected
	 *
	 * @param mixed $db
	 * @return void
	 */
	public function addQueueConnection($connection) {
		$this->connection = $connection;
	}

	/**
	 * This method is for adding/putting jobs into a queue
	 *
	 * @param string $pipeline
	 * @return Job_Queue
	 */
	public function selectPipeline(string $pipeline): Job_Queue {
		$this->pipeline = $pipeline;
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				// do nothing
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$this->connection->useTube($this->pipeline);
			break;
		}

		return $this;
	}

	/**
	 * This method is for workers that are processing jobs
	 *
	 * @param string $pipeline
	 * @return void
	 */
	public function watchPipeline(string $pipeline) {
		$this->pipeline = $pipeline;
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				// do nothing
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$this->connection->watch($this->pipeline)->ignore('default');
			break;
		}
	}

	/**
	 * Runs necessary checks to make sure the queue will work properly
	 *
	 * @return void
	 */
	protected function runPreChecks() {

		if(empty($this->pipeline)) {
			throw new Exception('Pipeline/Tube needs to be defined first');
		}

		if(empty($this->connection)) {
			throw new Exception('You need to add the connection for this queue type via the addQueueConnection() method first.');
		}

		if($this->isSqlQueueType()) {
			$this->checkAndIfNecessaryCreateJobQueueTable();
		}

	}

	/**
	 * Adds a new job to the job queue
	 *
	 * @param string $payload
	 * @param integer $delay
	 * @param integer $priority
	 * @return void
	 */
	public function addJob(string $payload, int $delay = 0, int $priority = 1024, int $time_to_retry = 60) {
		$this->runPreChecks();

		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				$table_name = $this->getSqlTableName()[0];
				$field_value = $this->usesCompression() ? 'COMPRESS(?)' : '?';
				$delay_date_time = gmdate('Y-m-d H:i:s', strtotime('now +'.$delay.' seconds UTC'));
				$added_dt = gmdate('Y-m-d H:i:s');
				$time_to_retry_dt = gmdate('Y-m-d H:i:s', strtotime('now +'.$time_to_retry.' seconds UTC'));
				$delay_column = $this->quoteDatabaseKey('delay', false);
				$statement = $this->connection->prepare("INSERT INTO {$table_name} (pipeline, payload, {$delay_column}, added_dt, send_dt, priority, is_reserved, reserved_dt, is_buried, attempts, time_to_retry_dt) VALUES (?, {$field_value}, ?, ?, ?, ?, 0, NULL, 0, 0, ?)");
				$statement->execute([
					$this->pipeline,
					$payload,
                    $delay,
					$added_dt,
					$delay_date_time,
					$priority,
					$time_to_retry_dt
				]);
				
				$job = [];
				$job['id'] = intval($this->connection->lastInsertId());
				$job['payload'] = $payload;
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$job = $this->connection->put($payload, $priority, $delay, $time_to_retry);

			break;
		}

		return $job;
	}

	/**
	 * Gets the next available job and reserves it. Sorted by delay and priority
	 *
	 * @return array 
	 */
	public function getNextJobAndReserve() {
		$this->runPreChecks();
		$job = [];
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				if ($this->connection instanceof PDO || $this->connection instanceof SQLite3) {
					// Selecting a candidate and claiming it are two statements, so between them
					// another worker can take the same row. The claim used to be an unconditional
					// `WHERE id = ?`, so BOTH workers succeeded and the job ran twice. It is now
					// conditional on the row still being free, and losing means looking for the
					// next candidate rather than running a job we do not hold.
					//
					// Bounded, because losing every race is indistinguishable from an empty
					// pipeline after a while and a worker must not spin on a busy queue.
					$attempts_left = 10;

					while ($attempts_left-- > 0) {
						$stale_before = gmdate('Y-m-d H:i:s', strtotime('now -1 minutes UTC'));
						$candidate = $this->selectPendingCandidate($stale_before);

						if ($candidate === null) {
							break;
						}

						if ($this->claimPendingJob($candidate['id'], $stale_before, $candidate['delay'])) {
							$job = [
								'id' => $candidate['id'],
								'attempts' => $candidate['attempts'] + 1,
								'payload' => $candidate['payload'],
							];
							break;
						}
					}
				}
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$job = $this->connection->reserve();
			break;
		}

		return $job;
	}

	/**
	 * The next job this worker could run, or null when the pipeline is idle.
	 *
	 * Selecting is deliberately separate from claiming: they are two statements against a table
	 * other workers are also reading, and keeping them apart is what makes the gap between them
	 * visible — both in the code and to a test, which can reserve the row in between and assert
	 * that the claim then fails.
	 */
	protected function selectPendingCandidate(string $stale_before): ?array {
		$table_name = $this->getSqlTableName()[0];
		$field = $this->usesCompression() ? 'UNCOMPRESS(payload) payload' : 'payload';
		$delay_column = $this->quoteDatabaseKey('delay', false);
		$send_dt = gmdate('Y-m-d H:i:s');

		$statement = $this->connection->prepare("SELECT id, {$field}, {$delay_column}, added_dt, send_dt, priority, is_reserved, reserved_dt, is_buried, buried_dt, attempts
			FROM {$table_name}
			WHERE pipeline = ? AND send_dt <= ? AND is_buried = 0 AND (is_reserved = 0 OR (is_reserved = 1 AND reserved_dt <= ? ) ) AND (attempts = 0 OR (attempts >= 1 AND time_to_retry_dt <= ?) )
			ORDER BY priority ASC LIMIT 1");
		$statement->execute([ $this->pipeline, $send_dt, $stale_before, $send_dt ]);
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);

		if (!count($result)) {
			return null;
		}

		return [
			'id' => intval($result[0]['id']),
			'attempts' => intval($result[0]['attempts']),
			'delay' => intval($result[0]['delay']),
			'payload' => $result[0]['payload'],
		];
	}

	/**
	 * Take ownership of a pending job. False means another worker got there first.
	 *
	 * The WHERE repeats the predicate the select used, so the row has to still be free — or still
	 * stale, for a reservation nobody renewed — at the moment of writing.
	 */
	protected function claimPendingJob(int $id, string $stale_before, int $delay): bool {
		$table_name = $this->getSqlTableName()[0];
		$reserved_dt = gmdate('Y-m-d H:i:s');
		$retry_time = gmdate('Y-m-d H:i:s', strtotime("now +$delay seconds UTC"));

		$statement = $this->connection->prepare("UPDATE {$table_name}
			SET is_reserved = 1, reserved_dt = ?, time_to_retry_dt = ?, attempts = attempts + 1
			WHERE id = ? AND is_buried = 0 AND (is_reserved = 0 OR (is_reserved = 1 AND reserved_dt <= ?) )");
		$statement->execute([ $reserved_dt, $retry_time, $id, $stale_before ]);

		return $statement->rowCount() === 1;
	}

	/**
	 * Gets the next available job. Sorted by delay and priority
	 * Requires `selectPipeline()` to be set.
	 *
	 * @return mixed 
	 */
	public function getNextBuriedJob() {
		$this->runPreChecks();
		$job = [];
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				if ($this->connection instanceof PDO || $this->connection instanceof SQLite3) {
					$attempts_left = 10;

					while ($attempts_left-- > 0) {
						$candidate = $this->selectBuriedCandidate();

						if ($candidate === null) {
							break;
						}

						if ($this->claimBuriedJob($candidate['id'], $candidate['delay'])) {
							$job = [
								'id' => $candidate['id'],
								'attempts' => $candidate['attempts'] + 1,
								'payload' => $candidate['payload'],
							];
							break;
						}
					}
				}
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$job = $this->connection->peekBuried();
			break;
		}

		return $job;
	}

	/** @see selectPendingCandidate() — the same split, for a buried job that is due to retry. */
	protected function selectBuriedCandidate(): ?array {
		$table_name = $this->getSqlTableName()[0];
		$field = $this->usesCompression() ? 'UNCOMPRESS(payload) payload' : 'payload';
		$delay_column = $this->quoteDatabaseKey('delay', false);
		$send_dt = gmdate('Y-m-d H:i:s');

		$statement = $this->connection->prepare("SELECT id, {$field}, {$delay_column}, added_dt, send_dt, priority, attempts, is_reserved, reserved_dt, is_buried, buried_dt
			FROM {$table_name}
			WHERE pipeline = ? AND send_dt <= ? AND is_buried = 1 AND (attempts >= 1 AND time_to_retry_dt <= ?)
			ORDER BY priority ASC LIMIT 1");
		$statement->execute([ $this->pipeline, $send_dt, $send_dt ]);
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);

		if (!count($result)) {
			return null;
		}

		return [
			'id' => intval($result[0]['id']),
			'attempts' => intval($result[0]['attempts']),
			'delay' => intval($result[0]['delay']),
			'payload' => $result[0]['payload'],
		];
	}

	/**
	 * Take ownership of a buried job that is due. False means another worker got there first.
	 *
	 * Pushing time_to_retry_dt forward is what hides this row from other workers, so the write has
	 * to be conditional on it still being due — unconditional, two workers both retried the same
	 * buried job.
	 */
	protected function claimBuriedJob(int $id, int $delay): bool {
		$table_name = $this->getSqlTableName()[0];
		$send_dt = gmdate('Y-m-d H:i:s');
		$retry_time = gmdate('Y-m-d H:i:s', strtotime("now +$delay seconds UTC"));

		$statement = $this->connection->prepare("UPDATE {$table_name}
			SET attempts = attempts + 1, time_to_retry_dt = ?
			WHERE id = ? AND is_buried = 1 AND time_to_retry_dt <= ?");
		$statement->execute([ $retry_time, $id, $send_dt ]);

		return $statement->rowCount() === 1;
	}

	/**
	 * Deletes a job
	 *
	 * @param mixed $job
	 * @return void
	 */
	public function deleteJob($job): void {
		$this->runPreChecks();
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				$table_name = $this->getSqlTableName()[0];
				$statement = $this->connection->prepare("DELETE FROM {$table_name} WHERE id = ?");
				$statement->execute([ $job['id'] ]);
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$this->connection->delete($job);
			break;
		}
	}

	/**
	 * Buries (hides) a job
	 *
	 * @param mixed $job
	 * @param int $time_to_retry
	 * 
	 * @return void
	 */
	public function buryJob($job, int $time_to_retry): void {
		$this->runPreChecks();
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				$table_name = $this->getSqlTableName()[0];
				$field_value = $this->usesCompression() ? 'COMPRESS(?)' : '?';
				$buried_dt = gmdate('Y-m-d H:i:s');
				$time_to_retry_dt = gmdate('Y-m-d H:i:s', strtotime('now +'.$time_to_retry.' seconds UTC'));
				$statement = $this->connection->prepare("UPDATE {$table_name} SET payload = {$field_value}, is_buried = 1, buried_dt = ?, time_to_retry_dt = ?, is_reserved = 0, reserved_dt = NULL WHERE id = ?");
				$statement->execute([$job['payload'], $buried_dt, $time_to_retry_dt, $job['id'] ]);
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$this->connection->bury($job);
			break;
		}
	}

	
	/**
	 * Fail a job (to failed job table)
	 *
	 * @param mixed $job
	 * @return int
	 */
	public function failJob($job): int {
		$this->runPreChecks();
		// Declared up front: the beanstalkd arm never assigned it, so the `return $id` below was an
		// undefined variable on that path, and the declared `: int` return turned that into a
		// TypeError rather than the 0 it reads as.
		$id = 0;
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				$table_name = $this->getSqlTableName()[1];
				$field_value = $this->usesCompression() ? 'COMPRESS(?)' : '?';
				$added_dt = gmdate('Y-m-d H:i:s');
				$statement = $this->connection->prepare("INSERT INTO {$table_name} (pipeline, payload, added_dt, attempts) VALUES (?, {$field_value}, ?, ?)");
				$statement->execute([
					$this->pipeline,
					$job['payload'],
					$added_dt,
					$job['attempts'] ?? 0
				]);
				
				$id = intval($this->connection->lastInsertId());
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$this->connection->bury($job);
			break;
		}
		return $id;
	}

	/**
	 * Kicks (releases, unburies) job
	 *
	 * @param mixed $job
	 * @return void
	 */
	public function kickJob($job): void {
		$this->runPreChecks();
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				// Was `$table_name[0] = $this->getSqlTableName();` — the subscript on the wrong
				// side, so $table_name became an ARRAY and interpolated as the string "Array".
				// Every call raised "Array to string conversion" and ran `UPDATE Array SET …`.
				$table_name = $this->getSqlTableName()[0];
				$statement = $this->connection->prepare("UPDATE {$table_name} SET is_buried = 0, buried_dt = NULL WHERE id = ?");
				$statement->execute([ $job['id'] ]);
			break;

			case self::QUEUE_TYPE_BEANSTALKD:
				$this->connection->kickJob($job);
			break;
		}
	}

	/**
	 * Gets the job id from given job
	 *
	 * @param mixed $job
	 * @return mixed
	 */
	public function getJobId($job) {
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				return $job['id'];

			case self::QUEUE_TYPE_BEANSTALKD:
				return $job->getId();
			break;
		}
	}

	/**
	 * Gets the job payload from given job
	 *
	 * @param mixed $job
	 * @return string
	 */
	public function getJobPayload($job): string {
		switch($this->queue_type) {
			case self::QUEUE_TYPE_MYSQL:
			case self::QUEUE_TYPE_SQLITE:
			case self::QUEUE_TYPE_PGSQL:
			case self::QUEUE_TYPE_SQLSRV:
				return $job['payload'];

			case self::QUEUE_TYPE_BEANSTALKD:
				return $job->getData();
			break;
		}
		return '';
	}

	/**
	*	Return quoted identifier name
	*	@return string
	*	@param $key
	*	@param bool $split
	 **/
	/**
	 * Quote an identifier for the connection's dialect.
	 *
	 * This matched its delimiter table against $queue_type, which can only ever hold the handful of
	 * values declared above — so the pgsql and sqlsrv rows in it were unreachable, and an
	 * unmatched type indexed an empty string. It now asks the connection what it is.
	 */
	protected function quoteDatabaseKey(string $key, bool $split = true): string {
		$delims = [
			self::QUEUE_TYPE_MYSQL  => '``',
			self::QUEUE_TYPE_SQLITE => '``',
			self::QUEUE_TYPE_PGSQL  => '""',
			self::QUEUE_TYPE_SQLSRV => '[]',
		];

		// Double quotes are the SQL standard, so an unrecognised driver gets the portable form
		// rather than a malformed one.
		$use = $delims[$this->sqlDriver()] ?? '""';

		return $use[0].($split ? implode($use[1].'.'.$use[0],explode('.',$key))
			: $key).$use[1];
	}

	/**
	 * The SQL driver actually behind $connection.
	 *
	 * Asked of the handle rather than inferred from the label passed to the constructor, because
	 * the two can disagree: an application may hand its own PDO to addQueueConnection(), and the
	 * dialect that matters for quoting and for COMPRESS() is the one the connection really speaks.
	 * Falls back to the declared type when the handle is absent or is a SQLite3 object, which has
	 * no equivalent question to ask.
	 */
	protected function sqlDriver(): string {
		if(isset($this->connection) && $this->connection instanceof PDO) {
			return (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
		}

		return $this->queue_type;
	}

	/**
	 * Whether payloads are stored through MySQL's COMPRESS()/UNCOMPRESS().
	 *
	 * Keyed on the REAL driver: only MySQL has those functions. This is also the one setting that
	 * cannot be changed for a queue with rows already in it — a payload written compressed is only
	 * readable back through UNCOMPRESS(), so flipping it strands every job already queued.
	 */
	protected function usesCompression(): bool {
		return $this->sqlDriver() === self::QUEUE_TYPE_MYSQL
			&& ($this->options[self::QUEUE_TYPE_MYSQL]['use_compression'] ?? false) === true;
	}

	/** Whether this queue is backed by a SQL table rather than a queue daemon. */
	public function isSqlQueueType(): bool {
		return in_array($this->queue_type, self::SQL_QUEUE_TYPES, true);
	}

	public function isMysqlQueueType(): bool {
		return $this->queue_type === self::QUEUE_TYPE_MYSQL;
	}

	public function isSqliteQueueType(): bool {
		return $this->queue_type === self::QUEUE_TYPE_SQLITE;
	}

	public function isBeanstalkdQueueType(): bool {
		return $this->queue_type === self::QUEUE_TYPE_BEANSTALKD;
	}

	protected function getSqlTableName(): array {
		// Keyed by the queue type generally, rather than by two hard-coded arms. Adding a driver
		// used to mean adding another `else if` here or silently falling back to the defaults.
		$options = $this->options[$this->queue_type] ?? [];

		$table_name = $options['table_name'] ?? 'job_queue_jobs';
		$failed_table_name = $options['failed_table_name'] ?? 'failed_jobs';

		return [$this->quoteDatabaseKey($table_name), $this->quoteDatabaseKey($failed_table_name)];
	}

	/** The table names as written in config, unquoted — for existence checks, which compare text. */
	protected function getRawSqlTableNames(): array {
		$options = $this->options[$this->queue_type] ?? [];

		return [
			$options['table_name'] ?? 'job_queue_jobs',
			$options['failed_table_name'] ?? 'failed_jobs',
		];
	}

	/**
	 * Does this table already exist?
	 *
	 * The MySQL arm used `SHOW TABLES LIKE`, whose argument is a LIKE PATTERN — a table name
	 * containing `_` (both defaults do) matches any single character there, so it could report a
	 * different table as present. information_schema compares the name exactly, and every driver
	 * here has it except SQLite.
	 */
	protected function sqlTableExists(string $table_name): bool {
		if($this->sqlDriver() === self::QUEUE_TYPE_SQLITE) {
			$statement = $this->connection->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
		} else {
			$statement = $this->connection->prepare("SELECT table_name FROM information_schema.tables WHERE table_name = ?");
		}

		$statement->execute([ $table_name ]);

		return count($statement->fetchAll(PDO::FETCH_ASSOC)) > 0;
	}

	/**
	 * DDL for the jobs table, in the connection's dialect.
	 *
	 * This shipped as one MySQL statement used for every driver — `int(11) AUTO_INCREMENT`,
	 * `tinyint(1)`, inline `KEY … (pipeline(75))`, `COMMENT` — none of which SQLite accepts, so the
	 * SQLite queue type could never actually create its own table despite being a declared option.
	 *
	 * @return string[] the CREATE, followed by any index statements the dialect needs separately
	 */
	protected function jobsTableDdl(string $table_name): array {
		$payload_type = $this->usesCompression() ? 'longblob' : 'longtext';

		return match($this->sqlDriver()) {
			self::QUEUE_TYPE_MYSQL => ["CREATE TABLE IF NOT EXISTS {$table_name} (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`pipeline` varchar(500) NOT NULL,
				`payload` {$payload_type} NOT NULL,
				`delay` smallint(8) UNSIGNED NOT NULL,
				`added_dt` datetime NOT NULL COMMENT 'In UTC',
				`send_dt` datetime NOT NULL COMMENT 'In UTC',
				`priority` int(11) NOT NULL,
				`is_reserved` tinyint(1) NOT NULL,
				`reserved_dt` datetime NULL COMMENT 'In UTC',
				`is_buried` tinyint(1) NOT NULL,
				`buried_dt` datetime NULL COMMENT 'In UTC',
				`attempts` tinyint(4) UNSIGNED NOT NULL,
				`time_to_retry_dt` datetime NULL,
				PRIMARY KEY (`id`),
				KEY `pipeline_send_dt_is_buried_is_reserved` (`pipeline`(75), `send_dt`, `is_buried`, `is_reserved`)
			);"],

			self::QUEUE_TYPE_SQLITE => ["CREATE TABLE IF NOT EXISTS {$table_name} (
				`id` INTEGER PRIMARY KEY AUTOINCREMENT,
				`pipeline` TEXT NOT NULL,
				`payload` BLOB NOT NULL,
				`delay` INTEGER NOT NULL,
				`added_dt` TEXT NOT NULL,
				`send_dt` TEXT NOT NULL,
				`priority` INTEGER NOT NULL,
				`is_reserved` INTEGER NOT NULL,
				`reserved_dt` TEXT NULL,
				`is_buried` INTEGER NOT NULL,
				`buried_dt` TEXT NULL,
				`attempts` INTEGER NOT NULL,
				`time_to_retry_dt` TEXT NULL
			);", "CREATE INDEX IF NOT EXISTS `job_queue_pipeline_idx` ON {$table_name} (`pipeline`, `send_dt`, `is_buried`, `is_reserved`);"],

			self::QUEUE_TYPE_PGSQL => ["CREATE TABLE IF NOT EXISTS {$table_name} (
				\"id\" SERIAL PRIMARY KEY,
				\"pipeline\" VARCHAR(500) NOT NULL,
				\"payload\" TEXT NOT NULL,
				\"delay\" INTEGER NOT NULL,
				\"added_dt\" TIMESTAMP NOT NULL,
				\"send_dt\" TIMESTAMP NOT NULL,
				\"priority\" INTEGER NOT NULL,
				\"is_reserved\" SMALLINT NOT NULL,
				\"reserved_dt\" TIMESTAMP NULL,
				\"is_buried\" SMALLINT NOT NULL,
				\"buried_dt\" TIMESTAMP NULL,
				\"attempts\" SMALLINT NOT NULL,
				\"time_to_retry_dt\" TIMESTAMP NULL
			);", "CREATE INDEX IF NOT EXISTS job_queue_pipeline_idx ON {$table_name} (\"pipeline\", \"send_dt\", \"is_buried\", \"is_reserved\");"],

			self::QUEUE_TYPE_SQLSRV => ["IF OBJECT_ID(N'{$table_name}', N'U') IS NULL CREATE TABLE {$table_name} (
				[id] INT IDENTITY(1,1) PRIMARY KEY,
				[pipeline] NVARCHAR(500) NOT NULL,
				[payload] NVARCHAR(MAX) NOT NULL,
				[delay] INT NOT NULL,
				[added_dt] DATETIME2 NOT NULL,
				[send_dt] DATETIME2 NOT NULL,
				[priority] INT NOT NULL,
				[is_reserved] TINYINT NOT NULL,
				[reserved_dt] DATETIME2 NULL,
				[is_buried] TINYINT NOT NULL,
				[buried_dt] DATETIME2 NULL,
				[attempts] TINYINT NOT NULL,
				[time_to_retry_dt] DATETIME2 NULL
			);"],

			default => throw new Exception("No job-queue schema is defined for driver '{$this->sqlDriver()}'."),
		};
	}

	/** @see jobsTableDdl() — the failed-jobs table, which only ever stores what could not run. */
	protected function failedJobsTableDdl(string $table_name): array {
		$payload_type = $this->usesCompression() ? 'longblob' : 'longtext';

		return match($this->sqlDriver()) {
			self::QUEUE_TYPE_MYSQL => ["CREATE TABLE IF NOT EXISTS {$table_name} (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`pipeline` varchar(500) NOT NULL,
				`payload` {$payload_type} NOT NULL,
				`added_dt` datetime NOT NULL COMMENT 'In UTC',
				`attempts` tinyint(4) UNSIGNED NOT NULL,
				PRIMARY KEY (`id`),
				KEY `pipeline` (`pipeline`(75))
			);"],

			self::QUEUE_TYPE_SQLITE => ["CREATE TABLE IF NOT EXISTS {$table_name} (
				`id` INTEGER PRIMARY KEY AUTOINCREMENT,
				`pipeline` TEXT NOT NULL,
				`payload` BLOB NOT NULL,
				`added_dt` TEXT NOT NULL,
				`attempts` INTEGER NOT NULL
			);"],

			self::QUEUE_TYPE_PGSQL => ["CREATE TABLE IF NOT EXISTS {$table_name} (
				\"id\" SERIAL PRIMARY KEY,
				\"pipeline\" VARCHAR(500) NOT NULL,
				\"payload\" TEXT NOT NULL,
				\"added_dt\" TIMESTAMP NOT NULL,
				\"attempts\" SMALLINT NOT NULL
			);"],

			self::QUEUE_TYPE_SQLSRV => ["IF OBJECT_ID(N'{$table_name}', N'U') IS NULL CREATE TABLE {$table_name} (
				[id] INT IDENTITY(1,1) PRIMARY KEY,
				[pipeline] NVARCHAR(500) NOT NULL,
				[payload] NVARCHAR(MAX) NOT NULL,
				[added_dt] DATETIME2 NOT NULL,
				[attempts] TINYINT NOT NULL
			);"],

			default => throw new Exception("No job-queue schema is defined for driver '{$this->sqlDriver()}'."),
		};
	}

	protected function checkAndIfNecessaryCreateJobQueueTable(): void {
		$raw_names = $this->getRawSqlTableNames();
		$quoted_names = $this->getSqlTableName();

		// Keyed by driver and table names. A single global flag meant the FIRST queue to run
		// marked "checked" for every queue afterwards, so a second queue configured with a
		// different table_name never had its tables created and failed on first use.
		$cache_key = 'job-queue-table-check:'.$this->sqlDriver().':'.implode(',', $raw_names);

		if(isset(self::$cache[$cache_key])) {
			return;
		}

		foreach ($raw_names as $index => $raw_name) {
			if($this->sqlTableExists($raw_name)) {
				continue;
			}

			$statements = $index < 1
				? $this->jobsTableDdl($quoted_names[$index])
				: $this->failedJobsTableDdl($quoted_names[$index]);

			foreach ($statements as $statement) {
				$this->connection->exec($statement);
			}
		}

		self::$cache[$cache_key] = true;
	}
}
