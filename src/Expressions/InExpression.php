<?php
    /*/
	 * Project Name:    Wingman — Database — In Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents an IN expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InExpression extends Predicate implements ExpressionCarrier {
        /**
         * The operand of an in expression.
         * @var mixed
         */
        protected mixed $operand;

        /**
         * The value of an in expression.
         * @var array|QueryExpression
         */
        protected array|QueryExpression $value;

        /**
         * Indicates whether an in expression is negated.
         * @var bool
         */
        protected bool $negated = false;

        /**
         * Creates a new IN expression.
         * @param mixed $operand The operand to be evaluated.
         * @param array|QueryExpression $value The list of values or subquery.
         * @param bool $not Indicates whether the expression is negated (NOT IN).
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (mixed $operand, array|QueryExpression $value, bool $negated = false, ?string $alias = null) {
            $this->operand = $operand;
            $this->value = $value;
            $this->negated = $negated;
            $this->alias($alias);
        }
        
        /**
         * Explains an in expression.
         * @param int $depth The depth of the expression for formatting purposes.
         * @return string The explanation of the expression.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
    
            $render = function ($value) use ($depth) {
                return ($value instanceof Expression) ? $value->explain($depth + 1) : (string) $value;
            };

            $op = $this->negated ? "NOT IN" : "IN";
            $renderedOperand = $render($this->operand);
            
            if ($this->value instanceof QueryExpression) {
                return "{$renderedOperand} {$op} (" . PHP_EOL . $this->value->explain($depth + 1) . "{$pad})";
            }
            elseif (is_array($this->value)) {
                $values = array_map($render, $this->value);
                return "{$renderedOperand} {$op} (" . implode(", ", $values) . ")";
            }
            else {
                return "{$renderedOperand} {$op} (?)";
            }
        }

        /**
         * Gets the expressions of an in expression.
         * @return array An array containing the operand and, if applicable, the subquery.
         */
        public function getExpressions () : array {
            $expressions = [$this->operand];

            if ($this->value instanceof QueryExpression) {
                $expressions[] = $this->value;
            }
            elseif (is_array($this->value)) {
                foreach ($this->value as $value) {
                    if ($value instanceof Expression) {
                        $expressions[] = $value;
                    }
                }
            }

            return $expressions;
        }

        /**
         * Gets the operand of an in expression.
         * @return mixed The operand.
         */
        public function getOperand () : mixed {
            return $this->operand;
        }

        /**
         * Gets all references used in a between expression.
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
         * Gets the value of an in expression.
         * @return array|QueryExpression The list of values or subquery.
         */
        public function getValue () : array|QueryExpression {
            return $this->value;
        }

        /**
         * Indicates whether an in expression is negated.
         * @return bool Whether the expression is negated.
         */
        public function isNegated () : bool {
            return $this->negated;
        }

        /**
         * Determines whether an in expression is sargable.
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
         * Sets the expressions of an in expression.
         * @param array $expressions An array containing the operand and, if applicable, the subquery.
         * @return static The current instance for method chaining.
         */
        public function setExpressions (array $expressions) : static {
            $this->operand = array_shift($expressions);

            if (isset($expressions[0]) && $expressions[0] instanceof QueryExpression) {
                $this->value = $expressions[0];
            }
            else $this->value = $expressions;

            return $this;
        }

        /**
         * Sets the expressions of an in expression (fluent interface).
         * @param array $expressions An array containing the operand and, if applicable, the subquery.
         * @return static The current instance for method chaining.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>