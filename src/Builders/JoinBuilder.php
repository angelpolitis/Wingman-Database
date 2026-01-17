<?php
    /*/
	 * Project Name:    Wingman — Database — Join Builder
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 05 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Builders;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\ComparisonExpression;
    use Wingman\Database\Interfaces\Expression;

    /**
     * Builder for SQL JOIN clauses.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JoinBuilder {
        /**
         * The ON conditions for a join.
         * @var array
         */
        protected array $conditions = [];

        /**
         * Gets the ON conditions for a join.
         * @return array The ON conditions.
         */
        public function getConditions () : array {
            return $this->conditions;
        }

        /**
         * Adds an ON condition to a join.
         * @param string|Expression $left The left side of the condition.
         * @param string $operator The comparison operator.
         * @param mixed $right The right side of the condition.
         * @return static The builder.
         */
        public function on (string|Expression $left, string $operator = '=', mixed $right = null) : static {
            # Handle shorthand: on('id', 5) -> id = 5
            if (func_num_args() === 2) {
                $right = $operator;
                $operator = '=';
            }

            $left = is_string($left) ? ColumnIdentifier::from($left) : $left;
            $right = is_string($right) ? ColumnIdentifier::from($right) : $right;

            $this->conditions[] = new ComparisonExpression($left, $operator, $right);
            
            return $this;
        }

        /**
         * Adds an OR ON condition to a join.
         * @param string|Expression $left The left side of the condition.
         * @param string $operator The comparison operator.
         * @param mixed $right The right side of the condition.
         */
        public function orOn (string|Expression $left, string $operator = '=', mixed $right = null) : static {
            $this->on($left, $operator, $right);
            end($this->conditions)->setConjunction("OR");
            return $this;
        }
    }
?>