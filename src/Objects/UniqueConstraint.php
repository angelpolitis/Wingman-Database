<?php
    /*/
	 * Project Name:    Wingman — Database — Unique Constraint
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 03 2026
    /*/

    # Use the Database.Objects namespace.
    namespace Wingman\Database\Objects;

    /**
     * Represents a unique constraint in a database table.
     * @package Wingman\Database\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UniqueConstraint {
        /**
         * Creates a new unique constraint.
         * @param string $name The name of the unique constraint.
         */
        public function __construct (protected string $name) {}

        /**
         * Converts a unique constraint to a string representation.
         * @return string The name of the unique constraint.
         */
        public function __toString () : string {
            return $this->name;
        }

        /**
         * Gets the name of the unique constraint.
         * @return string The name of the unique constraint.
         */
        public function getName () : string {
            return $this->name;
        }
    }
?>