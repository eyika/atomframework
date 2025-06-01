<?php
namespace Eyika\Atom\Framework\Support\Database;

use Exception;
use Eyika\Atom\Framework\Support\Arr;
use PDO;
use PDOException;

# an adaptation and improvement of https://github.com/mrcrypster/mysqly

class Connection {
  protected \PDO $db;
  protected $auth = [];
  protected $auth_file = '/var/lib/connection/.auth.php';
  protected $auto_create = false;

  protected $transaction_mode = false;

  protected array $config;
  protected string $driver;

  public function __construct(array $config)
  {
      $this->config = $config;
      // $this->connect();
  }
 
  /* Authentication */
  
  // public function auth($user, $pwd, $db, $host = 'localhost') {
  //   $this->auth = ['user' => $user, 'pwd' => $pwd, 'db' => $db, 'host' => $host];
  // }

  /**
   * Establish a database connection.
   */
  public function connect(): self
  {
      try {
          $this->driver = $this->config['default'];
          $dsn = $this->getDsn();
          $this->db = new PDO(
              $dsn,
              $this->config['connections'][$this->driver]['username'] ?? null,
              $this->config['connections'][$this->driver]['password'] ?? null,
              $this->getOptions()
          );

          // if ( !$this->auth ) {
          //   if (env('DB_USERNAME') && env('DB_PASSWORD') && env('DB_DATABASE')) {
          //     $this->auth(env('DB_USERNAME'), env('DB_PASSWORD'), env('DB_DATABASE'), env('DB_HOST'));
          //   } else {
          //     $this->auth = @include $this->auth_file;
          //   }
          // }
          // $this->db = new PDO('mysql:host=' . (isset($this->auth['host']) ? $this->auth['host'] : '127.0.0.1') . ';port=' . (isset($this->auth['port']) ? $this->auth['port'] : '3306') . ($this->auth['db'] ? ';dbname=' . $this->auth['db'] : ''), $this->auth['user'], $this->auth['pwd']);
          // $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
          throw new Exception("Database connection failed: " . $e->getMessage());
      }

      return $this;
  }

  /**
   * Get the Data Source Name (DSN) for the connection.
   */
  protected function getDsn(): string
  {
      $host = $this->config['connections'][$this->driver]['host'] ?? '127.0.0.1';
      $port = $this->config['connections'][$this->driver]['port'] ?? '3306';
      $database = $this->config['connections'][$this->driver]['database'];
      $charset = $this->config['connections'][$this->driver]['charset'] ?? 'utf8mb4';

      return match ($this->driver) {
          'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
          'sqlite' => "sqlite:{$database}",
          'pgsql' => "pgsql:host={$host};dbname={$database};",
          'sqlsrv' => "sqlsrv:Server={$host};Database={$database}",
          default => throw new Exception("Unsupported database driver: {$this->config['connections'][$this->driver]['driver']}"),
      };
  }

  /**
   * Get PDO options.
   */
  protected function getOptions(): array
  {
      return [
          PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES   => false,
      ];
  }

  /**
   * Get the underlying PDO instance.
   */
  public function getPdo(): PDO
  {
    return $this->db;
  }
  
