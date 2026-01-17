<?php
    /*/
     * Project Name:    Wingman — Database — Conjunctive
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;

    /**
     * Trait that provides functionality for handling conjunctions.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Conjunctive {
        /**
         * Gets the conjunction of an instance.
         * @return string|null The conjunction or `null` if none is set.
         */
        public function getConjunction () : string;

        /**
         * Sets the conjunction of an instance.
         * @param string $conjunction The conjunction to set.
         * @return static The instance itself for method chaining.
         */
        public function setConjunction (string $conjunction) : static;
    }
?>