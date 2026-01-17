<?php
    /*/
     * Project Name:    Wingman — Database — Literal Expression
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
     * Represents a literal value in a SQL statement (e.g., a number, a string, or NULL).
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LiteralExpression implements Expression {
        use CanHaveAlias;
        
        /**
         * The value of a literal.
         * @var mixed
         */
        protected mixed $value;

        /**
         * The alias of a literal.
         * @var string|null
         */
        protected ?string $alias = null;

        /**
         * Creates a new literal expression.
         * @param mixed $value The literal value.
         * @param string|null $alias Optional alias.
         */
        public function __construct (mixed $value, ?string $alias = null) {
            $this->value = $value;
            $this->alias($alias);
        }

        /**
         * Gets the references of a literal; literals do not have references.
         * @return array An empty array.
         */
        public function getReferences () : array {
            return [];
        }

        /**
         * Explains a literal value as a string.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The string explanation of the literal.
         */
        public function explain (int $depth = 0) : string {
            if ($this->value === null) {
                $string = "NULL";
            }
            elseif (is_bool($this->value)) {
                $string = $this->value ? "TRUE" : "FALSE";
            }
            elseif (is_string($this->value)) {
                $escaped = str_replace("'", "''", $this->value);
                $string = "'{$escaped}'";
            }
            else $string = (string) $this->value;

            if ($this->alias) {
                $string .= " AS " . $this->getAlias();
            }

            return $string;
        }

        /**
         * Gets the value of a literal.
         * @return mixed The literal value.
         */
        public function getValue () : mixed {
            return $this->value;
        }

        /**
         * Indicates whether a literal is sargable (search argument capable).
         * @return bool Whether the literal is sargable.
         */
        public function isSargable () : bool {
            return true;
        }
    }
?>