<?php
    /*/
	 * Project Name:    Wingman — Database — Plan Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;

    /**
     * Represents a plan node.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface PlanNode {
        /**
         * Explains a plan node.
         * @param int $depth The depth of the plan node for indentation purposes.
         * @return string The explanation of the plan node.
         */
        public function explain (int $depth = 0) : string;
    }
?>