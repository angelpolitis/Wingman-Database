<?php
    /*/
	 * Project Name:    Wingman — Database — Query
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Facades namespace.
    namespace Wingman\Database\Facades;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Builders\{
        CaseBuilder, InsertBuilder, QueryBuilder, UpdateBuilder
    };
    use Wingman\Database\Enums\JoinType;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Enums\NullPrecedence;
    use Wingman\Database\Enums\OrderDirection;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Expressions\{
        AggregateExpression, BetweenExpression, ComparisonExpression,
        RandomExpression, RawExpression, ColumnIdentifier,
        OrderExpression
    };
    use Wingman\Database\Objects\UniqueConstraint;

    /**
     * Represents a SQL query builder.
     * @package Wingman\Database\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Query {
        /**
         * Creates an aggregate function expression.
         * @param string $function The aggregate function name (e.g., COUNT, SUM).
         * @param string|Expression $expression The expression to aggregate.
         * @param bool $distinct Whether to apply DISTINCT to the aggregation (default: false).
         * @param string|null $alias An optional alias for the aggregated result.
         * @return AggregateExpression The aggregate expression.
         */
        public static function agg (string $function, string|Expression $expression, bool $distinct = false, ?string $alias = null) : AggregateExpression {
            $expression = QueryBuilder::ensureExpression($expression);
            return new AggregateExpression($function, $expression, $distinct, $alias);
        }

        /**
         * Creates a BETWEEN expression.
         * @param mixed $operand The operand to apply the BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @param bool $not Whether to use NOT BETWEEN (default: false).
         * @return BetweenExpression The BETWEEN expression.
         */
        public static function between (mixed $operand, mixed $min, mixed $max, bool $not = false) : BetweenExpression {
            return new BetweenExpression($operand, $min, $max, $not);
        }

        /**
         * Creates a CASE expression builder.
         * @param string|Expression $operand The operand for the CASE expression.
         * @return CaseBuilder The CASE expression builder.
         */
        public static function case (string|Expression $operand) : CaseBuilder {
            $operand = QueryBuilder::ensureExpression($operand);
            return new CaseBuilder($operand);
        }

        /**
         * Creates a column identifier.
         * @param string $name The name of the column.
         * @return ColumnIdentifier The column identifier.
         */
        public static function column (string $name) : ColumnIdentifier {
            return new ColumnIdentifier($name);
        }

        /**
         * Creates a comparison expression.
         * @param mixed $operand The operand to compare.
         * @param string $operator The comparison operator (e.g., '=', '>', '<').
         * @param mixed $value The value to compare against.
         * @param string|null $alias An optional alias for the comparison result.
         * @return ComparisonExpression The comparison expression.
         */
        public static function compare (mixed $operand, string $operator, mixed $value, ?string $alias = null) : ComparisonExpression {
            return new ComparisonExpression($operand, $operator, $value, $alias);
        }

        /**
         * Creates a unique constraint.
         * @param string $name The name of the constraint.
         * @return UniqueConstraint The constraint.
         */
        public static function constraint (string $name) : UniqueConstraint {
            return new UniqueConstraint($name);
        }

        /**
         * Specifies that the query is a DELETE operation.
         * @param string|null $targetAlias The alias of the target table to delete from (optional).
         * @return QueryBuilder The query builder with the delete operation applied.
         */
        public static function delete (?string $targetAlias = null) : QueryBuilder {
            return (new QueryBuilder())->delete($targetAlias);
        }

        /**
         * Specifies an EXCEPT operation between at least one or more queries.
         * @param QueryBuilder $first The first query builder.
         * @param QueryBuilder ...$others Additional query builders to except with.
         * @return QueryBuilder The query builder with the except applied.
         */
        public static function except (QueryBuilder $first, QueryBuilder ...$others) : QueryBuilder {
            return (new QueryBuilder())->except($first, ...$others);
        }
        
        /**
         * Specifies the table to select from.
         * @param string|array|QueryBuilder $table The table name, array of tables, or subquery.
         * @param string|null $alias An optional alias for the table.
         * @return QueryBuilder A new query builder with the from clause applied.
         */
        public static function from (string|array|QueryBuilder $table, ?string $alias = null) : QueryBuilder {
            return (new QueryBuilder())->from($table, $alias);
        }

        /**
         * Adds a full join to a query.
         * @param static|string|array|Expression $table The table to join (can be a subquery).
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex ON logic.
         * @param string|array|null $operator A comparison operator or the foreign key(s) (if 3-argument call).
         * @param mixed $foreignKey The foreign key(s) for the join (if 4-argument call).
         * @return QueryBuilder A new query builder with the full join applied.
         */
        public static function fullJoin (
            self|string|array|Expression $table,
            string|array|callable|null $localKey = null,
            string|array|null $operator = '=',
            mixed $foreignKey = null
        ) : QueryBuilder {
            return (new QueryBuilder())->join($table, $localKey, $operator, $foreignKey, JoinType::Full);
        }
        
        /**
         * Specifies the fields to group by.
         * @param int|string|array $fields The field(s) to group by (can be column names, expressions, or ordinals).
         * @return QueryBuilder A new query builder with the group by applied.
         */
        public static function groupBy (int|string|array $fields) : QueryBuilder {
            return (new QueryBuilder())->groupBy($fields);
        }
        
        /**
         * Specifies the condition used to filter grouped records.
         * @param mixed $column The column or expression to apply the HAVING condition on.
         * @param string $operator The comparison operator.
         * @param mixed $value The value to compare against.
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder A new query builder with the having condition applied.
         */
        public static function having (mixed $column, mixed $operator = null, mixed $value = null, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->having($column, $operator, $value, $conjunction);
        }
        
        /**
         * Adds an inner join to a query.
         * @param static|string|array|Expression $table The table to join (can be a subquery).
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex ON logic.
         * @param string|array|null $operator A comparison operator or the foreign key(s) (if 3-argument call).
         * @param mixed $foreignKey The foreign key(s) for the join (if 4-argument call).
         * @return QueryBuilder A new query builder with the inner join applied.
         */
        public static function innerJoin (
            self|string|array|Expression $table,
            string|array|callable|null $localKey = null,
            string|array|null $operator = '=',
            mixed $foreignKey = null
        ) : QueryBuilder {
            return (new QueryBuilder())->join($table, $localKey, $operator, $foreignKey, JoinType::Inner);
        }

        /**
         * Creates a builder that handles INSERT operations.
         * @param string $table The target table.
         * @return InsertBuilder A new insert builder.
         */
        public static function into (string $table) : InsertBuilder {
            return (new QueryBuilder())->into($table);
        }

        /**
         * Specifies an INTERSECT operation between at least one or more queries.
         * @param QueryBuilder $first The first query builder.
         * @param QueryBuilder ...$others Additional query builders to intersect with.
         * @return QueryBuilder The query builder with the intersect applied.
         */
        public static function intersect (QueryBuilder $first, QueryBuilder ...$others) : QueryBuilder {
            return (new QueryBuilder())->intersect($first, ...$others);
        }

        /**
         * Joins a table to a query.
         * @param static|string|array|Expression $table The table to join (can be a subquery).
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex ON logic.
         * @param string|array|null $operator A comparison operator or the foreign key(s) (if 3-argument call).
         * @param mixed $foreignKey The foreign key(s) for the join (if 4-argument call).
         * @param JoinType|string $type The type of join (default: INNER).
         * @return QueryBuilder A new query builder with the join applied.
         */
        public static function join (
            self|string|array|Expression $table,
            string|array|callable|null $localKey = null,
            string|array|null $operator = '=',
            mixed $foreignKey = null,
            JoinType|string $type = JoinType::Inner
        ) : QueryBuilder {
            return (new QueryBuilder())->join($table, $localKey, $operator, $foreignKey, $type);
        }

        /**
         * Adds a left join to a query.
         * @param QueryBuilder|string|array|Expression $table The table to join.
         * @param string|array|callable $localKey The local key, expression, or JoinBuilder callback.
         * @param mixed $operator The operator or foreign key.
         * @param mixed $foreignKey The foreign key (if operator is provided).
         * @return QueryBuilder The query builder with the left join applied.
         */
        public static function leftJoin (
            self|string|array $table, 
            string|array|callable $localKey, 
            mixed $operator = null, 
            mixed $foreignKey = null
        ) : QueryBuilder {
            return (new QueryBuilder())->join($table, $localKey, $operator, $foreignKey, JoinType::Left);
        }
        
        /**
         * Specifies a limit on the number of rows.
         * @param int $limit The maximum number of rows.
         * @param int $offset The number of rows to skip before starting (default: 0).
         * @return QueryBuilder The query builder with the limit applied.
         */
        public static function limit (int $limit, int $offset = 0) : QueryBuilder {
            return (new QueryBuilder())->limit($limit, $offset);
        }

        /**
         * Specifies a lock for the query.
         * @param string|LockType $type The type of lock (e.g., SHARED, EXCLUSIVE).
         * @param int|null $timeout An optional timeout in seconds.
         * @param bool $skipLocked Whether to skip locked rows (default: false).
         * @return QueryBuilder The query builder with the lock applied.
         */
        public static function lock (string|LockType $type, ?int $timeout = null, bool $skipLocked = false) : QueryBuilder {
            return (new QueryBuilder())->lock($type, $timeout, $skipLocked);
        }

        /**
         * Creates an order expression.
         * @param ColumnIdentifier|Expression|string $target The column or expression to order by.
         * @param string|OrderDirection $direction The order direction (default: ASC).
         * @param string|NullPrecedence $nulls The null precedence (default: none).
         * @return OrderExpression The order expression.
         */
        public static function order (
            ColumnIdentifier|Expression|string $target,
            string|OrderDirection $direction = OrderDirection::Ascending,
            string|NullPrecedence $nulls = NullPrecedence::None
        ) : OrderExpression {
            $target = QueryBuilder::ensureExpression($target, true);
            if ($target instanceof OrderExpression) return $target;
            $direction = OrderDirection::resolve($direction);
            $nulls = NullPrecedence::resolve($nulls);
            return new OrderExpression($target, $direction, $nulls);
        }
        
        /**
         * Specifies the order of the query results.
         * @param string|array|Expression $column The column(s) or expression(s) to order by.
         * @param string|OrderDirection $direction The order direction (default: ASC).
         * @param string|NullPrecedence $nulls The null precedence (default: none).
         * @return QueryBuilder The query builder with the order by applied.
         * @throws InvalidArgumentException If an invalid order by target is provided.
         */
        public static function orderBy (
            string|array|Expression $column,
            string|OrderDirection $direction = OrderDirection::Ascending,
            string|NullPrecedence $nulls = NullPrecedence::None
        ) : QueryBuilder {
            return (new QueryBuilder())->orderBy($column, $direction, $nulls);
        }

        /**
         * Specifies pagination for a query.
         * @param int $page The page number (1-based).
         * @param int $perPage The number of items per page (default: 20).
         * @return QueryBuilder The query builder with pagination applied.
         */
        public static function paginate (int $page, int $perPage = 20) : QueryBuilder {
            return (new QueryBuilder())->paginate($page, $perPage);
        }

        /**
         * Creates a random expression.
         * @return RandomExpression The random expression.
         */
        public static function random () : RandomExpression {
            return new RandomExpression();
        }

        /**
         * Creates a raw SQL expression.
         * @param string $expression The raw SQL expression.
         * @param array $params The parameters for the expression.
         * @return RawExpression The raw expression.
         */
        public static function raw (string $expression, array $params = []) : RawExpression {
            return new RawExpression($expression, $params);
        }

        /**
         * Specifies the columns to return.
         * @param string|array $columns The columns to return (default: all columns).
         * @return QueryBuilder The query builder with the return columns applied.
         */
        public static function return (string|array $columns = ['*']) : QueryBuilder {
            return (new QueryBuilder())->return($columns);
        }

        /**
         * Adds a right join to a query.
         * @param static|string|array|Expression $table The table to join.
         * @param string|array|callable $localKey The local key, expression, or JoinBuilder callback.
         * @param mixed $operator The operator or foreign key.
         * @param mixed $foreignKey The foreign key (if operator is provided).
         * @return QueryBuilder The query builder with the right join applied.
         */
        public static function rightJoin (
            self|string|array $table, 
            string|array|callable $localKey, 
            mixed $operator = null, 
            mixed $foreignKey = null
        ) : QueryBuilder {
            return (new QueryBuilder())->join($table, $localKey, $operator, $foreignKey, JoinType::Right);
        }

        /**
         * Specifies the fields to select.
         * @param string|array|Expression ...$fields The fields to select.
         * @return QueryBuilder The query builder with the select fields applied.
         * @throws InvalidArgumentException If an invalid select type is provided.
         */
        public static function select (string|array|Expression ...$fields) : QueryBuilder {
            return (new QueryBuilder())->select(...$fields);
        }

        /**
         * Specifies a raw SQL expression to select.
         * @param string $expression The raw SQL expression.
         * @param array $params The parameters for the expression.
         * @return QueryBuilder The query builder with the raw select applied.
         */
        public static function selectRaw (string $expression, array $params = []) : QueryBuilder {
            return (new QueryBuilder())->selectRaw($expression, $params);
        }

        /**
         * Specifies a SUM aggregate function.
         * @param string $column The column to sum.
         * @param bool $distinct Whether to apply DISTINCT to the aggregation (default: false).
         * @param string|null $alias An optional alias for the sum result.
         * @return QueryBuilder The query builder with the sum applied.
         */
        public static function sum (string $column, bool $distinct = false, ?string $alias = null) : QueryBuilder {
            return (new QueryBuilder())->sum($column, $distinct, $alias);
        }

        /**
         * Specifies a UNION operation between at least one or more queries.
         * @param QueryBuilder $first The first query builder.
         * @param QueryBuilder ...$others Additional query builders to union with.
         * @return QueryBuilder The query builder with the union applied.
         */
        public static function union (QueryBuilder $first, QueryBuilder ...$others) : QueryBuilder {
            return (new QueryBuilder())->union($first, ...$others);
        }

        /**
         * Specifies a UNION ALL operation between at least one or more queries.
         * @param QueryBuilder $first The first query builder.
         * @param QueryBuilder ...$others Additional query builders to union all with.
         * @return QueryBuilder The query builder with the union all applied.
         */
        public static function unionAll (QueryBuilder $first, QueryBuilder ...$others) : QueryBuilder {
            return (new QueryBuilder())->unionAll($first, ...$others);
        }

        /**
         * Creates a builder that handles UPDATE operations.
         * @param string $table The target table.
         * @return UpdateBuilder A new update builder.
         */
        public static function update (string $table) : UpdateBuilder {
            return (new QueryBuilder())->update($table);
        }

        /**
         * Conditionally applies a callback to the query.
         * @param mixed $value The condition value.
         * @param callable $callback The callback to execute if the condition is truthy.
         * @param callable|null $default The callback to execute if the condition is falsy (optional).
         * @return QueryBuilder The query builder with the conditional logic applied.
         */
        public static function when (mixed $value, callable $callback, ?callable $default = null) : QueryBuilder {
            return (new QueryBuilder())->when($value, $callback, $default);
        }

        /**
         * Specifies a condition for the WHERE clause of a query. Examples:
         * 1. `->where(['status' => 'active', 'role' => 'admin'])`
         * 2. `->where('id', 1) `
         * 3. `->where('price', '>', 100)`
         * 4. `->where(fn ($q) => ...)`
         * @param mixed $column The column name, array of conditions, or closure for nested conditions.
         * @param mixed $operator The comparison operator or value.
         * @param mixed $value The value to compare against.
         * @param string $conjunction The conjunction to use ('AND' or 'OR') with the previous condition.
         * @return QueryBuilder The query builder with the where condition applied.
         */
        public static function where (mixed $column, mixed $operator = null, mixed $value = null, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->where($column, $operator, $value, $conjunction);
        }

        /**
         * Specifies a BETWEEN condition in the WHERE clause.
         * @param mixed $operand The operand to apply the BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @param bool $not Whether to use NOT BETWEEN (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the between condition applied.
         */
        public static function whereBetween (mixed $operand, mixed $min, mixed $max, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereBetween($operand, $min, $max, $not, $conjunction);
        }

        /**
         * Specifies a column-to-column comparison in the WHERE clause.
         * @param string $first The first column.
         * @param string|null $operator The comparison operator or the second column.
         * @param string|null $second The second column (if an operator is provided).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the column comparison applied.
         */
        public static function whereColumn (string $first, ?string $operator = null, ?string $second = null, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereColumn($first, $operator, $second, $conjunction);
        }

        /**
         * Specifies an EXISTS condition in the WHERE clause.
         * @param QueryBuilder $subQuery The subquery to check for existence.
         * @param bool $not Whether to use NOT EXISTS (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the exists condition applied.
         */
        public static function whereExists (QueryBuilder $subQuery, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereExists($subQuery, $not, $conjunction);
        }
        
        /**
         * Specifies a group of criteria in the WHERE clause.
         * @param array $criteria An associative array of column-value pairs.
         * @param string $conjunction The conjunction to combine the criteria ('AND' or 'OR').
         * @param string $outerConjunction The boolean operator to combine with other conditions (default: 'AND').
         * @return QueryBuilder The query builder with the grouped criteria applied.
         */
        public static function whereGroup (array $criteria, string $innerConjunction = 'AND', string $outerConjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereGroup($criteria, $innerConjunction, $outerConjunction);
        }

        /**
         * Specifies a LIKE condition in the WHERE clause.
         * @param mixed $operand The operand to apply the LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @param bool $not Whether to use NOT LIKE (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the ILIKE condition applied.
         */
        public static function whereILike (mixed $operand, string $pattern, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereILike($operand, $pattern, $not, $conjunction);
        }

        /**
         * Specifies an IN condition in the WHERE clause.
         * @param mixed $operand The operand to apply the IN condition on.
         * @param array|QueryBuilder $values The values or subquery to check against.
         * @param bool $not Whether to use NOT IN (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the IN condition applied.
         */
        public static function whereIn (mixed $operand, array|QueryBuilder $values, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereIn($operand, $values, $not, $conjunction);
        }

        /**
         * Specifies a LIKE condition in the WHERE clause.
         * @param mixed $operand The operand to apply the LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @param bool $not Whether to use NOT LIKE (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the LIKE condition applied.
         */
        public static function whereLike (mixed $operand, string $pattern, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereLike($operand, $pattern, $not, $conjunction);
        }

        /**
         * Specifies a nested WHERE condition using a closure.
         * @param callable $callback The closure that defines the nested conditions.
         * @param string $conjunction The conjunction operator to combine with other conditions (default: "AND").
         * @return QueryBuilder The query builder with the nested conditions applied.
         */
        public static function whereNested (callable $callback, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereNested($callback, $conjunction);
        }

        /**
         * Specifies a NOT BETWEEN condition.
         * @param string $operand The operand to apply the NOT BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @return QueryBuilder The query builder with the NOT BETWEEN condition applied.
         */
        public static function whereNotBetween (string $operand, mixed $min, mixed $max) : QueryBuilder {
            return static::whereBetween($operand, $min, $max, true);
        }

        /**
         * Specifies a NOT EXISTS condition.
         * @param QueryBuilder $subQuery The subquery to check for non-existence.
         * @return QueryBuilder The query builder with the NOT EXISTS condition applied.
         */
        public static function whereNotExists (QueryBuilder $subQuery) : QueryBuilder {
            return static::whereExists($subQuery, true);
        }

        /**
         * Specifies a NOT ILIKE condition.
         * @param mixed $operand The operand to apply the NOT ILIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return QueryBuilder The query builder with the NOT ILIKE condition applied.
         */
        public static function whereNotILike (mixed $operand, string $pattern) : QueryBuilder {
            return static::whereILike($operand, $pattern, true);
        }

        /**
         * Specifies a NOT IN condition.
         * @param mixed $operand The operand to apply the NOT IN condition on.
         * @param array $values The values to check against.
         * @return QueryBuilder The query builder with the NOT IN condition applied.
         */
        public static function whereNotIn (mixed $operand, array $values) : QueryBuilder {
            return static::whereIn($operand, $values, true);
        }

        /**
         * Specifies a NOT LIKE condition.
         * @param mixed $operand The operand to apply the NOT LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return QueryBuilder The query builder with the NOT LIKE condition applied.
         */
        public static function whereNotLike (mixed $operand, string $pattern) : QueryBuilder {
            return static::whereLike($operand, $pattern, true);
        }

        /**
         * Specifies a IS NOT NULL condition.
         * @param mixed $operand The operand to check for non-null values.
         * @return QueryBuilder The query builder with the IS NOT NULL condition applied.
         */
        public static function whereNotNull (mixed $operand) : QueryBuilder {
            return static::whereNull($operand, true);
        }

        /**
         * Specifies a NOT REGEXP condition.
         * @param mixed $operand The operand to apply the NOT REGEXP condition on.
         * @param string $pattern The regex pattern to match against.
         * @return QueryBuilder The query builder with the NOT REGEXP condition applied.
         */
        public static function whereNotRegex (mixed $operand, string $pattern) : QueryBuilder {
            return static::whereRegex($operand, $pattern, true);
        }

        /**
         * Specifies a IS NULL condition in the WHERE clause.
         * @param mixed $operand The operand to check for null values.
         * @param bool $not Whether to use IS NOT NULL (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the null condition applied.
         */
        public static function whereNull (mixed $operand, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereNull($operand, $not, $conjunction);
        }

        /**
         * Specifies a raw condition for the WHERE clause of a query.
         * @param string $expression The raw SQL expression.
         * @param string|array $params The parameters for the expression.
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the raw where condition applied.
         */
        public static function whereRaw (string $expression, string|array $params = [], string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereRaw($expression, $params, $conjunction);
        }
        
        /**
         * Specifies a REGEXP condition in the WHERE clause.
         * @param mixed $operand The operand to apply the REGEXP condition on.
         * @param string $pattern The regex pattern to match against.
         * @param bool $not Whether to use NOT REGEXP (default: false).
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return QueryBuilder The query builder with the regex condition applied.
         */
        public static function whereRegex (mixed $operand, string $pattern, bool $not = false, string $conjunction = "AND") : QueryBuilder {
            return (new QueryBuilder())->whereRegex($operand, $pattern, $not, $conjunction);
        }

        /**
         * Registers a window function in the select clause.
         * @param string $function The window function name (e.g., ROW_NUMBER, RANK).
         * @param array $arguments The arguments for the window function.
         * @param array $partitionBy The columns to partition by.
         * @param array $orderBy The columns to order by.
         * @param string|null $alias An optional alias for the window result.
         * @return QueryBuilder The query builder with the window function applied.
         */
        public static function window (string $function, array $arguments = [], array $partitionBy = [], array $orderBy = [], ?string $alias = null) : QueryBuilder {
            return (new QueryBuilder())->window($function, $arguments, $partitionBy, $orderBy, $alias);
        }

        /**
         * Adds a Common Table Expression (CTE) to a query.
         * @param string $name The name of the CTE.
         * @param QueryBuilder $query The subquery defining the CTE.
         * @return QueryBuilder The query builder with the added CTE.
         */
        public static function with (string $name, QueryBuilder|callable $query, array $columns = []) : QueryBuilder {
            return (new QueryBuilder())->with($name, $query, $columns);
        }
        
        /**
         * Adds a recursive Common Table Expression (CTE) to a query.
         * @param string $name The name of the CTE.
         * @param QueryBuilder $anchor The anchor query for the recursive CTE.
         * @param QueryBuilder $recursive The recursive query for the CTE.
         * @param array $columns Optional column names for the CTE.
         * @return static The query builder with the added recursive CTE.
         */
        public static function withRecursive (string $name, QueryBuilder $anchor, QueryBuilder $recursive, array $columns = []) : QueryBuilder {
            return (new QueryBuilder())->withRecursive($name, $anchor, $recursive, $columns);
        }
    }
?>