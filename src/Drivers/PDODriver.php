<?php
    /*/
     * Project Name:    Wingman — Database — PDO Driver
     * Created by:      Angel Politis
     * Creation Date:   Dec 26 2025
     * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Drivers namespace.
    namespace Wingman\Database\Drivers;

    # Import the following classes to the current scope.
    use Closure;
    use PDO;
    use PDOException;
    use PDOStatement;
    use ReflectionClass;
    use RuntimeException;
    use Wingman\Database\Interfaces\SQLDialect;
    use Wingman\Database\Interfaces\SQLDriver;

    /**
     * Represents a PDO SQL driver.
     * @package Wingman\Database\SQL\Drivers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PDODriver implements SQLDriver {
        /**
         * The default PDO options.
         * @var array
         */
        protected static $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        /**
         * The configuration for the PDO connection.
         * @var array
         */
        protected array $config = [];

        /**
         * The PDO instance of a driver.
         * @var PDO|null
         */
        protected ?PDO $connection = null;

        /**
         * The SQL dialect of a driver.
         * @var SQLDialect|null
         */
        protected ?SQLDialect $dialect = null;

        /**
         * Indicates if a transaction is active.
         * @var bool
         */
        protected bool $inTransaction = false;

        /**
         * The last error information.
         * @var array|null
         */
        protected ?array $lastError = null;
    
        /**
         * Creates a new PDO driver.
         * @param SQLDialect|string $dialect The SQL dialect or its class name.
         * @param array $config The configuration for the PDO connection.
         * @throws RuntimeException If the dialect class does not exist.
         */
        public function __construct (SQLDialect|string $dialect, array $config = []) {
            if (is_string($dialect)) {
                if (!class_exists($dialect)) {
                    throw new RuntimeException("Dialect class '$dialect' does not exist.");
                }
                $dialectClass = $dialect;
                $this->dialect = new $dialectClass();
            }
            else $this->dialect = $dialect;
            
            $this->config = $config;
        }

        ############################################################################
        #                             PROTECTED METHODS                            #
        ############################################################################

        /**
         * Captures and handles PDO exceptions.
         * @param Closure $closure The closure to execute.
         * @param array $metadata Additional metadata for error context.
         * @return mixed The result of the closure execution.
         * @throws PDOException If an error occurs during execution.
         */
        protected function capture (Closure $closure, array $metadata = []) : mixed {
            try {
                $this->connect();
                return $closure();
            }
            catch (PDOException $e) {
                $this->lastError = [
                    "code" => $e->getCode(),
                    "info" => $e->errorInfo
                ] + $metadata;
                throw $e;
            }
        }

        /**
         * Binds values to a PDO statement.
         * @param PDOStatement $stmt The PDO statement.
         * @param array $bindings The bindings to apply.
         */
        protected function bindValues (PDOStatement $stmt, array $bindings) : void {
            foreach ($bindings as $index => $value) {
                $paramIndex = is_int($index) ? $index + 1 : $index;
                $type = PDO::PARAM_STR;

                if (is_int($value)) $type = PDO::PARAM_INT;
                elseif (is_bool($value)) $type = PDO::PARAM_BOOL;
                elseif (is_null($value)) $type = PDO::PARAM_NULL;

                $stmt->bindValue($paramIndex, $value, $type);
            }
        }

        /**
         * Executes a SQL statement and returns the PDOStatement or false on failure.
         * @param string $sql The SQL statement to execute.
         * @param array $params The parameters for the statement.
         * @return PDOStatement|false The resulting PDOStatement or false on failure.
         */
        protected function run (string $sql, array $params = []) : PDOStatement|false {
            if (empty($params)) return $this->connection->query($sql);

            $stmt = $this->connection->prepare($sql);
            $this->bindValues($stmt, $params);
            $stmt->execute();
            return $stmt;
        }

        #############################################################################
        #                               PUBLIC METHODS                              #
        #############################################################################

        /**
         * Begins a transaction.
         * @return bool Whether the transaction was started successfully.
         * @throws PDOException If an error occurs while starting the transaction.
         * @throws RuntimeException If the transaction could not be started.
         */
        public function beginTransaction () : bool {
            return $this->capture(function () {
                try {
                    $result = $this->connection->beginTransaction();
                    $this->inTransaction = $result;

                    if (!$result) {
                        throw new RuntimeException("Failed to start transaction.");
                    }

                    return $result;
                }
                catch (PDOException $e) {
                    $this->inTransaction = false;
                    throw $e;
                }
            });
        }

        /**
         * Commits the current transaction.
         * @return bool Whether the transaction was committed successfully.
         * @throws RuntimeException If there is no active transaction.
         */
        public function commit () : bool {
            if (!$this->inTransaction) {
                throw new RuntimeException("No active transaction to commit.");
            }
            try {
                return $this->connection->commit();
            }
            finally {
                $this->inTransaction = false;
            }
        }

        /**
         * Connects to the database using the provided configuration.
         * @param array $config The configuration for the PDO connection.
         * @param SQLDialect|null $dialect The SQL dialect to use.
         * @return static The PDO driver instance.
         */
        public function connect (?array $config = null, ?SQLDialect $dialect = null) : static {
            if ($this->connection) return $this;

            if ($config === null) {
                $config = $this->config;
            }
            else $this->config = $config;

            if ($dialect !== null) {
                $this->dialect = $dialect;
            }

            if (!isset($config["dsn"]) && is_null($this->dialect)) {
                throw new RuntimeException("SQL Dialect must be provided before connecting.");
            }

            $this->connection = new PDO(
                $config["dsn"] ?? $this->dialect->compileDsn($config), 
                $config["username"] ?? null, 
                $config["password"] ?? null, 
                $config["options"] ?? static::$defaultOptions
            );
            return $this;
        }

        /**
         * Disconnects the PDO connection.
         * @return static The PDO driver instance.
         */
        public function disconnect () : static {
            $this->connection = null;
            return $this;
        }

        /**
         * Executes a non-query SQL statement.
         * @param string $sql The SQL statement to execute.
         * @param array $bindings The bindings for the statement.
         * @return int The number of affected rows.
         * @throws PDOException If an error occurs during execution.
         */
        public function execute (string $sql, array $bindings = []) : int {
            return $this->capture(function () use ($sql, $bindings) {
                $stmt = $this->run($sql, $bindings);
                return $stmt->rowCount();
            }, compact("sql", "bindings"));
        }

        /**
         * Executes a query and returns a single row as an associative array.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @param int $mode The fetch mode for the result.
         * @return array|null The resulting row or null if no row is found.
         */
        public function fetch (string $sql, array $bindings = [], int $mode = PDO::FETCH_ASSOC) : ?array {
            return $this->capture(function () use ($sql, $bindings, $mode) {
                $stmt = $this->run($sql, $bindings);
                return $stmt->fetch($mode) ?: null;
            }, compact("sql", "bindings"));
        }
    
        /**
         * Executes a query and returns all rows as an array of associative arrays.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @param int $mode The fetch mode for the results.
         * @return array The resulting rows.
         */
        public function fetchAll (string $sql, array $bindings = [], int $mode = PDO::FETCH_ASSOC) : array {
            return $this->capture(function () use ($sql, $bindings, $mode) {
                $stmt = $this->run($sql, $bindings);
                return $stmt->fetchAll($mode) ?: [];
            }, compact("sql", "bindings"));
        }

        /**
         * Executes a query and returns a single column from the first row.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @param int $column The column index to fetch.
         * @return mixed The value of the specified column or null if no row is found.
         */
        public function fetchColumn (string $sql, array $bindings = [], int $column = 0) : mixed {
            return $this->capture(function () use ($sql, $bindings, $column) {
                $stmt = $this->run($sql, $bindings);
                $result = $stmt->fetchColumn($column);
                return $result === false ? null : $result;
            }, compact("sql", "bindings"));
        }
        
        /**
         * Executes a query and returns all values from a single column.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @param int $column The column index to fetch.
         * @return array The values of the specified column.
         */
        public function fetchValues (string $sql, array $bindings = [], int $column = 0) : array {
            return $this->capture(function () use ($sql, $bindings, $column) {
                $stmt = $this->run($sql, $bindings);
                return $stmt->fetchAll(PDO::FETCH_COLUMN, $column) ?: [];
            }, compact("sql", "bindings"));
        }

        /**
         * Gets the underlying PDO connection of a driver.
         * @return PDO|null The PDO instance.
         */
        public function getConnection () : ?PDO {
            return $this->connection;
        }

        /**
         * Gets the database name of a driver from the configuration.
         * @return string|null The database name.
         */
        public function getDatabase () : ?string {
            if (!empty($this->config["database"])) {
                return (string) $this->config["database"];
            }

            $dsn = $this->config["dsn"] ?? null;
            if (!is_string($dsn) || $dsn === "") return null;

            # URL-style DSN support (e.g. mysql://user:pass@host/dbname?charset=utf8mb4)
            # parse_url requires a scheme, so we only attempt when it looks like one.
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\//', $dsn)) {
                $parts = parse_url($dsn);
                $path = $parts["path"] ?? "";
                $name = ltrim($path, "/");
                return $name !== "" ? $name : null;
            }

            # Key/value style PDO DSN (e.g. mysql:host=localhost;dbname=test;port=3306)
            if (preg_match('/\bdbname=([^;]+)/i', $dsn, $m)) {
                $name = trim($m[1]);
                return $name !== "" ? $name : null;
            }

            return null;
        }

        /**
         * Gets the SQL dialect of a driver.
         * @return SQLDialect|null The SQL dialect.
         */
        public function getDialect () : ?SQLDialect {
            return $this->dialect;
        }

        /**
         * Gets the last error information.
         * @return array|null The last error information.
         */
        public function getLastError () : ?array {
            return $this->lastError;
        }

        /**
         * Gets the ID of the last inserted row.
         * @param string|null $name The name of the sequence object from which the ID should be returned.
         * @return int|string The last insert ID.
         */
        public function getLastInsertId (?string $name = null) : int|string {
            return $this->connection->lastInsertId();
        }

        /**
         * Prepares a driver for bulk data loading.
         * @return static The PDO driver instance.
         */
        public function prepareForBulkLoad () : static {
            if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === "mysql") {
                $this->connection->setAttribute(PDO::MYSQL_ATTR_LOCAL_INFILE, true);
            }
            return $this;
        }
    
        /**
         * Executes a query and returns the results as an associative array.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @return PDOStatement|false The resulting PDOStatement or `false` on failure.
         */
        public function query (string $sql, array $bindings = []) : PDOStatement|false {
            return $this->capture(function () use ($sql, $bindings) {
                return $this->run($sql, $bindings);
            }, compact("sql", "bindings"));
        }

        /**
         * Rolls back the current transaction.
         * @return bool Whether the transaction was rolled back successfully.
         * @throws RuntimeException If there is no active transaction.
         */
        public function rollBack () : bool {
            if (!$this->inTransaction) {
                throw new RuntimeException("No active transaction to commit.");
            }
            try {
                return $this->connection->rollBack();
            }
            finally {
                $this->inTransaction = false;
            }
        }

        /**
         * Creates a PDO driver using an existing PDO connection.
         * @param PDO $connection The existing PDO connection.
         * @return static The PDO driver instance.
         */
        public static function using (PDO $connection) : static {
            $reflection = new ReflectionClass(static::class);
            $driver = $reflection->newInstanceWithoutConstructor();
            $driver->connection = $connection;
            return $driver;
        } 
    }
?>