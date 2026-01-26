<?php
    /*/
     * Project Name:    Wingman — Database — Connection
     * Created by:      Angel Politis
     * Creation Date:   Dec 29 2025
     * Last Modified:   Jan 26 2026
    /*/

    # Use the Database namespace.
    namespace Wingman\Database;

    # Import the following classes to the current scope.
    use DateTimeInterface;
    use RuntimeException;
    use Throwable;
    use Wingman\Database\Analysis\PlanAnalyser;
    use Wingman\Database\Builders\QueryBuilder;
    use Wingman\Database\Compilers\PlanCompiler;
    use Wingman\Database\Enums\IndexAlgorithm;
    use Wingman\Database\Enums\IndexType;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Enums\ReferentialAction;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\SQLDriver;
    use Wingman\Database\Interfaces\SQLDialect;
    use Wingman\Database\Objects\Filter;

    /**
     * Represents a database connection.
     * @package Wingman\Database
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Connection {
        /**
         * The default auto-increment column name.
         * @var string
         */
        public const DEFAULT_AI_COLUMN = "id";

        /**
         * The SQL dialect used by a connection.
         * @var SQLDialect
         */
        protected SQLDialect $dialect;

        /**
         * The SQL driver used by a connection.
         * @var SQLDriver
         */
        protected SQLDriver $driver;

        /**
         * The plan compiler used by a connection.
         * @var PlanCompiler
         */
        protected PlanCompiler $compiler;

        /**
         * The configuration of a connection.
         * @var array
         */
        protected array $config = [];

        /**
         * The errors of a connection.
         * @var array[]
         */
        protected array $errors = [];

        /**
         * The active database of a connection.
         * @var string|null
         */
        protected ?string $activeDatabase = null;

        /**
         * The active timezone of a connection.
         * @var string|null
         */
        protected ?string $activeTimezone = null;

        /**
         * The current transaction level of a connection.
         * @var int
         */
        protected int $transactionLevel = 0;

        /**
         * Creates a new database connection.
         * @param SQLDriver|class-string $driver The SQL driver.
         * @param SQLDialect|class-string $dialect The SQL dialect.
         * @param array $config The connection configuration.
         */
        public function __construct (SQLDriver|string $driver, SQLDialect|string $dialect, array $config = []) {
            if (is_string($dialect)) $dialect = new $dialect();
            if (is_string($driver)) $driver = new $driver($dialect, $config);
            $this->driver = $driver;
            $this->dialect = $dialect;
            $this->config = $config;
            $this->compiler = new PlanCompiler($this->dialect);
            $this->activeDatabase = $this->driver->getDatabase();
        }
        
        /**
         * Destroys the database connection.
         */
        public function __destruct () {
            $this->getDriver()->disconnect();
        }

        ############################################################################
        #                             PROTECTED METHODS                            #
        ############################################################################

        /**
         * Estimates the payload size of a set of rows.
         * @param array $rows The rows to estimate.
         * @return int The estimated payload size in bytes.
         */
        protected function estimatePayloadSize (array $rows) : int {
            $size = 0;
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $size += match (true) {
                        is_string($value) => mb_strlen($value, "8bit"),
                        is_array($value)  => strlen(json_encode($value)), # JSON estimate
                        is_null($value)   => 4,  # "NULL"
                        is_bool($value)   => 1,  # "1" or "0"
                        is_numeric($value)=> strlen((string)$value),
                        $value instanceof DateTimeInterface => 19, # "YYYY-MM-DD HH:MM:SS"
                        default => 10 # Safety fallback
                    };
                    # Add 2 bytes for the comma and space overhead per value.
                    $size += 2;
                }
                # Add overhead for parentheses and commas per row.
                $size += 5; 
            }
            return $size;
        }

        /**
         * Commits the transaction of a connection.
         */
        protected function executeCommit () : void {
            # Only commit the root transaction.
            if ($this->transactionLevel === 1) $this->driver->commit();
        }

        /**
         * Rolls back the transaction of a connection.
         * @param string|null $savepointName The savepoint name (if applicable).
         */
        protected function executeRollback (?string $savepointName = null) : void {
            if ($this->transactionLevel > 1) {
                # Nested: Rollback to specific savepoint or the automatic one.
                $name = $savepointName ?: "wingman_sp_{$this->transactionLevel}";
                $this->driver->execute($this->dialect->compileSavepointRollback($name));
            }
            else $this->driver->rollBack();
        }

        /**
         * Creates a savepoint in the current transaction.
         * @param string $name The savepoint name.
         */
        protected function executeSavepoint (string $name) : void {
            $this->driver->execute($this->dialect->compileSavepoint($name));
        }

        /**
         * Executes an insert or update query with returning capability.
         * @param TableIdentifier $table The table.
         * @param string $query The SQL query.
         * @param array $bindings The query bindings.
         * @param array $returnColumns The columns to return.
         * @param Expression|null $filter The filter expression (optional).
         * @return array|int The returned rows or the number of affected rows.
         */
        protected function executeWithReturningCapability (TableIdentifier $table, string $query, array $bindings, array $returnColumns, ?Expression $filter) : array|int {
            if (empty($returnColumns)) {
                return $this->driver->execute($query, $bindings);
            }

            if ($this->dialect->supportsReturning()) {
                $query .= " " . $this->dialect->compileReturning($returnColumns);
                return $this->driver->fetchAll($query, $bindings);
            }
    
            return $this->transact(function () use ($table, $query, $bindings, $returnColumns, $filter) {
                $affected = $this->driver->execute($query, $bindings);
                if ($affected === 0) return [];
                $selectQuery = $this->dialect->compileSelect($table, $returnColumns, $filter);
                $sql = $selectQuery->getQuery();
                if ($this->dialect->supportsLocking()) {
                    $sql .= " " . $this->dialect->compileLock(LockType::Exclusive, null, false);
                }
                return $this->driver->fetchAll($sql, $selectQuery->getBindings($this->dialect));
            });
        }
        
        /**
         * Normalises a table identifier.
         * @param string|TableIdentifier $table The table.
         * @return TableIdentifier The normalised table identifier.
         */
        protected function normaliseTable (string|TableIdentifier $table) : TableIdentifier {
            if (is_string($table)) {
                $table = TableIdentifier::from($table);
            }
            return $table;
        }

        /**
         * Resolves a QueryBuilder object or raw SQL string into SQL and bindings.
         * @param QueryBuilder|string $query The query to resolve.
         * @return array An array containing the SQL string and bindings.
         */
        protected function resolveQuery (QueryBuilder|string $query) : array {
            if ($query instanceof QueryBuilder) {
                $plan = $query->getPlan();
                $sql = $this->compiler->compile($plan);
                $analyser = new PlanAnalyser($plan);
                $bindings = $analyser->getBindings($this->dialect);
                return [$sql, $bindings];
            }
            return [$query, []];
        }

        ##############################################################################
        #                               PUBLIC METHODS                               #
        ##############################################################################

        /**
         * Adds a foreign key to a table.
         * @param string $name The foreign key name.
         * @param string|TableIdentifier $table The table.
         * @param string|TableIdentifier $targetTable The target table.
         * @param array $localColumns The local columns.
         * @param array $targetColumns The target columns.
         * @param ReferentialAction|null $onDelete The action on delete (optional).
         * @param ReferentialAction|null $onUpdate The action on update (optional).
         * @return bool Whether the foreign key was added successfully.
         */
        public function addForeignKey (
            string $name,
            string|TableIdentifier $table,
            string|TableIdentifier $targetTable,
            array $localColumns,
            array $targetColumns,
            ?ReferentialAction $onDelete = null,
            ?ReferentialAction $onUpdate = null
        ) : bool {
            $query = $this->dialect->compileAlterTableAddForeignKey($table, $name, $targetTable, $localColumns, $targetColumns, $onDelete, $onUpdate);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Adds an index to a table.
         * @param string $name The index name.
         * @param string|TableIdentifier $table The table.
         * @param array $columns The columns to index.
         * @param IndexType|null $type The index type (optional).
         * @param IndexAlgorithm|null $algorithm The index algorithm (optional).
         * @return bool Whether the index was added successfully.
         */
        public function addIndex (
            string $name,
            string|TableIdentifier $table,
            array $columns,
            ?IndexType $type = null,
            ?IndexAlgorithm $algorithm = null
        ) : bool {
            $query = $this->dialect->compileAlterTableAddIndex($name, $table, $columns, $type, $algorithm);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Adds a primary key to a table.
         * @param string|TableIdentifier $table The table.
         * @param array $columns The columns to include in the primary key.
         * @return bool Whether the primary key was added successfully.
         */
        public function addPrimaryKey (string|TableIdentifier $table, array $columns) : bool {
            $query = $this->dialect->compileAlterTableAddPrimaryKey($table, $columns);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Adds a unique key to a table.
         * @param string $name The unique key name.
         * @param string|TableIdentifier $table The table.
         * @param array $columns The columns to include in the unique key.
         * @return bool Whether the unique key was added successfully.
         */
        public function addUniqueKey (string $name, string|TableIdentifier $table, array $columns) : bool {
            $query = $this->dialect->compileAlterTableAddUniqueKey($name, $table, $columns);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Drops a column from a table.
         * @param string|TableIdentifier $table The table.
         * @param string $column The column name.
         * @return bool Whether the column was dropped successfully.
         */
        public function dropColumn (string|TableIdentifier $table, string $column) : bool {
            $query = $this->dialect->compileAlterTableDropColumn($table, $column);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Drops a database/schema.
         * @param string $database The database name.
         * @param bool $ifExists Whether to include IF EXISTS clause.
         * @return bool Whether the database was dropped successfully.
         */
        public function dropDatabase (string $database, bool $ifExists = true) : bool {
            $query = $this->dialect->compileDropDatabase($database, $ifExists);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Drops a foreign key from a table.
         * @param string|TableIdentifier $table The table.
         * @param string $name The foreign key name.
         * @return bool Whether the foreign key was dropped successfully.
         */
        public function dropForeignKey (string|TableIdentifier $table, string $name) : bool {
            $query = $this->dialect->compileAlterTableDropForeignKey($table, $name);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Drops an index from a table.
         * @param string|TableIdentifier $table The table.
         * @param string $name The index name.
         * @return bool Whether the index was dropped successfully.
         */
        public function dropIndex (string|TableIdentifier $table, string $name) : bool {
            $query = $this->dialect->compileAlterTableDropIndex($table, $name);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Drops the primary key from a table.
         * @param string|TableIdentifier $table The table.
         * @return bool Whether the primary key was dropped successfully.
         */
        public function dropPrimaryKey (string|TableIdentifier $table) : bool {
            $query = $this->dialect->compileAlterTableDropPrimaryKey($table);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Drops a table from the database.
         * @param string|TableIdentifier $table The table to drop.
         * @param bool $ifExists Whether to include IF EXISTS clause.
         * @return bool Whether the table was dropped successfully.
         */
        public function dropTable (string|TableIdentifier $table, bool $ifExists = true) : bool {
            $query = $this->dialect->compileDropTable($table, $ifExists);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Performs a bulk load of data into a table using a temporary CSV file.
         * @param string|TableIdentifier $table The table.
         * @param array $rows The rows to load.
         * @param array $fields The fields to load (optional).
         * @return bool Whether the bulk load was successful.
         */
        public function bulkLoad (string|TableIdentifier $table, array $rows, array $fields = []) : bool {
            $table = $this->normaliseTable($table);
            $dialect = $this->getDialect();
            
            $stream = fopen("php://temp/maxmemory:5242880", "w+"); 
            
            try {
                foreach ($rows as $row) {
                    // Use dialect to format the row for CSV compatibility
                    $formattedRow = array_map(function ($v) use ($dialect) {
                        if ($v === null) return $dialect->getNullInternal();
                        if (is_bool($v)) return $v ? '1' : '0';
                        return $v;
                    }, $row);
                    
                    fputcsv($stream, $formattedRow);
                }
                
                rewind($stream);
        
                return $this->transact(function () use ($stream, $table, $fields) {
                    $this->driver->prepareForBulkLoad();
                    return $this->driver->executeBulkStream($table, $stream, $fields);
                });
            }
            finally {
                if (is_resource($stream)) fclose($stream);
            }
        }

        /**
         * Indicates whether a column exists in a table.
         * @param string|ColumnIdentifier $column The column name or identifier.
         * @param string|TableIdentifier $table The table name or identifier.
         * @param string|null $database The database name (optional).
         * @return bool Whether the column exists.
         */
        public function columnExists (string|ColumnIdentifier $column, string|TableIdentifier $table, ?string $database = null) : bool {
            $query = $this->dialect->compileColumnExists($column, $table, $database);
            $result = $this->driver->fetchColumn($query->getQuery(), $query->getBindings());
            return $result > 0;
        }
        
        /**
         * Indicates whether a database/schema exists.
         * @param string $database The name of the database.
         * @return bool Whether the database exists.
         */
        public function databaseExists (string $database) : bool {
            $query = $this->dialect->compileDatabaseExists($database);
            return (int) $this->fetchValue($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Deletes rows from a table.
         * @param string[]|TableIdentifier[]|string|TableIdentifier $table The table(s) to delete from.
         * @param array $filter The filter conditions.
         * @param array|string|TableIdentifier|null $targets The target tables for multi-table deletes (optional).
         * @return int The number of affected rows.
         * @throws RuntimeException If the filter is empty.
         */
        public function delete (array|string|TableIdentifier $sources, array $filter, array|string|TableIdentifier|null $targets = null) : int {
            if (empty($filter)) {
                throw new RuntimeException("Delete operation requires a filter to prevent accidental full-table deletions.");
            }
            $filter = new Filter($filter);
            $expression = $filter->getExpression();
            $bindings = $filter->getBindings($this->dialect);
            $sql = $this->dialect->compileDelete($sources, $targets, $expression);
            return $this->driver->execute($sql, $bindings);
        }

        /**
         * Executes a query and returns the number of affected rows.
         * @param QueryBuilder|string $query The query to execute.
         * @param array $bindings The bindings for the query.
         * @return int The number of affected rows.
         */
        public function execute (QueryBuilder|string $query, array $bindings = []) : int {
            [$query, $bindings] = $this->resolveQuery($query);
            return $this->driver->execute($query, $bindings);
        }

        /**
         * Fetches a single row from the result of a query.
         * @param QueryBuilder|string $query The query to execute.
         * @param array $bindings The bindings for the query.
         * @return array|null The fetched row or null if not found.
         */
        public function fetch (QueryBuilder|string $query, array $bindings = []) : ?array {
            [$sql, $bindings] = $this->resolveQuery($query);
            return $this->driver->fetch($sql, $bindings);
        }

        /**
         * Fetches all rows from the result of a query.
         * @param QueryBuilder|string $query The query to execute.
         * @param array $bindings The bindings for the query.
         * @return array The fetched rows.
         */
        public function fetchAll (QueryBuilder|string $query, array $bindings = []) : array {
            [$sql, $bindings] = $this->resolveQuery($query);
            return $this->driver->fetchAll($sql, $bindings);
        }

        /**
         * Fetches a single column from the result of a query.
         * @param QueryBuilder|string $query The query to execute.
         * @param array|int $bindingsOrColumn The bindings for the query or the column index.
         * @param int $column The column index to fetch.
         * @return mixed The fetched column value.
         */
        public function fetchColumn (QueryBuilder|string $query, array|int $bindingsOrColumn = [], int $column = 0) : mixed {
            [$sql, $bindings] = $this->resolveQuery($query);

            if (is_int($bindingsOrColumn)) {
                $column = $bindingsOrColumn;
                $bindings = [];
            }
            else {
                $bindings = $bindingsOrColumn;
            }

            return $this->driver->fetchColumn($sql, $bindings, $column);
        }

        /**
         * Fetches a single value from the result of a query.
         * @param QueryBuilder|string $query The query to execute.
         * @param array $bindings The bindings for the query.
         * @return mixed The fetched value.
         */
        public function fetchValue (QueryBuilder|string $query, array $bindings = []) : mixed {
            return $this->fetchColumn($query, $bindings, 0);
        }

        /**
         * Gets the SQL dialect of a connection.
         * @return SQLDialect The SQL dialect.
         */
        public function getDialect () : SQLDialect {
            return $this->dialect;
        }

        /**
         * Gets the SQL driver of a connection.
         * @return SQLDriver The SQL driver.
         */
        public function getDriver () : SQLDriver {
            return $this->driver;
        }

        /**
         * Gets the errors of a connection.
         * @return array[] The errors.
         */
        public function getErrors () : array {
            return $this->errors;
        }

        /**
         * Gets the last error of a connection.
         * @return array|null The last error.
         */
        public function getLastError () : ?array {
            return $this->driver->getLastError();
        }

        /**
         * Gets the last insert ID of a connection.
         * @param string|null $name The name of the sequence (if applicable).
         * @return string|int The last insert ID.
         */
        public function getLastInsertId (?string $name = null) : string|int {
            return $this->driver->getLastInsertId($name);
        }

        /**
         * Gets the auto-increment column of a table.
         * @param string|TableIdentifier $table The table.
         * @return string|null The auto-increment column name or null if none exists.
         */
        public function getTableAutoIncrementColumn (string|TableIdentifier $table) : ?string {
            $table = $this->normaliseTable($table);
            $query = $this->dialect->compileTableAutoIncrementColumn(
                $table->getName(), 
                $table->getSchema() ?? $this->activeDatabase
            );
        
            return $this->driver->fetchColumn($query->getQuery(), $query->getBindings());
        }

        /**
         * Gets the definition of a specific column in a table.
         * @param string|TableIdentifier $table The table.
         * @param string $column The column name.
         * @return array|null The column definition or null if not found.
         */
        public function getTableColumn (string|TableIdentifier $table, string $column) : ?array {
            $table = $this->normaliseTable($table);
            $query = $this->dialect->compileTableColumn($table->getName(), $column, $table->getSchema() ?? $this->activeDatabase);
            return $this->driver->fetch($query->getQuery(), $query->getBindings());
        }

        /**
         * Gets the columns of a table.
         * @param string|TableIdentifier $table The table.
         * @return array The columns.
         */
        public function getTableColumns (string|TableIdentifier $table) : array {
            $table = $this->normaliseTable($table);
            $query = $this->dialect->compileTableColumns($table->getName(), $table->getSchema());
            return $this->driver->fetchValues($query->getQuery(), $query->getBindings() ?? []);
        }

        /**
         * Gets the foreign keys of a table.
         * @param string|TableIdentifier $table The table name.
         * @return array An array of foreign keys, each represented as an array of column names.
         * @throws RuntimeException If the SQL dialect does not support fetching foreign keys.
         */
        public function getTableForeignKeys (string|TableIdentifier $table) : array {
            $table = $this->normaliseTable($table);
            if ($table->getSchema() === null) {
                $table = $table->withSchema($this->activeDatabase);
            }
            $query = $this->dialect->compileTableForeignKeys($table);
            $columnMap = $query->getColumnMap();

            if (!isset($columnMap->columns)) {
                $class = $this->dialect::class;
                throw new RuntimeException("The SQL dialect '$class' does not support fetching foreign keys.");
            }
            
            $results = $this->driver->fetchAll($query->getQuery(), $query->getBindings());

            $foreignKeys = [];
            if (isset($columnMap->name)) {
                foreach ($results as $row) {
                    $foreignKeys[$row[$columnMap->name]] = explode(',', $row[$columnMap->columns]);
                }
            }
            else {
                foreach ($results as $row) {
                    $foreignKeys[] = explode(',', $row[$columnMap->columns]);
                }
            }

            return $foreignKeys;
        }
        
        /**
         * Gets the primary key columns of a table.
         * @param string|TableIdentifier $table The table name.
         * @return array The primary key columns.
         */
        public function getTablePrimaryKey (string|TableIdentifier $table) : array {
            $table = $this->normaliseTable($table);
            $query = $this->dialect->compileTablePrimaryKey($table);
            
            $results = $this->driver->fetchAll($query->getQuery(), $query->getBindings());
        
            # If the dialect provided a custom filter (like SQLite's pk > 0 check) use it to filter the result set.
            if ($query->hasFilter()) {
                $results = array_filter($results, $query->getFilter());
            }
        
            return array_column($results, $query->getIndexKey());
        }

        /**
         * Gets the unique keys of a table.
         * @param string|TableIdentifier $table The table name.
         * @return array An array of unique keys, each represented as an array of column names.
         * @throws RuntimeException If the SQL dialect does not support fetching unique keys.
         */
        public function getTableUniqueKeys (string|TableIdentifier $table) : array {
            $table = $this->normaliseTable($table);
            if ($table->getSchema() === null) {
                $table = $table->withSchema($this->activeDatabase);
            }
            $query = $this->dialect->compileTableUniqueKeys($table);
            $columnMap = $query->getColumnMap();

            if (!isset($columnMap->columns)) {
                $class = $this->dialect::class;
                throw new RuntimeException("The SQL dialect '$class' does not support fetching unique keys.");
            }
            
            $results = $this->driver->fetchAll($query->getQuery(), $query->getBindings());

            $uniqueKeys = [];
            if (isset($columnMap->name)) {
                foreach ($results as $row) {
                    $uniqueKeys[$row[$columnMap->name]] = explode(',', $row[$columnMap->columns]);
                }
            }
            else {
                foreach ($results as $row) {
                    $uniqueKeys[] = explode(',', $row[$columnMap->columns]);
                }
            }

            return $uniqueKeys;
        }

        /**
         * Inserts a new row into a table.
         * @param string|TableIdentifier $table The table.
         * @param array $data The data to insert.
         * @param array $returnColumns The columns to return (optional).
         * @return array|bool The inserted row data if returnColumns is specified, otherwise a boolean indicating success.
         */
        public function insert (string|TableIdentifier $table, array $data, array $returnColumns = []) : array|bool {
            $table = $this->normaliseTable($table);

            # 1. Compile the base insert query.
            $compiled = $this->dialect->compileInsert($table, $data);
            $sql = $compiled->getQuery();
            $bindings = $compiled->getBindings();

            # 2. Handle the returning logic.
            if (!empty($returnColumns)) {
                # 3.1. For dialects supporting RETURNING, append a returning clause to the main query.
                if ($this->dialect->supportsReturning()) {
                    $sql .= " " . $this->dialect->compileReturning($returnColumns);
                    return $this->driver->fetch($sql, $bindings);
                }

                # 3.2. Fallback for dialects with no RETURNING support.
                return $this->transact(function () use ($sql, $data, $table, $returnColumns, $bindings) {
                    $this->driver->execute($sql, $bindings);

                    $uniqueKeys = [];
                    $primaryKey = $this->getTablePrimaryKey($table->getName());

                    if (empty($primaryKey)) {
                        $uniqueKeys = $this->getTableUniqueKeys($table->getName());
                    }
                    else $uniqueKeys[] = $primaryKey;
                    
                    $criteria = [];
                    if (!empty($uniqueKeys)) {
                        foreach ($uniqueKeys as $keyColumns) {
                            $match = true;
                            $currentCriteria = [];
                            foreach ($keyColumns as $column) {
                                if (!isset($data[$column])) {
                                    $match = false;
                                    break;
                                }
                                $currentCriteria[] = ["@$column", $data[$column]];
                            }
                            if ($match) {
                                $criteria = $currentCriteria;
                                break;
                            }
                        }
                    }

                    # 3.3. If no unique key match is found, fall back to LastInsertId as a last resort, assuming "id" is the primary key.
                    if (empty($criteria)) {
                        $lastId = $this->getLastInsertId();
                        $aiColumn = $this->getTableAutoIncrementColumn($table->getName()) ?: "id"; 
                        $criteria = [["@$aiColumn", $lastId]];
                    }

                    return $this->select($table->getName(), $criteria, $returnColumns);
                });
            }

            # 4. Standard Insert (No columns to return).
            return $this->driver->execute($sql, $bindings) > 0;
        }
        
        /**
         * Inserts multiple rows into a table.
         * @param string|TableIdentifier $table The table name.
         * @param array $rows The rows to insert.
         * @param array $fields The fields to insert (optional).
         * @param array $returnColumns The columns to return (optional).
         * @return array|bool An array of inserted row data if returnColumns is specified, otherwise a boolean indicating success.
         */
        public function insertMany (string|TableIdentifier $table, array $rows, array $fields = [], array $returnColumns = []) : array|bool {
            $table = $this->normaliseTable($table);
            $rowCount = count($rows);
            if ($rowCount === 0) return [];
        
            $dialect = $this->getDialect();
            $maxInsert = $this->config["maxRowsUsingInsert"] ?? $this->config["max_rows_using_insert"] ?? 1000;
            $maxPacket = $this->config["maxPacketSize"] ?? $this->config["max_packet_size"] ?? 1024**2;
            $canReturn = !empty($returnColumns) && $dialect->supportsReturning();
        
            # Case A: Standard multi-row INSERT.
            if ($rowCount <= $maxInsert && $this->estimatePayloadSize($rows) <= $maxPacket * .8) {
                $compiled = $dialect->compileMultiInsert($table, $fields, $rows);
                $sql = $compiled->getQuery();

                if ($canReturn) {
                    $sql .= " " . $dialect->compileReturning($returnColumns);
                    return $this->driver->fetchAll($sql, $compiled->getBindings());
                }

                $this->driver->execute($sql, $compiled->getBindings());
                return true;
            }

            # Case B: High-performance Bulk Import.
            return $this->bulkLoad($table, $rows, $fields);
        }

        /**
         * Indicates whether a connection is open.
         * @return bool Whether the connection is open.
         */
        public function isOpen () : bool {
            return $this->driver->isConnected();
        }

        /**
         * Renames a column in a table.
         * @param string|TableIdentifier $table The table.
         * @param string $oldName The old column name.
         * @param string $newName The new column name.
         * @return bool Whether the column was renamed successfully.
         */
        public function renameColumn (string|TableIdentifier $table, string $oldName, string $newName) : bool {
            $query = $this->dialect->compileAlterTableRenameColumn($table, $oldName, $newName);
            return $this->driver->execute($query->getQuery(), $query->getBindings()) > 0;
        }

        /**
         * Selects rows from a table.
         * @param string|TableIdentifier $table The table.
         * @param array $filter The filter conditions.
         * @param array $columns The columns to select.
         * @param array|null $order The order conditions (optional).
         * @param int|null $limit The maximum number of rows to return (optional).
         * @param int $offset The number of rows to skip (optional).
         * @param LockType $lock The lock type (optional).
         * @return array The selected rows.
         */
        public function select (string|TableIdentifier $table, array $filter = [], array $columns = ['*'], ?array $order = null, ?int $limit = null, int $offset = 0, LockType $lock = LockType::None) : array {
            $filter = new Filter($filter);
            $query = $this->dialect->compileSelect($table, $columns, $filter->getExpression(), $order, $limit, $offset, $lock);
            return $this->driver->fetchAll($query->getQuery(), $filter->getBindings($this->dialect));
        }

        /**
         * Selects a single column from a table.
         * @param string|TableIdentifier $table The table.
         * @param string $column The column to select.
         * @param array $filter The filter conditions.
         * @param array|null $order The order conditions (optional).
         * @param int|null $limit The maximum number of rows to return (optional).
         * @param int $offset The number of rows to skip (optional).
         * @param LockType $lock The lock type (optional).
         * @return array The selected column values.
         */
        public function selectColumn (string|TableIdentifier $table, string $column, array $filter = [], ?array $order = null, ?int $limit = null, int $offset = 0, LockType $lock = LockType::None) : array {
            $filter = new Filter($filter);
            $query = $this->dialect->compileSelect($table, [$column], $filter->getExpression(), $order, $limit, $offset, $lock);
            return $this->driver->fetchValues($query->getQuery(), $filter->getBindings($this->dialect, 0));
        }
        
        /**
         * Sets the active database for the connection.
         * @param string $database The database name.
         * @return static The current connection instance.
         */
        public function setDatabase (string $database) : static {
            $this->activeDatabase = $database;
            $sql = $this->dialect->compileSetDatabase($database);
            $this->driver->execute($sql);
            return $this;
        }
        
        /**
         * Sets the active timezone for the connection.
         * @param string $timezone The timezone identifier.
         * @return static The current connection instance.
         */
        public function setTimezone (string $timezone) : static {
            $this->activeTimezone = $timezone;
            $sql = $this->dialect->compileSetTimezone($timezone);
            $this->driver->execute($sql, [$timezone]);
            return $this;
        }

        /**
         * Indicates whether a table exists in a database.
         * @param string|TableIdentifier $table The table name or identifier.
         * @param string|null $database The database name (optional).
         * @return bool Whether the table exists.
         */
        public function tableExists (string|TableIdentifier $table, ?string $database = null) : bool {
            $query = $this->dialect->compileTableExists($table, $database);
            $result = $this->driver->fetchColumn($query->getQuery(), $query->getBindings());
            return $result > 0;
        }

        /**
         * Executes a series of operations within a transaction. Supports nested transactions using savepoints.
         * @param callable $operation The operation to execute. Receives commit, rollback, and save point callables.
         * @param bool $throws Whether to re-throw exceptions after rollback.
         * @return mixed The result of the operation.
         * @throws Throwable If an error occurs and $throws is true.
         */
        public function transact (callable $operation, bool $throws = true) : mixed {
            $this->transactionLevel++;

            try {
                if ($this->transactionLevel === 1) {
                    $this->driver->beginTransaction();
                }
                else {
                    # Automatically create a savepoint for the nested level.
                    $this->executeSavepoint("wingman_sp_{$this->transactionLevel}");
                }

                $result = $operation($this);

                # Only commit the root transaction.
                if ($this->transactionLevel === 1) $this->executeCommit();

                return $result;
            }
            catch (Throwable $e) {
                $this->executeRollback();
                if ($throws) throw $e;
                return false;
            }
            finally {
                $this->transactionLevel = max(0, $this->transactionLevel - 1);
            }
        }

        /**
         * Truncates a table.
         * @param string|TableIdentifier $table The table to truncate.
         * @return bool Whether the truncate was successful.
         */
        public function truncate (string|TableIdentifier $table) : bool {
            $query = $this->dialect->compileTruncate($table);
            return $this->driver->execute($query->getQuery());
        }

        /**
         * Updates rows in a table.
         * @param string $table The table name.
         * @param array $data The data to update.
         * @param array $filter The filter conditions (optional).
         * @param array $returnColumns The columns to return (optional).
         * @return array|int The updated row data if returnColumns is specified, otherwise the number of affected rows.
         */
        public function update (string|TableIdentifier $table, array $data, array $filter = [], array $returnColumns = []) : array|int {
            if (empty($data)) return 0;

            $table = $this->normaliseTable($table);
            
            $filter = new Filter($filter);
            $expression = $filter->getExpression();
            $query = $this->dialect->compileUpdate($table, $data, $expression);
            $bindings = array_merge(array_values($data), $filter->getBindings($this->dialect));
        
            return $this->executeWithReturningCapability($table, $query->getQuery(), $bindings, $returnColumns, $expression);
        }

        /**
         * Updates multiple rows in a table in a single query using CASE statements.
         * @param string|TableIdentifier $table The table.
         * @param array $data The data to update.
         * @param array $filters Additional filter conditions.
         * @param array|string $fixedKey The primary key column(s).
         * @param array $returnColumns The columns to return (optional).
         * @return array|int The updated row data if returnColumns is specified, otherwise the number of affected rows.
         */
        public function updateMany (string|TableIdentifier $table, array $data, array $filter = [], array|string $fixedKey = [self::DEFAULT_AI_COLUMN], array $returnColumns = []) : array|int {
            if (empty($data)) return 0;

            $table = $this->normaliseTable($table);
            $fixedKeys = (array) $fixedKey;

            $filter = new Filter($filter);
            $expression = $filter->getExpression();
            $query = $this->dialect->compileUpdateMany($table, $data, $fixedKeys, $expression);
            $bindings = array_merge($query->getBindings(), $filter->getBindings($this->dialect));

            return $this->executeWithReturningCapability($table, $query->getQuery(), $bindings, $returnColumns, $expression);
        }
        
        /**
         * Inserts a row or updates it if a unique/primary key conflict occurs.
         * @param string|TableIdentifier $table
         * @param array $data The data to insert.
         * @param array|null $updateColumns Specific columns to update. If null, updates all non-key columns.
         * @param array $returnColumns The columns to return (optional).
         * @return array|int The updated row data if returnColumns is specified, otherwise the number of affected rows.
         */
        public function upsert (string|TableIdentifier $table, array $data, ?array $updateColumns = null, array $returnColumns = []) : array|int {
            if (empty($data)) return 0;

            $table = $this->normaliseTable($table);

            # If no update columns are specified, we update everything that isn't part of a unique/primary key.
            if ($updateColumns === null) {
                $keys = array_merge($this->getTablePrimaryKey($table), ...array_values($this->getTableUniqueKeys($table)));
                $updateColumns = array_diff(array_keys($data), $keys);
            }

            $query = $this->dialect->compileUpsert($table, $data, $updateColumns);

            return $this->executeWithReturningCapability($table, $query->getQuery(), $query->getBindings(), $returnColumns, null);
        }
    }
?>