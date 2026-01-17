<?php
    /*/
	 * Project Name:    Wingman — Database — Set Operation Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 15 2026
	 * Last Modified:   Jan 15 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the join types for database operations.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum SetOperation : string {
        /**
         * The EXCEPT set operation.
         * Returns the records from the first SELECT statement that are not present in the result set of the second SELECT statement.
         * @var string
         */
        case Except = "EXCEPT";

        /**
         * The INTERSECT set operation.
         * Returns only the records that are common to the result sets of two or more SELECT statements.
         * @var string
         */
        case Intersect = "INTERSECT";

        /**
         * The UNION set operation.
         * Combines the result sets of two or more SELECT statements into a single result set, removing duplicates.
         * @var string
         */
        case Union = "UNION";

        /**
         * The UNION ALL set operation.
         * Combines the result sets of two or more SELECT statements into a single result set, including all duplicates.
         * @var string
         */
        case UnionAll = "UNION ALL";

        /**
         * Resolves a set operation from a string or returns the existing instance.
         * @param static|string $operation The operation to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $operation) : static {
            return $operation instanceof static ? $operation : static::from(strtoupper($operation));
        }
    }
?>