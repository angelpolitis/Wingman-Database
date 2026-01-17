<?php
    /*/
	 * Project Name:    Wingman — Database — Query Type Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the query types for database operations.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum QueryType : string {
        /**
         * The DELETE query type.
         * Deletes records from a table based on specified conditions.
         * @var string
         */
        case Delete = "DELETE";

        /**
         * The INSERT query type.
         * Inserts new records into a table.
         * @var string
         */
        case Insert = "INSERT";

        /**
         * The SELECT query type.
         * Retrieves records from one or more tables.
         * @var string
         */
        case Select = "SELECT";

        /**
         * The UPDATE query type.
         * Updates existing records in a table based on specified conditions.
         * @var string
         */
        case Update = "UPDATE";

        /**
         * Resolves a query type from a string or returns the existing instance.
         * @param static|string $type The type to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $type) : static {
            return $type instanceof static ? $type : static::from(strtoupper($type));
        }
    }
?>