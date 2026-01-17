<?php
    /*/
	 * Project Name:    Wingman — Database — Join Type Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 02 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the join types for database operations.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum JoinType : string {
        /**
         * The CROSS join type.
         * Produces the Cartesian product of the two tables involved in the join.
         * Each row from the first table is combined with every row from the second table.
         * This type of join does not require any condition to join the tables.
         * @var string
         */
        case Cross = "CROSS";

        /**
         * The FULL join type.
         * Returns all records when there is a match in either left (table1) or right (table2) table records.
         * Records that do not have a match in the other table will have NULLs for the columns of the other table.
         * @var string
         */
        case Full = "FULL";

        /**
         * The INNER join type.
         * Returns records that have matching values in both tables involved in the join.
         * Only the rows that satisfy the join condition are included in the result set.
         * @var string
         */
        case Inner = "INNER";

        /**
         * The LEFT join type.
         * Returns all records from the left table (table1), and the matched records from the right table (table2).
         * If there is no match, the result is NULL on the side of the right table.
         * @var string
         */
        case Left = "LEFT";

        /**
         * The RIGHT join type.
         * Returns all records from the right table (table2), and the matched records from the left table (table1).
         * If there is no match, the result is NULL on the side of the left table.
         * @var string
         */
        case Right = "RIGHT";

        /**
         * Resolves a join type from a string or returns the existing instance.
         * @param static|string $type The type to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $type) : static {
            return $type instanceof static ? $type : static::from(strtoupper($type));
        }
    }
?>