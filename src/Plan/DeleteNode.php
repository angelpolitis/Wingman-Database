<?php
    /*/
    * Project Name:    Wingman — Database — Unary Node
    * Created by:      Angel Politis
    * Creation Date:   Dec 28 2025
    * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;

    /**
     * Represents a delete operation in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class DeleteNode extends UnaryNode {
        /**
         * The target alias for the delete operation.
         * @var string|null
         */
        protected ?string $targetAlias = null;

        /**
         * Creates a new delete node.
         * @param PlanNode $input The input plan node.
         * @param string|null $targetAlias The target alias for the delete operation.
         */
        public function __construct (PlanNode $input, ?string $targetAlias = null) {
            parent::__construct($input);
            $this->targetAlias = $targetAlias;
        }

        /**
         * Explains the delete operation in a human-readable format.
         * @param int $depth The depth of the node in the plan tree (for indentation).
         * @return string A string representation of the delete operation.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $displayName = $this->targetAlias ?: $this->getTable()->getName();
            return "{$indent}DELETE FROM {$displayName}" . PHP_EOL . $this->input->explain($depth + 1);
        }
        
        /**
         * Gets the target alias for the delete operation.
         * @return string|null The target alias, or `null` if not set.
         */
        public function getTargetAlias () : ?string {
            return $this->targetAlias;
        }
    }
?>