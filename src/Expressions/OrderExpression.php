<?php
    /*/
	 * Project Name:    Wingman — Database — Order Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\NullPrecedence;
    use Wingman\Database\Enums\OrderDirection;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents an SQL Order By expression.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class OrderExpression implements Expression, ExpressionCarrier {
        /**
         * The direction of ordering.
         * @var OrderDirection
         */
        protected OrderDirection $direction;

        /**
         * The precedence of NULL values in ordering.
         * @var NullPrecedence
         */
        protected NullPrecedence $precedence;

        /**
         * The target to order by.
         * @var ColumnIdentifier|Expression|string
         */
        protected ColumnIdentifier|Expression|string $target;
        
        /**
         * Creates a new order expression.
         * @param ColumnIdentifier|Expression|string $target The target to order by.
         * @param OrderDirection $direction The direction of ordering.
         */
        public function __construct (
            ColumnIdentifier|Expression $target,
            OrderDirection $direction = OrderDirection::Ascending,
            NullPrecedence $precedence = NullPrecedence::None
        ) {
            $this->target = $target;
            $this->direction = $direction;
            $this->precedence = $precedence;
        }

        /**
         * Explains an order expression for debugging.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The explained order expression.
         */
        public function explain (int $depth = 0) : string {
            $target = $this->target->explain();
    
            $sql = "{$target} {$this->direction->value}";
            
            if ($this->precedence !== NullPrecedence::None) {
                $sql .= " {$this->precedence->value}";
            }
    
            return $sql;
        }

        /**
         * Gets the direction of ordering.
         * @return OrderDirection The order direction.
         */
        public function getDirection () : OrderDirection {
            return $this->direction;
        }

        /**
         * Gets all expressions used in an order expression.
         * @return array An array of expressions.
         */
        public function getExpressions () : array {
            return $this->target instanceof Expression ? [$this->target] : [];
        }

        /**
         * Gets the references used in the order expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            $references = [];
            
            foreach ($this->getExpressions() as $expression) {
                $references = array_merge($references, $expression->getReferences());
            }
            
            if (empty($references) && is_string($this->target)) {
                $references[] = $this->target;
            }

            return array_unique($references);
        }

        /**
         * Gets the precedence of NULL values in ordering.
         * @return NullPrecedence The NULL precedence.
         */
        public function getPrecedence () : NullPrecedence {
            return $this->precedence;
        }

        /**
         * Gets the target to order by.
         * @return ColumnIdentifier|Expression The order target.
         */
        public function getTarget () : ColumnIdentifier|Expression {
            return $this->target;
        }

        /**
         * Determines whether an order expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the expressions used in an order expression.
         * @param array $expressions An array of expressions.
         * @return static The current order expression instance.
         */
        public function setExpressions (array $expressions) : static {
            if (isset($expressions[0])) {
                $this->target = $expressions[0];
            }
            return $this;
        }

        /**
         * Creates a new order expression with the given expressions.
         * @param array $expressions An array of expressions.
         * @return static A new order expression instance.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>