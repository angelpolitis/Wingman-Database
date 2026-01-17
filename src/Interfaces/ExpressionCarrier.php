<?php
    /*/
	 * Project Name:    Wingman — Database — Expression Carrier Interface
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 05 2026
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;
    
    /**
     * An interface for carriers of expressions that can contain sub-expressions.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ExpressionCarrier {
        /**
         * Gets the expressions of a carrier.
         * @return Expression[] The sub-expressions.
         */
        public function getExpressions () : array;

        /**
         * Sets the expressions of a carrier.
         * @param Expression[] $expressions The sub-expressions to set.
         * @return static The carrier.
         */
        public function setExpressions (array $expressions) : static;

        /**
         * Creates a copy of the carrier with new expressions.
         * @param Expression[] $expressions The new sub-expressions.
         * @return static The new carrier.
         */
        public function withExpressions (array $expressions) : static;
    }
?>