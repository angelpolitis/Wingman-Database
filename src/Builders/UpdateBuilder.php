<?php
    /*/
	 * Project Name:    Wingman — Database — Update Builder
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Builders;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Facades\Query;
    use Wingman\Database\Objects\QueryState;
    use Wingman\Database\Traits\CanProxyJoin;
    use Wingman\Database\Traits\CanProxyWhere;
    use Wingman\Database\Traits\CanReturn;
    use Wingman\Database\Traits\ProxiesQuery;

    /**
     * Represents a builder for constructing update queries.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UpdateBuilder {
        use ProxiesQuery, CanProxyJoin, CanProxyWhere, CanReturn;

        /**
         * The query of a builder.
         * @var QueryBuilder
         */
        protected QueryBuilder $query;

        /**
         * The state of the query.
         * @var QueryState
         */
        protected QueryState $state;

        /**
         * Creates a new update builder.
         * @param QueryBuilder $query The main query object being built.
         * @param QueryState $state The state of the query.
         */
        public function __construct (QueryBuilder $query, QueryState $state) {
            $this->query = $query;
            $this->state = $state;
        }

        /**
         * Copies values from one column to another.
         * Supports:
         * 1. `->copy('targetColumn', 'sourceColumn')`
         * 2. `->copy(['targetColumn1' => 'sourceColumn1', 'targetColumn2' => 'sourceColumn2'])`
         * @param array|string $target The target column(s) to copy to.
         * @param string|null $source The source column to copy from (if $target is a string).
         * @return static The builder.
         */
        public function copy (array|string $target, ?string $source = null) : static {
            if (is_array($target)) {
                $assignments = [];
                foreach ($target as $t => $s) {
                    $assignments[$t] = Query::column($s);
                }
                return $this->set($assignments);
            }
            return $this->set($target, Query::column($source));
        }
        
        /**
         * Decrements a column by a specified amount.
         * @param string $column The column to decrement.
         * @param int $amount The amount to decrement by (default: 1).
         * @return static The builder.
         */
        public function decrement (string $column, int $amount = 1) : static {
            return $this->set([
                $column => Query::raw("$column - $amount")
            ]);
        }

        /**
         * Increments a column by a specified amount.
         * @param string $column The column to increment.
         * @param int $amount The amount to increment by (default: 1).
         * @return static The builder.
         */
        public function increment (string $column, int $amount = 1) : static {
            return $this->set([
                $column => Query::raw("$column + $amount")
            ]);
        }
        
        /**
         * Specifies a limit on the number of rows to update.
         * @param int $limit The maximum number of rows to update.
         * @param int $offset The number of rows to skip before starting to update (default: 0).
         * @return static The builder.
         */
        public function limit (int $limit, int $offset = 0) : static {
            $this->query->limit($limit, $offset);
            return $this;
        }
        
        /**
         * Sets specified columns to NULL.
         * @param string|array ...$columns The column names to set to NULL.
         * @return static The builder.
         */
        public function nullify (string|array ...$columns) : static {
            $columns = (count($columns) === 1 && is_array($columns[0])) ? $columns[0] : $columns;
            $assignments = array_fill_keys($columns, null);
            
            return $this->set($assignments);
        }

        /**
         * Specifies the order of the update query results.
         * @param array $specification The order specification; example : ['column1' => 'ASC', 'column2' => 'DESC'].
         * @return static The builder.
         */
        public function orderBy (array $specification) : static {
            $this->query->orderBy($specification);
            return $this;
        }

        /**
         * Sets the assignments for the update.
         * Supports:
         * 1. `->set(['col1' => 'val1', 'col2' => 'val2'])`
         * 2. `->set('col1', 'val1')`
         * 3. `->set('col1', Query) for subqueries`
         * 4. `->set('col1', Query::column(...)) for column references`
         * 5. `->set('col1' => Query::raw(...)) for raw expressions`
         * @param array|string $column The column name or an associative array of assignments.
         * @param mixed|null $value The value to set (if $column is a string).
         * @return static The builder.
         */
        public function set (array|string $column, mixed $value = null): static {
            $assignments = is_array($column) ? $column : [$column => $value];
        
            foreach ($assignments as $target => $val) {
                if ($val instanceof QueryBuilder) $val = new QueryExpression($val);
        
                $this->state->addAssignment($target, $val);
            }
        
            return $this;
        }

        /**
         * Sets specified columns to the current timestamp.
         * @param string ...$columns The column names to set to the current timestamp.
         * @return static The builder.
         */
        public function setTimestamp (string ...$columns) : static {
            $assignments = [];
            foreach ($columns as $column) {
                $assignments[$column] = Query::raw("CURRENT_TIMESTAMP");
            }
            return $this->set($assignments);
        }

        /**
         * Toggles a boolean column's value.
         * @param string|array $columns The column or columns to toggle.
         * @return static The builder.
         */
        public function toggle (string|array $columns) : static {
            $columns = is_array($columns) ? $columns : [$columns];
            foreach ($columns as $column) {
                $this->set($column, Query::raw("NOT $column"));
            }
            return $this;
        }

        /**
         * Allows or prohibits the update operation to proceed without any WHERE constraints.
         * Use with caution as this will update all rows in the table.
         * @param bool $status Whether to allow global updates (default: true).
         * @return static The builder.
         */
        public function withoutConstraints (bool $status = true) : static {
            $this->state->setGlobalUpdatesAllowed($status);
            return $this;
        }
    }
?>