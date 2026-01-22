<?php
    /*/
	 * Project Name:    Wingman — Database — Table Identifier
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents a table identifier in a database table.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TableIdentifier implements Expression, Aliasable {
        use CanHaveAlias;
        
        /**
         * The name of a table identifier.
         * @var string
         */
        protected string $name;

        /**
         * The schema of a table identifier.
         * @var string|null
         */
        protected ?string $schema = null;

        /**
         * Creates a new table identifier.
         * @param string $name The name of the table identifier.
         * @param string|null $alias An optional alias for the table identifier.
         * @param string|null $schema An optional schema for the table identifier.
         */
        public function __construct (string $name, ?string $alias = null, ?string $schema = null) {
            $this->name = $name;
            $this->schema = $schema;
            $this->alias($alias);
        }

        /**
         * Converts a table identifier to a string representation.
         * @return string The name of the table identifier.
         */
        public function __toString () : string {
            return $this->explain();
        }

        /**
         * Explains a table identifier.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The explanation of the table identifier.
         */
        public function explain (int $depth = 0) : string {
            $string = ($this->schema ? "{$this->schema}." : "") . $this->name;
            return $this->alias ? "{$string} AS {$this->alias}" : $string;
        }

        /**
         * Creates a table identifier from a string definition.
         * @param string $definition The string definition of the table identifier.
         * @return static A new table identifier.
         */
        public static function from (string $definition) : static {
            $table = trim($definition);
            $schema = null;
            $name = null;
            $alias = null;
        
            # 1. Separate the Table/Schema part from the Alias part.
            # Split by the first whitespace found to isolate the identifier from the alias
            $parts = preg_split('/\s+/', $table, 2);
            $identifierPart = $parts[0];
            $remainder = $parts[1] ?? null;
        
            # 2. Parse Schema and Name (e.g., "public.users" or "users")
            if (str_contains($identifierPart, '.')) {
                $identifierParts = explode('.', $identifierPart);
                # Supports "database.schema.table" if needed, but usually schema.table
                $name = array_pop($identifierParts);
                $schema = implode('.', $identifierParts);
            }
            else $name = $identifierPart;
        
            # 3. Parse Alias (e.g., "AS u" or "u").
            if ($remainder) {
                # Remove optional "AS" case-insensitively.
                $alias = preg_replace('/^AS\s+/i', "", $remainder);
            }
        
            return new static($name, $alias, $schema);
        }

        /**
         * Gets the name of a table identifier.
         * @return string The name of the table identifier.
         */
        public function getName () : string {
            return $this->name;
        }

        /**
         * Gets the qualified name of a table identifier.
         * @return string The qualified name of the table identifier.
         */
        public function getQualifiedName () : string {
            return ($this->schema ? "{$this->schema}." : "") . $this->name;
        }
        
        /**
         * Gets the references of a table identifier.
         * Since a table is a source and not a dependency, this returns an empty array.
         * @return array
         */
        public function getReferences () : array {
            return [];
        }

        /**
         * Gets the schema of a table identifier.
         * @return string|null The schema of the table identifier, or null if none is set.
         */
        public function getSchema () : ?string {
            return $this->schema;
        }

        /**
         * Determines whether a table identifier is sargable.
         * @return bool Whether the identifier is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Creates a new table identifier with the specified schema.
         * @param string|null $schema The schema to set.
         * @return TableIdentifier A new table identifier with the specified schema.
         */
        public function withSchema (?string $schema) : TableIdentifier {
            return new static($this->name, $this->alias, $schema);
        }
    }