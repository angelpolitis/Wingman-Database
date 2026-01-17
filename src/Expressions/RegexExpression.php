<?php
    /*/
	 * Project Name:    Wingman — Database — Regex Expression
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
     * Represents a REGEX expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class RegexExpression extends Predicate implements ExpressionCarrier {
        /**
         * The operand of a regex expression.
         * @var mixed
         */
        protected mixed $operand;

        /**
         * The pattern of a regex expression.
         * @var string
         */
        protected string $pattern;

        /**
         * Indicates whether a regex expression is negated.
         * @var bool
         */
        protected bool $negated = false;

        /**
         * Creates a new regex expression.
         * @param mixed $operand The operand to be evaluated.
         * @param string $pattern The pattern to match against.
         * @param bool $negated Indicates whether the expression is negated.
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (mixed $operand, string $pattern, bool $negated = false, ?string $alias = null) {
            $this->operand = $operand;
            $this->pattern = $pattern;
            $this->negated = $negated;
            $this->alias($alias);
        }

        /**
         * Explains a regex expression.
         * @param int $depth The depth for formatting purposes (not used here).
         * @return string The human-readable explanation.
         */
        public function explain (int $depth = 0) : string {
            $renderedOperand = ($this->operand instanceof Expression) 
                ? $this->operand->explain() 
                : (string) $this->operand;

            $op = $this->negated ? "NOT REGEXP" : "REGEXP";

            return "{$renderedOperand} {$op} '{$this->pattern}'";
        }

        /**
         * Gets all expressions used in a regex expression.
         * @return array An array of expressions.
         */
        public function getExpressions () : array {
            return $this->operand instanceof Expression ? [$this->operand] : [];
        }
        
        /**
         * Gets the operand of a between expression.
         * @return mixed The operand.
         */
        public function getOperand () : mixed {
            return $this->operand;
        }

        /**
         * Gets the pattern of a regex expression.
         * @return string The pattern.
         */
        public function getPattern () : string {
            return $this->pattern;
        }

        /**
         * Gets all table references used in a regex expression.
         * @return array An array of table aliases.
         */
        public function getReferences () : array {
            $references = [];
            foreach ($this->getExpressions() as $expression) {
                $references = array_merge($references, $expression->getReferences());
            }
            return array_unique($references);
        }

        /**
         * Indicates whether a between expression is negated.
         * @return bool Whether the expression is negated.
         */
        public function isNegated () : bool {
            return $this->negated;
        }

        /**
         * Indicates whether a regex expression is sargable (search argument capable).
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return $this->operand instanceof Expression && $this->operand->isSargable();
        }

        /**
         * Sets the expressions for a regex expression.
         * @param array $expressions An array of expressions.
         * @return static The current instance for chaining.
         */
        public function setExpressions (array $expressions) : static {
            if (isset($expressions[0])) {
                $this->operand = $expressions[0];
            }
            return $this;
        }

        /**
         * Creates a new regex expression with the given expressions.
         * @param array $expressions An array of expressions.
         * @return static A new expression with the specified expressions.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>