  /**
   * Prepare conditions
   * 
   * @param string $k
   * @param string|array $v
   * @param string $or_and
   * @param array $where
   * @param bool &$incr_operator
   * @param string $_operator
   * 
   * @return void
   */
  private function condition($k, $v, &$where, &$bind, &$incr_operator, $or_and = '', $_operator = '=') {
    $or_and = $or_and !== '' ? ' ' . $or_and : $or_and;
    if ( is_array($v) ) {
      $in = [];

      foreach ( $v as $i => $sub_v ) {
        $in[] = ":{$k}_{$i}";
        $bind[":{$k}_{$i}"] = $sub_v;
      }

      $in = implode(', ', $in);
      $where[] = "`{$k}` {$_operator} ($in){$or_and}";
      $incr_operator = false;
    }
    else if ($v !== null && is_string($v) && str_contains(strtoupper($v), 'NULL')) {
      $v = strtoupper($v);
      $where[] = "`{$k}` {$v}{$or_and}";
      $incr_operator = false;
    }
    else if ($v !== null && is_string($v) && str_contains(strtolower($v), 'now')) {
      $where[] = "`{$k}` $_operator now(){$or_and}";
      $incr_operator = true;
    }
    else if ($_operator !== null && is_string($_operator) && str_contains(strtoupper($_operator), 'LIKE')) {
      $where[] = "LOWER({$k}) $_operator LOWER(:$k){$or_and}";
      $bind[":{$k}"] = "%$v%";
      $incr_operator = false;
    }
    else {
      $where[] = "`{$k}` $_operator :{$k}{$or_and}";
      $bind[":{$k}"] = $v;
      $incr_operator = true;
    }
  }
  
  /**
   * Bind values and return the values
   * 
   * @param array $data
   * @param array &$bind
   * @return string
   */
  private function values($data, &$bind = []) {
    foreach ( $data as $name => $value ) {
      if ( strpos($name, '.') ) {
        $path = explode('.', $name);
        $place_holder = implode('_', $path);
        $name = array_shift($path);
        $key = implode('.', $path);
        $values[] = "`{$name}` = JSON_SET({$name}, '$.{$key}', :{$place_holder}) ";
        $bind[":{$place_holder}"] = $value;
      }
      else if ($value !== null && is_string($value) && str_contains($value, 'now')) {
        $values[] = "`{$name}` = current_timestamp";
      } else if ($value === null or $value === 'null') {
        $values[] = "`{$name}` = :{$name}";
        $bind[":{$name}"] = null;
      }
      else {
        $values[] = "`{$name}` = :{$name}";
        $bind[":{$name}"] = $value;
      }
    }
    
    return implode(', ', $values);
  }
  
  
  /**
   * exec() General SQL query execution
   * 
   * @param string $sql
   * @param array $bind
   * 
   * @return \PDOStatement|false $statement
   */
  
  public function exec($sql, $bind = []) {
    if ( !isset($this->db) ) {
      $this->connect();
    }
    
    $params = [];
    if ( $bind ) foreach ( $bind as $k => $v ) {
      if ( is_array($v) ) {
        $in = [];
        foreach ( $v as $i => $sub_v ) {
          $in[] = ":{$k}_{$i}";
          $params[":{$k}_{$i}"] = $sub_v;
        }
        
        $sql = str_replace(':' . $k, implode(', ', $in), $sql);
      }
      else {
        if (!str_contains($k, ':')) {
          $k = ':'.$k;
        }
        $params[$k] = $v;
      }
    }

    // Manually replace the placeholders for LIMIT and OFFSET
    // if (isset($params[':limit']) && isset($params[':offset'])) {
    //   $sql = str_replace(':limit', (int)$params[':limit'], $sql);
    //   $sql = str_replace(':offset', (int)$params[':offset'], $sql);
    //   unset($params[':limit'], $params[':offset']);
    // }

    // echo $sql.PHP_EOL.PHP_EOL;
    
    $statement = $this->db->prepare($sql);
    foreach ($params as $key => &$value) {
      switch (true) {
          case is_int($value):
              $statement->bindParam($key, $value, PDO::PARAM_INT);
              break;
          case is_bool($value):
              $statement->bindParam($key, $value, PDO::PARAM_BOOL);
              break;
          case is_null($value):
              $statement->bindParam($key, $value, PDO::PARAM_NULL);
              break;
          case is_double($value):
          case is_float($value):
              // PDO doesn't have a specific type for floats, so we treat them as strings.
              $statement->bindParam($key, $value, PDO::PARAM_STR);
              break;
          case is_resource($value):
              // For BLOB data
              $statement->bindParam($key, $value, PDO::PARAM_LOB);
              break;
          default:
              $statement->bindParam($key, $value, PDO::PARAM_STR);
              break;
      }
    }
    $statement->execute($params);
    
    return $statement;
  }
  
