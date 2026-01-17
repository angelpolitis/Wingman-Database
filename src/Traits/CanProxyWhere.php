<?php
    /*/
     * Project Name:    Wingman — Database — Can Proxy Where Trait
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Traits namespace.
    namespace Wingman\Database\Traits;

    # Import the following classes to the current scope.
    use Wingman\Database\Builders\QueryBuilder;
    
    /**
     * Trait that provides functionality for proxying where clauses in queries.
     * @package Wingman\Database\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanProxyWhere {
        /**
         * Adds an OR condition to the WHERE clause.
         * @param mixed $column The column name or expression.
         * @param mixed $operator The comparison operator or value.
         * @param mixed $value The value to compare against.
         */
        public function orWhere (mixed $column, mixed $operator = null, mixed $value = null) : static {
            return $this->where($column, $operator, $value, "OR");
        }
        
        /**
         * Specifies a BETWEEN condition in the WHERE clause with OR conjunction.
         * @param string $column The column to apply the BETWEEN condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @return static The query.
         */
        public function orWhereBetween (string $column, mixed $min, mixed $max) : static {
            return $this->whereBetween($column, $min, $max, false, "OR");
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
         * @param QueryBuilder $subQuery The subquery to check for existence.
         * @return static The query.
         */
        public function orWhereExists (QueryBuilder $subQuery) : static {
            return $this->whereExists($subQuery, false, "OR");
        }
        
        /**
         * Specifies an ILIKE condition in the WHERE clause.
         * @param string $column The column to apply the ILIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return static The query.
         */
        public function orWhereILike (string $column, string $pattern) : static {
            return $this->whereILike($column, $pattern, false, "OR");
        }
    
        /**
         * Specifies a LIKE condition in the WHERE clause.
         * @param string $column The column to apply the LIKE condition on.
         * @param string $pattern The pattern to match against.
         * @return static The query.
         */
        public function orWhereLike (string $column, string $pattern) : static {
            return $this->whereLike($column, $pattern, false, "OR");
        }
        
        /**
         * Specifies a REGEXP condition in the WHERE clause with OR conjunction.
         * @param string $column The column to apply the REGEXP condition on.
         * @param string $pattern The regex pattern to match against.
         * @return static The query.
         */
        public function orWhereRegex (string $column, string $pattern) : static {
            return $this->whereRegex($column, $pattern, false, "OR");
        }

        /**
         * Adds a where condition to a query.
         * @param mixed $column The column to apply the condition on.
         * @param mixed|null $operator The operator for the condition.
         * @param mixed|null $value The value for the condition.
         * @return static The current update builder instance.
         */
        public function where (mixed $column, mixed $operator = null, mixed $value = null) : static {
            $this->query->where($column, $operator, $value);
            return $this;
        }
        
        /**
         * Adds a BETWEEN where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @return static The current update builder instance.
         */
        public function whereBetween (string $column, mixed $min, mixed $max) : static {
            $this->query->whereBetween($column, $min, $max);
            return $this;
        }

        /**
         * Specifies a column-to-column comparison in the WHERE clause.
         * @param string $first The first column.
         * @param string $operator The comparison operator.
         * @param string $second The second column.
         * @param string $conjunction The conjunction to use ('AND' or 'OR').
         * @return static The query.
         */
        public function whereColumn (string $first, string $operator, string $second, string $conjunction = "AND") : static {
            $this->query->whereColumn($first, $operator, $second, $conjunction);
            return $this;
        }
    
        /**
         * Adds an EXISTS where condition to a query.
         * @param QueryBuilder $subQuery The subquery to check for existence.
         * @param bool $not Whether to negate the condition (default: false).
         * @return static The current update builder instance.
         */
        public function whereExists (QueryBuilder $subQuery, bool $not = false) : static {
            $this->query->whereExists($subQuery, $not);
            return $this;
        }
    
        /**
         * Adds a grouped where condition to a query.
         * @param array $criteria The criteria for the group.
         * @param string $conjunction The conjunction to use ('AND' or 'OR', default: 'AND').
         * @return static The current update builder instance.
         */
        public function whereGroup (array $criteria, string $conjunction = 'AND') : static {
            $this->query->whereGroup($criteria, $conjunction);
            return $this;
        }
    
        /**
         * Adds an ILIKE where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param string $pattern The pattern to match.
         * @param bool $not Whether to negate the condition (default: false).
         * @return static The current update builder instance.
         */
        public function whereILike (string $column, string $pattern, bool $not = false) : static {
            $this->query->whereILike($column, $pattern, $not);
            return $this;
        }

        /**
         * Adds an IN where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param array $values The values for the IN condition.
         * @param bool $not Whether to negate the condition (default: false).
         * @return static The current update builder instance.
         */
        public function whereIn (string $column, array $values, bool $not = false) : static {
            $this->query->whereIn($column, $values, $not);
            return $this;
        }
    
        /**
         * Adds a LIKE where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param string $pattern The pattern to match.
         * @param bool $not Whether to negate the condition (default: false).
         * @return static The current update builder instance.
         */
        public function whereLike (string $column, string $pattern, bool $not = false) : static {
            $this->query->whereLike($column, $pattern, $not);
            return $this;
        }
    
        /**
         * Adds a nested where condition to a query.
         * @param callable $callback The callback to define the nested conditions.
         * @param string $conjunction The conjunction to use ('AND' or 'OR', default: 'AND').
         * @return static The current update builder instance.
         */
        public function whereNested (callable $callback, string $conjunction = 'AND') : static {
            $this->query->whereNested($callback, $conjunction);
            return $this;
        }
    
        /**
         * Adds a NOT BETWEEN where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @return static The current update builder instance.
         */
        public function whereNotBetween (string $column, mixed $min, mixed $max) : static {
            $this->query->whereNotBetween($column, $min, $max);
            return $this;
        }
    
        /**
         * Adds a NOT EXISTS where condition to a query.
         * @param QueryBuilder $subQuery The subquery to check for non-existence.
         * @return static The current update builder instance.
         */
        public function whereNotExists (QueryBuilder $subQuery) : static {
            return $this->whereExists($subQuery, true);
        }

        /**
         * Adds a NOT ILIKE where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param string $pattern The pattern to match.
         * @return static The current update builder instance.
         */
        public function whereNotILike (string $column, string $pattern) : static {
            return $this->whereILike($column, $pattern, true);
        }
    
        /**
         * Adds a NOT IN where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param array $values The values for the NOT IN condition.
         * @return static The current update builder instance.
         */
        public function whereNotIn (string $column, array $values) : static {
            return $this->whereIn($column, $values, true);
        }
    
        /**
         * Adds a NOT LIKE where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param string $pattern The pattern to match.
         * @return static The current update builder instance.
         */
        public function whereNotLike (string $column, string $pattern) : static {
            return $this->whereLike($column, $pattern, true);
        }
    
        /**
         * Adds a WHERE NOT NULL condition to a query.
         * @param string $column The column to check for NOT NULL.
         * @return static The current update builder instance.
         */
        public function whereNotNull (string $column) : static {
            return $this->whereNull($column, true);
        }
    
        /**
         * Adds a NOT REGEXP where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param string $pattern The regex pattern to match.
         * @return static The current update builder instance.
         */
        public function whereNotRegex (string $column, string $pattern) : static {
            return $this->whereRegex($column, $pattern, true);
        }
    
        /**
         * Adds a WHERE NULL condition to a query.
         * @param string $column The column to check for NULL.
         * @param bool $not Whether to negate the condition (default: false).
         * @return static The current update builder instance.
         */
        public function whereNull (string $column, bool $not = false) : static {
            $this->query->whereNull($column, $not);
            return $this;
        }
    
        /**
         * Adds a raw where condition to a query.
         * @param string $expression The raw SQL expression.
         * @param string|array $params The parameters for the expression.
         * @return static The current update builder instance.
         */
        public function whereRaw (string $expression, string|array $params = []) : static {
            $this->query->whereRaw($expression, $params);
            return $this;
        }

        /**
         * Adds a REGEXP where condition to a query.
         * @param string $column The column to apply the condition on.
         * @param string $pattern The regex pattern to match.
         * @param bool $not Whether to negate the condition (default: false).
         * @return static The current update builder instance.
         */
        public function whereRegex (string $column, string $pattern, bool $not = false) : static {
            $this->query->whereRegex($column, $pattern, $not);
            return $this;
        }
    }
?>