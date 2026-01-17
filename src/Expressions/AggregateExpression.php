<?php
    /*/
	 * Project Name:    Wingman — Database — Aggregate Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents an aggregate expression (e.g., COUNT, SUM, AVG).
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class AggregateExpression implements Expression, Aliasable, ExpressionCarrier {
        use CanHaveAlias;

        /**
         * Indicates whether an aggregate function is DISTINCT.
         * @var bool
         */
        protected bool $distinct = false;

        /**
         * The column or expression an aggregate function is applied to.
         * @var Expression
         */
        protected Expression $target;

        /**
         * The aggregate function (e.g., COUNT, SUM).
         * @var string
         */
        protected string $function;

        /**
         * Creates a new aggregate expression.
         * @param string $function The aggregate function.
         * @param Expression $target The column or expression to aggregate.
         * @param bool $distinct Whether to apply DISTINCT to the aggregate.
         * @param string|null $alias Optional alias for the aggregate expression.
         */
        public function __construct (string $function, Expression $target, bool $distinct = false, ?string $alias = null) {
            $this->function = strtoupper($function);
            $this->target = $target;
            $this->distinct = $distinct;
            $this->alias($alias);
        }

        /**
         * Explains an aggregate expression as a string.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The explanation of the aggregate expression.
         */
        public function explain (int $depth = 0) : string {
            $distinct = $this->distinct ? "DISTINCT " : '';
            $col = ($this->target instanceof Expression) 
                ? $this->target->explain() 
                : $this->target;

            return "{$this->function}({$distinct}{$col})";
        }

        /**
         * Gets the expressions of an aggregate.
         * @return Expression[] The sub-expressions.
         */
        public function getExpressions () : array {
            return [$this->target];
        }

        /**
         * Gets the function of an aggregate.
         * @return string The aggregate function.
         */
        public function getFunction () : string {
            return $this->function;
        }

        /**
         * Gets the references used in an aggregate expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            return $this->target->getReferences();
        }
        
        /**
         * Gets the column or expression of an aggregate.
         * @return Expression The column or expression.
         */
        public function getTarget () : Expression {
            return $this->target;
        }

        /**
         * Checks if an aggregate is DISTINCT.
         * @return bool True if DISTINCT, false otherwise.
         */
        public function isDistinct () : bool {
            return $this->distinct;
        }

        /**
         * Determines whether an aggregate expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the sub-expressions of an aggregate.
         * @param Expression[] $expressions The sub-expressions to set.
         * @return static The aggregate.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("AggregateExpression expects exactly one sub-expression.");
            }
            $this->target = $expressions[0];
            return $this;
        }

        /**
         * Creates a copy of the aggregate with new sub-expressions.
         * @param Expression[] $expressions The new sub-expressions.
         * @return static The new aggregate.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>