  public function now() {
    return $this->fetch('SELECT NOW() now')[0]['now'];
  }
  
  /**
   * transaction() wrap a query in a callback
   * and run inside a transaction
   * 
   * @param callable $callback
   * @return void
  */
  
  public function transaction($callback) {
    $this->exec('START TRANSACTION');
    $result = $callback();
    $this->exec( $result ? 'COMMIT' : 'ROLLBACK' );
  }

  /**
   * start a transaction chain
   * subsequent queries will be executed in a transaction
   * 
   * @return void
   */
  public function beginTransaction() {
    $this->exec('START TRANSACTION');
    $this->transaction_mode = true;
  }
  
  /**
   * commit all changes made in the transaction chain
   * 
   * @return void
   */
  public function commit() {
    if ($this->transaction_mode)
      $this->exec('COMMIT');
  }
  
  /**
   * rollback all changes made in the transaction chain
   * 
   * @return void
   */
  public function rollback() {
    if ($this->transaction_mode)
      $this->exec('ROLLBACK');
  }

  /* Internal implementation */
  
  private function filter($filter, string|array $or_ands = "AND", string|array $operators = '=') {
    // if this method fails in the future, check fetch_cursor below to copy implementation
    $bind = []; $query = []; $incr_operator = false;
    if ( is_array($filter) ) {


      $i = 0; $j = 0; $len = count($filter);

      foreach ( $filter as $k => $v ) {
        if ($len - $j === 1)
          $or_and = '';
        else
          $or_and = is_array($or_ands) ? $or_ands[$j] ?? '' : "$or_ands";
        
        $operator = is_array($operators) ? $operators[$i] ?? '' : $operators;
        $this->condition($k, $v, $query, $bind, $incr_operator, $or_and, $operator);
        $j++;
        if ($incr_operator)
          $i++;
        $incr_operator = false;
      }
    }
    else {
      $this->condition('id', $filter, $query, $bind, $incr_operator);
    }
    
    return [$query ? ('WHERE ' . implode(' ', $query)) : '', $bind];
  }
  
  
  
  /**
   * fetch_cursor
   * 
   * @param string $sql_or_table
   * @param array $bind_or_filter
   * @param array|string $select_or_what
   * 
   * @return \PDOStatement|false
   */
  
  public function fetch_cursor($sql_or_table, $bind_or_filter = [], $select_what = '*', string|array $operators = "=", string|array $or_ands = "AND") {
    if ( strpos($sql_or_table, ' ') || (strpos($sql_or_table, 'SELECT ') === 0) ) {
      $sql = $sql_or_table;
      $bind = $bind_or_filter;
    }
    else {
      $select_str = is_array($select_what) ? implode(', ', $select_what) : $select_what;
      $sql = "SELECT {$select_str} FROM `{$sql_or_table}`";
      $order_limit_or_offset = '';
      
      if ( $bind_or_filter ) {
        if ( is_array($bind_or_filter) ) {
          $i = 0; $j = 0; $len = count($bind_or_filter);
          foreach (["ORDER BY", 'LIMIT', 'OFFSET'] as $_val) {
            if (array_key_exists($_val, $bind_or_filter)) {
              $len--;
            }
          }

          if (is_array($or_ands)) {
            while ($len <= count($or_ands))
              array_shift($or_ands);
          }

          foreach ( $bind_or_filter as $k => $v ) {
            if ( $k == 'ORDER BY' ) {
              $order_limit_or_offset .= " ORDER BY $v";
              // $j++;
              continue;
            }
            if ( in_array($k, ['LIMIT', 'OFFSET']) ) {
              $order_limit_or_offset .= " $k $v";
              // $j++;
              continue;
            }
            if ($len - $j <= 1)
              $or_and = '';
            else
              $or_and = is_array($or_ands) ? "$or_ands[$j]" : "$or_ands";

            if (is_array($operators)){
              $operator = is_string($v) ? (str_contains($v, 'NULL') ? '' : $operators[$i]) : $operators[$i];
            } else {
              $operator = $operators;
            }
            
            $this->condition($k, $v, $where, $bind, $incr_operator, $or_and, $operator);
            $j++;
            if ($incr_operator)
              $i++;
            $incr_operator = false;
          }

          if ( $where ?? null ) {
            $sql .= ' WHERE ' . implode( " ", $where);
          }
        }
        else {
          $sql .= ' WHERE id = :id';
          $bind[":id"] = $bind_or_filter;
        }
      }

      $sql .= $order_limit_or_offset;
    }
    // logger()->info($sql, isset($bind) &&  is_array($bind) ? $bind : []);

    $res = isset($bind) ? $this->exec($sql, $bind) : $this->exec($sql);
    return $res;
  }
  
