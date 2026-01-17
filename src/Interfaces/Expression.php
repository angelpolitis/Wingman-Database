<?php
    /*/
	 * Project Name:    Wingman — Database — Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 07 2026
    /*/
    
    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;

    /**
     * Represents a database expression.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Expression {
        /**
         * Explains an expression.
         * @param int $depth The depth of the expression for formatting purposes.
         * @return string A string explanation of the expression.
         */
        public function explain (int $depth = 0) : string;

        /**
         * Gets the references of an expression.
         * @return array An array of references.
         */
        public function getReferences () : array;

        /**
         * Indicates whether a predicate is sargable (search argument capable).
         * @return bool Whether the predicate is sargable.
         */
        public function isSargable () : bool;
    }
?>