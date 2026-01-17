<?php
    /*/
     * Project Name:    Wingman — Database — Scope Type
     * Created by:      Angel Politis
     * Creation Date:   Jan 08 2026
     * Last Modified:   Jan 08 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the scope types for query plans.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScopeType : string {
        
        /**
         * The CTE (Common Table Expression) scope type.
         * Refers to temporary result sets defined within the execution scope of a single SQL statement.
         * @var string
         */
        case Cte = "CTE";

        /**
         * The EXISTS scope type.
         * Refers to subqueries used in EXISTS clauses.
         * @var string
         */
        case Exists = "EXISTS";

        /**
         * The ROOT scope type.
         * Refers to the top-level scope of a query plan.
         * @var string
         */
        case Root = "ROOT";

        /**
         * The SET_BRANCH scope type.
         * Refers to branches of set operations like UNION, INTERSECT, etc.
         * @var string
         */
        case SetBranch = "SET_BRANCH";

        /**
         * The SUBQUERY scope type.
         * Refers to nested queries within a larger query.
         * @var string
         */
        case Subquery = "SUBQUERY";

        /**
         * Resolves a scope type from a string or returns the existing instance.
         * @param static|string $type The type to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $type) : static {
            return $type instanceof static ? $type : static::from(strtoupper($type));
        }
    }
?>