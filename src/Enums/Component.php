<?php
    /*/
	 * Project Name:    Wingman — Database — Component Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the different components of a database query.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum Component {
        /**
         * Represents the SET clause in an UPDATE query.
         * @var string
         */
        case Assignments;

        /**
         * Represents the CTE (Common Table Expressions) clauses in a query.
         * @var string
         */
        case Cte;

        /**
         * Represents the GROUP BY clause in a query.
         * @var string
         */
        case GroupBy;

        /**
         * Represents the HAVING clause in a query.
         * @var string
         */
        case Having;

        /**
         * Represents the JOIN clauses in a query (can contain subqueries).
         * @var string
         */
        case Joins;

        /**
         * Represents the LIMIT clause in a query.
         * @var string
         */
        case Limit;

        /**
         * Represents the LOCK clause in a query.
         * @var string
         */
        case Lock;

        /**
         * Represents the OFFSET clause in a query.
         * @var string
         */
        case Offset;

        /**
         * Represents the ORDER BY clause in a query.
         * @var string
         */
        case OrderBy;

        /**
         * Represents the SELECT projections in a query.
         * @var string
         */
        case Projections;

        /**
         * Represents the RETURNING clause in a query.
         * @var string
         */
        case Returning;

        /**
         * Represents the main table(s) in the FROM clause of a query.
         * @var string
         */
        case Sources;

        /**
         * Represents the set operations in a query.
         * @var string
         */
        case SetOperation;

        /**
         * Represents the VALUES clause in an INSERT query.
         * @var string
         */
        case Values;

        /**
         * Represents the WHERE clause in a query.
         * @var string
         */
        case Where;
    }
?>