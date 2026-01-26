<?php
    /*/
	 * Project Name:    Wingman — Database — SQL Driver
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 26 2025
    /*/

    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\TableIdentifier;

    /**
     * Represents an SQL driver.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface SQLDriver {
        /**
         * Begins a transaction.
         * @return bool Whether the transaction was started successfully.
         * @throws PDOException If an error occurs while starting the transaction.
         * @throws RuntimeException If the transaction could not be started.
         */
        public function beginTransaction () : bool;

        /**
         * Commits the current transaction.
         * @return bool Whether the transaction was committed successfully.
         * @throws RuntimeException If there is no active transaction.
         */
        public function commit () : bool;

        /**
         * Connects to the database using the provided configuration.
         * @param array $config The configuration for the PDO connection.
         * @param SQLDialect|null $dialect The SQL dialect to use.
         * @return static The driver.
         */
        public function connect (?array $config = null, ?SQLDialect $dialect = null) : static;

        /**
         * Disconnects the connection.
         * @return static The driver.
         */
        public function disconnect () : static;

        /**
         * Executes a non-query SQL statement.
         * @param string $sql The SQL statement to execute.
         * @param array $bindings The bindings for the statement.
         * @return int The number of affected rows.
         * @throws PDOException If an error occurs during execution.
         */
        public function execute (string $sql, array $bindings = []) : int;

        /**
         * Executes a bulk data load from a stream into a table.
         * @param string|TableIdentifier $table The target table.
         * @param resource $stream The data stream.
         * @param array $fields The fields to load.
         * @return bool Whether the bulk load was successful.
         * @throws RuntimeException If bulk stream load is not supported for the driver.
         */
        public function executeBulkStream (string|TableIdentifier $table, $stream, array $fields = []) : bool;

        /**
         * Executes a query and returns a single row as an associative array.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @return array|null The resulting row or null if no row is found.
         */
        public function fetch (string $sql, array $bindings = []) : ?array;

        /**
         * Executes a query and returns all rows as an array of associative arrays.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @return array The resulting rows.
         */
        public function fetchAll (string $sql, array $bindings = []) : array;

        /**
         * Executes a query and returns a single column from the first row.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @param int $column The column index to fetch.
         * @return mixed The value of the specified column or null if no row is found.
         */
        public function fetchColumn (string $sql, array $bindings = [], int $column = 0) : mixed;

        /**
         * Executes a query and returns all values from a single column.
         * @param string $sql The SQL query to execute.
         * @param array $bindings The bindings for the query.
         * @param int $column The column index to fetch.
         * @return array The values of the specified column.
         */
        public function fetchValues (string $sql, array $bindings = [], int $column = 0) : array;

        /**
         * Gets the database name of a driver from the configuration.
         * @return string|null The database name.
         */
        public function getDatabase () : ?string;

        /**
         * Gets the SQL dialect of a driver.
         * @return SQLDialect|null The SQL dialect.
         */
        public function getDialect () : ?SQLDialect;

        /**
         * Gets the last error information.
         * @return array|null The last error information.
         */
        public function getLastError () : ?array;

        /**
         * Gets the ID of the last inserted row.
         * @param string|null $name The name of the sequence object from which the ID should be returned.
         * @return int|string The last insert ID.
         */
        public function getLastInsertId (?string $name = null) : int|string;

        /**
         * Checks whether the driver is connected to the database.
         * @return bool Whether the driver is connected.
         */
        public function isConnected () : bool;

        /**
         * Prepares a driver for bulk data loading.
         * @return static The driver.
         */
        public function prepareForBulkLoad () : static;

        /**
         * Rolls back the current transaction.
         * @return bool Whether the transaction was rolled back successfully.
         * @throws RuntimeException If there is no active transaction.
         */
        public function rollBack () : bool;
    }
?>