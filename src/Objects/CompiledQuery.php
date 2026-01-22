<?php
    /*/
	 * Project Name:    Wingman — Database — Compiled Query
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 18 2026
	 * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Objects namespace.
    namespace Wingman\Database\Objects;

    # Import the following classes to the current scope.
    use Closure;

    /**
     * Represents a compiled query with its SQL and bindings.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CompiledQuery {
        /**
         * The SQL query.
         * @var string
         */
        protected string $query;

        /**
         * The bindings for a query.
         * @var array
         */
        protected array $bindings;

        /**
         * An optional index key for a query.
         * @var string|null
         */
        protected ?string $indexKey;

        /**
         * An optional filter closure for the query results.
         * @var Closure|null
         */
        protected ?Closure $filter;

        /**
         * A column map for the query results.
         * @var object
         */
        protected object $columnMap;

        /**
         * Creates a new compiled query.
         * @param string $query The SQL query.
         * @param array $bindings The bindings for the query.
         * @param array $columnMap A column map for the query results.
         * @param string|null $indexKey An optional index key for the query.
         * @param Closure|null $filter An optional filter closure for the query results.
         */
        public function __construct (string $query, array $bindings = [], array $columnMap = [], ?string $indexKey = null, ?Closure $filter = null) {
            $this->query = $query;
            $this->bindings = $bindings;
            $this->columnMap = (object) $columnMap;
            $this->indexKey = $indexKey;
            $this->filter = $filter;
        }

        /**
         * Gets the bindings for a compiled query.
         * @return array The bindings.
         */
        public function getBindings () {
            return $this->bindings;
        }

        /**
         * Gets the column map for a compiled query.
         * @return object The column map.
         */
        public function getColumnMap () : object {
            return $this->columnMap;
        }

        /**
         * Gets the filter closure for a compiled query.
         * @return Closure|null The filter closure, or null if not set.
         */
        public function getFilter () : ?Closure {
            return $this->filter;
        }

        /**
         * Gets the index key of a compiled query.
         * @return string|null The index key, or null if not set.
         */
        public function getIndexKey () : ?string {
            return $this->indexKey;
        }

        /**
         * Gets the SQL query.
         * @return string The SQL query.
         */
        public function getQuery () {
            return $this->query;
        }

        /**
         * Indicates whether the compiled query has a filter.
         * @return bool True if a filter is set, false otherwise.
         */
        public function hasFilter () : bool {
            return $this->filter !== null;
        }
    }
?>