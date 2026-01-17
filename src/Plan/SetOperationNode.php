<?php
    /*/
	 * Project Name:    Wingman — Database — Set Operation Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 15 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\SetOperation;
    use Wingman\Database\Interfaces\PlanNode;
    
    /**
     * Represents a set operation (UNION, INTERSECT, EXCEPT) between two query plans.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SetOperationNode extends BinaryNode {
        /**
         * The operation of a set operation node.
         * @var SetOperation
         */
        protected SetOperation $operation;

        /**
         * Creates a new set operation node.
         * @param PlanNode $left The left plan node.
         * @param PlanNode $right The right plan node.
         * @param SetOperation $operation The operation type (UNION, INTERSECT, EXCEPT).
         */
        public function __construct (PlanNode $left, PlanNode $right, SetOperation $operation = SetOperation::Union) {
            parent::__construct($left, $right);
            $this->operation = $operation;
        }

        /**
         * Explains a set operation node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the set operation node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $innerIndent = str_pad("", ($depth + 1) * 3);
            return "{$indent}{$this->operation->value}" . PHP_EOL
                . "{$innerIndent}Left:" . PHP_EOL . $this->left->explain($depth + 2) . PHP_EOL
                . "{$innerIndent}Right:" . PHP_EOL . $this->right->explain($depth + 2);
        }

        /**
         * Gets the expressions contained in a set operation node.
         * @return array An array of expressions.
         */
        public function getExpressions () : array {
            return [];
        }

        /**
         * Gets the operation type.
         * @return SetOperation The operation type.
         */
        public function getOperation () : SetOperation {
            return $this->operation;
        }

        /**
         * Sets the expressions for the set operation node.
         * @param array $expressions The new expressions.
         * @return static The updated set operation node.
         */
        public function setExpressions (array $expressions) : static {
            return $this;
        }

        /**
         * Creates a new set operation node with the given expressions.
         * @param array $expressions The new expressions.
         * @return static The new set operation node.
         */
        public function withExpressions (array $expressions) : static {
            return $this;
        }
    }
?>