<?php
    /*/
	 * Project Name:    Wingman — Database — MySQL Dialect
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 26 2026
    /*/

    # Use the Database.Dialects namespace.
    namespace Wingman\Database\Dialects;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use LogicException;
    use SplObjectStorage;
    use Wingman\Database\Compilers\ExpressionCompiler;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Enums\ConflictStrategy;
    use Wingman\Database\Enums\Component;
    use Wingman\Database\Enums\IndexAlgorithm;
    use Wingman\Database\Enums\IndexType;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Enums\NullPrecedence;
    use Wingman\Database\Enums\OrderDirection;
    use Wingman\Database\Enums\SetOperation;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\SQLDialect;
    use Wingman\Database\Objects\CompiledQuery;
    use Wingman\Database\Plan\DeleteNode;
    use Wingman\Database\Plan\InsertNode;
    use Wingman\Database\Plan\UpsertNode;
    use Wingman\Database\Enums\ReferentialAction;
    use Wingman\Database\Expressions\LiteralExpression;
    use Wingman\Database\Traits\Caster;

    /**
     * Represents the MySQL dialect.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class MySQLDialect implements SQLDialect {
        use Caster;

        /**
         * The default MySQL version.
         * @var string
         */
        public const DEFAULT_VERSION = "8.0.0";

        /**
         * The expression compiler.
         * @var ExpressionCompiler
         */
        protected ?ExpressionCompiler $compiler = null;

        /**
         * The default MySQL version.
         * @var string
         */
        protected string $version = self::DEFAULT_VERSION;

        /**
         * Constructs a new MySQL dialect.
         * @param string $version The MySQL version string.
         * @param ExpressionCompiler $compiler The expression compiler.
         */
        public function __construct (string $version = self::DEFAULT_VERSION, ?ExpressionCompiler $compiler = null) {
            $this->version = $version;
            $this->compiler = $compiler;
        }

        ############################################################################
        #                             PROTECTED METHODS                            #
        ############################################################################

        /**
         * Compiles a bulk WHERE clause for multiple rows based on fixed keys.
         * @param array $data The data rows.
         * @param array $fixedKey The fixed key columns.
         * @param array &$bindings The bindings array to populate.
         * @return string The compiled WHERE clause.
         */
        protected function compileBulkWhere (array $data, array $fixedKey, array &$bindings) : string {
            $quotedKeys = array_map([$this, "quoteIdentifier"], $fixedKey);
            $keyString = count($fixedKey) > 1 
                ? "(" . implode(", ", $quotedKeys) . ")" 
                : $quotedKeys[0];

            $placeholders = [];
            foreach ($data as $row) {
                if (count($fixedKey) > 1) {
                    $rowPlaceholders = array_fill(0, count($fixedKey), '?');
                    $placeholders[] = "(" . implode(", ", $rowPlaceholders) . ")";
                }
                else $placeholders[] = "?";

                foreach ($fixedKey as $key) {
                    $bindings[] = $row[$key];
                }
            }

            $allPlaceholders = implode(", ", $placeholders);
            
            return "$keyString IN ($allPlaceholders)";
        }

        /**
         * Formats a JOIN clause from the given join definition.
         * @param array $join An associative array containing:
         * - `'type' (string)`: The type of join (e.g., 'INNER', 'LEFT').
         * - `'table' (string)`: The table to join.
         * - `'on' (string)`: The ON condition for the join.
         * @return string The formatted JOIN clause.
         */
        protected function formatJoin (array $join) : string {
            $type = $join["type"]->value;
            $table = $this->quoteIdentifier($join["table"]);
            $on = $join["on"];
            
            return "{$type} JOIN {$table} ON {$on}";
        }

        /**
         * Normalises table sources into quoted identifiers.
         * @param array|string|TableIdentifier $tables The table(s) to normalise.
         * @return array An array of quoted table identifiers.
         */
        protected function normaliseSources (array|string|TableIdentifier $tables) : array {
            $tables = is_array($tables) ? $tables : [$tables];
            $quotedTables = [];

            foreach ($tables as $table) {
                if (is_string($table)) {
                    $table = TableIdentifier::from($table);
                }
                
                $quotedTables[] = $this->quoteIdentifier(
                    $table->getAlias() 
                    ? ($table->getName() . " AS " . $table->getAlias()) 
                    : $table->getName()
                );
            }

            return $quotedTables;
        }

        /**
         * Normalises a table identifier into a qualified name.
         * @param string|TableIdentifier $table The table to normalise.
         * @return string The qualified table name.
         */
        protected function normaliseTable (string|TableIdentifier $table) : string {
            if (is_string($table)) {
                $table = TableIdentifier::from($table);
            }
            return $this->quoteIdentifier($table->getQualifiedName());
        }

        /**
         * Normalises table source aliases into quoted identifiers.
         * @param array|string|TableIdentifier $tables The table(s) to normalise.
         * @return array An array of quoted table identifiers.
         */
        protected function normaliseTargets (array|string|TableIdentifier $tables) : array {
            $tables = is_array($tables) ? $tables : [$tables];
            $quotedTables = [];

            foreach ($tables as $table) {
                if (is_string($table)) {
                    $table = TableIdentifier::from($table);
                }
                $quotedTables[] = $this->quoteIdentifier($table->getAlias() ?: $table->getName());
            }

            return $quotedTables;
        }

        ##############################################################################
        #                              COMPILER METHODS                              #
        ##############################################################################
        
        /**
         * Builds an ALTER TABLE statement.
         * @param string|TableIdentifier $table The name of the table to alter.
         * @param CompiledQuery[] $commands The list of ALTER TABLE commands.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTable (string|TableIdentifier $table, array $commands) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            return new CompiledQuery("ALTER TABLE $quotedTable " . implode(", ", $commands));
        }

        /**
         * Builds an ALTER TABLE statement to add a foreign key.
         * @param string $name The name of the foreign key.
         * @param string|TableIdentifier $table The table to alter.
         * @param string|TableIdentifier $targetTable The target table for the foreign key.
         * @param array $localColumns The local columns in the foreign key.
         * @param array $targetColumns The target columns in the foreign key.
         * @param ReferentialAction|null $onDelete The action on delete.
         * @param ReferentialAction|null $onUpdate The action on update.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableAddForeignKey (
            string $name,
            string|TableIdentifier $table,
            string|TableIdentifier $targetTable,
            array $localColumns,
            array $targetColumns,
            ?ReferentialAction $onDelete = null,
            ?ReferentialAction $onUpdate = null
        ) : CompiledQuery {
            return $this->compileAlterTable($table, [
                new CompiledQuery("ADD " . $this->compileForeignKey($name, $targetTable, $localColumns, $targetColumns, $onDelete, $onUpdate)->getQuery())
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to add an index.
         * @param string $name The name of the index.
         * @param string|TableIdentifier $table The table to alter.
         * @param array $columns The columns that make up the index.
         * @param IndexType|null $type The type of the index (e.g., UNIQUE, FULLTEXT).
         * @param IndexAlgorithm|null $algorithm The algorithm of the index (e.g., BTREE, HASH).
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableAddIndex (
            string $name,
            string|TableIdentifier $table,
            array $columns,
            ?IndexType $type = null,
            ?IndexAlgorithm $algorithm = null
        ) : CompiledQuery {
            return $this->compileAlterTable($table, [
                new CompiledQuery("ADD " . $this->compileIndex($name, $columns, $type, $algorithm)->getQuery())
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to add a primary key.
         * @param string|TableIdentifier $table The table to alter.
         * @param array $columns The columns that make up the primary key.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableAddPrimaryKey (string|TableIdentifier $table, array $columns) : CompiledQuery {
            return $this->compileAlterTable($table, [
                new CompiledQuery("ADD " . $this->compilePrimaryKey($columns)->getQuery())
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to add an index.
         * @param string $name The name of the index.
         * @param string|TableIdentifier $table The table to alter.
         * @param array $columns The columns that make up the index.
         * @param IndexType|null $type The type of the index (e.g., UNIQUE, FULLTEXT).
         * @param IndexAlgorithm|null $algorithm The algorithm of the index (e.g., BTREE, HASH).
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableAddUniqueKey (
            string $name,
            string|TableIdentifier $table,
            array $columns
        ) : CompiledQuery {
            return $this->compileAlterTable($table, [
                new CompiledQuery("ADD " . $this->compileUniqueKey($name, $columns)->getQuery())
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to drop a column.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $name The name of the column to drop.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropColumn (string|TableIdentifier $table, string $name) : CompiledQuery {
            return $this->compileAlterTable($table, [
                $this->compileDropColumn($name)
            ]);
        }
        
        /**
         * Builds an ALTER TABLE statement to drop a foreign key.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $name The name of the foreign key to drop.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropForeignKey (string|TableIdentifier $table, string $name) : CompiledQuery {
            return $this->compileAlterTable($table, [
                $this->compileDropForeignKey($name)
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to drop an index.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $name The name of the index to drop.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropIndex (string|TableIdentifier $table, string $name) : CompiledQuery {
            return $this->compileAlterTable($table, [
                $this->compileDropIndex($name)
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to drop the primary key.
         * @param string|TableIdentifier $table The table to alter.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropPrimaryKey (string|TableIdentifier $table) : CompiledQuery {
            return $this->compileAlterTable($table, [
                $this->compileDropPrimaryKey()
            ]);
        }

        /**
         * Builds an ALTER TABLE statement to rename a column.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $from The current name of the column.
         * @param string $to The new name of the column.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableRenameColumn (string|TableIdentifier $table, string $from, string $to) : CompiledQuery {
            return $this->compileAlterTable($table, [
                $this->compileRenameColumn($from, $to)
            ]);
        }
        
        /**
         * Builds a query to check if a column exists in a table.
         * @param string|ColumnIdentifier $column The name of the column.
         * @param string|TableIdentifier|null $table The name of the table, or null to use the column's table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to check for column existence.
         * @throws LogicException If the table name is not provided and cannot be inferred.
         */
        public function compileColumnExists (string|ColumnIdentifier $column, string|TableIdentifier|null $table = null, ?string $schema = null) : CompiledQuery {
            if (is_string($column)) {
                $column = ColumnIdentifier::from($column);
            }
            if (is_string($table)) {
                $table = TableIdentifier::from($table);
            }
            if ($table instanceof TableIdentifier) {
                $schema ??= $table->getSchema();
                $table = $table->getName();
            }
            else $schema ??= null;

            $bindings = [];

            if (is_null($table)) {
                throw new LogicException("Table name must be provided if column is not associated with a table.");
            }

            if ($schema) {
                $bindings[] = $schema;
                $schemaString = '?';
            }
            else $schemaString = "DATABASE()";

            $bindings[] = $table ?? $column->getTable();
            $bindings[] = $column->getName();

            return new CompiledQuery(
                "SELECT COUNT(*) FROM `information_schema`.`columns` WHERE `table_schema` = $schemaString AND `table_name` = ? AND `column_name` = ?",
                $bindings
            );
        }
        
        /**
         * Compiles a concatenation expression.
         * @param Expression[] $expressions The expressions to concatenate.
         * @return CompiledQuery The compiled CONCAT SQL expression.
         */
        public function compileConcatenate (array $expressions) : CompiledQuery {
            return new CompiledQuery("CONCAT(" . implode(", ", array_map(fn ($expression) => $this->compiler->compile($expression), $expressions)) . ")");
        }

        /**
         * Compiles conflict references for UPSERT statements.
         * @param string $sql The SQL statement containing NEW.column references.
         * @return string The SQL statement with NEW.column references replaced by VALUES(column).
         */
        public function compileConflictReference (string $sql) : string {
            return preg_replace_callback('/NEW\.(\w+)/', function($matches) {
                return "VALUES(" . $this->quoteIdentifier($matches[1]) . ")";
            }, $sql);
        }

        /**
         * Builds a query to check if a database exists.
         * @param string $database The name of the database.
         * @return CompiledQuery The compiled query to check for database existence.
         */
        public function compileDatabaseExists (string $database) : CompiledQuery {
            return new CompiledQuery(
                "SELECT COUNT(*) FROM `information_schema`.`schemata` WHERE `schema_name` = ?",
                [$database]
            );
        }

        /**
         * Builds a DELETE statement from the given table and filter columns.
         * @param array|string|TableIdentifier $sources The source table(s) to delete from.
         * @param array|string|TableIdentifier|null $targets The target table(s) to delete.
         * @param Expression|null $filter The filter expression for the WHERE clause.
         * @return string The compiled SQL DELETE statement.
         */
        public function compileDelete (array|string|TableIdentifier $sources, array|string|TableIdentifier|null $targets, ?Expression $filter = null) : string {
            $targets = $targets ?? $sources;
            $sourceString = implode(", ", $this->normaliseSources($sources));
            $targetString = implode(", ", $this->normaliseTargets($targets));
            
            $whereSql = $filter ? " WHERE " . $this->compiler->compile($filter) : "";

            return sprintf("DELETE %s FROM %s%s", $targetString, $sourceString, $whereSql);
        }

        /**
         * Compiles a DROP COLUMN statement.
         * @param string $name The name of the column to drop.
         * @return CompiledQuery The compiled DROP COLUMN SQL.
         */
        public function compileDropColumn (string $name) : CompiledQuery {
            return new CompiledQuery("DROP COLUMN " . $this->quoteIdentifier($name));
        }

        /**
         * Compiles a DROP DATABASE statement.
         * @param string $database The name of the database to drop.
         * @param bool $ifExists Whether to include IF EXISTS clause.
         * @return CompiledQuery The compiled DROP DATABASE SQL.
         */
        public function compileDropDatabase (string $database, bool $ifExists = true) : CompiledQuery {
            $ifExistsSql = $ifExists ? "IF EXISTS " : "";
            return new CompiledQuery("DROP DATABASE $ifExistsSql" . $this->quoteIdentifier($database));
        }
        
        /**
         * Compiles a DROP FOREIGN KEY statement.
         * @param string $name The name of the foreign key to drop.
         * @return CompiledQuery The compiled DROP FOREIGN KEY SQL.
         */
        public function compileDropForeignKey (string $name) : CompiledQuery {
            return new CompiledQuery("DROP FOREIGN KEY " . $this->quoteIdentifier($name));
        }
        
        /**
         * Compiles a DROP INDEX statement.
         * @param string $name The name of the index to drop.
         * @return CompiledQuery The compiled DROP INDEX SQL.
         */
        public function compileDropIndex (string $name) : CompiledQuery {
            return new CompiledQuery("DROP INDEX " . $this->quoteIdentifier($name));
        }

        /**
         * Compiles a DROP PRIMARY KEY statement.
         * @return CompiledQuery The compiled DROP PRIMARY KEY SQL.
         */
        public function compileDropPrimaryKey () : CompiledQuery {
            return new CompiledQuery("DROP PRIMARY KEY");
        }

        /**
         * Compiles a DROP TABLE statement.
         * @param string|TableIdentifier $table The name of the table to drop.
         * @param bool $ifExists Whether to include IF EXISTS clause.
         * @return CompiledQuery The compiled DROP TABLE SQL.
         */
        public function compileDropTable (string|TableIdentifier $table, bool $ifExists = true) : CompiledQuery {
            $table = $this->normaliseTable($table);
            $ifExistsSql = $ifExists ? "IF EXISTS " : "";
            return new CompiledQuery("DROP TABLE $ifExistsSql{$table}");
        }

        /**
         * Compiles a DSN string from the given configuration.
         * @param array $config The database configuration array.
         * @return string The compiled DSN string.
         */
        public function compileDsn (array $config) : string {
            return sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config["host"] ?? "127.0.0.1",
                $config["port"] ?? 3306,
                $config["database"] ?? "",
                $config["charset"] ?? "utf8mb4"
            );
        }
        
        /**
         * Builds a foreign key constraint definition.
         * @param string $name The name of the foreign key constraint.
         * @param string|TableIdentifier $targetTable The target table for the foreign key.
         * @param array $localColumns The local columns in the foreign key.
         * @param array $targetColumns The target columns in the foreign key.
         * @param ReferentialAction|null $onDelete The action on delete.
         * @param ReferentialAction|null $onUpdate The action on update.
         * @return CompiledQuery The compiled foreign key constraint definition.
         */
        public function compileForeignKey (
            string $name,
            string|TableIdentifier $targetTable,
            array $localColumns,
            array $targetColumns,
            ?ReferentialAction $onDelete = null,
            ?ReferentialAction $onUpdate = null
        ) : CompiledQuery {
            $name = $this->quoteIdentifier($name);
            $targetTable = $this->normaliseTable($targetTable);
            $localKeys = [];
            $targetKeys = [];
            for ($i = 0; $i < count($localColumns); $i++) {
                $localKeys[] = $this->quoteIdentifier($localColumns[$i]);
                $targetKeys[] = $this->quoteIdentifier($targetColumns[$i]);
            }
            $onDelete ??= ReferentialAction::NoAction;
            $onUpdate ??= ReferentialAction::NoAction;

            $sql = sprintf(
                "CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)",
                $name,
                implode(", ", $localKeys),
                $targetTable,
                implode(", ", $targetKeys)
            );
            if ($onDelete && $onDelete !== ReferentialAction::NoAction) {
                $sql .= " ON DELETE " . $onDelete->value;
            }
            if ($onUpdate && $onUpdate !== ReferentialAction::NoAction) {
                $sql .= " ON UPDATE " . $onUpdate->value;
            }
        
            return new CompiledQuery($sql);
        }

        /**
         * Compiles an INDEX creation statement.
         * @param string $name The name of the index.
         * @param array $columns The columns that make up the index.
         * @param IndexType|null $type The type of the index (e.g., UNIQUE, FULLTEXT).
         * @param IndexAlgorithm|null $algorithm The algorithm of the index (e.g., BTREE, HASH).
         * @return CompiledQuery The compiled INDEX creation SQL.
         */
        public function compileIndex (string $name, array $columns, ?IndexType $type = null, ?IndexAlgorithm $algorithm = null) : CompiledQuery {
            $name = $this->quoteIdentifier($name);
            $type = ($type ?? IndexType::Plain)->value;
            $algorithm = ($algorithm ?? IndexAlgorithm::Btree)->value;
            $columns = implode(", ", array_map([$this, "quoteIdentifier"], $columns));
            $algorithm = $algorithm ? " USING $algorithm" : "";
            return new CompiledQuery("$type $name ($columns)$algorithm");
        }

        /**
         * Builds an INSERT statement.
         * @param string|TableIdentifier $table The name of the table to insert into.
         * @param array $data The columns to insert values into.
         * @return CompiledQuery The compiled SQL INSERT statement.
         */
        public function compileInsert (string|TableIdentifier $table, array $data) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            $quotedColumns = [];
            $bindings = [];
            foreach ($data as $column => $value) {
                $bindings[] = $value;

                if (is_numeric($column)) continue;
                
                $quotedColumns[] = $this->quoteIdentifier($column);
            }
            $quotedColumns = implode(", ", $quotedColumns);
            $placeholders = implode(", ", array_fill(0, count($data), '?'));
            $columnPart = $quotedColumns ? " ($quotedColumns)" : "";
            return new CompiledQuery(
                query: "INSERT INTO $quotedTable{$columnPart} VALUES ($placeholders)",
                bindings: $bindings
            );
        }

        /**
         * Builds a LIMIT/OFFSET clause.
         * @param int|null $limit The LIMIT value, or null for no limit.
         * @param int|null $offset The OFFSET value, or null for no offset.
         * @return string The compiled LIMIT/OFFSET clause.
         */
        public function compileLimitOffset (?int $limit, ?int $offset) : string {
            if ($limit === null && $offset > 0) {
                # MySQL quirk: requires limit if offset is present
                return "LIMIT 18446744073709551615 OFFSET {$offset}";
            }
            $sql = $limit !== null ? "LIMIT {$limit}" : "";
            if ($offset > 0) $sql .= " OFFSET {$offset}";
            return $sql;
        }

        /**
         * Builds a LOAD DATA INFILE statement.
         * @param string|TableIdentifier $table The name of the table to load data into.
         * @param string $file The path to the data file.
         * @param array $fields The list of fields to load.
         * @return string The compiled SQL LOAD DATA INFILE statement.
         */
        public function compileLoadData (string|TableIdentifier $table, string $file, array $fields) : string {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            $quotedFile  = $this->quoteValue($file);
            
            $columnList = "";
            if (!empty($fields)) {
                $columnList = "(" . implode(", ", array_map([$this, "quoteIdentifier"], $fields)) . ")";
            }
        
            return <<<SQL
                LOAD DATA LOCAL INFILE $quotedFile 
                INTO TABLE $quotedTable 
                FIELDS TERMINATED BY ',' 
                OPTIONALLY ENCLOSED BY '"' 
                LINES TERMINATED BY '\\n' 
                $columnList
            SQL;
        }

        /**
         * Renders a LOCK clause for SELECT statements.
         * @param LockType $type The type of lock (Exclusive or Shared).
         * @param int|null $timeout The lock timeout in seconds, or null for default behavior.
         * @param bool $skipLocked Whether to skip locked rows.
         * @return string The compiled LOCK clause.
         */
        public function compileLock (LockType $type, ?int $timeout, bool $skipLocked) : string {
            $sql = ($type === LockType::Exclusive) ? "FOR UPDATE" : "FOR SHARE";
            if ($skipLocked) return $sql . " SKIP LOCKED";
            if ($timeout === 0) return $sql . " NOWAIT";
            return $sql;
        }

        /**
         * Builds a multi-row INSERT statement.
         * @param string|TableIdentifier $table The name of the table to insert into.
         * @param array $fields The list of fields to insert values into.
         * @param array $rows The list of rows to insert.
         * @return CompiledQuery The compiled SQL multi-row INSERT statement.
         */
        public function compileMultiInsert (string|TableIdentifier $table, array $fields, array $rows) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            
            $columnString = "";
            if (!empty($fields)) {
                $quotedFields = array_map([$this, "quoteIdentifier"], $fields);
                $columnString = " (" . implode(', ', $quotedFields) . ")";
            }

            $valueGroups = [];
            $bindings = [];
            foreach ($rows as $row) {
                $placeholders = [];
                foreach ($fields as $field) {
                    $bindings[] = $row[$field] ?? null;
                    $placeholders[] = '?';
                }
                $valueGroups[] = "(" . implode(", ", $placeholders) . ")";
            }

            $valueString = implode(", ", $valueGroups);

            return new CompiledQuery(
                "INSERT INTO $quotedTable$columnString VALUES $valueString",
                $bindings
            );
        }

        /**
         * Compiles an ORDER BY clause with NULL precedence.
         * @param string $sql The SQL expression to order by.
         * @param OrderDirection $direction The direction of the order (ASC or DESC).
         * @param NullPrecedence $precedence The NULL precedence (FIRST or LAST).
         * @return string The compiled ORDER BY clause.
         */
        public function compileOrder (string $sql, OrderDirection $direction, NullPrecedence $precedence) : string {
            if ($precedence === NullPrecedence::Last && $direction === OrderDirection::Descending) {
                return "$sql IS NULL ASC, $sql DESC";
            }
            
            if ($precedence === NullPrecedence::First && $direction === OrderDirection::Ascending) {
                return "$sql IS NULL DESC, $sql ASC";
            }

            return "$sql {$direction->value}";
        }
        
        /**
         * Compiles a PRIMARY KEY constraint.
         * @param array $columns The columns that make up the primary key.
         * @return CompiledQuery The compiled PRIMARY KEY SQL.
         */
        public function compilePrimaryKey (array $columns) : CompiledQuery {
            $keys = array_map([$this, "quoteIdentifier"], $columns);
            return new CompiledQuery("PRIMARY KEY (" . implode(", ", $keys) . ")");
        }
        
        /**
         * Compiles a RENAME COLUMN statement.
         * @param string $from The current column name.
         * @param string $to The new column name.
         * @return CompiledQuery The compiled RENAME COLUMN SQL.
         */
        public function compileRenameColumn (string $from, string $to) : CompiledQuery {
            return new CompiledQuery("RENAME COLUMN " . $this->quoteIdentifier($from) . " TO " . $this->quoteIdentifier($to));
        }

        /**
         * Compiles the RETURNING clause for the dialect.
         * @param array $columns The columns to return.
         * @throws LogicException Always because MySQL does not support RETURNING clauses.
         */
        public function compileReturning (array $columns) : string {
            throw new LogicException("MySQL does not support RETURNING clauses.");
        }

        /**
         * Builds a SAVEPOINT statement.
         * @param string $name The name of the savepoint.
         * @return string The compiled SQL SAVEPOINT statement.
         */
        public function compileSavepoint (string $name) : string {
            return "SAVEPOINT " . $this->quoteIdentifier($name);
        }
        
        /**
         * Builds a ROLLBACK TO SAVEPOINT statement.
         * @param string $name The name of the savepoint.
         * @return string The compiled SQL ROLLBACK TO SAVEPOINT statement.
         */
        public function compileSavepointRollback (string $name) : string {
            return "ROLLBACK TO SAVEPOINT " . $this->quoteIdentifier($name);
        }
        
        /**
         * Builds a SELECT statement.
         * @param string|TableIdentifier $table The name of the table to select from.
         * @param array $columns The list of columns to select.
         * @param Expression|null $filter The filter expression for the WHERE clause.
         * @param array|string|int|null $order The order conditions (optional).
         * @param int|null $limit The maximum number of rows to return (optional).
         * @param int $offset The number of rows to skip (optional).
         * @param LockType $lock The type of lock to apply (optional).
         * @return CompiledQuery The compiled SQL SELECT statement.
         */
        public function compileSelect (string|TableIdentifier $table, array $columns = ['*'], ?Expression $filter = null, array|string|int|null $order = null, ?int $limit = null, int $offset = 0, LockType $lock = LockType::None) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            $columnString = implode(", ", array_map([$this, "quoteIdentifier"], $columns));
        
            $sql = "SELECT $columnString FROM $quotedTable";
        
            if ($filter !== null) {
                $sql .= $filter ? " WHERE " . $this->compiler->compile($filter) : "";
            }

            if (!empty($order)) {
                $direction = OrderDirection::Ascending;
                $nulls = NullPrecedence::None;
                $specs = [];

                # 1. Handle array input.
                if (is_array($order)) {
                    foreach ($order as $col => $dir) {
                        # If $dir is an array, it might be [OrderDirection, NullPrecedence].
                        if (is_array($dir)) {
                            $specs[] = [$col, $dir[0] ?? $direction, $dir[1] ?? $nulls];
                        }
                        else $specs[] = [$col, $dir, $nulls];
                    }
                } 
                # 2. Handle single column/expression input.
                else $specs[] = [$order, $direction, $nulls];

                $orderClauses = [];
                foreach ($specs as [$target, $dir, $precedence]) {
                    $normalisedTarget = match (true) {
                        $target instanceof Expression => $target,
                        is_int($target) => new LiteralExpression($target),
                        is_string($target) => ColumnIdentifier::from($target),
                        default => throw new InvalidArgumentException("Invalid order by target.")
                    };
                    $expression = $this->compiler->compile($normalisedTarget);
                    $dir = OrderDirection::resolve($dir);
                    $precedence = NullPrecedence::resolve($precedence);
                    $orderClauses[] = $this->compileOrder($expression, $dir, $precedence);
                }
                $sql .= " ORDER BY " . implode(", ", $orderClauses);
            }

            $limitOffsetSql = $this->compileLimitOffset($limit, $offset);

            if (!empty($limitOffsetSql)) {
                $sql .= " " . $limitOffsetSql;
            }

            if ($lock !== LockType::None) {
                $sql .= " " . $this->compileLock($lock, null, false);
            }

            return new CompiledQuery($sql);
        }

        /**
         * Builds a USE DATABASE statement.
         * @param string $database The name of the database to switch to.
         * @return string The compiled SQL USE DATABASE statement.
         */
        public function compileSetDatabase (string $database) : string {
            return "USE " . $this->quoteIdentifier($database);
        }
        
        /**
         * Builds a SET TIMEZONE statement.
         * @param string $timezone The timezone to set.
         * @return string The compiled SQL SET TIMEZONE statement.
         */
        public function compileSetTimezone (string $timezone) : string {
            return "SET time_zone = ?";
        }
        
        /**
         * Builds a query to retrieve the auto-increment column of a table.
         * @param string|TableIdentifier $table The table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve the auto-increment column name.
         */
        public function compileTableAutoIncrementColumn (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery {
            $bindings = [$table];
            $schemaSql = "DATABASE()";
        
            if ($schema) {
                $schemaSql = "?";
                $bindings[] = $schema;
            }
        
            return new CompiledQuery(
                <<<SQL
                    SELECT `column_name` 
                    FROM `information_schema`.`columns` 
                    WHERE `table_name` = ? 
                    AND `table_schema` = $schemaSql 
                    AND `extra` LIKE '%auto_increment%'
                SQL,
                $bindings
            );
        }

        /**
         * Builds a query to retrieve a specific table column.
         * @param string|ColumnIdentifier $column The name of the column.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve column information.
         * @throws LogicException If the table name is not provided and cannot be inferred.
         */
        public function compileTableColumn (string|ColumnIdentifier $column, string|TableIdentifier $table, ?string $schema = null) : CompiledQuery {
            if (is_string($column)) {
                $column = ColumnIdentifier::from($column);
            }
            if (is_string($table)) {
                $table = TableIdentifier::from($table);
            }
            if ($table instanceof TableIdentifier) {
                $schema ??= $table->getSchema();
                $table = $table->getName();
            }
            else $schema ??= null;
            

            $bindings = [];

            if (is_null($table)) {
                throw new LogicException("Table name must be provided if column is not associated with a table.");
            }

            if ($schema) {
                $bindings[] = $schema;
                $schemaString = '?';
            }
            else $schemaString = "DATABASE()";

            $bindings[] = $table ?? $column->getTable();
            $bindings[] = $column->getName();

            return new CompiledQuery(
                "SELECT * FROM `information_schema`.`columns` WHERE `table_schema` = $schemaString AND `table_name` = ? AND `column_name` = ?",
                $bindings,
                [
                    "catalog" => "TABLE_CATALOG",
                    "schema" => "TABLE_SCHEMA",
                    "table" => "TABLE_NAME",
                    "name" => "COLUMN_NAME",
                    "position" => "ORDINAL_POSITION",
                    "defaultValue" => "COLUMN_DEFAULT",
                    "isNullable" => "IS_NULLABLE",
                    "dataType" => "DATA_TYPE",
                    "maxLength" => "CHARACTER_MAXIMUM_LENGTH",
                    "octetLength" => "CHARACTER_OCTET_LENGTH",
                    "numericPrecision" => "NUMERIC_PRECISION",
                    "numericScale" => "NUMERIC_SCALE",
                    "datetimePrecision" => "DATETIME_PRECISION",
                    "characterSet" => "CHARACTER_SET_NAME",
                    "collation" => "COLLATION_NAME",
                    "type" => "COLUMN_TYPE",
                    "keyType" => "COLUMN_KEY",
                    "extra" => "EXTRA",
                    "privileges" => "PRIVILEGES",
                    "comment" => "COLUMN_COMMENT",
                    "generationExpr" => "GENERATION_EXPRESSION",
                    "srsId" => "SRS_ID"
                ]
            );
        }

        /**
         * Builds a query to retrieve table columns.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve column names.
         */
        public function compileTableColumns (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $schema = $schema ?? $table->getSchema();
                $table = $table->getName();
            }
            return new CompiledQuery(
                query: "SELECT `column_name` FROM `information_schema`.`columns` WHERE `table_name` = ? AND `table_schema` = ?",
                bindings: [$table, $schema],
                indexKey: "column_name"
            );
        }

        /**
         * Builds a query to check if a table exists.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to check for table existence.
         */
        public function compileTableExists (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery {
            if (is_string($table)) {
                $table = TableIdentifier::from($table);
            }
            $schema = $schema ?? $table->getSchema();
            $table = $table->getName();

            $bindings = [];

            if ($schema) {
                $bindings[] = $schema;
                $schemaString = '?';
            }
            else $schemaString = "DATABASE()";

            $bindings[] = $table;

            return new CompiledQuery(
                "SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema` = $schemaString AND `table_name` = ?",
                $bindings
            );
        }

        /**
         * Builds a query to retrieve the foreign keys of a table.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve foreign key information.
         */
        public function compileTableForeignKeys (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $schema = $schema ?? $table->getSchema();
                $table = $table->getName();
            }

            $sql = <<<SQL
                SELECT 
                    `CONSTRAINT_NAME` AS `fk_name`,
                    GROUP_CONCAT(`COLUMN_NAME` ORDER BY `ORDINAL_POSITION`) AS `local_columns`,
                    `REFERENCED_TABLE_NAME` AS `target_table`,
                    GROUP_CONCAT(`REFERENCED_COLUMN_NAME` ORDER BY `ORDINAL_POSITION`) AS `target_columns`
                FROM `information_schema`.`KEY_COLUMN_USAGE`
                WHERE `TABLE_NAME` = ? 
                AND `TABLE_SCHEMA` = ? 
                AND `REFERENCED_TABLE_NAME` IS NOT NULL
                GROUP BY `CONSTRAINT_NAME`, `REFERENCED_TABLE_NAME`
            SQL;

            return new CompiledQuery(
                $sql,
                [$table, $schema],
                [
                    "name" => "fk_name",
                    "local" => "local_columns",
                    "table" => "target_table",
                    "columns" => "target_columns"
                ]
            );
        }
        
        /**
         * Builds a query to retrieve the primary key columns of a table.
         * @param string|TableIdentifier $table The name of the table.
         * @return CompiledQuery The compiled query to retrieve primary key column names.
         */
        public function compileTablePrimaryKey (string|TableIdentifier $table) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getName();
            }
            return new CompiledQuery(
                query: "SHOW KEYS FROM " . $this->quoteIdentifier($table) . " WHERE `Key_name` = 'PRIMARY'",
                bindings: [],
                indexKey: "Column_name"
            );
        }

        /**
         * Builds a query to retrieve the unique keys of a table.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve unique key names and their columns.
         */
        public function compileTableUniqueKeys (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $schema = $schema ?? $table->getSchema();
                $table = $table->getName();
            }

            $sql = <<<SQL
                SELECT `tc`.`CONSTRAINT_NAME`, GROUP_CONCAT(`kcu`.`COLUMN_NAME` ORDER BY `kcu`.`ORDINAL_POSITION`) AS `COLUMNS`
                FROM `information_schema`.`TABLE_CONSTRAINTS` AS `tc`
                JOIN `information_schema`.`KEY_COLUMN_USAGE` AS `kcu`
                    ON `tc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME`
                    AND `tc`.`TABLE_SCHEMA` = `kcu`.`TABLE_SCHEMA`
                WHERE `tc`.`TABLE_NAME` = ?
                    AND `tc`.`TABLE_SCHEMA` = ?
                    AND `tc`.`CONSTRAINT_TYPE` = 'UNIQUE'
                GROUP BY `tc`.`CONSTRAINT_NAME`
            SQL;

            return new CompiledQuery(
                $sql,
                [$table, $schema],
                ["columns" => "COLUMNS", "name" => "CONSTRAINT_NAME"]
            );
        }

        /**
         * Builds a TRUNCATE TABLE statement.
         * @param string|TableIdentifier $table The name of the table to truncate.
         * @return CompiledQuery The compiled SQL TRUNCATE TABLE statement.
         */
        public function compileTruncate (string|TableIdentifier $table) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            return new CompiledQuery("TRUNCATE TABLE $quotedTable");
        }
        
        /**
         * Compiles a UNIQUE key constraint.
         * @param string $name The name of the unique key.
         * @param array $columns The columns that make up the unique key.
         * @return CompiledQuery The compiled UNIQUE key constraint SQL.
         */
        public function compileUniqueKey (string $name, array $columns) : CompiledQuery {
            $name = $this->quoteIdentifier($name);
            $keys = array_map([$this, "quoteIdentifier"], $columns);
            return new CompiledQuery("CONSTRAINT $name UNIQUE (" . implode(", ", $keys) . ")");
        }

        /**
         * Builds an UPDATE statement.
         * @param string|TableIdentifier $table The name of the table to update.
         * @param array $columns The columns to update.
         * @param Expression|null $filter The filter expression for the WHERE clause.
         * @return CompiledQuery The compiled SQL UPDATE statement.
         */
        public function compileUpdate (string|TableIdentifier $table, array $columns, ?Expression $filter = null) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            
            $set = implode(", ", array_map(fn ($column) => $this->quoteIdentifier($column) . " = ?", $columns));
            $where = $filter ? "WHERE " . $this->compiler->compile($filter) : "";
            
            return new CompiledQuery("UPDATE $quotedTable SET $set{$where}");
        }

        /**
         * Builds a bulk UPDATE statement for multiple rows.
         * @param string|TableIdentifier $table The name of the table to update.
         * @param array $data The data rows to update.
         * @param array $fixedKey The fixed key columns used to identify rows.
         * @param Expression|null $filter An optional filter expression for the WHERE clause.
         * @return CompiledQuery The compiled SQL bulk UPDATE statement.
         */
        public function compileUpdateMany (string|TableIdentifier $table, array $data, array $fixedKey, ?Expression $filter = null) : CompiledQuery {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            $bindings = [];
            $cases = [];
            $columns = array_keys($data[0]);
        
            foreach ($columns as $column) {
                if (in_array($column, $fixedKey)) continue;
        
                $quotedColumn = $this->quoteIdentifier($column);
                $caseParts = [];
        
                foreach ($data as $row) {
                    $conditions = [];
                    foreach ($fixedKey as $key) {
                        $conditions[] = $this->quoteIdentifier($key) . " = ?";
                        $bindings[] = $row[$key];
                    }
                    $caseParts[] = "WHEN " . implode(" AND ", $conditions) . " THEN ?";
                    $bindings[] = $row[$column];
                }
        
                # ELSE `column` ensures that if a row matches the WHERE but not a CASE, it retains its original value.
                $cases[] = "$quotedColumn = CASE " . implode(" ", $caseParts) . " ELSE $quotedColumn END";
            }
        
            $whereSql = $this->compileBulkWhere($data, $fixedKey, $bindings);
            $sql = "UPDATE $quotedTable SET " . implode(", ", $cases) . " WHERE $whereSql";
        
            return new CompiledQuery($sql, $bindings);
        }

        /**
         * Builds an UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) statement.
         * @param string|TableIdentifier $table The name of the table to upsert into.
         * @param array $data The data to insert.
         * @param array $updatedColumns The columns to update on duplicate key.
         * @return CompiledQuery The compiled SQL UPSERT statement.
         */
        public function compileUpsert (string|TableIdentifier $table, array $data, array $updatedColumns) : CompiledQuery {
            $table = $this->normaliseTable($table);
            $insert = $this->compileInsert($table, $data);
            $sql = $insert->getQuery();
            $updates = [];
            foreach ($updatedColumns as $column) {
                $quoted = $this->quoteIdentifier($column);
                $updates[] = "$quoted = VALUES($quoted)";
            }
            
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(", ", $updates);
            
            return new CompiledQuery($sql, $insert->getBindings());
        }

        /**
         * Gets the component order for DELETE statements.
         * @return array The ordered list of components for DELETE statements.
         */
        public function getDeleteOrder () : array {
            return [
                Component::Cte,
                Component::Joins,
                Component::Where,
                Component::OrderBy,
                Component::Limit
            ];
        }

        /**
         * Gets the component order for INSERT statements.
         * @return array The ordered list of components for INSERT statements.
         */
        public function getInsertOrder () : array {
            return [
                Component::Cte,
                Component::Sources,
                Component::Values,
                Component::Assignments
            ];
        }
        
        /**
         * Gets the internal representation of a NULL value for bulk loading.
         * @return string The string representing NULL in bulk loading.
         */
        public function getNullInternal () : string {
            return '\N';
        }

        /**
         * Gets the component order for a given plan node.
         * @param PlanNode $node The plan node.
         * @return array The ordered list of components for the plan node's operation.
         */
        public function getOrderForNode (PlanNode $node) : array {
            switch (true) {
                case $node instanceof DeleteNode: return $this->getDeleteOrder();
                case $node instanceof InsertNode:
                case $node instanceof UpsertNode: return $this->getInsertOrder();
            }
            return $this->getSelectOrder();
        }

        /**
         * Gets the component order for SELECT statements.
         * @return array The ordered list of components for SELECT statements.
         */
        public function getSelectOrder () : array {
            return [
                Component::Cte,
                Component::Projections,
                Component::Sources,
                Component::Joins,
                Component::Where,
                Component::GroupBy,
                Component::Having,
                Component::SetOperation,
                Component::OrderBy,
                Component::Limit,
                Component::Offset
            ];
        }

        /**
         * Gets the component order for UPDATE statements.
         * @return array The ordered list of components for UPDATE statements.
         */
        public function getUpdateOrder () : array {
            return [
                Component::Cte,
                Component::Joins,
                Component::Assignments,
                Component::Where,
                Component::OrderBy,
                Component::Limit
            ];
        }

        /**
         * Sets the expression compiler of a dialect.
         * @param ExpressionCompiler $compiler The expression compiler.
         * @return static The current instance for chaining.
         */
        public function setCompiler (ExpressionCompiler $compiler) : static {
            $this->compiler = $compiler;
            return $this;
        }
        
        /**
         * Indicates whether the dialect supports combined ALTER statements.
         * @return true Because MySQL supports combined ALTER statements.
         */
        public function supportsCombinedAlter () : bool {
            return true;
        }

        /**
         * Indicates whether the dialect supports DELETE statements with LIMIT clauses.
         * @return true Because MySQL supports DELETE statements with LIMIT clauses.
         */
        public function supportsDeleteLimit () : bool {
            return true;
        }

        /**
         * Indicates whether the dialect supports FULL OUTER JOINs.
         * @return false Because MySQL does not support FULL OUTER JOINs.
         */
        public function supportsFullOuterJoin () : bool {
            return false;
        }

        /**
         * Indicates whether the dialect supports LOCK clauses.
         * @return true Because MySQL supports LOCK clauses.
         */
        public function supportsLocking () : bool {
            return true;
        }
        
        /**
         * Indicates whether a dialect supports renaming columns.
         * @return bool Whether the dialect supports renaming columns.
         */
        public function supportsRenameColumn () : bool {
            return version_compare($this->version, "8.0.0", ">=");
        }

        /**
         * Indicates whether the dialect supports RETURNING clauses.
         * @return false Because MySQL does not support RETURNING clauses.
         */
        public function supportsReturning () : bool {
            return false;
        }

        /**
         * Quotes an identifier (table or column name).
         * @param string $name The identifier to quote.
         * @return string The quoted identifier.
         */
        public function quoteIdentifier (string $identifier) : string {
            if ($identifier === '*') return $identifier;

            # 1. Handle "Table AS Alias".
            if (stripos($identifier, " AS ") !== false) {
                $parts = preg_split('/\s+AS\s+/i', $identifier);
                return $this->quoteIdentifier($parts[0]) . " AS " . $this->quoteIdentifier($parts[1]);
            }
        
            # 2. Handle "Table.Column".
            if (str_contains($identifier, '.')) {
                $parts = explode('.', $identifier);
                return implode('.', array_map([$this, 'quoteIdentifier'], $parts));
            }
        
            # 3. Basic Quoting (avoid double quoting).
            $trimmed = trim($identifier, '`');
            return "`{$trimmed}`";
        }

        /**
         * Quotes a value for use in SQL statements.
         * @param mixed $value The value to quote.
         * @return string The quoted value.
         */
        public function quoteValue (mixed $value) : string {
            if (is_null($value)) return "NULL";
            if (is_bool($value)) return $value ? '1' : '0';
            if (is_numeric($value)) return (string) $value;
            return "'" . addslashes((string) $value) . "'";
        }

        ###########################################################################################################
        #                                    METHODS USED BY THE PLAN COMPILER                                    #
        ###########################################################################################################

        /**
         * Builds a DELETE statement from the given table and buckets.
         * @param string $table The name of the table to delete from.
         * @param array $buckets An associative array containing:
         * - `'join' (array)`: List of join definitions.
         * - `'where' (array)`: List of WHERE conditions.
         * - `'limit' (int|null)`: LIMIT value.
         * @return string The compiled SQL DELETE statement.
         */
        public function delete (TableIdentifier $target, string $sourceString, SplObjectStorage $bucket) : string {
            $quotedTarget = $this->quoteIdentifier($target->getAlias() ?? $target->getName());
            
            $sql = "DELETE $quotedTarget $sourceString";
        
            if (!empty($bucket[Component::Joins])) {
                $sql .= " " . $bucket[Component::Joins];
            }
        
            if (!empty($bucket[Component::Where])) {
                $sql .= " WHERE " . $bucket[Component::Where];
            }
        
            if (isset($bucket[Component::Limit])) {
                $sql .= " " . $this->compileLimitOffset($bucket[Component::Limit], $bucket[Component::Offset], null);
            }
        
            return $sql;
        }

        /**
         * Builds an INSERT statement from the given table and assignments.
         * @param string|TableIdentifier $table The name of the table to insert into.
         * @param array $data An associative array containing:
         * - `'columns' (array)`: List of columns to insert into.
         * - `'values' (array)`: List of value placeholders or subquery.
         * - `'subquery' (string, optional)`: A subquery string for INSERT ... SELECT.
         * - `'ignore' (bool)`: Whether to use INSERT IGNORE.
         * @return string The compiled SQL INSERT statement.
         */
        public function insert (string|TableIdentifier $table, array $data) : string {
            if ($table instanceof TableIdentifier) {
                $table = $table->getQualifiedName();
            }
            $quotedTable = $this->quoteIdentifier($table);
            $quotedCols = $data["columns"] ? "" : " (" . implode(", ", array_map([$this, "quoteIdentifier"], $data["columns"])) . ')';
            
            $ignore = $data["ignore"] ? " IGNORE" : "";
            $sql = "INSERT{$ignore} INTO {$quotedTable}{$quotedCols} ";

            if (isset($data["subquery"])) {
                return $sql . $data['subquery'];
            }

            # $data['values'] looks like ["(?, ?)", "(?, ?)"].
            return $sql . "VALUES " . implode(', ', $data["values"]);
        }

        /**
         * Appends a RETURNING clause to the given SQL statement.
         * @param string $sql The base SQL statement.
         * @param array $fields List of fields to return.
         * @return string The original SQL statement (MySQL does not support RETURNING).
         */
        public function returning (string $sql, array $fields) : string {
            return $sql;
        }

        /**
         * Builds a SELECT statement from the given buckets.
         * @param array $buckets An associative array containing query components:
         * - `'distinct' (bool)`: Whether to use DISTINCT.
         * - `'select' (array)`: List of columns to select.
         * - `'from' (array)`: List of tables to select from.
         * - `'join' (array)`: List of join definitions.
         * - `'where' (array)`: List of WHERE conditions.
         * - `'group' (array)`: List of GROUP BY columns.
         * - `'order' (array)`: List of ORDER BY clauses.
         * - `'limit' (int|`null): LIMIT value.
         * - `'offset' (int|`null): OFFSET value.
         * @return string The compiled SQL SELECT statement.
         */
        public function select (array $buckets) : string {
            $sql = $buckets["distinct"] ? "SELECT DISTINCT " : "SELECT ";
            $sql .= empty($buckets["select"]) ? "*" : implode(', ', $buckets["select"]);
            
            if (!empty($buckets["from"])) {
                $sql .= " FROM " . implode(', ', $buckets["from"]);
            }

            if (!empty($buckets["join"])) {
                foreach ($buckets["join"] as $join) {
                    $sql .= " " . $this->formatJoin($join);
                }
            }

            if (!empty($buckets["where"])) {
                $sql .= " WHERE " . implode(' AND ', $buckets["where"]);
            }

            if (!empty($buckets["group"])) {
                $sql .= " GROUP BY " . implode(', ', $buckets["group"]);
            }

            if (!empty($buckets["order"])) {
                $sql .= " ORDER BY " . implode(', ', $buckets["order"]);
            }

            if ($buckets["limit"] || $buckets["offset"]) {
                $sql .= " " . $this->compileLimitOffset($buckets["limit"], $buckets["offset"]);
            }

            return $sql;
        }

        /**
         * Builds a set operation (UNION, INTERSECT, EXCEPT) between two SQL queries.
         * @param string $left The left SQL query.
         * @param string $right The right SQL query.
         * @param SetOperation $operation The set operation ('UNION', 'INTERSECT', 'EXCEPT').
         * @return string The compiled SQL set operation.
         */
        public function setOperation (string $left, string $right, SetOperation $operation) : string {
            return "($left) {$operation->value} ($right)";
        }

        /**
         * Builds an UPDATE statement from the given table and buckets.
         * @param TableIdentifier $table The table to update.
         * @param array $data An associative array containing:
         * - `'columns' (array)`: List of columns to update.
         * @param SplObjectStorage $bucket An object storage containing:
         * - `'join' (array)`: List of join definitions.
         * - `'where' (array)`: List of WHERE conditions.
         * @return string The compiled SQL UPDATE statement.
         */
        public function update (TableIdentifier $table, array $data, SplObjectStorage $bucket) : string {
            $name = $this->quoteIdentifier($table->getName());
            $alias = $table->getAlias();
            $joins = $bucket[Component::Joins] ?? null;
            $where = $bucket[Component::Where] ?? null;
        
            # Determine whether we use the alias. In MySQL, "UPDATE table AS alias SET" is invalid for single tables
            # and is only valid if treated as a multiple-table update.
            $hasJoins = !empty($joins);
            $tableReference = ($hasJoins && $alias) 
                ? "{$name} AS " . $this->quoteIdentifier($alias) 
                : $name;
        
            $set = implode(", ", array_map(fn ($c) => $this->quoteIdentifier($c) . " = ?", $data['columns']));
        
            $sql = "UPDATE $tableReference";
            
            if ($joins) $sql .= " $joins";
            
            $sql .= " SET $set";
            
            if ($where) $sql .= " WHERE $where";
        
            return $sql;
        }

        /**
         * Builds a bulk UPDATE statement using CASE expressions.
         * @param TableIdentifier $table The table to update.
         * @param array $data An associative array containing:
         * - `'keys' (array)`: List of primary key columns.
         * - `'columns' (array)`: List of columns to update.
         * - `'values' (array)`: List of rows of value placeholders.
         * @param string $whereSql An optional WHERE clause to limit affected rows.
         * @return string The compiled SQL UPDATE statement.
         */
        public function updateBulk (TableIdentifier $table, array $data, string $whereSql = "") : string {
            $quotedTable = $this->quoteIdentifier($table->getName());
            $primaryKeys = $data["keys"];
            $columns = $data["columns"];
            $rows = $data["values"];

            $sets = [];
            foreach ($columns as $column) {
                if (in_array($column, $primaryKeys)) continue;

                $quotedColumn = $this->quoteIdentifier($column);
                $cases = [];

                foreach ($rows as $row) {
                    $condition = [];
                    foreach ($primaryKeys as $pk) {
                        $condition[] = $this->quoteIdentifier($pk) . " = ?";
                    }
                    $cases[] = "WHEN " . implode(" AND ", $condition) . " THEN ?";
                }

                $sets[] = "$quotedColumn = CASE " . implode(' ', $cases) . " ELSE $quotedColumn END";
            }

            $sql = "UPDATE $quotedTable SET " . implode(", ", $sets);
            
            if ($whereSql) $sql .= " WHERE $whereSql";

            return $sql;
        }

        /**
         * Builds an UPSERT statement from the given data.
         * @param string $table The name of the table to upsert into.
         * @param array $data An associative array containing:
         * - `'columns' (array)`: List of columns to insert/update.
         * - `'values' (array)`: List of value placeholders.
         * - `'update' (array)`: List of columns to update on duplicate key.
         * - `'update_values' (array)`: Associative array of column to raw expression or placeholder for update values.
         * @return string The compiled SQL UPSERT statement.
         */
        public function upsert (string $table, array $data) : string {
            $quotedTable = $this->quoteIdentifier($table);
            $colsArray = array_map([$this, "quoteIdentifier"], $data["columns"]);
            $cols = implode(", ", $colsArray);
            
            # 1. Determine the source of the data (VALUES or SUBQUERY).
            if (isset($data["subquery"])) {
                $sourceSql = (string) $data["subquery"];
            }
            else {
                $sourceSql = "VALUES " . implode(", ", $data["values"]);
            }
            
            $strategy = $data["strategy"];

            $colString = $cols ? " ($cols)" : "";
        
            # 2. Handle SKIP strategy (INSERT IGNORE).
            if ($strategy === ConflictStrategy::Skip) {
                return "INSERT IGNORE INTO {$quotedTable}$colString $sourceSql";
            }
        
            $sql = "INSERT INTO {$quotedTable}$colString $sourceSql";
            $updateParts = [];
        
            # 3. Handle OVERWRITE strategy.
            if ($strategy === ConflictStrategy::Overwrite) {
                foreach ($colsArray as $quoted) {
                    $updateParts[] = "$quoted = VALUES($quoted)";
                }
            } 
            # 4. Handle UPDATE strategy (Explicit assignments).
            else {
                foreach ($data["update"] as $col) {
                    $quoted = $this->quoteIdentifier($col);
                    $val = $data["update_values"][$col] ?? '?';
                    $updateParts[] = "$quoted = $val";
                }
            }
        
            return "$sql ON DUPLICATE KEY UPDATE " . implode(", ", $updateParts);
        }

        /**
         * Builds a WITH clause from the given definitions and main SQL.
         * @param array $definitions An associative array where keys are CTE names and values are their SQL definitions.
         * @param string $mainSql The main SQL query that uses the CTEs.
         * @param bool $recursive Whether the CTEs are recursive.
         * @return string The compiled SQL WITH clause followed by the main SQL.
         */
        public function with (array $definitions, string $mainSql, bool $recursive) : string {
            $prefix = $recursive ? "WITH RECURSIVE " : "WITH ";
            $parts = [];
            foreach ($definitions as $name => $sql) {
                $parts[] = $this->quoteIdentifier($name) . " AS ($sql)";
            }
            return $prefix . implode(", ", $parts) . " $mainSql";
        }
    }
?>