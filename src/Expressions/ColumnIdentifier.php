<?php
    /*/
	 * Project Name:    Wingman — Database — Column Identifier
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 09 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents a column identifier in a database table.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ColumnIdentifier implements Expression, Aliasable {
        use CanHaveAlias;

        /**
         * The name of a column identifier.
         * @var string
         */
        protected string $name;

        /**
         * The table of a column identifier.
         * @var string|null
         */
        protected ?string $table = null;

        /**
         * The schema of a column identifier.
         * @var string|null
         */
        protected ?string $schema = null;

        /**
         * Creates a new column identifier.
         * @param string $name The name of the column identifier.
         * @param string $table The table of the column identifier.
         * @param string|null $alias An optional alias for the column identifier.
         */
        public function __construct (string $name, ?string $table = null, ?string $alias = null) {
            $this->name = $name;
            $this->table = $table;
            $this->alias = $alias;
        }

        /**
         * Converts a column identifier to a string representation.
         * @return string The name of the column identifier.
         */
        public function __toString () : string {
            return $this->explain();
        }

        /**
         * Explains a colunn identifier.
         * @param int $depth The depth of the expression for formatting purposes (not used here).
         * @return string The explanation of the column identifier.
         */
        public function explain (int $depth = 0) : string {
            $string = ($this->table ? "{$this->table}." : "") . $this->name;
            return $this->alias ? "{$string} AS {$this->alias}" : $string;
        }

        /**
         * Creates a column identifier from a string definition.
         * @param string $definition The string definition of the column identifier.
         * @return static A new column identifier.
         */
        public static function from (string $definition) : static {
            $parts = preg_split('/\s+(?:AS\s+)?/i', trim($definition), 2);
            
            $identifierPart = $parts[0];
            $alias = $parts[1] ?? null;
            $table = null;
        
            if (str_contains($identifierPart, '.')) {
                $identifierParts = explode('.', $identifierPart);
                $name = array_pop($identifierParts);
                $table = implode('.', $identifierParts);
            }
            else $name = $identifierPart;
        
            return new static($name, $table, $alias);
        }

        /**
         * Gets the alias of a column identifier.
         * @return string|null The alias of the column identifier, or null if none is set.
         */
        public function getAlias () : ?string {
            return $this->alias;
        }

        /**
         * Gets the name of a column identifier.
         * @return string The name of the column identifier.
         */
        public function getName () : string {
            return $this->name;
        }

        /**
         * Gets the qualified name of a column identifier.
         * @return string The qualified name of the column identifier.
         */
        public function getQualifiedName () : string {
            return ($this->table ? "{$this->table}." : "") . $this->name;
        }

        /**
         * Gets the references of a column identifier.
         * @return array An array of references for the column identifier.
         */
        public function getReferences () : array {
            $references = [];
            if ($this->table) $references[] = $this->table;
            return $references;
        }

        /**
         * Gets the table of a column identifier.
         * @return string|null The table of the column identifier, or null if none is set.
         */
        public function getTable () : ?string {
            return $this->table;
        }

        /**
         * Indicates whether a column identifier is sargable (search argument capable).
         * @return bool Whether the column identifier is sargable.
         */
        public function isSargable () : bool {
            return true;
        }

        /**
         * Sets the schema of a column identifier.
         * @param string|null $schema The new schema for the column identifier.
         * @return static The current instance with the updated schema.
         */
        public function setSchema (?string $schema) : static {
            $this->schema = $schema;
            return $this;
        }

        /**
         * Creates a new column identifier with the specified table.
         * @param string|null $table The new table for the column identifier.
         * @return static A new column identifier with the specified table.
         */
        public function withTable (?string $table) : static {
            $new = clone $this;
            $new->table = $table;
            return $new;
        }
    }