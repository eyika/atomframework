<?php
namespace Eyika\Atom\Framework\Support\Database;

use Eyika\Atom\Framework\Support\Arr;
use PDO;
use PDOException;

# https://github.com/mrcrypster/mysqly

class mysqly {
  protected static \PDO $db;
  protected static $auth = [];
  protected static $auth_file = '/var/lib/mysqly/.auth.php';
  protected static $auto_create = false;

  protected static $transaction_mode = false;
  
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
  private static function condition($k, $v, &$where, &$bind, &$incr_operator, $or_and = '', $_operator = '=') {
    $or_and = $or_and !== '' ? ' ' . $or_and : $or_and;
    if ( is_array($v) ) {
      $in = [];

      foreach ( $v as $i => $sub_v ) {
        $in[] = ":{$k}_{$i}";
        $bind[":{$k}_{$i}"] = $sub_v;
      }

      $in = implode(', ', $in);
      $where[] = "`{$k}` IN ($in){$or_and}";
      $incr_operator = false;
    }
    else if ($v !== null && str_contains((string)$v, 'NULL')) {
      $where[] = "`{$k}` {$v}{$or_and}";
      $incr_operator = false;
    }
    else if ($v !== null && is_string($v) && str_contains($v, 'now')) {
      $where[] = "`{$k}` $_operator now(){$or_and}";
      $incr_operator = true;
    }
    else if ($_operator !== null && str_contains((string)$_operator, 'LIKE')) {
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
  private static function values($data, &$bind = []) {
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
  
  public static function exec($sql, $bind = []) {
    if ( !isset(static::$db) ) {
      if ( !static::$auth ) {
        if (env('DB_USERNAME') && env('DB_PASSWORD') && env('DB_DATABASE')) {
          static::auth(env('DB_USERNAME'), env('DB_PASSWORD'), env('DB_DATABASE'), env('DB_HOST'));
        } else {
          static::$auth = @include static::$auth_file;
        }
      }
      static::$db = new PDO('mysql:host=' . (isset(static::$auth['host']) ? static::$auth['host'] : '127.0.0.1') . ';port=' . (isset(static::$auth['port']) ? static::$auth['port'] : '3306') . (static::$auth['db'] ? ';dbname=' . static::$auth['db'] : ''), static::$auth['user'], static::$auth['pwd']);
      static::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $params[$k] = $v;
      }
    }

    // Manually replace the placeholders for LIMIT and OFFSET
    // if (isset($params[':limit']) && isset($params[':offset'])) {
    //   $sql = str_replace(':limit', (int)$params[':limit'], $sql);
    //   $sql = str_replace(':offset', (int)$params[':offset'], $sql);
    //   unset($params[':limit'], $params[':offset']);
    // }
    
    $statement = static::$db->prepare($sql);
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
  
  
  
  /* Authentication */
  
  public static function auth($user, $pwd, $db, $host = 'localhost') {
    static::$auth = ['user' => $user, 'pwd' => $pwd, 'db' => $db, 'host' => $host];
  }
  
  public static function now() {
    return static::fetch('SELECT NOW() now')[0]['now'];
  }

  
  
  /**
   * transaction() wrap a query in a callback
   * and run inside a transaction
   * 
   * @param callable $callback
   * @return void
  */
  
  public static function transaction($callback) {
    static::exec('START TRANSACTION');
    $result = $callback();
    static::exec( $result ? 'COMMIT' : 'ROLLBACK' );
  }

  /**
   * start a transaction chain
   * subsequent queries will be executed in a transaction
   * 
   * @return void
   */
  public static function beginTransaction() {
    static::exec('START TRANSACTION');
    static::$transaction_mode = true;
  }
  
  /**
   * commit all changes made in the transaction chain
   * 
   * @return void
   */
  public static function commit() {
    if (static::$transaction_mode)
      static::exec('COMMIT');
  }
  
  /**
   * rollback all changes made in the transaction chain
   * 
   * @return void
   */
  public static function rollback() {
    if (static::$transaction_mode)
      static::exec('ROLLBACK');
  }

  /* Internal implementation */
  
  private static function filter($filter, string|array $or_ands = "AND", string|array $operators = '=') {
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
        static::condition($k, $v, $query, $bind, $incr_operator, $or_and, $operator);
        $j++;
        if ($incr_operator)
          $i++;
        $incr_operator = false;
      }
    }
    else {
      static::condition('id', $filter, $query, $bind, $incr_operator);
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
  
  public static function fetch_cursor($sql_or_table, $bind_or_filter = [], $select_what = '*', string|array $operators = "=", string|array $or_ands = "AND") {
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
          foreach (['order_by', 'LIMIT', 'OFFSET'] as $_val) {
            if (array_key_exists($_val, $bind_or_filter)) {
              $len--;
            }
          }

          if (is_array($or_ands)) {
            while ($len <= count($or_ands))
              array_shift($or_ands);
          }

          foreach ( $bind_or_filter as $k => $v ) {
            if ( $k == 'order_by' ) {
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
            
            static::condition($k, $v, $where, $bind, $incr_operator, $or_and, $operator);
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
    // if (env('APP_ENV', null) === 'local')
      // logger()->info($sql, isset($bind) &&  is_array($bind) ? $bind : []);

    $res = isset($bind) ? static::exec($sql, $bind) : static::exec($sql);
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
  public static function fetch($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND") {
    if (!$statement = static::fetch_cursor($sql_or_table, $bind_or_filter, $select_what, $operators, $or_ands)) {
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
  // public static function fetchOr($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
  // {
  //   return static::fetch($sql_or_table, $bind_or_filter, $select_what, $operators, "OR");
  // }
  
  public static function array($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
  {
    $rows = static::fetch($sql_or_table, $bind_or_filter, $select_what, $operators, $or_ands);
    foreach ( $rows as $row ) $list[] = array_shift($row);
    return $list;
  }
  
  public static function key_vals($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
  {
    $rows = static::fetch($sql_or_table, $bind_or_filter, $select_what, $operators, $or_ands);
    foreach ( $rows as $row ) $list[array_shift($row)] = array_shift($row);
    return $list;
  }
  
  public static function count($sql_or_table, $bind_or_filter = [], array|string $operators = '=', array|string $or_ands = "AND")
  {
    $_select_str = '*';
    $operators = Arr::wrap($operators);

    foreach ($operators as $key => $op) {
      if ($op && str_starts_with($op, 'DISTINCT ')) {
        $_select_str = Arr::pull($operators, $key) ?? '*';
        break;
      }
    }

    $rows = static::fetch($sql_or_table, $bind_or_filter, "count($_select_str)", $operators, $or_ands);
    $val = array_shift($rows);
    return intval(array_shift($val));
  }
  
  public static function random($table, $filter = [], string|array $operators = '=', string|array $or_ands = "AND")
  {
    list($where, $bind) = static::filter($filter, $or_ands, $operators);
    $sql = 'SELECT * FROM `' . $table . '` ' . $where . ' ORDER BY RAND() LIMIT 1';
    return static::fetch($sql, $bind)[0];
  }
  
  
  
  /* --- Atomic increments/decrements --- */
  
  public static function increment($column, $table, $filters, string|array $operators = '=', string|array $or_ands = "AND", $step = 1) {
    $bind = $where = [];

    $i = 0; $j = 0; $len = count($filters);
    foreach ( $filters as $k => $v ) {
      if ($len - $j === 1)
        $or_and = '';
      else
        $or_and = is_array($or_ands) ? "$or_ands[$j]" : "$or_ands";

      $operator = is_array($operators) ? $operators[$i] : $operators;
      static::condition($k, $v, $where, $bind, $incr_operator, $or_and, $operator);
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
    
    return static::exec("UPDATE `{$table}` SET `{$column}` = `{$column}` {$step} {$where_sql}", $bind);
  }
  
  public static function decrement($column, $table, $filters) {
    return static::increment($column, $table, $filters, -1);
  }
  
  
  
  /* --- Toggle column value --- */
  
  public static function toggle($table, $filters, $column, $if, $then, string|array $operators = '=', string|array $or_ands = "AND") {
    $bind = $where = [];
    $i = 0; $j = 0; $len = count($filters);

    foreach ( $filters as $k => $v ) {    
      if ($len - $j === 1)
        $or_and = '';
      else
        $or_and = is_array($or_ands) ? "$or_ands[$j]" : "$or_ands";
      
      $operator = is_array($operators) ? $operators[$i] : $operators; 
      static::condition($k, $v, $where, $bind, $incr_operator, $or_and, $operator);
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
    
    return static::exec("UPDATE `{$table}` SET `{$column}` = IF(`{$column}` = :if, :then, :v) {$where_sql}", $bind);
  }
  
  
  
  /**
   * Data insertion
   * 
   * @param string $table
   * @param array $data
   * @param bool $ignore
   * @return int
   */
  
  public static function insert($table, $data, $ignore = false) {
    $bind = [];
    $values = static::values($data, $bind);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}` SET {$values}";
    
    // if (env('APP_ENV', null) === 'local')
      // logger()->info($sql, $bind);
    try {
      static::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      static::handle_insert_exception($e, $table, $data, $ignore);
    }
    
    return static::$db->lastInsertId();
  }
  
  /**
   * Insert to table or update value
   * 
   * @param string $table
   * @param array $data
   * @return void
   */
  public static function insert_update($table, $data) {
    $bind = [];
    $values = static::values($data, $bind);
    $sql = "INSERT INTO `{$table}` SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    // logger()->info($sql, $bind);
    
    try {
      $statement = static::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      $statement = static::handle_insert_update_exception($e, $table, $data);
    }
  }
  
  public static function multi_insert($table, $rows, $ignore = false) {
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
    static::exec($sql, $bind);
    return static::$db->lastInsertId();
  }
  
  
  
  /* Data export */
  
  public static function export_csv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*', $operators = [], $or_and = "AND") {
    $cursor = static::fetch_cursor($sql_or_table, $bind_or_filter, $select_what, $or_and, $operators);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row);
    }
    
    fclose($f);
  }
  
  public static function export_tsv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*', $operators = [], $or_and = "AND", ) {
    $cursor = static::fetch_cursor($sql_or_table, $bind_or_filter, $select_what, $or_and, $operators);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row, "\t");
    }
    
    fclose($f);
  }
  
  

  /* Data update */  

  public static function update($table, $filter, $data, string|array $operators = '=', string|array $or_ands = "AND"): int {
    list($where, $bind) = static::filter($filter, $or_ands, $operators);
    $values = static::values($data, $bind);
    
    $sql = "UPDATE `{$table}` SET {$values} {$where}";
    
    try {
      // logger()->info($sql, is_array($bind) ? $bind : []);
      $statement = static::exec($sql, $bind);
      return $statement->rowCount();
    }
    catch ( PDOException $e ) {
      return static::handle_update_exception($e, $table, $filter, $data);
    }
  }
  
  public static function remove($table, $filter, string|array $operators = '=', string|array $or_ands = "AND"): bool {
    list($where, $bind) = static::filter($filter, $or_ands, $operators);
    if (!$statement = static::exec("DELETE FROM `{$table}` " . $where, $bind)) {
      return false;
    }
    if ($statement->rowCount() < 1) {
      return false;
    }
    return true;
  }
  
  
  
  /* --- Dynamic methods --- */
  
  public static function __callStatic($name, $args) {
    
    # get row or column from table
    if ( $args[0] && (count($args) == 1) && strpos($name, '_') ) {
      list($table, $col) = explode('_', $name);
      list($where, $bind) = static::filter($args[0]);
      $rows = static::fetch('SELECT ' . ($col ? "`{$col}`" : '*') . ' FROM `' . $table . '` ' . $where, $bind);
      if ( $rows ) {
        return $col ? $rows[0][$col] : $rows[0];
      }
    }
    
    # get aggregates by filters
    else if ( $args[0] && (count($args) == 2) && strpos($name, '_') && in_array(explode('_', $name)[0], ['min', 'max', 'avg']) ) {
      list($agr, $col) = explode('_', $name);
      $table = $args[0];
      list($where, $bind) = static::filter($args[1]);
      $row = static::fetch('SELECT ' . $agr . '( ' . $col . ') FROM `' . $table . '` ' . $where, $bind)[0];
      return array_shift($row);
    }
    
    # get list of rows from table
    else if ( count($args) == 0 || count($args) == 1 ) {
      return static::fetch($name, $args[0] ?: []);
    }
    
    
    else {
      throw new PDOException($name . '() method is unknown' );
    }
  }
  
  
  
  /**
   * Key-value storage table name
   * 
   * @param string $space
   * @return string
   */
  
  protected static function key_value_table($space) {
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
  public static function get($key, $space = 'default') {
    $table = static::key_value_table($space);
    
    try {
      $values = static::fetch($table, ['key' => $key], 'value');

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
  public static function set($key, $value, $space = 'default') {
    echo "called set";
    $table = static::key_value_table($space);
    
    try {
      static::insert_update($table, ['key' => $key, 'value' => $value]);
    }
    catch (PDOException $e) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        static::exec("CREATE TABLE `{$table}`(`key` varchar(128) PRIMARY KEY, `value` TEXT) ENGINE = INNODB");
        static::insert($table, ['key' => $key, 'value' => $value]);
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
  public static function unset($key, $space = 'default') {
    $table = static::key_value_table($space);
    
    try {
      static::remove($table, ['key' => $key]);
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
  public static function clear($space = 'default') {
    $table = static::key_value_table($space);
    $sql = "DROP TABLE `{$table}`";

    try {
      static::exec($sql);
    }
    catch (PDOException $e) {}
  }
 
  /* Cache storage */

  public static function cache($key, $populate = null, $ttl = 60, $table = '_cache') {
    $key = sha1($key);
    
    try {
      $data = static::fetch($table, ['key' => $key]);
      if ($data && count($data) > 0) {
        $data = $data[0];
      }
    } catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        static::exec("CREATE TABLE $table(`key` varchar(40) PRIMARY KEY, `expire` int unsigned, `value` TEXT) ENGINE = INNODB");
      }
      return false;
    }
    
    if ( !$data || ($data['expire'] < time() || json_decode($data['value'], 1) !== $populate) ) {
      if ( $populate !== null ) {
        try {
          static::insert_update($table, [
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

  public static function clear_cache($table = '_cache')
  {
    return self::remove($table, []);
  }
  
  public static function uncache($key, $table = '_cache') {
    $key = sha1($key);
    
    try {
      static::remove($table, ['key' => $key]);
      return true;
    }
    catch ( PDOException $e ) {
      return false;
    }
  }
  
  
  
  /* Cache storage */
  
  public static function writejob($event, $data) {
    try {
      static::insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
    }
    catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        static::exec("CREATE TABLE _queue(`id` SERIAL PRIMARY KEY, `event` varchar(32), `data` TEXT, KEY event_id(`event`, `id`)) ENGINE = INNODB");
        static::insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
      }
    }
  }
  
  public static function readjob($event) {
    try {
      static::exec('START TRANSACTION');
      
      $row = static::fetch('SELECT * FROM _queue WHERE event = :event ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED', [':event' => $event])[0];
      if ( $row ) {
        static::remove('_queue', ['id' => $row['id']]);
        $return = json_decode($row['data'], 1);
      }
      
      static::exec('COMMIT');
      
      return $return;
    }
    catch ( PDOException $e ) {}
  }
    
  public static function popjob($event) {
    try {
      static::exec('START TRANSACTION');
      
      $row = static::fetch('SELECT * FROM _queue WHERE event = :event ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED', [':event' => $event])[0];
      if ( $row ) {
        static::remove('_queue', ['id' => $row['id']]);
        $return = json_decode($row['data'], 1);
      }
      
      static::exec('COMMIT');
      
      return $return;
    }
    catch ( PDOException $e ) {}
  }
  
  public static function on($event, $cb) {
    while ( true ) {
      $data = static::read($event);
      
      if ( $data === null ) {
        usleep(1000);
        continue;
      }
      
      $cb($data);
    }
  }
  
  
  
  /* Auto fields creation mode */
  
  public static function auto_create($flag = true) {
    static::$auto_create = $flag;
  }
  
  protected static function create_table_columns($names) {
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
  
  protected static function handle_insert_exception($exception, $table, $insert, $ignore) {
    if ( !static::$auto_create || strpos($exception->getMessage(), "doesn't exist") === false ) {
      throw $exception;
    }
    
    $create = static::create_table_columns(array_keys($insert));
    static::exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    static::insert($table, $insert, $ignore);
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
  protected static function handle_insert_update_exception($exception, $table, $insert) {
    if ( !static::$auto_create ||
         ( (strpos($exception->getMessage(), "doesn't exist") === false) &&
           (strpos($exception->getMessage(), "Unknown column") === false) )
       ) {
      throw $exception;
    }
    
    if ( strpos($exception->getMessage(), "doesn't exist") !== false ) {
      $create = static::create_table_columns(array_keys($insert));
      static::exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    }
    else {
      preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
      $col = $m[1];
      
      static::exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    }
    
    static::insert_update($table, $insert);
  }
  
  protected static function handle_update_exception($exception, $table, $filder, $data) {
    if ( !static::$auto_create || strpos($exception->getMessage(), "Unknown column") === false ) {
      throw $exception;
    }
    
    preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
    $col = $m[1];
    
    static::exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    return static::update($table, $filder, $data);
  }
}
