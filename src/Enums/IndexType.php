<?php
    /*/
     * Project Name:    Wingman — Database — Index Type Enum
     * Created by:      Angel Politis
     * Creation Date:   Dec 29 2025
     * Last Modified:   Jan 02 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the possible index types for database schemas.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum IndexType : string {
        /**
         * The plain index type.
         * A standard index that improves query performance without enforcing uniqueness.
         * Example: An index on a 'last_name' column to speed up searches.
         * @var string
         */
        case Plain = "INDEX";

        /**
         * The unique index type.
         * An index that enforces uniqueness on the indexed columns, preventing duplicate values.
         * Example: A unique index on an 'email' column to ensure no two users can register with the same email address.
         * @var string
         */
        case Unique = "UNIQUE INDEX";

        /**
         * The fulltext index type.
         * An index optimized for full-text search capabilities, allowing for efficient searching of large text fields.
         * Example: A fulltext index on a 'content' column in a blog posts table to enable fast keyword searches.
         * @var string
         */
        case Fulltext = "FULLTEXT INDEX";

        /**
         * The spatial index type.
         * An index designed for spatial data types, enabling efficient querying of geometric data.
         * Example: A spatial index on a 'location' column storing geographic coordinates to speed up location-based queries.
         * @var string
         */
        case Spatial = "SPATIAL INDEX";

        /**
         * Resolves an index type from a string or returns the existing instance.
         * @param static|string $type The index type to resolve.
         * @return static The resolved index type.
         */
        public static function resolve (self|string $type) : static {
            return $type instanceof static ? $type : static::from(strtoupper($type));
        }
    }
?>