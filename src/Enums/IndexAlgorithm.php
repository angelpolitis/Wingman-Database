<?php
    /*/
     * Project Name:    Wingman — Database — Index Algorithm Enum
     * Created by:      Angel Politis
     * Creation Date:   Dec 29 2025
     * Last Modified:   Jan 02 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the possible index algorithms for database schemas.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum IndexAlgorithm : string {
        /**
         * The B-tree index algorithm.
         * A balanced tree structure that maintains sorted data and allows for efficient insertion, deletion, and search operations.
         * Example: A B-tree index on a 'username' column to speed up lookups in a user table.
         * @var string
         */
        case Btree = "BTREE";

        /**
         * The hash index algorithm.
         * An index that uses a hash table to map keys to their corresponding values, allowing for fast equality searches.
         * Example: A hash index on a 'session_id' column to quickly retrieve session data.
         * @var string
         */
        case Hash  = "HASH";

        /**
         * The GiST index algorithm.
         * A generalized search tree that can be used for various data types, including geometric and full-text data.
         * Example: A GiST index on a 'geometry' column to optimize spatial queries.
         * @var string
         */
        case Gist  = "GIST";

        /**
         * The GIN index algorithm.
         * A generalized inverted index that is particularly effective for indexing composite values, such as arrays and full-text search data.
         * Example: A GIN index on a 'tags' array column to speed up searches for posts with specific tags.
         * @var string
         */
        case Gin   = "GIN";

        /**
         * The SP-GiST index algorithm.
         * A space-partitioned generalized search tree that is useful for indexing multi-dimensional data.
         * Example: An SP-GiST index on a 'location' column to optimize queries based on geographic coordinates.
         * @var string
         */
        case Spgist = "SPGIST";

        /**
         * The BRIN index algorithm.
         * A block range index that is efficient for very large tables where the indexed column values are correlated with their physical location on disk.
         * Example: A BRIN index on a 'created_at' timestamp column to speed up queries filtering by date ranges.
         * @var string
         */
        case Brin  = "BRIN";

        /**
         * Resolves an index algorithm from a string or returns the existing instance.
         * @param static|string $algorithm The algorithm to resolve.
         * @return static The resolved index algorithm.
         */
        public static function resolve (self|string $algorithm) : static {
            return $algorithm instanceof static ? $algorithm : static::from(strtoupper($algorithm));
        }
    }
?>