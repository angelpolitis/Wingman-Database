<?php
    /*/
	 * Project Name:    Wingman — Database — Exists Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents an EXISTS expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ExistsExpression extends Predicate implements ExpressionCarrier {
        /**
         * Indicates whether an expression is negated (NOT EXISTS).
         * @var bool
         */
        protected bool $negated = false;

        /**
         * The sub-query of an EXISTS expression.
         * @var QueryExpression
         */
        protected QueryExpression $value;

        /**
         * Creates a new EXISTS expression.
         * @param QueryExpression $value The sub-query to be evaluated.
         * @param bool $negated Indicates whether the expression is negated (NOT EXISTS).
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (QueryExpression $value, bool $negated = false, ?string $alias = null) {
            $this->value = $value;
            $this->negated = $negated;
            $this->alias($alias);
        }

        /**
         * Explains an exists expression.
         * @param int $depth The depth of the expression for formatting purposes.
         * @return string The explanation of the expression.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
            $op = $this->negated ? "NOT EXISTS" : "EXISTS";
            
            $inner = $this->value->getPlan() 
                ? $this->value->getPlan()->explain($depth + 1) 
                : $this->value->explain($depth + 1);
    
            return PHP_EOL . "{$pad}{$op} (" . PHP_EOL . $inner . "{$pad})";
        }

        /**
         * Gets the expressions contained within an EXISTS expression.
         * @return array An array containing the sub-query expression.
         */
        public function getExpressions () : array {
            return [$this->value];
        }

        /**
         * Gets the references of an EXISTS expression.
         * @return array The references of the expression's sub-query.
         */
        public function getReferences () : array {
            return $this->value->getReferences();
        }

        /**
         * Gets the sub-query of an EXISTS expression.
         * @return QueryExpression The sub-query.
         */
        public function getValue () : QueryExpression {
            return $this->value;
        }

        /**
         * Checks whether an exists expression is negated.
         * @return bool Whether the expression is negated.
         */
        public function isNegated () : bool {
            return $this->negated;
        }

        /**
         * Determines whether an exists expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the expressions of an EXISTS expression.
         * @param array $expressions An array containing the sub-query expression.
         * @return static The instance itself for method chaining.
         */
        public function setExpressions (array $expressions) : static {
            [$this->value] = $expressions;
            return $this;
        }

        /**
         * Sets the expressions of an EXISTS expression (fluent interface).
         * @param array $expressions An array containing the sub-query expression.
         * @return static The instance itself for method chaining.
         */
        public function withExpressions (array $expressions) : static {
            return $this->setExpressions($expressions);
        }
    }
?>