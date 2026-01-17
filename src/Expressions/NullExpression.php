<?php
    /*/
	 * Project Name:    Wingman — Database — Null Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a NULL expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class NullExpression extends Predicate implements ExpressionCarrier {
        /**
         * The operand of a null expression.
         * @var mixed
         */
        protected mixed $operand;

        /**
         * Indicates whether a null expression is negated.
         * @var bool
         */
        protected bool $negated = false;

        /**
         * Creates a new Null expression.
         * @param mixed $operand The operand to be evaluated.
         * @param bool $negated Indicates whether the expression is negated.
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (mixed $operand, bool $negated = false, ?string $alias = null) {
            $this->operand = $operand;
            $this->negated = $negated;
            $this->alias($alias);
        }

        /**
         * Explains a null expression as a string.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The explanation of the expression.
         */
        public function explain (int $depth = 0) : string {
            $renderedOperand = ($this->operand instanceof Expression) 
                ? $this->operand->explain() 
                : (string) $this->operand;

            $op = $this->negated ? " IS NOT NULL" : " IS NULL";
            
            return "{$renderedOperand}{$op}";
        }

        /**
         * Gets all expressions used in a null expression.
         * @return array An array of expressions.
         */
        public function getExpressions () : array {
            return $this->operand instanceof Expression ? [$this->operand] : [];
        }

        /**
         * Gets the operand of a null expression.
         * @return mixed The operand.
         */
        public function getOperand () : mixed {
            return $this->operand;
        }

        /**
         * Gets all references used in a null expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            $references = [];
            foreach ($this->getExpressions() as $expression) {
                $references = array_merge($references, $expression->getReferences());
            }
            return array_unique($references);
        }

        /**
         * Checks whether a null expression is negated.
         * @return bool Whether the expression is negated.
         */
        public function isNegated () : bool {
            return $this->negated;
        }

        /**
         * Determines whether a null expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            foreach ($this->getExpressions() as $expr) {
                if ($expr instanceof Expression && !$expr->isSargable()) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Sets the expressions used in a null expression.
         * @param array $expressions An array of expressions.
         * @return static The current instance for method chaining.
         */
        public function setExpressions (array $expressions) : static {
            if (isset($expressions[0])) {
                $this->operand = $expressions[0];
            }
            return $this;
        }

        /**
         * Creates a new null expression with the specified expressions.
         * @param array $expressions An array of expressions.
         * @return static A new null expression with the specified expressions.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>