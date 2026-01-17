<?php
    /*/
	 * Project Name:    Wingman — Database — Conflict Strategy Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 02 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the conflict resolution strategies for database operations.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ConflictStrategy : string {
        /**
         * The SKIP action.
         * When a conflict occurs, the conflicting row is ignored and not inserted or updated.
         * This is useful when you want to avoid duplicate entries without raising an error.
         * Example: If a row with a duplicate primary key is attempted to be inserted, it will be skipped.
         * @var string
         */
        case Skip = "SKIP";

        /**
         * The OVERWRITE action.
         * When a conflict occurs, the existing row is replaced with the new row.
         * This is useful when you want to ensure that the latest data is always present in the database.
         * Example: If a row with a duplicate primary key is attempted to be inserted, the existing row will be overwritten with the new data.
         * @var string
         */
        case Overwrite = "OVERWRITE";

        /**
         * The UPDATE action.
         * When a conflict occurs, the existing row is updated with specified values from the new row.
         * This is useful when you want to modify certain fields of an existing record without replacing the entire row.
         * Example: If a row with a duplicate primary key is attempted to be inserted, specific columns of the existing row will be updated with values from the new row.
         * @var string
         */
        case Update = "UPDATE";

        /**
         * Resolves a conflict strategy from a string or returns the existing instance.
         * @param static|string $action The action to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $action) : static {
            return $action instanceof static ? $action : static::from(strtoupper($action));
        }
    }
?>