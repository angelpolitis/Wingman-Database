<?php
    /*/
	 * Project Name:    Wingman — Database — Conflict Builder
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Builders;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\ConflictStrategy;
    use Wingman\Database\Expressions\BooleanExpression;
    use Wingman\Database\Expressions\ComparisonExpression;
    use Wingman\Database\Facades\Query;
    use Wingman\Database\Objects\Conflict;
    use Wingman\Database\Objects\QueryState;

    /**
     * Represents a builder for handling conflict resolution strategies during insert operations.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConflictBuilder {
        /**
         * The filter expression for conditional conflict handling.
         * @var ComparisonExpression[]
         */
        protected array $filter = [];

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
         * The columns to check for conflicts.
         * @var array
         */
        protected array $targets;

        /**
         * Creates a new conflict builder.
         * @param QueryBuilder $query The main query object being built.
         * @param QueryState $state The state of the query.
         * @param array $targets The columns to check for conflicts.
         */
        public function __construct (QueryBuilder $query, QueryState $state, array $targets) {
            $this->query = $query;
            $this->state = $state;
            $this->targets = $targets;
        }

        /**
         * Applies the specified conflict resolution strategy to the query of a builder.
         * @param ConflictStrategy $strategy The conflict resolution strategy ('skip', 'overwrite', 'update').
         * @param array $data The data for update assignments if applicable.
         * @return QueryBuilder The updated query object.
         */
        protected function applyStrategy (ConflictStrategy $strategy, array $data = []) : QueryBuilder {
            $filter = $this->filter ? new BooleanExpression($this->filter) : null;
            $this->state->setConflict(new Conflict($this->targets, $strategy, $data, $filter));
            return $this->query;
        }

        /**
         * Sets the conflict resolution strategy to overwrite existing records.
         * @return QueryBuilder The updated query object.
         */
        public function overwrite () : QueryBuilder {
            return $this->applyStrategy(ConflictStrategy::Overwrite);
        }

        /**
         * Sets the conflict resolution strategy to skip inserting conflicting records.
         * @return QueryBuilder The updated query object.
         */
        public function skip () : QueryBuilder {
            return $this->applyStrategy(ConflictStrategy::Skip);
        }

        /**
         * Sets the conflict resolution strategy to update existing records.
         * If no assignments are provided, all columns from the insert are updated.
         * @param array $assignments Specific column-value pairs, or empty for all.
         * @return QueryBuilder The updated query object.
         */
        public function update (array $assignments = []) : QueryBuilder {
            if (empty($assignments) && !empty($this->state->getColumns())) {
                foreach ($this->state->getColumns() as $column) {
                    $assignments[$column] = Query::raw("NEW.$column");
                }
            }
            return $this->applyStrategy(ConflictStrategy::Update, $assignments);
        }

        /**
         * Adds a WHERE condition to the conflict target.
         * @param string $column The column name.
         * @param string $operator The comparison operator.
         * @param mixed $value The value to compare against.
         * @return static The builder.
         */
        public function where (string $column, string $operator, mixed $value) : static {
            $this->filter[] = new ComparisonExpression($column, $operator, $value);
            return $this;
        }
    }
?>