  /**
   * fetch() fetch one or mor row from a table
   * 
   * @param string $sql_or_table
   * @param array $bind_or_filter
   * @param array|string $select_what
   * 
   * @return array
   */
  public function fetch($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND") {
    if (!$statement = $this->fetch_cursor($sql_or_table, $bind_or_filter, $select_what, $operators, $or_ands)) {
      return false;
    }
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ( $rows as $row ) {
      foreach ( $row as $k => $v ) {
        if ( strpos($k, '_json') ) {
          $row[$k] = json_decode($v, 1);
        }
      }
      
      $list[] = $row;
    }
    
    return $list;
  }

  //**
  //  * fetchOr() fetch one or mor row from a table
  //  * 
  //  * @param string $sql_or_table
  //  * @param array $bind_or_filter
  //  * @param array|string $select_what
  //  * 
  //  * @return array
  //  */
  // public function fetchOr($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
  // {
  //   return $this->fetch($sql_or_table, $bind_or_filter, $select_what, $operators, "OR");
  // }
  
  public function array($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
  {
    $rows = $this->fetch($sql_or_table, $bind_or_filter, $select_what, $operators, $or_ands);
    foreach ( $rows as $row ) $list[] = array_shift($row);
    return $list;
  }
  
  public function key_vals($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
  {
    $rows = $this->fetch($sql_or_table, $bind_or_filter, $select_what, $operators, $or_ands);
    foreach ( $rows as $row ) $list[array_shift($row)] = array_shift($row);
    return $list;
  }
  
  public function count($sql_or_table, $bind_or_filter = [], array|string $operators = '=', array|string $or_ands = "AND")
  {
    $_select_str = '*';
    $operators = Arr::wrap($operators);

    foreach ($operators as $key => $op) {
      if ($op && str_starts_with(strtoupper($op), 'DISTINCT ')) {
        $_select_str = Arr::pull($operators, $key) ?? '*';
        break;
      }
    }

    $rows = $this->fetch($sql_or_table, $bind_or_filter, "count($_select_str)", $operators, $or_ands);
    $val = array_shift($rows);
    return intval(array_shift($val));
  }
  
  public function random($table, $filter = [], string|array $operators = '=', string|array $or_ands = "AND")
  {
    list($where, $bind) = $this->filter($filter, $or_ands, $operators);
    $sql = 'SELECT * FROM `' . $table . '` ' . $where . ' ORDER BY RAND() LIMIT 1';
    return $this->fetch($sql, $bind)[0];
  }
  
  
  
  /* --- Atomic increments/decrements --- */
  
  public function increment($column, $table, $filters, string|array $operators = '=', string|array $or_ands = "AND", $step = 1) {
    $bind = $where = [];

    $i = 0; $j = 0; $len = count($filters);
    foreach ( $filters as $k => $v ) {
      if ($len - $j === 1)
        $or_and = '';
      else
        $or_and = is_array($or_ands) ? "$or_ands[$j]" : "$or_ands";

      $operator = is_array($operators) ? $operators[$i] : $operators;
      $this->condition($k, $v, $where, $bind, $incr_operator, $or_and, $operator);
      $j++;
      if ($incr_operator)
        $i++;
      $incr_operator = false;
    }

    if ( $where ) {
      $where_sql = 'WHERE ' . implode( " ", $where);
    }
    
    $step = intval($step);
    if ( $step > 0 ) {
      $step = "+{$step}";
    }
    
    return $this->exec("UPDATE `{$table}` SET `{$column}` = `{$column}` {$step} {$where_sql}", $bind);
  }
  
  public function decrement($column, $table, $filters) {
    return $this->increment($column, $table, $filters, -1);
  }
  
  
  
  /* --- Toggle column value --- */
  
  public function toggle($table, $filters, $column, $if, $then, string|array $operators = '=', string|array $or_ands = "AND") {
    $bind = $where = [];
    $i = 0; $j = 0; $len = count($filters);

    foreach ( $filters as $k => $v ) {    
      if ($len - $j === 1)
        $or_and = '';
      else
        $or_and = is_array($or_ands) ? "$or_ands[$j]" : "$or_ands";
      
      $operator = is_array($operators) ? $operators[$i] : $operators; 
      $this->condition($k, $v, $where, $bind, $incr_operator, $or_and, $operator);
      $j++;
      if ($incr_operator)
        $i++;
      $incr_operator = false;
    }

    if ( $where ) {
      $where_sql = 'WHERE ' . implode( " ", $where);
    }
    
    $bind[':if'] = $if;
    $bind[':v'] = $if;
    $bind[':then'] = $then;
    
    return $this->exec("UPDATE `{$table}` SET `{$column}` = IF(`{$column}` = :if, :then, :v) {$where_sql}", $bind);
  }
  
  
  
  /**
   * Data insertion
   * 
   * @param string $table
   * @param array $data
   * @param bool $ignore
   * @return int
   */
  
  public function insert($table, $data, $ignore = false) {
    $bind = [];
    $values = $this->values($data, $bind);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}` SET {$values}";

    // logger()->info($sql, $bind);
    try {
      $this->exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      $this->handle_insert_exception($e, $table, $data, $ignore);
    }
    
    return $this->db->lastInsertId();
  }
  
  /**
   * Insert to table or update value
   * 
   * @param string $table
   * @param array $data
   * @return void
   */
  public function insert_update($table, $data) {
    $bind = [];
    $values = $this->values($data, $bind);
    $sql = "INSERT INTO `{$table}` SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    // logger()->info($sql, $bind);
    
    try {
      $statement = $this->exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      $statement = $this->handle_insert_update_exception($e, $table, $data);
    }
  }
  
  public function multi_insert($table, $rows, $ignore = false) {
    $bind = [];
    
    $cols = array_keys($rows[0]);
    $cols = implode(',', $cols);
    
    foreach ( $rows as $r => $row ) {
      $values[] = '(' . implode(',', array_map(function($c) use($r) { return ":r{$r}{$c}"; }, range(0, count($row)-1))) . ')';
      
      $c = 0;
      foreach ( $row as $v ) {
        $bind[":r{$r}{$c}"] = $v;
        $c++;
      }
    }
    
    $values = implode(',', $values);
    
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}`({$cols}) VALUES{$values}";
    $this->exec($sql, $bind);
    return $this->db->lastInsertId();
  }
  
  
  
  /* Data export */
  
  public function export_csv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*', $operators = [], $or_and = "AND") {
    $cursor = $this->fetch_cursor($sql_or_table, $bind_or_filter, $select_what, $or_and, $operators);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row);
    }
    
    fclose($f);
  }
  
  public function export_tsv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*', $operators = [], $or_and = "AND", ) {
    $cursor = $this->fetch_cursor($sql_or_table, $bind_or_filter, $select_what, $or_and, $operators);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row, "\t");
    }
    
    fclose($f);
  }
  
  

  /* Data update */  

  public function update($table, $filter, $data, string|array $operators = '=', string|array $or_ands = "AND"): int {
    list($where, $bind) = $this->filter($filter, $or_ands, $operators);
    $values = $this->values($data, $bind);
    
    $sql = "UPDATE `{$table}` SET {$values} {$where}";
    
    try {
      // logger()->info($sql, is_array($bind) ? $bind : []);
      $statement = $this->exec($sql, $bind);
      return $statement->rowCount();
    }
    catch ( PDOException $e ) {
      return $this->handle_update_exception($e, $table, $filter, $data);
    }
  }
  
  public function remove($table, $filter, string|array $operators = '=', string|array $or_ands = "AND"): bool {
    list($where, $bind) = $this->filter($filter, $or_ands, $operators);
    if (!$statement = $this->exec("DELETE FROM `{$table}` " . $where, $bind)) {
      return false;
    }
    if ($statement->rowCount() < 1) {
      return false;
    }
    return true;
  }
  
  
  /**
   * Dynamic methods
   * 
   * Examples
   * 
   * **Get rows from table by parametric filters**
   * `Connection::{table}($filters)` e.g `Connection::{users}([ 'id' => 1, 'status' => 'active' ])`
   * 
   * **Get single row from table by "ID" column**
   * `Connection::{table}_($id)` e.g `Connection::{users}_(2)`
   * 
   * **Get single column value by paremetric filter**
   * `Connection::{table}_{column}($filters)` e.g `Connection::{users}_{gender}([ 'id' => 1, 'status' => 'active' ])`
   * 
   * **'min', 'max', 'avg', 'sum', 'group_concat', 'var_pop', 'stddev', 'bit_and', 'bit_or', 'bit_xor' column values**
   * ```php
   *    Connection::min_{column}();  //Get the minimun value of a column in a table
   *    Connection::max_{column}();  //Get the maximun value of a column in a table
   *    Connection::avg_{column}();  //Get the average of values of a column in a table
   *    Connection::sum_{column}();  //Get the sum of all values of a column in a table
   *    Connection::group_concat_{column}();  //Get the group_concat of values of a column in a table
   *    Connection::var_pop_{column}();  //Get the var_pop of values of a column in a table
   *    Connection::stddev_{column}();  //Get the standard deviation value of a column in a table
   *    Connection::bit_and_{column}();  //Get the maximun value of a column in a table
   *    // ...etc
   * 
   *   /// you can use 'DISTINCT' or 'distinct' keyword to make the result distinct ///
   *   Connection::sum_{column}('users', [], 'distinct');  //Get the sum of all distinct values of a column in a table
   *   Connection::sum_{column}('users', ['status' => 'active'], 'distinct');  //Get the sum of all values of a column in a table
   * 
   *   /// you can use perform a more refined filter ///
   *   Connection::sum_{column}('users', ['age' => '40', 'country' => 'nigeria'], ['<', '='], ['AND', 'AND'], 'distinct');  //Get the sum of all values of a column in a table
   * ```
   **/
  
  public static function __callStatic($name, $args) {
    $instance = new static(config('database')); // Create an instance of the class

    # get row or column from table
    if ( $args[0] && (count($args) == 1) && strpos($name, '_') ) {
      list($table, $col) = explode('_', $name);
      list($where, $bind) = $instance->filter($args[0]);
      $rows = $instance->fetch('SELECT ' . ($col ? "`{$col}`" : '*') . ' FROM `' . $table . '` ' . $where, $bind);
      if ( $rows ) {
        return $col ? $rows[0][$col] : $rows[0];
      }
    }
    
    # get aggregates by filters
    else if ( $args[0] && (count($args) == 2 || count($args) == 3) && strpos($name, '_') && in_array(strtolower(explode('_', $name)[0] ?? ''), ['min', 'max', 'avg', 'sum', 'group_concat', 'var_pop', 'stddev', 'bit_and', 'bit_or', 'bit_xor']) ) {
      list($agr, $col) = explode('_', $name);
      $table = $args[0];
      list($where, $bind) = $instance->filter($args[1]);
      $distinct = isset($args[2]) ? 'DISTINCT ' : '';
      $row = $instance->fetch('SELECT ' . $agr . "($distinct" . $col . ') FROM `' . $table . '` ' . $where, $bind)[0];
      return array_shift($row);
    }
    else if ( $args[0] && (count($args) > 1 || count($args) < 6) && strpos($name, '_') && in_array(strtolower(explode('_', $name)[0] ?? ''), ['min', 'max', 'avg', 'sum', 'group_concat', 'var_pop', 'stddev', 'bit_and', 'bit_or', 'bit_xor']) ) {
      list($agr, $col) = explode('_', $name);
      $table = $args[0];
      list($where, $bind) = $instance->filter($args[1], $args[3], $args[2]);
      $distinct = isset($args[4]) ? 'DISTINCT ' : '';
      $row = $instance->fetch('SELECT ' . $agr . "($distinct" . $col . ') FROM `' . $table . '` ' . $where, $bind)[0];
      return array_shift($row);
    }
    
    # get list of rows from table
    else if ( count($args) == 0 || count($args) == 1 ) {
      return $instance->fetch($name, $args[0] ?: []);
    }
    
    
    else {
      throw new PDOException("$name() method does not exist on the database service" );
    }
  }
  
  
  /**
   * Key-value storage table name
   * 
   * @param string $space
   * @return string
   */
  
  protected function key_value_table($space) {
    return '_kv_' . strtolower($space);
  }
  
  /**
   * get a value from a db storage
   * 
   * @param string $key
   * @param string $space
   * 
   * @return string|array|void
   */
  public function get($key, $space = 'default') {
    $table = $this->key_value_table($space);
    
    try {
      $values = $this->fetch($table, ['key' => $key], 'value');

      $value = empty($values) ? null : $values[0]['value'];

      return $value;
    }
    catch (PDOException $e) {
      // echo $e->getMessage().PHP_EOL;
      return;
    }
  }
  
  /**
   * set a value in a db storage
   * 
   * @param string $key
   * @param string|array $value
   * @param string $space
   * 
   * @return void
   */
  public function set($key, $value, $space = 'default') {
    // echo "called set";
    $table = $this->key_value_table($space);
    
    try {
      $this->insert_update($table, ['key' => $key, 'value' => $value]);
    }
    catch (PDOException $e) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        $this->exec("CREATE TABLE `{$table}`(`key` varchar(128) PRIMARY KEY, `value` TEXT) ENGINE = INNODB");
        $this->insert($table, ['key' => $key, 'value' => $value]);
      }
    }
  }
  
  /**
   * unset a key-value in a db storage
   * 
   * @param string $key
   * @param string $space
   * 
   * @return void
   */
  public function unset($key, $space = 'default') {
    $table = $this->key_value_table($space);
    
    try {
      $this->remove($table, ['key' => $key]);
    }
    catch (PDOException $e) {}
  }

  /**
   * clear an entire storage the db storage
   * 
   * @param string $key
   * @param string $space
   * 
   * @return void
   */
  public function clear($space = 'default') {
    $table = $this->key_value_table($space);
    $sql = "DROP TABLE `{$table}`";

    try {
      $this->exec($sql);
    }
    catch (PDOException $e) {}
  }
 
  /* Cache storage */

  public function cache($key, $populate = null, $ttl = 60, $table = '_cache') {
    $key = sha1($key);
    
    try {
      $data = $this->fetch($table, ['key' => $key]);
      if ($data && count($data) > 0) {
        $data = $data[0];
      }
    } catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        $this->exec("CREATE TABLE $table(`key` varchar(40) PRIMARY KEY, `expire` int unsigned, `value` TEXT) ENGINE = INNODB");
      }
      return false;
    }
    
    if ( !$data || ($data['expire'] < time() || json_decode($data['value'], 1) !== $populate) ) {
      if ( $populate !== null ) {
        try {
          $this->insert_update($table, [
            'key' => $key,
            'expire' => time() + $ttl,
            'value' => json_encode($populate)
          ]);
        }
        catch ( PDOException $e ) {
          return false;
        }
        
        return $populate;
      }
    } else {
      return json_decode($data['value'], 1);
    }
    return false;
  }

  public function clear_cache($table = '_cache')
  {
    return self::remove($table, []);
  }
  
  public function uncache($key, $table = '_cache') {
    $key = sha1($key);
    
    try {
      $this->remove($table, ['key' => $key]);
      return true;
    }
    catch ( PDOException $e ) {
      return false;
    }
  }
  
  
  
  /* Cache storage */
  
  public function writejob($event, $data) {
    try {
      $this->insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
    }
    catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        $this->exec("CREATE TABLE _queue(`id` SERIAL PRIMARY KEY, `event` varchar(32), `data` TEXT, KEY event_id(`event`, `id`)) ENGINE = INNODB");
        $this->insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
      }
    }
  }
  
  public function readjob($event) {
    try {
      $this->exec('START TRANSACTION');
      
      $row = $this->fetch('SELECT * FROM _queue WHERE event = :event ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED', [':event' => $event])[0];
      if ( $row ) {
        $this->remove('_queue', ['id' => $row['id']]);
        $return = json_decode($row['data'], 1);
      }
      
      $this->exec('COMMIT');
      
      return $return;
    }
    catch ( PDOException $e ) {}
  }
    
  public function popjob($event) {
    try {
      $this->exec('START TRANSACTION');
      
      $row = $this->fetch('SELECT * FROM _queue WHERE event = :event ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED', [':event' => $event])[0];
      if ( $row ) {
        $this->remove('_queue', ['id' => $row['id']]);
        $return = json_decode($row['data'], 1);
      }
      
      $this->exec('COMMIT');
      
      return $return;
    }
    catch ( PDOException $e ) {}
  }
  
  public function on($event, $cb) {
    while ( true ) {
      $data = $this->readjob($event);
      
      if ( $data === null ) {
        usleep(1000);
        continue;
      }
      
      $cb($data);
    }
  }
  
  
  
  /* Auto fields creation mode */
  
  public function auto_create($flag = true) {
    $this->auto_create = $flag;
  }
  
  protected function create_table_columns($names) {
    $cols = [];
    foreach ( $names as $k ) {
      $type = 'TEXT';
      
      if ( $k == 'id' ) {
        $type = 'SERIAL PRIMARY KEY';
      }
      
      $cols[] = "`{$k}` {$type}";
    }
    
    return implode(',', $cols);
  }
  
  protected function handle_insert_exception($exception, $table, $insert, $ignore) {
    if ( !$this->auto_create || strpos($exception->getMessage(), "doesn't exist") === false ) {
      throw $exception;
    }
    
    $create = $this->create_table_columns(array_keys($insert));
    $this->exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    $this->insert($table, $insert, $ignore);
  }
  /**
   * handle insert update exception
   * 
   * @param PDOException $exception
   * @param string $table
   * @param array $insert
   * 
   * @return void
   */
  protected function handle_insert_update_exception($exception, $table, $insert) {
    if ( !$this->auto_create ||
         ( (strpos($exception->getMessage(), "doesn't exist") === false) &&
           (strpos($exception->getMessage(), "Unknown column") === false) )
       ) {
      throw $exception;
    }
    
    if ( strpos($exception->getMessage(), "doesn't exist") !== false ) {
      $create = $this->create_table_columns(array_keys($insert));
      $this->exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    }
    else {
      preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
      $col = $m[1];
      
      $this->exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    }
    
    $this->insert_update($table, $insert);
  }
  
  protected function handle_update_exception($exception, $table, $filder, $data) {
    if ( !$this->auto_create || strpos($exception->getMessage(), "Unknown column") === false ) {
      throw $exception;
    }
    
    preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
    $col = $m[1];
    
    $this->exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    return $this->update($table, $filder, $data);
  }
}
