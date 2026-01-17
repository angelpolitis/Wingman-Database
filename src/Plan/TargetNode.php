<?php
    /*/
	 * Project Name:    Wingman — Database — Target Node
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 13 2026
	 * Last Modified:   Jan 13 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a target node.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TargetNode implements PlanNode, ExpressionCarrier {
        /**
         * The target of a target node.
         * @var TableIdentifier The target table.
         */
        protected TableIdentifier $target;

        /**
         * Creates a new target node.
         * @param TableIdentifier $target The target table.
         */
        public function __construct (TableIdentifier $target) {
            $this->target = $target;
        }

        /**
         * Explains a target node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the target node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            return sprintf("{$indent}Target (%s)" . PHP_EOL, $this->target->getName());
        }

        /**
         * Gets the expressions of a target node.
         * @return array An array containing the target expression.
         */
        public function getExpressions () : array {
            return [$this->target];
        }

        /**
         * Gets the target of a target node.
         * @return TableIdentifier The target table or plan node.
         */
        public function getTarget () : TableIdentifier {
            return $this->target;
        }

        /**
         * Sets the expressions of a target node.
         * @param array $expressions An array containing the new target expression.
         * @return static The current instance.
         * @throws InvalidArgumentException If the number of expressions is not one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("TargetNode can only have one expression.");
            }
            $this->target = $expressions[0];
            return $this;
        }

        /**
         * Creates a new target node with the given expressions.
         * @param array $expressions An array containing the new target expression.
         * @return static A new target node instance.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>