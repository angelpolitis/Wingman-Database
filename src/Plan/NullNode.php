<?php

    /*/
	 * Project Name:    Wingman — Database — Null Node
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;

    /**
     * Represents a null operation in a query plan.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class NullNode implements PlanNode {
        /**
         * Creates a new null node.
         */
        public function __construct () {}

        /**
         * Explains a null node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the null node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            return "{$indent}Null" . PHP_EOL;
        }
    }
?>