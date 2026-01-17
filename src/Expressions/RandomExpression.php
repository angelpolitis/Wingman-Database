<?php
    /*/
     * Project Name:    Wingman — Database — Random Expression
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents a RANDOM() expression.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class RandomExpression implements Expression {
        use CanHaveAlias;

        /**
         * Gets the references for the expression; random functions don't reference tables.
         * @return array An empty array.
         */
        public function getReferences () : array {
            return [];
        }

        /**
         * Explains a RANDOM() expression.
         * @param int $depth The depth of the expression for formatting purposes (not used here).
         * @return string The human-readable representation of the RANDOM() expression.
         */
        public function explain (int $depth = 0) : string {
            $string = "RANDOM()";
            
            return $this->alias ? "{$string} AS {$this->alias}" : $string;
        }

        /**
         * Determines whether a random expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }
    }
?>