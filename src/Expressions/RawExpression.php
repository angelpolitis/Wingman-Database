<?php
    /*/
	 * Project Name:    Wingman — Database — Raw Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 07 2026
    /*/
    
    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    /**
     * Represents a raw SQL expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class RawExpression extends Predicate {
        /**
         * The raw SQL string of a raw expression.
         * @var string
         */
        protected string $value;

        /**
         * The parameters for the raw SQL string.
         * @var array
         */
        protected array $params;

        /**
         * Creates a new raw expression.
         * @param string $value The raw SQL string.
         * @param array $params The parameters for the raw SQL string.
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (string $value, array $params = [], ?string $alias = null) {
            $this->value = $value;
            $this->params = $params;
            $this->alias($alias);
        }

        /**
         * Converts a raw expression to a string.
         * @return string The raw SQL string.
         */
        public function __toString () : string {
            return $this->value;
        }

        /**
         * Explains a raw expression.
         * @param int $depth The depth of the expression for formatting purposes (not used here).
         * @return string The human-readable representation of the raw expression.
         */
        public function explain (int $depth = 0) : string {
            $paramsStr = !empty($this->params) 
                ? " [" . implode(", ", $this->params) . "]" 
                : "";
    
            $string = "RAW: \"{$this->value}\"{$paramsStr}";
            
            return $this->alias ? "({$string}) AS {$this->alias}" : $string;
        }

        /**
         * Gets the parameters for a raw expression.
         * @return array The parameters of the raw expression.
         */
        public function getParams () : array {
            return $this->params;
        }

        /**
         * Gets the referenced tables/aliases in the raw SQL string.
         * @return array An array of referenced table/alias names.
         */
        public function getReferences () : array {
            # Look for words followed by a dot but ensure it's not preceded by a digit (decimal check)
            # and handle potential backticks/quotes if your users use them.
            preg_match_all('/(?<![0-9])\b([a-zA-Z_][a-zA-Z0-9_]*)\.[a-zA-Z_][a-zA-Z0-9_]*\b/', $this->value, $matches);
            
            return array_unique($matches[1] ?? []);
        }

        /**
         * Gets the raw SQL value of a raw expression.
         * @return string The raw SQL string.
         */
        public function getValue () : string {
            return $this->value;
        }

        /**
         * Determines whether a raw expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }
    }
?>