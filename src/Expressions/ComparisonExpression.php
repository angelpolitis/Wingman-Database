<?php
    /*/
	 * Project Name:    Wingman — Database — Comparison Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\Predicate;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a comparison expression in a database query.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ComparisonExpression extends Predicate implements ExpressionCarrier {
        /**
         * The left operand of a comparison expression.
         * @var mixed
         */
        protected mixed $leftOperand;

        /**
         * The right operand of a comparison expression.
         * @var mixed
         */
        protected mixed $rightOperand;

        /**
         * The comparison operator.
         * @var string
         */
        protected string $operator;

        /**
         * Creates a new comparison expression.
         * @param string $leftOperand The left operand.
         * @param string $operator The comparison operator.
         * @param mixed $rightOperand The right operand.
         * @param string|null $alias An optional alias for the comparison expression.
         */
        public function __construct (mixed $leftOperand, string $operator, mixed $rightOperand, ?string $alias = null) {
            $this->leftOperand = $leftOperand;
            $this->operator = $operator;
            $this->rightOperand = $rightOperand;
            $this->alias($alias);
        }

        /**
         * Explains a comparison expression.
         * @param int $depth The depth of the expression for formatting purposes (not used here).
         * @return string The explanation of the comparison expression.
         */
        public function explain (int $depth = 0) : string {
            $render = function ($operand) {
                if ($operand instanceof Expression) {
                    return $operand->explain();
                }
                return (string) $operand;
            };
        
            $string = $render($this->leftOperand) . " {$this->operator} " . $render($this->rightOperand);
            
            return $this->alias ? "{$string} AS {$this->alias}" : $string;
        }

        /**
         * Gets the expressions of a comparison expression.
         * @return array An array containing the left operand, operator, and right operand.
         */
        public function getExpressions () : array {
            return [$this->leftOperand, $this->rightOperand];
        }
        
        /**
         * Gets the left operand of a comparison expression.
         * @return mixed The left operand.
         */
        public function getLeftOperand () : mixed {
            return $this->leftOperand;
        }

        /**
         * Gets the operator of a comparison expression.
         * @return string The comparison operator.
         */
        public function getOperator () : string {
            return $this->operator;
        }
        
        /**
         * Gets the references used in a comparison expression.
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
         * Gets the right operand of a comparison expression.
         * @return mixed The right operand.
         */
        public function getRightOperand () : mixed {
            return $this->rightOperand;
        }

        /**
         * Determines whether a comparison expression is sargable.
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
         * Sets the expressions of a comparison expression.
         * @param array $expressions An array containing the left operand, operator, and right operand.
         * @return static The instance itself for method chaining.
         */
        public function setExpressions (array $expressions) : static {
            [$this->leftOperand, $this->rightOperand] = $expressions;
            return $this;
        }

        /**
         * Creates a new comparison expression with the given expressions.
         * @param array $expressions An array containing the left operand, operator, and right operand.
         * @return static A new comparison expression with the updated expressions.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>