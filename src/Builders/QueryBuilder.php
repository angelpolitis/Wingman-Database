<?php
    /*/
	 * Project Name:    Wingman — Database — Query Builder
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Builders;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Compilers\QueryPlanner;
    use Wingman\Database\Enums\JoinType;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Enums\NullPrecedence;
    use Wingman\Database\Enums\OrderDirection;
    use Wingman\Database\Enums\SetOperation;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Expressions\{
        AggregateExpression,
        BetweenExpression, BooleanExpression, ComparisonExpression,
        CteExpression,
        ExistsExpression, InExpression, JoinExpression, LikeExpression, LiteralExpression, NullExpression, OrderExpression, RandomExpression, RawExpression, RegexExpression,
        QueryExpression,
        WindowExpression,
        ColumnIdentifier,
        Predicate
    };
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Objects\QueryState;

    /**
     * Represents a builder for constructing SQL queries.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class QueryBuilder {
        /**
         * The state of a query.
         * @var QueryState
         */
        protected QueryState $state;

        /**
         * Creates a new query builder.
         */
        public function __construct () {
            $this->state = new QueryState();
        }

        /**
         * Maps input parameters to an appropriate expression.
         * @param mixed $column The column name or expression.
         * @param string $operator The comparison operator.
         * @param mixed $value The value to compare against.
         * @return Predicate The mapped expression.
         * @throws InvalidArgumentException If the value for IN operator is invalid.
         */
        protected static function createPredicate (mixed $column, string $operator, mixed $value) : Predicate {
            $upperOp = strtoupper(trim($operator));
    
            $column = static::ensureExpression($column, true);

            if ($value === null) {
                $isNot = in_array($upperOp, ["!=", "<>", "IS NOT"]);
                return new NullExpression($column, $isNot);
            }

            if ($upperOp === "IN" || $upperOp === "NOT IN") {
                if ($value instanceof QueryBuilder) {
                    $value = new QueryExpression($value);
                }
                
                if (is_scalar($value) || ($value instanceof Expression && !($value instanceof QueryExpression))) {
                    $value = [$value];
                }

                return new InExpression($column, $value, str_contains($upperOp, "NOT"));
            }

            $value = static::ensureExpression($value, false);
            
            return new ComparisonExpression($column, $operator, $value);
        }

        /**
         * Registers an aggregate function in the select clause.
         * @param string $function The aggregate function name (e.g., COUNT, SUM).
         * @param string|Expression $column The column to aggregate.
         * @param bool $distinct Whether to apply DISTINCT to the aggregation (default: false).
         * @param string|null $alias An optional alias for the aggregated result.
         */
        public function aggregate (string $function, string|Expression $column, bool $distinct = false, ?string $alias) : static {
            $column = static::ensureExpression($column, true);
            $this->state->addSelect(new AggregateExpression($function, $column, $distinct, $alias));
            return $this;
        }

        /**
         * Sets an alias for a query (useful for subqueries).
         * @param string $alias The alias name.
         * @return static The query.
         */
        public function alias (string $alias) : static {
            $this->state->setAlias($alias);
            return $this;
        }

        /**
         * Specifies a MIN aggregate function.
         * @param string|Expression $column The column to find the minimum value.
         * @param bool $distinct Whether to apply DISTINCT to the aggregation (default: false).
         * @param string|null $alias An optional alias for the min result.
         * @return static The query.
         */
        public function average (string|Expression $column, bool $distinct = false, ?string $alias = null) : static {
            return $this->aggregate("AVG", $column, $distinct, $alias);
        }

        /**
         * Specifies a COUNT aggregate function.
         * @param string|Expression $column The column or expression to count (default: '*').
         * @param bool $distinct Whether to apply DISTINCT to the aggregation (default: false).
         * @param string|null $alias An optional alias for the count result.
         * @return static The query.
         */
        public function count (string|Expression $column = '*', bool $distinct = false, ?string $alias = null) : static {
            return $this->aggregate("COUNT", $column, $distinct, $alias);
        }

        /**
         * Adds a cross join to a query.
         * @param static|string|array $table The table to join.
         * @return static The query.
         */
        public function crossJoin (self|string|array $table) : static {
            return $this->join($table, [], [], JoinType::Cross);
        }

        /**
         * Creates a builder that handles DELETE operations.
         * @param string|null $targetAlias The alias of the target table to delete from (optional).
         * @return static The query.
         */
        public function delete (?string $targetAlias = null) : static {
            $this->state->setDelete(true);
            $this->state->setDeleteTarget($targetAlias);
            return $this;
        }

        /**
         * Marks a query as distinct.
         * @return static The query.
         */
        public function distinct () : static {
            $this->state->setDistinct(true);
            return $this;
        }

        /**
         * Ensures a value is wrapped as an Expression.
         * @param mixed $value The value to ensure as an expression.
         * @param bool $preferColumn Whether to prefer treating strings as column identifiers (default: false).
         * @return Expression The ensured expression.
         */
        public static function ensureExpression (mixed $value, bool $preferColumn = false) : Expression {
            if ($value instanceof Expression) return $value;

            if ($value instanceof QueryBuilder) return new QueryExpression($value);
        
            if (is_string($value)) {
                if ($preferColumn || str_contains($value, '.')) {
                    return ColumnIdentifier::from($value);
                }
                return new LiteralExpression($value);
            }
        
            return new LiteralExpression($value);
        }

        /**
         * Specifies an EXCEPT operation between two queries.
         * @param self $first The first query to except with.
         * @param self ...$other The other queries to except with.
         * @return static The query.
         */
        public function except (self $first, self ...$other) : static {
            $this->state->addSetOperations(SetOperation::Except, array_merge([$first], $other));
            return $this;
        }

        /**
         * Specifies the source table(s) for a query.
         * @param static|string|array|Expression $table The table name, array of tables, or subquery.
         * @param string|null $alias An optional alias for the table.
         * @return static The query.
         */
        public function from (self|string|array|Expression $table, ?string $alias = null) : static {
            $this->state->addSource($table, $alias);
            return $this;
        }

        /**
         * Adds a full join to a query.
         * @param static|string|array|Expression $table The table to join (can be a subquery).
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex ON logic.
         * @param string|array|null $operator A comparison operator or the foreign key(s) (if 3-argument call).
         * @param mixed $foreignKey The foreign key(s) for the join (if 4-argument call).
         * @return static The query.
         */
        public function fullJoin (
            self|string|array|Expression $table,
            string|array|callable|null $localKey = null,
            string|array|null $operator = '=',
            mixed $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $operator, $foreignKey, JoinType::Full);
        }

        /**
         * Gets the alias of a query (if any).
         * @return string|null The alias or `null` if not set.
         */
        public function getAlias () : ?string {
            return $this->state->getAlias();
        }
        
        /**
         * Compiles the current state of a query into a plan.
         * @return PlanNode The compiled plan node.
         */
        public function getPlan () : PlanNode {
            return (new QueryPlanner())->compile($this->state);
        }

        /**
         * Gets the current state of a query.
         * @return QueryState The query state.
         */
        public function getState () : QueryState {
            return $this->state;
        }

        /**
         * Specifies the fields to group by.
         * @param int|string|array $fields The field(s) to group by (can be column names, expressions, or ordinals).
         * @return static The query.
         */
        public function groupBy (int|string|array $fields) : static {
            $fields = is_array($fields) ? $fields : [$fields];

            foreach ($fields as $field) {
                $expression = static::ensureExpression($field, true);
                
                $this->state->addGroup($expression);
            }

            return $this;
        }
        
        /**
         * Specifies the condition used to filter grouped records.
         * @param mixed $column The column or expression to apply the HAVING condition on.
         * @param string $operator The comparison operator.
         * @param mixed $value The value to compare against.
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function having (mixed $column, mixed $operator = null, mixed $value = null, string $conjunction = "AND") : static {
            # 1. Handle Closures for Nested Grouping
            if (is_callable($column)) {
                $nestedQuery = new static();
                $column($nestedQuery);
                
                # Extract the expressions from the nested state.
                $nestedExpressions = $nestedQuery->getState()->getHavings();
                
                # Wrap the nested expressions in a boolean expression.
                if (!empty($nestedExpressions)) {
                    $expression = new BooleanExpression($nestedExpressions);
                    $this->state->addHaving($expression->setConjunction($conjunction));
                }
                
                return $this;
            }
        
            # 2. Standard Logic for Comparisons
            $numArgs = func_num_args();
            if ($numArgs === 2) {
                $value = $operator;
                $operator = '=';
            }
        
            $expression = static::createPredicate($column, (string) $operator, $value);
            $this->state->addHaving($expression->setConjunction($conjunction));
        
            return $this;
        }
        
        /**
         * Adds an inner join to a query.
         * @param static|string|array|Expression $table The table to join (can be a subquery).
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex ON logic.
         * @param string|array|null $operator A comparison operator or the foreign key(s) (if 3-argument call).
         * @param mixed $foreignKey The foreign key(s) for the join (if 4-argument call).
         * @return static The query.
         */
        public function innerJoin (
            self|string|array|Expression $table,
            string|array|callable|null $localKey = null,
            string|array|null $operator = '=',
            mixed $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $operator, $foreignKey, JoinType::Inner);
        }

        /**
         * Specifies an INTERSECT operation between two queries.
         * @param self $first The first query to intersect with.
         * @param self ...$other The other queries to intersect with.
         * @return static The query.
         */
        public function intersect (self $first, self ...$other) : static {
            $this->state->addSetOperations(SetOperation::Intersect, array_merge([$first], $other));
            return $this;
        }

        /**
         * Creates a builder that handles INSERT operations.
         * @param string $table The target table.
         * @return InsertBuilder A new insert builder.
         */
        public function into (string $table) : InsertBuilder {
            $this->state->setTargetTable($table);
            return new InsertBuilder($this, $this->state);
        }

        /**
         * Joins a table to a query.
         * @param static|string|array|Expression $table The table to join (can be a subquery).
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex ON logic.
         * @param string|array|null $operator A comparison operator or the foreign key(s) (if 3-argument call).
         * @param mixed $foreignKey The foreign key(s) for the join (if 4-argument call).
         * @param JoinType|string $type The type of join (default: INNER).
         */
        public function join (
            self|string|array|Expression $table,
            string|array|callable|null $localKey = null,
            string|array|null $operator = '=',
            mixed $foreignKey = null,
            JoinType|string $type = JoinType::Inner
        ) : static {
            $type = JoinType::resolve($type);
        
            $source = QueryState::normaliseSource($table);

            $conditions = [];
        
            # 1. Handle a callback; create a JoinBuilder for complex ON logic.
            if (is_callable($localKey)) {
                $builder = new JoinBuilder();
                $localKey($builder);
                $conditions = $builder->getConditions();
            }

            # 2. Handle shorthand keys (equality or custom operator).
            elseif ($localKey !== null) {
                # Allow parameter shifting.
                if (func_num_args() === 3 || (func_num_args() === 4 && $type instanceof JoinType)) {
                    $foreignKey = $operator;
                    $operator = '=';
                }
        
                $conditions[] = new ComparisonExpression(
                    ColumnIdentifier::from($localKey),
                    $operator,
                    ColumnIdentifier::from($foreignKey)
                );
            }
        
            $this->state->addJoin(new JoinExpression($source, $type, $conditions));
            return $this;
        }

        /**
         * Adds a left join to a query.
         * @param static|string|array|Expression $table The table to join.
         * @param string|array|callable $localKey The local key, expression, or JoinBuilder callback.
         * @param mixed $operator The operator or foreign key.
         * @param mixed $foreignKey The foreign key (if operator is provided).
         * @return static The query.
         */
        public function leftJoin (
            self|string|array $table, 
            string|array|callable $localKey, 
            mixed $operator = null, 
            mixed $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $operator, $foreignKey, JoinType::Left);
        }
        
        /**
         * Specifies a limit on the number of rows.
         * @param int $limit The maximum number of rows.
         * @param int $offset The number of rows to skip before starting (default: 0).
         * @return static The query.
         */
        public function limit (int $limit, int $offset = 0) : static {
            $this->state->setLimitOffset($limit, $offset);
            return $this;
        }

        /**
         * Sets the locking clause for the query.
         * @param LockType|string $type The lock type (Exclusive, Shared, None).
         * @param int|null $timeout Timeout in seconds (0 for NOWAIT).
         * @param bool $skipLocked Whether to skip already locked rows.
         */
        public function lock (LockType|string $type, ?int $timeout = null, bool $skipLocked = false) : static {
            $this->state->setLockType(LockType::resolve($type));
            $this->state->setLockTimeout($timeout);
            $this->state->setLockedSkipped($skipLocked);
            return $this;
        }
        
        /**
         * Specifies the order of the query results.
         * @param string|array|Expression $column The column(s) or expression(s) to order by.
         * @param string|OrderDirection $direction The order direction (default: ASC).
         * @param string|NullPrecedence $nulls The null precedence (default: none).
         * @return static The query.
         * @throws InvalidArgumentException If an invalid order by target is provided.
         */
        public function orderBy (
            string|array|Expression $column,
            string|OrderDirection $direction = OrderDirection::Ascending,
            string|NullPrecedence $nulls = NullPrecedence::None
        ) : static {
            $specs = [];
        
            # 1. Handle array input.
            if (is_array($column)) {
                foreach ($column as $col => $dir) {
                    # If $dir is an array, it might be [OrderDirection, NullPrecedence]
                    if (is_array($dir)) {
                        $specs[] = [$col, $dir[0] ?? $direction, $dir[1] ?? $nulls];
                    }
                    else $specs[] = [$col, $dir, $nulls];
                }
            } 
            # 2. Handle single column/expression input
            else $specs[] = [$column, $direction, $nulls];
        
            foreach ($specs as [$target, $dir, $precedence]) {
                if ($target instanceof OrderExpression) {
                    $this->state->addOrder($target);
                    continue;
                }
                $normalisedTarget = match (true) {
                    $target instanceof Expression => $target,
                    is_int($target) => new LiteralExpression($target),
                    is_string($target) => ColumnIdentifier::from($target),
                    default => throw new InvalidArgumentException("Invalid order by target.")
                };
        
                $resolvedDir = OrderDirection::resolve($dir);
                $resolvedNulls = NullPrecedence::resolve($precedence);
        
                $this->state->addOrder(new OrderExpression($normalisedTarget, $resolvedDir, $resolvedNulls));
            }
        
            return $this;
        }

        /**
         * Adds an OR condition to the HAVING clause.
         * @param mixed $column The column name or expression.
         * @param mixed $operator The comparison operator or value.
         * @param mixed $value The value to compare against.
         */
        public function orHaving (mixed $column, mixed $operator = null, mixed $value = null) : static {
            return $this->having($column, $operator, $value, "OR");
        }

        /**
         * Adds an OR condition to the WHERE clause.
         * @param mixed $leftOperand The left operand (column name, array of conditions, or closure).
         * @param mixed $operator The comparison operator or value.
         * @param mixed $rightOperand The right operand (value to compare against).
         */
        public function orWhere (mixed $leftOperand, mixed $operator = null, mixed $rightOperand = null) : static {
            return $this->where($leftOperand, $operator, $rightOperand, "OR");
        }
        
        /**
         * Specifies a BETWEEN condition in the WHERE clause with OR conjunction.
         * @param mixed $operand The operand to apply the BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @return static The query.
         */
        public function orWhereBetween (mixed $operand, mixed $min, mixed $max) : static {
            return $this->whereBetween($operand, $min, $max, false, "OR");
        }

        /**
         * Specifies a column-to-column comparison in the WHERE clause with OR conjunction.
         * @param string $first The first column.
         * @param string $operator The comparison operator.
         * @param string $second The second column.
         * @return static The query.
         */
        public function orWhereColumn (string $first, string $operator, string $second) : static {
            return $this->whereColumn($first, $operator, $second, "OR");
        }
        
        /**
         * Specifies an EXISTS condition in the WHERE clause with OR conjunction.
         * @param self $subQuery The subquery to check for existence.
         * @return static The query.
         */
        public function orWhereExists (self $subQuery) : static {
            return $this->whereExists($subQuery, false, "OR");
        }
        
        /**
         * Specifies an ILIKE condition in the WHERE clause.
         * @param mixed $operand The operand to apply the ILIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return static The query.
         */
        public function orWhereILike (mixed $operand, string $pattern) : static {
            return $this->whereILike($operand, $pattern, false, "OR");
        }

        /**
         * Specifies a LIKE condition in the WHERE clause.
         * @param mixed $operand The operand to apply the LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return static The query.
         */
        public function orWhereLike (mixed $operand, string $pattern) : static {
            return $this->whereLike($operand, $pattern, false, "OR");
        }
        
        /**
         * Specifies a REGEXP condition in the WHERE clause with OR conjunction.
         * @param mixed $operand The operand to apply the REGEXP condition on.
         * @param string $pattern The regex pattern to match against.
         * @return static The query.
         */
        public function orWhereRegex (mixed $operand, string $pattern) : static {
            return $this->whereRegex($operand, $pattern, false, "OR");
        }

        /**
         * Specifies pagination for a query.
         * @param int $page The page number (1-based).
         * @param int $perPage The number of items per page (default: 20).
         * @return static The query.
         */
        public function paginate (int $page, int $perPage = 20) : static {
            return $this->limit($perPage, ($page - 1) * $perPage);
        }

        /**
         * Specifies the columns to return.
         * @param string|array $columns The columns to return (default: all columns).
         * @return static The query.
         */
        public function return (string|array $columns = ['*']) : static {
            $this->state->addReturns((array) $columns);
            return $this;
        }

        /**
         * Adds a right join to a query.
         * @param static|string|array|Expression $table The table to join.
         * @param string|array|callable $localKey The local key, expression, or JoinBuilder callback.
         * @param mixed $operator The operator or foreign key.
         * @param mixed $foreignKey The foreign key (if operator is provided).
         * @return static The query.
         */
        public function rightJoin (
            self|string|array $table, 
            string|array|callable $localKey, 
            mixed $operator = null, 
            mixed $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $operator, $foreignKey, JoinType::Right);
        }

        /**
         * Specifies the fields to select.
         * @param string|array|Expression ...$fields The fields to select.
         * @return static The query.
         * @throws InvalidArgumentException If an invalid select type is provided.
         */
        public function select (string|array|Expression ...$fields) : static {
            # 1. Flatten the variadic arguments.
            $columns = (count($fields) === 1 && is_array($fields[0])) ? $fields[0] : $fields;

            # 2. Default to all if empty.
            if (empty($columns)) {
                $columns = ['*'];
            }

            $normalised = [];

            foreach ($columns as $key => $value) {
                $alias = is_string($key) ? $key : null;

                $field = static::ensureExpression($value, true);

                # If an alias was provided in the array key, override any alias parsed from the string.
                if ($alias && $field instanceof Aliasable) {
                    $field->alias($alias);
                }

                $normalised[] = $field;
            }

            $this->state->addSelects($normalised);

            return $this;
        }

        /**
         * Specifies a raw SQL expression to select.
         * @param string $expression The raw SQL expression.
         * @param array $params The parameters for the expression.
         * @return static The query.
         */
        public function selectRaw (string $expression, array $params = []) : static {
            $this->state->addSelect(new RawExpression($expression, $params));
            return $this;
        }

        /**
         * Specifies a SUM aggregate function.
         * @param mixed $operand The operand to sum.
         * @param bool $distinct Whether to apply DISTINCT to the aggregation (default: false).
         * @param string|null $alias An optional alias for the sum result.
         * @return static The query.
         */
        public function sum (mixed $operand, bool $distinct = false, ?string $alias = null) : static {
            return $this->aggregate("SUM", $operand, $distinct, $alias);
        }

        /**
         * Specifies a UNION operation between two queries.
         * @param self $first The first query to union with.
         * @param self ...$other The other queries to union with.
         * @return static The query.
         */
        public function union (self $first, self ...$other) : static {
            $this->state->addSetOperations(SetOperation::Union, array_merge([$first], $other));
            return $this;
        }

        /**
         * Specifies a UNION ALL operation between two queries.
         * @param self $first The first query to union with.
         * @param self ...$other The other queries to union with.
         * @return static The query.
         */
        public function unionAll (self $first, self ...$other) : static {
            $this->state->addSetOperations(SetOperation::UnionAll, array_merge([$first], $other));
            return $this;
        }
        
        /**
         * Creates a builder that handles UPDATE operations.
         * @param string $table The target table.
         * @return UpdateBuilder A new update builder.
         */
        public function update (string $table) : UpdateBuilder {
            $this->state->setTargetTable($table);
            return new UpdateBuilder($this, $this->state);
        }

        /**
         * Conditionally applies a callback to the query.
         * @param mixed $value The condition value.
         * @param callable $callback The callback to execute if the condition is truthy.
         * @param callable|null $default The callback to execute if the condition is falsy (optional).
         * @return static The query.
         */
        public function when (mixed $value, callable $callback, ?callable $default = null) : static {
            ($value ? $callback : $default)($this, $value);
            return $this;
        }

        /**
         * Specifies a condition for the WHERE clause of a query. Examples:
         * 1. `->where(['status' => 'active', 'role' => 'admin'])`
         * 2. `->where('id', 1) `
         * 3. `->where('price', '>', 100)`
         * 4. `->where(fn ($q) => ...)`
         * @param mixed $operand The column name, array of conditions, or closure for nested conditions.
         * @param mixed $operator The comparison operator or value.
         * @param mixed $value The value to compare against.
         * @param string $conjunction The conjunction to use ('AND' or 'OR') with the previous condition.
         */
        public function where (mixed $operand, mixed $operator = null, mixed $value = null, string $conjunction = "AND") : static {
            $numArgs = func_num_args();

            # 1. Array of conditions: ['status' => 'active', 'deleted' => 0]
            if (is_array($operand)) {
                foreach ($operand as $key => $val) {
                    $this->where($key, '=', $val, $conjunction);
                }
                return $this;
            }

            # 2. Nested Logic via Closure
            if (is_callable($operand) && !($operand instanceof Expression)) {
                return $this->whereNested($operand, $conjunction);
            }

            # 3. Shorthand: where('id', 1) -> id = 1
            if ($numArgs === 2 || ($numArgs === 3 && $value === null && !in_array(strtoupper((string) $operator), ["IS", "IS NOT"]))) {
                $value = $operator;
                $operator = '=';
            }

            # 4. Create the correct Expression Object
            $expression = static::createPredicate($operand, (string) $operator, $value);
            
            # Ensure the conj$conjunction (AND/OR) is respected.
            $expression->setConjunction($conjunction);

            $this->state->addWhere($expression);

            return $this;
        }

        /**
         * Specifies a BETWEEN condition in the WHERE clause.
         * @param mixed $operand The operand to apply the BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @param bool $not Whether to use NOT BETWEEN (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereBetween (mixed $operand, mixed $min, mixed $max, bool $not = false, string $conjunction = "AND") : static {
            $expression = new BetweenExpression($operand, $min, $max, $not);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }

        /**
         * Specifies a column-to-column comparison in the WHERE clause.
         * @param string $first The first column.
         * @param string|null $operator The comparison operator or the second column.
         * @param string|null $second The second column (if an operator is provided).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereColumn (string $first, ?string $operator = null, ?string $second = null, string $conjunction = "AND") : static {
            if (func_num_args() === 2) {
                $second = $operator;
                $operator = '=';
            } 
            elseif (func_num_args() === 3) {
                $upper = strtoupper($second);
                if ($upper === "AND" || $upper === "OR") {
                    $conjunction = $upper;
                    $second = $operator;
                    $operator = '=';
                }
            }

            $first = ColumnIdentifier::from($first);
            $second = ColumnIdentifier::from($second);
            $expression = new ComparisonExpression($first, $operator, $second);

            $this->state->addWhere($expression->setConjunction($conjunction));
            
            return $this;
        }

        /**
         * Specifies an EXISTS condition in the WHERE clause.
         * @param static $subQuery The subquery to check for existence.
         * @param bool $not Whether to use NOT EXISTS (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereExists (self $subQuery, bool $not = false, string $conjunction = "AND") : static {
            $expression = new ExistsExpression(new QueryExpression($subQuery), $not);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }
        
        /**
         * Specifies a group of criteria in the WHERE clause.
         * @param array $criteria An associative array of column-value pairs.
         * @param string $conjunction The conjunction to combine the criteria ('AND' or 'OR').
         * @param string $outerConjunction The boolean operator to combine with other conditions (default: 'AND').
         * @return static The query.
         */
        public function whereGroup (array $criteria, string $innerConjunction = 'AND', string $outerConjunction = "AND") : static {
            $expressions = [];
            $isFirst = true;
        
            foreach ($criteria as $column => $value) {
                $expr = static::createPredicate($column, '=', $value);
                $expr->setConjunction($isFirst ? "" : $innerConjunction);
                $expressions[] = $expr;
                $isFirst = false;
            }
            
            $this->state->addWhere(new BooleanExpression($expressions, $innerConjunction, $outerConjunction));
        
            return $this;
        }

        /**
         * Specifies a LIKE condition in the WHERE clause.
         * @param mixed $operand The operand to apply the LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @param bool $not Whether to use NOT LIKE (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereILike (mixed $operand, string $pattern, bool $not = false, string $conjunction = "AND") : static {
            $expression = new LikeExpression($operand, $pattern, $not, true);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }

        /**
         * Specifies an IN condition in the WHERE clause.
         * @param mixed $operand The operand to apply the IN condition on.
         * @param array|static $values The values or subquery to check against.
         * @param bool $not Whether to use NOT IN (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereIn (mixed $operand, array|self $values, bool $not = false, string $conjunction = "AND") : static {
            if ($values instanceof self) {
                $values = new QueryExpression($values);
            }
            else $values = array_map(fn($v) => static::ensureExpression($v), $values);
            $operand = static::ensureExpression($operand, true);
            $expression = new InExpression($operand, $values, $not);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }

        /**
         * Specifies a LIKE condition in the WHERE clause.
         * @param mixed $operand The operand to apply the LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @param bool $not Whether to use NOT LIKE (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereLike (mixed $operand, string $pattern, bool $not = false, string $conjunction = "AND") : static {
            $expression = new LikeExpression($operand, $pattern, $not, false);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }

        /**
         * Specifies a nested WHERE condition using a closure.
         * @param callable $callback The closure that defines the nested conditions.
         * @param string $conjunction The conjunction operator to combine with other conditions (default: "AND").
         * @return static The query.
         */
        public function whereNested (callable $callback, string $conjunction = "AND") : static {
            $subQuery = new static();
            $callback($subQuery);
        
            if ($subQuery->state->hasWheres()) {
                $group = new BooleanExpression($subQuery->state->getWheres(), $conjunction);
                $this->state->addWhere($group);
            }
        
            return $this;
        }

        /**
         * Specifies a NOT BETWEEN condition.
         * @param mixed $operand The operand to apply the NOT BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @return static The query.
         */
        public function whereNotBetween (mixed $operand, mixed $min, mixed $max) : static {
            return $this->whereBetween($operand, $min, $max, true);
        }

        /**
         * Specifies a NOT EXISTS condition.
         * @param self $subQuery The subquery to check for non-existence.
         * @return static The query.
         */
        public function whereNotExists (self $subQuery) : static {
            return $this->whereExists($subQuery, true);
        }

        /**
         * Specifies a NOT ILIKE condition.
         * @param mixed $operand The operand to apply the NOT ILIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return static The query.
         */
        public function whereNotILike (mixed $operand, string $pattern) : static {
            return $this->whereILike($operand, $pattern, true);
        }

        /**
         * Specifies a NOT IN condition.
         * @param mixed $operand The operand to apply the NOT IN condition on.
         * @param array $values The values to check against.
         * @return static The query.
         */
        public function whereNotIn (mixed $operand, array $values) : static {
            return $this->whereIn($operand, $values, true);
        }

        /**
         * Specifies a NOT LIKE condition.
         * @param mixed $operand The operand to apply the NOT LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return static The query.
         */
        public function whereNotLike (mixed $operand, string $pattern) : static {
            return $this->whereLike($operand, $pattern, true);
        }

        /**
         * Specifies a IS NOT NULL condition.
         * @param mixed $operand The operand to check for non-null values.
         * @return static The query.
         */
        public function whereNotNull (mixed $operand) : static {
            return $this->whereNull($operand, true);
        }

        /**
         * Specifies a NOT REGEXP condition.
         * @param mixed $operand The operand to apply the NOT REGEXP condition on.
         * @param string $pattern The regex pattern to match against.
         * @return static The query.
         */
        public function whereNotRegex (mixed $operand, string $pattern) : static {
            return $this->whereRegex($operand, $pattern, true);
        }

        /**
         * Specifies a IS NULL condition in the WHERE clause.
         * @param mixed $operand The operand to check for null values.
         * @param bool $not Whether to use IS NOT NULL (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereNull (mixed $operand, bool $not = false, string $conjunction = "AND") : static {
            $expression = new NullExpression($operand, $not);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }

        /**
         * Specifies a raw condition for the WHERE clause of a query.
         * @param string $expression The raw SQL expression.
         * @param string|array $params The parameters for the expression.
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         */
        public function whereRaw (string $expression, string|array $params = [], string $conjunction = "AND") : static {
            $params = (array) $params;
            $expression = new RawExpression($expression, $params);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }
        
        /**
         * Specifies a REGEXP condition in the WHERE clause.
         * @param mixed $operand The operand to apply the REGEXP condition on.
         * @param string $pattern The regex pattern to match against.
         * @param bool $not Whether to use NOT REGEXP (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereRegex (mixed $operand, string $pattern, bool $not = false, string $conjunction = "AND") : static {
            $expression = new RegexExpression($operand, $pattern, $not);
            $this->state->addWhere($expression->setConjunction($conjunction));
            return $this;
        }

        /**
         * Registers a window function in the select clause.
         * @param string $function The window function name (e.g., ROW_NUMBER, RANK).
         * @param array $arguments The arguments for the window function.
         * @param array $partitionBy The columns to partition by.
         * @param array $orderBy The columns to order by.
         * @param string|null $alias An optional alias for the window result.
         */
        public function window (string $function, array $arguments = [], array $partitionBy = [], array $orderBy = [], ?string $alias = null) : static {
            $orderExpressions = [];
            foreach ($orderBy as $key => $value) {
                if ($value instanceof OrderExpression) {
                    $orderExpressions[] = $value;
                }
                else {
                    $target = is_int($key) ? $value : $key;
                    $direction = is_int($key) ? OrderDirection::Ascending : OrderDirection::resolve($value);
                    $orderExpressions[] = new OrderExpression($target, $direction);
                }
            }

            $this->state->addSelect(new WindowExpression($function, $arguments, $partitionBy, $orderBy, $alias));
            return $this;
        }

        /**
         * Adds a Common Table Expression (CTE) to a query.
         * @param string $name The name of the CTE.
         * @param static|QueryExpression|callable $query The subquery defining the CTE.
         * @param array $columns Optional column names for the CTE.
         * @return static The query with the added CTE.
         */
        public function with (string $name, self|QueryExpression|callable $query, array $columns = []) : static {
            if (is_callable($query)) {
                $callback = $query;
                $query = new static();
                $callback($query);
            }
            $query = $query instanceof QueryExpression ? $query : new QueryExpression($query);
            $this->state->addCte(new CteExpression(name: $name, query: $query, columns: $columns));
            return $this;
        }
        
        /**
         * Adds a recursive Common Table Expression (CTE) to a query.
         * @param string $name The name of the CTE.
         * @param static|QueryExpression $anchor The anchor query for the recursive CTE.
         * @param static|QueryExpression $recursive The recursive query for the CTE.
         * @param array $columns Optional column names for the CTE.
         * @return static The query with the added recursive CTE.
         */
        public function withRecursive (string $name, self|QueryExpression $anchor, self|QueryExpression $recursive, array $columns = []) : static {
            $anchor = $anchor instanceof QueryExpression ? $anchor : new QueryExpression($anchor);
            $recursive = $recursive instanceof QueryExpression ? $recursive : new QueryExpression($recursive);
            $this->state->addCte(new CteExpression($name, $anchor, true, $recursive, $columns));
            return $this;
        }
    }
?>