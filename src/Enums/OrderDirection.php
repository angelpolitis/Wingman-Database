<?php
    /*/
	 * Project Name:    Wingman — Database — Order Direction Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the directions for ordering query results.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum OrderDirection : string {
        /**
         * The ASC (ascending) order direction.
         * Orders results from lowest to highest.
         * @var string
         */
        case Ascending = "ASC";

        /**
         * The DESC (descending) order direction.
         * Orders results from highest to lowest.
         * @var string
         */
        case Descending = "DESC";

        /**
         * Resolves an order direction from a string or returns the existing instance.
         * @param static|string $direction The direction to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $direction) : static {
            return $direction instanceof static ? $direction : static::from(strtoupper($direction));
        }
    }
?>