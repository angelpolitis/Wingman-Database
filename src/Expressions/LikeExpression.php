<?php
    /*/
	 * Project Name:    Wingman — Database — Like Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a LIKE expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LikeExpression extends Predicate implements ExpressionCarrier {
        /**
         * The operand of a like expression.
         * @var mixed
         */
        protected mixed $operand;

        /**
         * The pattern of a like expression.
         * @var string
         */
        protected string $pattern;

        /**
         * Indicates whether a like expression is case-insensitive.
         * @var bool
         */
        protected bool $caseInsensitive = false;

        /**
         * Indicates whether a like expression is negated.
         * @var bool
         */
        protected bool $negated = false;

        /**
         * Creates a new like expression.
         * @param mixed $operand The operand to be evaluated.
         * @param string $pattern The pattern to match against.
         * @param bool $negated Indicates whether the expression is negated.
         * @param bool $caseInsensitive Indicates whether the expression is case-insensitive.
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (mixed $operand, string $pattern, bool $negated = false, bool $caseInsensitive = false, ?string $alias = null) {
            $this->operand = $operand;
            $this->pattern = $pattern;
            $this->negated = $negated;
            $this->caseInsensitive = $caseInsensitive;
            $this->alias($alias);
        }

        /**
         * Explains a like expression.
         * @param int $depth The depth for formatting purposes (not used here).
         * @return string The human-readable explanation.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
            
            $renderedOperand = ($this->operand instanceof Expression) 
                ? $this->operand->explain(0) 
                : (string) $this->operand;
    
            $op = $this->caseInsensitive ? "ILIKE" : "LIKE";
            if ($this->negated) {
                $op = "NOT " . $op;
            }
    
            return "{$pad}{$renderedOperand} {$op} '{$this->pattern}'";
        }

        /**
         * Gets all expressions used in a like expression.
         * @return array An array of expressions.
         */
        public function getExpressions () : array {
            return ($this->operand instanceof Expression) ? [$this->operand] : [];
        }

        /**
         * Gets the operand of a between expression.
         * @return mixed The operand.
         */
        public function getOperand () : mixed {
            return $this->operand;
        }

        /**
         * Gets the pattern of a like expression.
         * @return string The pattern.
         */
        public function getPattern () : string {
            return $this->pattern;
        }

        /**
         * Gets all table references used in a like expression.
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
         * Indicates whether a LIKE expression is case insensitive.
         * @return bool Whether the expression is case insensitive.
         */
        public function isCaseInsensitive () : bool {
            return $this->caseInsensitive;
        }

        /**
         * Indicates whether a between expression is negated.
         * @return bool Whether the expression is negated.
         */
        public function isNegated () : bool {
            return $this->negated;
        }

        /**
         * Indicates whether a LIKE expression is sargable (search argument capable).
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            $firstChar = substr($this->pattern, 0, 1);
            return $firstChar !== '%' && $firstChar !== '_'
                && (!($this->operand instanceof Expression) || $this->operand->isSargable());
        }

        /**
         * Sets the expressions of a like expression.
         * @param array $expressions An array containing exactly one expression.
         * @return static The expression.
         */
        public function setExpressions (array $expressions) : static {
            if (isset($expressions[0])) {
                $this->operand = $expressions[0];
            }
            return $this;
        }

        /**
         * Creates a clone of a like expression with new expressions.
         * @param array $expressions An array containing exactly one expression.
         * @return static The new expression.
         */
        public function withExpressions(array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>