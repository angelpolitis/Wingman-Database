<?php
    /*/
     * Project Name:    Wingman — Database — Aliasable
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Interfaces namespace.
    namespace Wingman\Database\Interfaces;

    /**
     * Interface that provides functionality for handling aliases.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Aliasable {
        /**
         * Sets the alias of an instance.
         * @param string|null $alias The alias to set or `null` to remove it.
         * @return static The instance itself for method chaining.
         */
        public function alias (?string $alias) : static;

        /**
         * Gets the alias of an instance.
         * @return string|null The alias or `null` if none is set.
         */
        public function getAlias () : ?string;
    }
?>