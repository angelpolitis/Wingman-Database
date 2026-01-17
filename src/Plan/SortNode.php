<?php
    /*/
	 * Project Name:    Wingman — Database — Sort Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 30 2025
	 * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\OrderExpression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a sort node.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SortNode extends UnaryNode implements ExpressionCarrier {
        /**
         * The expressions of a sort node.
         * @var OrderExpression[]
         */
        protected array $expressions;

        /**
         * Creates a new sort node.
         * @param PlanNode $input The input plan node.
         * @param array $expressions The sort expressions.
         */
        public function __construct (PlanNode $input, array $expressions) {
            parent::__construct($input);
            $this->expressions = $expressions;
        }

        /**
         * Explains a sort node.
         * @param int $depth The depth of the explanation (used for indentation).
         * @return string The explanation of the sort node.
         */
        public function explain (int $depth = 0): string {
            $indent = str_pad("", $depth * 3);
            $expressions = [];
            foreach ($this->expressions as $field => $expression) {
                $target = $expression->getTarget();
                $target = is_string($target) ? $target : $target->explain(0);
                $direction = $expression->getDirection()->value;
                $precedence = $expression->getPrecedence()->value;
                $expressions[] = $precedence ? "$target $direction $precedence" : "$target $direction";
            }
            
            return "{$indent}Sort (" . implode(", ", $expressions) . ")" . PHP_EOL . $this->input->explain($depth + 1);
        }

        /**
         * Gets the sort expressions of a sort node.
         * @return OrderExpression[] The sort expressions.
         */
        public function getExpressions () : array {
            return $this->expressions;
        }

        /**
         * Sets the sort expressions of a sort node.
         * @param OrderExpression[] $expressions The new sort expressions.
         * @return static The sort node.
         */
        public function setExpressions (array $expressions) : static {
            $this->expressions = $expressions;
            return $this;
        }

        /**
         * Creates a new sort node with the given sort expressions.
         * @param OrderExpression[] $expressions The new sort expressions.
         * @return static The new sort node.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>