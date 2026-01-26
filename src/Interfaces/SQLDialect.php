<?php
    /*/
	 * Project Name:    Wingman — Database — SQL Dialect
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 26 2026
    /*/

    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;

    # Import the following classes to the current scope.
    use SplObjectStorage;
    use Wingman\Database\Compilers\ExpressionCompiler;
    use Wingman\Database\Enums\IndexAlgorithm;
    use Wingman\Database\Enums\IndexType;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Enums\NullPrecedence;
    use Wingman\Database\Enums\OrderDirection;
    use Wingman\Database\Enums\ReferentialAction;
    use Wingman\Database\Enums\SetOperation;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Objects\CompiledQuery;

    /**
     * Represents an SQL dialect.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface SQLDialect {
        /**
         * Builds an ALTER TABLE statement.
         * @param string|TableIdentifier $table The name of the table to alter.
         * @param CompiledQuery[] $commands The list of ALTER TABLE commands.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTable (string|TableIdentifier $table, array $commands) : CompiledQuery;

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
        ) : CompiledQuery;

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
        ) : CompiledQuery;

        /**
         * Builds an ALTER TABLE statement to add a primary key.
         * @param string|TableIdentifier $table The table to alter.
         * @param array $columns The columns that make up the primary key.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableAddPrimaryKey (string|TableIdentifier $table, array $columns) : CompiledQuery;

        /**
         * Builds an ALTER TABLE statement to add an index.
         * @param string $name The name of the index.
         * @param string|TableIdentifier $table The table to alter.
         * @param array $columns The columns that make up the index.
         * @param IndexType|null $type The type of the index (e.g., UNIQUE, FULLTEXT).
         * @param IndexAlgorithm|null $algorithm The algorithm of the index (e.g., BTREE, HASH).
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableAddUniqueKey (string $name, string|TableIdentifier $table, array $columns) : CompiledQuery;

        /**
         * Builds an ALTER TABLE statement to drop a column.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $name The name of the column to drop.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropColumn (string|TableIdentifier $table, string $name) : CompiledQuery;
        
        /**
         * Builds an ALTER TABLE statement to drop a foreign key.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $name The name of the foreign key to drop.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropForeignKey (string|TableIdentifier $table, string $name) : CompiledQuery;

        /**
         * Builds an ALTER TABLE statement to drop an index.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $name The name of the index to drop.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropIndex (string|TableIdentifier $table, string $name) : CompiledQuery;

        /**
         * Builds an ALTER TABLE statement to drop the primary key.
         * @param string|TableIdentifier $table The table to alter.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableDropPrimaryKey (string|TableIdentifier $table) : CompiledQuery;

        /**
         * Builds an ALTER TABLE statement to rename a column.
         * @param string|TableIdentifier $table The table to alter.
         * @param string $from The current name of the column.
         * @param string $to The new name of the column.
         * @return CompiledQuery The compiled SQL ALTER TABLE statement.
         */
        public function compileAlterTableRenameColumn (string|TableIdentifier $table, string $from, string $to) : CompiledQuery;
        
        /**
         * Builds a query to check if a column exists in a table.
         * @param string|ColumnIdentifier $column The name of the column.
         * @param string|TableIdentifier|null $table The name of the table, or null to use the column's table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to check for column existence.
         * @throws LogicException If the table name is not provided and cannot be inferred.
         */
        public function compileColumnExists (string|ColumnIdentifier $column, string|TableIdentifier|null $table = null, ?string $schema = null) : CompiledQuery;
        
        /**
         * Compiles a concatenation expression.
         * @param Expression[] $expressions The expressions to concatenate.
         * @return CompiledQuery The compiled CONCAT SQL expression.
         */
        public function compileConcatenate (array $expressions) : CompiledQuery;

        /**
         * Compiles conflict references for UPSERT statements.
         * @param string $sql The SQL statement containing NEW.column references.
         * @return string The SQL statement with NEW.column references replaced by VALUES(column).
         */
        public function compileConflictReference (string $sql) : string;

        /**
         * Builds a query to check if a database exists.
         * @param string $database The name of the database.
         * @return CompiledQuery The compiled query to check for database existence.
         */
        public function compileDatabaseExists (string $database) : CompiledQuery;

        /**
         * Builds a DELETE statement from the given table and filter columns.
         * @param array|string|TableIdentifier $sources The source table(s) to delete from.
         * @param array|string|TableIdentifier|null $targets The target table(s) to delete.
         * @param Expression|null $filter The filter expression for the WHERE clause.
         * @return string The compiled SQL DELETE statement.
         */
        public function compileDelete (array|string|TableIdentifier $sources, array|string|TableIdentifier|null $targets, ?Expression $filter = null) : string;

        /**
         * Compiles a DROP COLUMN statement.
         * @param string $name The name of the column to drop.
         * @return CompiledQuery The compiled DROP COLUMN SQL.
         */
        public function compileDropColumn (string $name) : CompiledQuery;

        /**
         * Compiles a DROP DATABASE statement.
         * @param string $database The name of the database to drop.
         * @param bool $ifExists Whether to include IF EXISTS clause.
         * @return CompiledQuery The compiled DROP DATABASE SQL.
         */
        public function compileDropDatabase (string $database, bool $ifExists = true) : CompiledQuery;
        
        /**
         * Compiles a DROP FOREIGN KEY statement.
         * @param string $name The name of the foreign key to drop.
         * @return CompiledQuery The compiled DROP FOREIGN KEY SQL.
         */
        public function compileDropForeignKey (string $name) : CompiledQuery;
        
        /**
         * Compiles a DROP INDEX statement.
         * @param string $name The name of the index to drop.
         * @return CompiledQuery The compiled DROP INDEX SQL.
         */
        public function compileDropIndex (string $name) : CompiledQuery;

        /**
         * Compiles a DROP PRIMARY KEY statement.
         * @return CompiledQuery The compiled DROP PRIMARY KEY SQL.
         */
        public function compileDropPrimaryKey () : CompiledQuery;

        /**
         * Compiles a DROP TABLE statement.
         * @param string|TableIdentifier $table The name of the table to drop.
         * @param bool $ifExists Whether to include IF EXISTS clause.
         * @return CompiledQuery The compiled DROP TABLE SQL.
         */
        public function compileDropTable (string|TableIdentifier $table, bool $ifExists = true) : CompiledQuery;

        /**
         * Compiles a DSN string from the given configuration.
         * @param array $config The database configuration array.
         * @return string The compiled DSN string.
         */
        public function compileDsn (array $config) : string;
        
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
        ) : CompiledQuery;

        /**
         * Compiles an INDEX creation statement.
         * @param string $name The name of the index.
         * @param array $columns The columns that make up the index.
         * @param IndexType|null $type The type of the index (e.g., UNIQUE, FULLTEXT).
         * @param IndexAlgorithm|null $algorithm The algorithm of the index (e.g., BTREE, HASH).
         * @return CompiledQuery The compiled INDEX creation SQL.
         */
        public function compileIndex (string $name, array $columns, ?IndexType $type = null, ?IndexAlgorithm $algorithm = null) : CompiledQuery;

        /**
         * Builds an INSERT statement.
         * @param string|TableIdentifier $table The name of the table to insert into.
         * @param array $data The columns to insert values into.
         * @return CompiledQuery The compiled SQL INSERT statement.
         */
        public function compileInsert (string|TableIdentifier $table, array $data) : CompiledQuery;

        /**
         * Builds a LIMIT/OFFSET clause.
         * @param int|null $limit The LIMIT value, or null for no limit.
         * @param int|null $offset The OFFSET value, or null for no offset.
         * @return string The compiled LIMIT/OFFSET clause.
         */
        public function compileLimitOffset (?int $limit, ?int $offset) : string;

        /**
         * Builds a LOAD DATA INFILE statement.
         * @param string|TableIdentifier $table The name of the table to load data into.
         * @param string $file The path to the data file.
         * @param array $fields The list of fields to load.
         * @return string The compiled SQL LOAD DATA INFILE statement.
         */
        public function compileLoadData (string|TableIdentifier $table, string $file, array $fields) : string;

        /**
         * Renders a LOCK clause for SELECT statements.
         * @param LockType $type The type of lock (Exclusive or Shared).
         * @param int|null $timeout The lock timeout in seconds, or null for default behavior.
         * @param bool $skipLocked Whether to skip locked rows.
         * @return string The compiled LOCK clause.
         */
        public function compileLock (LockType $type, ?int $timeout, bool $skipLocked) : string;

        /**
         * Builds a multi-row INSERT statement.
         * @param string|TableIdentifier $table The name of the table to insert into.
         * @param array $fields The list of fields to insert values into.
         * @param array $rows The list of rows to insert.
         * @return CompiledQuery The compiled SQL multi-row INSERT statement.
         */
        public function compileMultiInsert (string|TableIdentifier $table, array $fields, array $rows) : CompiledQuery;

        /**
         * Compiles an ORDER BY clause with NULL precedence.
         * @param string $sql The SQL expression to order by.
         * @param OrderDirection $direction The direction of the order (ASC or DESC).
         * @param NullPrecedence $precedence The NULL precedence (FIRST or LAST).
         * @return string The compiled ORDER BY clause.
         */
        public function compileOrder (string $sql, OrderDirection $direction, NullPrecedence $precedence) : string;
        
        /**
         * Compiles a PRIMARY KEY constraint.
         * @param array $columns The columns that make up the primary key.
         * @return CompiledQuery The compiled PRIMARY KEY SQL.
         */
        public function compilePrimaryKey (array $columns) : CompiledQuery;
        
        /**
         * Compiles a RENAME COLUMN statement.
         * @param string $from The current column name.
         * @param string $to The new column name.
         * @return CompiledQuery The compiled RENAME COLUMN SQL.
         */
        public function compileRenameColumn (string $from, string $to) : CompiledQuery;

        /**
         * Compiles the RETURNING clause for the dialect.
         * @param array $columns The columns to return.
         * @throws LogicException If the dialect does not support RETURNING clauses.
         */
        public function compileReturning (array $columns) : string;

        /**
         * Builds a SAVEPOINT statement.
         * @param string $name The name of the savepoint.
         * @return string The compiled SQL SAVEPOINT statement.
         */
        public function compileSavepoint (string $name) : string;
        
        /**
         * Builds a ROLLBACK TO SAVEPOINT statement.
         * @param string $name The name of the savepoint.
         * @return string The compiled SQL ROLLBACK TO SAVEPOINT statement.
         */
        public function compileSavepointRollback (string $name) : string;
        
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
        public function compileSelect (string|TableIdentifier $table, array $columns = ['*'], ?Expression $filter = null, array|string|int|null $order = null, ?int $limit = null, int $offset = 0, LockType $lock = LockType::None) : CompiledQuery;

        /**
         * Builds a USE DATABASE statement.
         * @param string $database The name of the database to switch to.
         * @return string The compiled SQL USE DATABASE statement.
         */
        public function compileSetDatabase (string $database) : string;
        
        /**
         * Builds a SET TIMEZONE statement.
         * @param string $timezone The timezone to set.
         * @return string The compiled SQL SET TIMEZONE statement.
         */
        public function compileSetTimezone (string $timezone) : string;
        
        /**
         * Builds a query to retrieve the auto-increment column of a table.
         * @param string|TableIdentifier $table The table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve the auto-increment column name.
         */
        public function compileTableAutoIncrementColumn (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery;

        /**
         * Builds a query to retrieve a specific table column.
         * @param string|ColumnIdentifier $column The name of the column.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve column information.
         * @throws LogicException If the table name is not provided and cannot be inferred.
         */
        public function compileTableColumn (string|ColumnIdentifier $column, string|TableIdentifier $table, ?string $schema = null) : CompiledQuery;

        /**
         * Builds a query to retrieve table columns.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve column names.
         */
        public function compileTableColumns (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery;

        /**
         * Builds a query to check if a table exists.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to check for table existence.
         */
        public function compileTableExists (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery;

        /**
         * Builds a query to retrieve the foreign keys of a table.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve foreign key information.
         */
        public function compileTableForeignKeys (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery;
        
        /**
         * Builds a query to retrieve the primary key columns of a table.
         * @param string|TableIdentifier $table The name of the table.
         * @return CompiledQuery The compiled query to retrieve primary key column names.
         */
        public function compileTablePrimaryKey (string|TableIdentifier $table) : CompiledQuery;

        /**
         * Builds a query to retrieve the unique keys of a table.
         * @param string|TableIdentifier $table The name of the table.
         * @param string|null $schema The schema name, or null for the current schema.
         * @return CompiledQuery The compiled query to retrieve unique key names and their columns.
         */
        public function compileTableUniqueKeys (string|TableIdentifier $table, ?string $schema = null) : CompiledQuery;

        /**
         * Builds a TRUNCATE TABLE statement.
         * @param string|TableIdentifier $table The name of the table to truncate.
         * @return CompiledQuery The compiled SQL TRUNCATE TABLE statement.
         */
        public function compileTruncate (string|TableIdentifier $table) : CompiledQuery;
        
        /**
         * Compiles a UNIQUE key constraint.
         * @param string $name The name of the unique key.
         * @param array $columns The columns that make up the unique key.
         * @return CompiledQuery The compiled UNIQUE key constraint SQL.
         */
        public function compileUniqueKey (string $name, array $columns) : CompiledQuery;

        /**
         * Builds an UPDATE statement.
         * @param string|TableIdentifier $table The name of the table to update.
         * @param array $columns The columns to update.
         * @param Expression|null $filter The filter expression for the WHERE clause.
         * @return CompiledQuery The compiled SQL UPDATE statement.
         */
        public function compileUpdate (string|TableIdentifier $table, array $columns, ?Expression $filter = null) : CompiledQuery;

        /**
         * Builds a bulk UPDATE statement for multiple rows.
         * @param string|TableIdentifier $table The name of the table to update.
         * @param array $data The data rows to update.
         * @param array $fixedKey The fixed key columns used to identify rows.
         * @param Expression|null $filter An optional filter expression for the WHERE clause.
         * @return CompiledQuery The compiled SQL bulk UPDATE statement.
         */
        public function compileUpdateMany (string|TableIdentifier $table, array $data, array $fixedKey, ?Expression $filter = null) : CompiledQuery;

        /**
         * Builds an UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) statement.
         * @param string|TableIdentifier $table The name of the table to upsert into.
         * @param array $data The data to insert.
         * @param array $updatedColumns The columns to update on duplicate key.
         * @return CompiledQuery The compiled SQL UPSERT statement.
         */
        public function compileUpsert (string|TableIdentifier $table, array $data, array $updatedColumns) : CompiledQuery;

        /**
         * Gets the component order for DELETE statements.
         * @return array The ordered list of components for DELETE statements.
         */
        public function getDeleteOrder () : array;

        /**
         * Gets the component order for INSERT statements.
         * @return array The ordered list of components for INSERT statements.
         */
        public function getInsertOrder () : array;
        
        /**
         * Gets the internal representation of a NULL value for bulk loading.
         * @return string The string representing NULL in bulk loading.
         */
        public function getNullInternal () : string;

        /**
         * Gets the component order for a given plan node.
         * @param PlanNode $node The plan node.
         * @return array The ordered list of components for the plan node's operation.
         */
        public function getOrderForNode (PlanNode $node) : array;

        /**
         * Gets the component order for SELECT statements.
         * @return array The ordered list of components for SELECT statements.
         */
        public function getSelectOrder () : array;

        /**
         * Gets the component order for UPDATE statements.
         * @return array The ordered list of components for UPDATE statements.
         */
        public function getUpdateOrder () : array;

        /**
         * Sets the expression compiler of a dialect.
         * @param ExpressionCompiler $compiler The expression compiler.
         * @return static The current instance for chaining.
         */
        public function setCompiler (ExpressionCompiler $compiler) : static;
        
        /**
         * Indicates whether the dialect supports combined ALTER statements.
         * @return bool Whether the dialect supports combined ALTER statements.
         */
        public function supportsCombinedAlter () : bool;

        /**
         * Indicates whether the dialect supports DELETE statements with LIMIT clauses.
         * @return bool Whether the dialect supports DELETE statements with LIMIT clauses.
         */
        public function supportsDeleteLimit () : bool;

        /**
         * Indicates whether the dialect supports FULL OUTER JOINs.
         * @return bool Whether the dialect supports FULL OUTER JOINs.
         */
        public function supportsFullOuterJoin () : bool;
        
        /**
        * Indicates whether the dialect supports LOCK clauses.
        * @return bool Whether the dialect supports LOCK clauses.
        */
        public function supportsLocking () : bool;
        
        /**
         * Indicates whether a dialect supports renaming columns.
         * @return bool Whether the dialect supports renaming columns.
         */
        public function supportsRenameColumn () : bool;

        /**
         * Indicates whether the dialect supports RETURNING clauses.
         * @return bool Whether the dialect supports RETURNING clauses.
         */
        public function supportsReturning () : bool;

        /**
         * Quotes an identifier (table or column name).
         * @param string $name The identifier to quote.
         * @return string The quoted identifier.
         */
        public function quoteIdentifier (string $identifier) : string;

        /**
         * Quotes a value for use in SQL statements.
         * @param mixed $value The value to quote.
         * @return string The quoted value.
         */
        public function quoteValue (mixed $value) : string;

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
        public function delete (TableIdentifier $target, string $sourceString, SplObjectStorage $bucket) : string;
        
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
        public function insert (string|TableIdentifier $table, array $data) : string;
        
        /**
         * Appends a RETURNING clause to the given SQL statement.
         * @param string $sql The base SQL statement.
         * @param array $fields List of fields to return.
         * @return string The SQL statement with the RETURNING clause appended.
         */
        public function returning (string $sql, array $fields) : string;
        
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
        public function select (array $buckets) : string;
        
        /**
         * Builds a set operation (UNION, INTERSECT, EXCEPT) between two SQL queries.
         * @param string $left The left SQL query.
         * @param string $right The right SQL query.
         * @param SetOperation $operation The set operation ('UNION', 'INTERSECT', 'EXCEPT').
         * @return string The compiled SQL set operation.
         */
        public function setOperation (string $left, string $right, SetOperation $operation) : string;
        
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
        public function update (TableIdentifier $table, array $data, SplObjectStorage $bucket) : string;
        
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
        public function updateBulk (TableIdentifier $table, array $data, string $whereSql = "") : string;
        
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
        public function upsert (string $table, array $data) : string;
        
        /**
         * Builds a WITH clause from the given definitions and main SQL.
         * @param array $definitions An associative array where keys are CTE names and values are their SQL definitions.
         * @param string $mainSql The main SQL query that uses the CTEs.
         * @param bool $recursive Whether the CTEs are recursive.
         * @return string The compiled SQL WITH clause followed by the main SQL.
         */
        public function with (array $definitions, string $mainSql, bool $recursive) : string;
    }
?>