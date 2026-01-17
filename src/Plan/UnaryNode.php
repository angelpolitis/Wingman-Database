<?php
    /*/
	 * Project Name:    Wingman — Database — Unary Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 30 2025
	 * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Expressions\TableIdentifier;

    /**
     * Represents a unary plan node with a single input.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class UnaryNode implements PlanNode {
        /**
         * The input of a unary node.
         * @var PlanNode
         */
        protected PlanNode $input;

        /**
         * Creates a new unary node.
         * @param PlanNode $input The input plan node.
         */
        public function __construct (PlanNode $input) {
            $this->input = $input;
        }

        /**
         * Gets the input of a unary node.
         * @return PlanNode The input plan node.
         */
        public function getInput () : PlanNode {
            return $this->input;
        }

        /**
         * Creates a clone of this node with a different input.
         * @param PlanNode $input The new input plan node.
         * @return static A clone of this node with the new input.
         */
        public function withInput (PlanNode $input) : static {
            $clone = clone $this;
            $clone->input = $input;
            return $clone;
        }

        /**
         * Resolves the target table for mutation operations.
         * @return TableIdentifier|QueryExpression The target table identifier or query expression.
         * @throws RuntimeException If the target table cannot be resolved.
         */
        public function getTable () : TableIdentifier|QueryExpression {
            $node = $this->input;
    
            while (true) {
                if ($node instanceof SourceNode) break;
        
                if ($node instanceof UnaryNode) {
                    $node = $node->getInput();
                    continue;
                }
        
                if ($node instanceof JoinNode) {
                    $node = $node->getLeft();
                    continue;
                }
            }
            return $node->getSource();
        }
    }
?>