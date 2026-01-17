<?php
    /*/
     * Project Name:    Wingman — Database — Scope Dependency Type
     * Created by:      Angel Politis
     * Creation Date:   Jan 08 2026
     * Last Modified:   Jan 08 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Defines the semantic relationship between two query scopes.
     * These dependency types are used to build the scope graph and reason about resolution order, legality, and optimization barriers.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScopeDependencyType : string {
        /**
         * A scope defines another scope.
         * Used primarily for CTEs:
         *   `WITH cte AS (...) SELECT ...`
         * The defining scope must be resolved before the consumer.
         * @var string
         */
        case Defines = "DEFINES";

        /**
         * A scope is structurally derived from another scope.
         * Used for UNION / SET operation branches.
         * Example:
         *   `(SELECT ...) UNION (SELECT ...)`
         * @var string
         */
        case DerivesFrom = "DERIVES_FROM";

        /**
         * A scope references symbols from another scope.
         * Used for correlated subqueries (EXISTS, scalar subqueries, IN).
         * This dependency is *read-only* and does not imply full evaluation order.
         * @var string
         */
        case CorrelatedTo = "CORRELATED_TO";

        /**
         * A structural containment relationship.
         * Used to represent nesting without semantic dependency.
         * Example:
         *   A root scope *contains* a subquery scope,
         *   but does not depend on it semantically.
         * This edge is informational and non-blocking.
         * @var string
         */
        case Contains = "CONTAINS";

        /**
         * Resolves a scope dependency type from a string or returns the existing instance.
         * @param static|string $type The type to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $type) : static {
            return $type instanceof static ? $type : static::from(strtoupper($type));
        }
    }
?>