<?php
    /*/
	 * Project Name:    Wingman — Database — Insert Builder
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Builders;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\RawExpression;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Objects\UniqueConstraint;
    use Wingman\Database\Objects\QueryState;
    use Wingman\Database\Traits\CanReturn;
    use Wingman\Database\Traits\ProxiesQuery;

    /**
     * Represents a builder for constructing insert queries.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InsertBuilder {
        use ProxiesQuery, CanReturn;

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
         * Creates a new insert builder.
         * @param QueryBuilder $query The main query object being built.
         * @param QueryState $state The state of the query.
         */
        public function __construct (QueryBuilder $query, QueryState $state) {
            $this->query = $query;
            $this->state = $state;
        }

        /**
         * Specifies the columns to insert into.
         * @param array $columns The columns to insert into.
         * @return static The builder.
         */
        public function columns (array $columns) : static {
            $this->state->setColumns($columns);
            return $this;
        }

        /**
         * Specifies that conflicts should be ignored during the insert operation.
         * @param bool $value Whether to ignore conflicts (default: true).
         * @return QueryBuilder The updated query object.
         */
        public function ignore (bool $value = true) : QueryBuilder {
            $this->state->setConflictsIgnored($value);
            return $this->query;
        }

        /**
         * Specifies the values to insert.
         * @param array ...$rows The rows of values to insert.
         * @return static The builder.
         */
        public function insert (array ...$rows) : static {
            # 1. Determine columns if not explicitly set via ->columns().
            if (!$this->state->hasColumns() && !empty($rows)) {
                $this->state->setColumns(array_keys($rows[0]));
            }
        
            $targetColumns = $this->state->getColumns();
            
            # 2. Normalize the rows against the columns.
            foreach ($rows as $row) {
                $normalised = [];
                foreach ($targetColumns as $index => $column) {
                    $value = $row[$column] ?? ($row[$index] ?? null);
        
                    if ($value instanceof QueryBuilder) {
                        $value = new QueryExpression($value);
                    }
        
                    $normalised[$column] = $value;
                }
                $this->state->addValue($normalised);
            }
        
            return $this;
        }

        /**
         * Specifies the conflict resolution strategy for the insert operation.
         * @param array|string|UniqueConstraint|RawExpression $target The target column(s) for conflict detection.
         * @param string|UniqueConstraint|RawExpression ...$more Additional target columns for conflict detection.
         * @return ConflictBuilder A conflict builder for further configuration.
         */
        public function onConflict (array|string|UniqueConstraint|RawExpression $target, string|UniqueConstraint|RawExpression ...$more) : ConflictBuilder {
            $targets = is_array($target) ? $target : [$target];
            
            if (!empty($more)) {
                $targets = array_merge($targets, $more);
            }

            foreach ($targets as &$target) {
                if (!is_string($target)) continue;
                $target = new ColumnIdentifier($target);
            }

            return new ConflictBuilder($this->query, $this->state, $targets);
        }

        /**
         * Specifies a subquery to insert data from.
         * @param QueryBuilder $subQuery The subquery to select data from.
         * @return static The builder.
         */
        public function select (QueryBuilder $subQuery) : static {
            $this->state->setValues(new QueryExpression($subQuery));
            return $this;
        }
    }
?>