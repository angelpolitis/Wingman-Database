<?php
    /*/
	 * Project Name:    Wingman — Database — Binary Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 30 2025
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a binary plan node with two children (left and right).
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class BinaryNode implements PlanNode, ExpressionCarrier {
        /**
         * The left child node.
         * @var PlanNode
         */
        protected PlanNode $left;

        /**
         * The right child node.
         * @var PlanNode
         */
        protected PlanNode $right;

        /**
         * Creates a new binary node.
         * @param PlanNode $left The left child node.
         * @param PlanNode $right The right child node.
         */
        public function __construct (PlanNode $left, PlanNode $right) {
            $this->left = $left;
            $this->right = $right;
        }

        /**
         * Gets the left child node.
         * @return PlanNode The left child node.
         */
        public function getLeft () : PlanNode {
            return $this->left;
        }

        /**
         * Gets the right child node.
         * @return PlanNode The right child node.
         */
        public function getRight () : PlanNode {
            return $this->right;
        }

        /**
         * Creates a clone of the current node with new expressions.
         * @param array $expressions The new expressions to set.
         * @return static A new instance of the binary node with the updated expressions.
         */
        public function withExpressions (array $expressions) : static {
            $clone = clone $this;
            $clone->setExpressions($expressions);
            return $clone;
        }

        /**
         * Creates a clone of the current node with new left and right components.
         * @param PlanNode $left The new left child node.
         * @param PlanNode $right The new right child node.
         * @return static A new instance of the binary node with the updated components.
         */
        public function withComponents (PlanNode $left, PlanNode $right) : static {
            $clone = clone $this;
            $clone->left = $left;
            $clone->right = $right;
            return $clone;
        }

        /**
         * Creates a clone of the current node with a new left child.
         * @param PlanNode $input The new left child node.
         * @return static A new instance of the binary node with the updated left child.
         */
        public function withLeft (PlanNode $input) : static {
            $clone = clone $this;
            $clone->left = $input;
            return $clone;
        }

        /**
         * Creates a clone of the current node with a new right child.
         * @param PlanNode $right The new right child node.
         * @return static A new instance of the binary node with the updated right child.
         */
        public function withRight (PlanNode $right) : static {
            $clone = clone $this;
            $clone->right = $right;
            return $clone;
        }
    }
?>