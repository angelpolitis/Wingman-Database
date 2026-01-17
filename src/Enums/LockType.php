<?php
    /*/
	 * Project Name:    Wingman — Database — Lock Type Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 17 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the lock types for database operations.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum LockType : string {
        /**
         * The EXCLUSIVE lock type.
         * Acquires an exclusive lock on the selected rows, preventing other transactions from reading or modifying them until the lock is released.
         * This type of lock is typically used in scenarios where data integrity is critical, such as during updates or deletions.
         * @var string
         */
        case Exclusive = "EXCLUSIVE";

        /**
         * The NONE lock type.
         * Indicates that no lock should be applied to the selected rows.
         * This means that other transactions can read or modify the rows without any restrictions.
         * This type of lock is typically used in scenarios where performance is prioritized over data integrity.
         * @var string
         */
        case None = "NONE";

        /**
         * The SHARED lock type.
         * Acquires a shared lock on the selected rows, allowing other transactions to read the rows but preventing them from modifying them until the lock is released.
         * This type of lock is typically used in scenarios where data consistency is important, such as during read operations that require a stable view of the data.
         * @var string
         */
        case Shared = "SHARED";

        /**
         * Resolves a lock type from a string or returns the existing instance.
         * @param static|string $type The type to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $type) : static {
            return $type instanceof static ? $type : static::from(strtoupper($type));
        }
    }
?>