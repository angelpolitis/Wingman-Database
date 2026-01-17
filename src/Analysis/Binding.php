<?php
    /*/
     * Project Name:    Wingman — Database — Binding
     * Created by:      Angel Politis
     * Creation Date:   Jan 11 2026
     * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Analysis namespace.
    namespace Wingman\Database\Analysis;
    
    /**
     * Represents a binding of a value in a query plan.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Binding {
        /** 
         * Creates a new binding with the given value.
         * @param mixed $value The value to bind.
         */
        public function __construct (protected mixed $value) {}

        /**
         * Gets the value of a binding.
         * @return mixed The bound value.
         */
        public function getValue () : mixed {
            return $this->value;
        }
    }
?>