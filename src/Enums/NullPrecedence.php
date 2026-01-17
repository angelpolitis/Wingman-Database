<?php
    /*/
	 * Project Name:    Wingman — Database — Null Precedence Enum
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Enums namespace.
    namespace Wingman\Database\Enums;

    /**
     * Enumerates the null precedence for ordering query results.
     * @package Wingman\Database\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum NullPrecedence : string {
        /**
         * The NULLS FIRST precedence.
         * Places NULL values before non-NULL values in the ordering.
         * @var string
         */
        case First = "NULLS FIRST";

        /**
         * The NULLS LAST precedence.
         * Places NULL values after non-NULL values in the ordering.
         * @var string
         */
        case Last  = "NULLS LAST";

        /**
         * The default precedence (none specified).
         * @var string
         */
        case None  = "";

        /**
         * Resolves a precedence from a string or returns the existing instance.
         * @param static|string $precedence The precedence to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $precedence) : static {
            if ($precedence instanceof static) return $precedence;

            $precedence = strtoupper(str_replace('_', ' ', $precedence));
            
            return static::from($precedence);
        }
    }
?>