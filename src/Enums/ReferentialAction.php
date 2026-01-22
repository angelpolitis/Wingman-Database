<?php
    /*/
    * Project Name:    Wingman — Database — Referential Action Enum
    * Created by:      Angel Politis
    * Creation Date:   Dec 29 2025
    * Last Modified:   Dec 29 2025
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the possible referential actions for foreign key constraints.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ReferentialAction : string {
        /**
         * The CASCADE action.
         * When a referenced row is deleted or updated, the same action is applied to the rows that reference it.
         * This is useful for maintaining referential integrity by automatically propagating changes.
         * Example: If a parent row is deleted, all child rows that reference it will also be deleted.
         * @var string
         */
        case Cascade = "CASCADE";

        /**
         * The RESTRICT action.
         * Prevents the deletion or update of a referenced row if there are any existing rows that reference it.
         * This ensures that no orphaned rows are created in the referencing table.
         * Example: If a parent row is referenced by child rows, attempting to delete or update the parent will fail.
         * @var string
         */
        case Restrict = "RESTRICT";

        /**
         * The SET NULL action.
         * When a referenced row is deleted or updated, the foreign key values in the referencing rows are set to NULL.
         * This is useful when you want to remove the reference without deleting the referencing rows.
         * Example: If a parent row is deleted, the foreign key columns in child rows that reference it will be set to NULL.
         * @var string
         */
        case SetNull = "SET NULL";

        /**
         * The NO ACTION action.
         * No automatic action is taken when a referenced row is deleted or updated.
         * However, if the operation would violate referential integrity, it will be rejected.
         * This is similar to RESTRICT, but the check is deferred until the end of the transaction.
         * Example: If a parent row is deleted, and there are child rows referencing it, the deletion will fail unless handled within a transaction.
         * @var string
         */
        case NoAction = "NO ACTION";

        /**
         * The SET DEFAULT action.
         * When a referenced row is deleted or updated, the foreign key values in the referencing rows are set to their default values.
         * This is useful when you want to reset the reference to a predefined default instead of NULL or deleting the rows.
         * Example: If a parent row is deleted, the foreign key columns in child rows that reference it will be set to their default values.
         * @var string
         */
        case SetDefault = "SET DEFAULT";

        /**
         * Resolves a referential action from a string or returns the existing instance.
         * @param self|string $action The action to resolve.
         * @return self The resolved ReferentialAction instance.
         */
        public static function resolve (self|string $action) : self {
            return $action instanceof self ? $action : self::from(strtoupper($action));
        }
    }
?>