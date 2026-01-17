<?php
    /*/
	 * Project Name:    Wingman — Database — Between Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a BETWEEN expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class BetweenExpression extends Predicate implements ExpressionCarrier {
        /**
         * The operand of a between expression.
         * @var mixed
         */
        protected mixed $operand;

        /**
         * The minimum value of a between expression.
         * @var mixed
         */
        protected mixed $min;

        /**
         * The maximum value of a between expression.
         * @var mixed
         */
        protected mixed $max;

        /**
         * Indicates whether a between expression is negated.
         * @var bool
         */
        protected bool $negated = false;

        /**
         * Creates a new between expression.
         * @param mixed $operand The operand to be evaluated.
         * @param mixed $min The minimum value of the range.
         * @param mixed $max The maximum value of the range.
         * @param bool $negated Indicates whether the expression is negated.
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (mixed $operand, mixed $min, mixed $max, bool $negated = false, ?string $alias = null) {
            $this->operand = $operand;
            $this->min = $min;
            $this->max = $max;
            $this->negated = $negated;
            $this->alias($alias);
        }

        /**
         * Explains a between expression.
         * @param int $depth The depth of the expression for formatting purposes.
         * @return string The explanation of the expression.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
            $render = function ($value) {
                return ($value instanceof Expression) ? $value->explain() : (string) $value;
            };
            $op = $this->negated ? "NOT BETWEEN" : "BETWEEN";
            return "$pad{$render($this->operand)} {$op} {$render($this->min)} AND {$render($this->max)}";
        }

        /**
         * Gets the expressions of a between expression.
         * @return Expression[] The sub-expressions.
         */
        public function getExpressions () : array {
            return [$this->operand, $this->min, $this->max];
        }

        /**
         * Gets the operand of a between expression.
         * @return mixed The operand.
         */
        public function getOperand () : mixed {
            return $this->operand;
        }

        /**
         * Gets the minimum value of a between expression.
         * @return mixed The minimum value.
         */
        public function getMin () : mixed {
            return $this->min;
        }

        /**
         * Gets the maximum value of a between expression.
         * @return mixed The maximum value.
         */
        public function getMax () : mixed {
            return $this->max;
        }

        /**
         * Gets all references used in a between expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            $references = [];
            foreach ($this->getExpressions() as $expression) {
                array_push($references, ...$expression->getReferences());
            }
            return array_unique($references);
        }

        /**
         * Checks whether a between expression is negated.
         * @return bool Whether the expression is negated.
         */
        public function isNegated () : bool {
            return $this->negated;
        }

        /**
         * Indicates whether a between expression is sargable.
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
         * Sets the expressions of a between expression.
         * @param Expression[] $expressions The sub-expressions to set.
         * @return static The between expression.
         * @throws InvalidArgumentException If the number of expressions is not exactly 3.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 3) {
                throw new InvalidArgumentException("BetweenExpression requires exactly 3 expressions: operand, min, max.");
            }
            [$this->operand, $this->min, $this->max] = $expressions;
            return $this;
        }

        /**
         * Creates a copy of the between expression with new expressions.
         * @param Expression[] $expressions The new sub-expressions.
         * @return static The new between expression.
         * @throws InvalidArgumentException If the number of expressions is not exactly 3.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>