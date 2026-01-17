<?php
    /*/
     * Project Name:    Wingman — Database — CTE Expression
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a Common Table Expression (CTE) in an SQL statement.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CteExpression extends Predicate implements ExpressionCarrier {
        /**
         * The name of a CTE.
         * @var string
         */
        protected string $name;

        /**
         * The main query (anchor) of a CTE.
         * @var QueryExpression
         */
        protected QueryExpression $query;

        /**
         * The recursive part of a CTE, if applicable.
         * @var QueryExpression|null
         */
        protected ?QueryExpression $recursivePart;

        /**
         * Whether a CTE is recursive.
         * @var bool
         */
        protected bool $recursive = false;

        /**
         * The explicit column definitions for a CTE.
         * @var ColumnDefinition[]
         */
        protected array $columns;

        /**
         * The union type used between anchor and recursive parts.
         * @var string
         */
        protected string $unionType;

        /**
         * Creates a new CTE expression.
         * @param string $name The name of the CTE.
         * @param QueryExpression $query The main query or anchor.
         * @param bool $recursive Whether this is a recursive CTE.
         * @param QueryExpression|null $recursivePart The recursive portion (required if $recursive is true).
         * @param array $columns Optional explicit column definitions for the CTE.
         * @param string $unionType The union operator between anchor and recursive (UNION or UNION ALL).
         */
        public function __construct (
            string $name,
            QueryExpression $query,
            bool $recursive = false,
            ?QueryExpression $recursivePart = null,
            array $columns = [],
            string $unionType = "UNION ALL"
        ) {
            $this->name = $name;
            $this->query = $query;
            $this->recursive = $recursive;
            $this->recursivePart = $recursivePart;
            $this->columns = $columns;
            $this->unionType = $unionType;
        }

        /**
         * Gets the expressions contained in a CTE.
         * @return Expression[] The sub-expressions.
         */
        public function getExpressions () : array {
            return array_filter([$this->query, $this->recursivePart]);
        }

        /**
         * Collects all table and column references from the subqueries.
         * @return array The list of references.
         */
        public function getReferences () : array {
            # Collect the references from the anchor.
            $refs = $this->query->getValue()->getState()->getReferences();
        
            # Collect the references from the recursive part.
            if ($this->recursive && $this->recursivePart) {
                $refs = array_merge($refs, $this->recursivePart->getValue()->getState()->getReferences());
            }
        
            # Clean up: A CTE doesn't "depend" on its own name.
            $refs = array_diff(array_unique($refs), [$this->name]);
            
            return array_values($refs);
        }

        /**
         * Explains a CTE expression for debugging purposes.
         * @param int $depth The depth of the explanation (for nested structures).
         * @return string The explanation string.
         */
        public function explain (int $depth = 0): string {
            $pad = str_pad("", $depth * 3);
            $cols = !empty($this->columns) ? '(' . implode(", ", $this->columns) . ')' : '';
            
            $type = $this->recursive ? "RECURSIVE CTE" : "CTE";
            $output = "{$pad}{$type} {$this->name}{$cols} AS (" . PHP_EOL;
            
            $output .= $this->query->getPlan()->explain($depth + 1);
            
            if ($this->recursive && $this->recursivePart) {
                $output .= "{$pad}{$this->unionType}" . PHP_EOL;
                $output .= $this->recursivePart->getPlan()->explain($depth + 1);
            }
            
            $output .= "{$pad})";
            return $output;
        }

        /**
         * Gets the explicit column definitions for a CTE.
         * @return array The column definitions.
         */
        public function getColumns () : array {
            return $this->columns;
        }

        /**
         * Gets the name of a CTE.
         * @return string The CTE name.
         */
        public function getName () : string {
            return $this->name;
        }

        /**
         * Gets the main query (anchor) of a CTE.
         * @return QueryExpression The main query.
         */
        public function getQuery () : QueryExpression {
            return $this->query;
        }

        /**
         * Gets the recursive part of a CTE, if applicable.
         * @return QueryExpression|null The recursive query or null if not recursive.
         */
        public function getRecursivePart () : ?QueryExpression {
            return $this->recursivePart;
        }

        /**
         * Gets the union type used between anchor and recursive parts.
         * @return string The union type (e.g., "UNION" or "UNION ALL").
         */
        public function getUnionType () : string {
            return $this->unionType;
        }

        /**
         * Checks whether a CTE is recursive.
         * @return bool Whether the expression is recursive.
         */
        public function isRecursive () : bool {
            return $this->recursive;
        }

        /**
         * Determines whether a CTE expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the expressions of a CTE.
         * @param array $expressions An array containing the main query and recursive part.
         * @return static The current instance for method chaining.
         */
        public function setExpressions (array $expressions) : static {
            [$this->query, $this->recursivePart] = $expressions + [null, null];
            return $this;
        }

        /**
         * Creates a clone of the CTE expression with new sub-expressions.
         * @param array $expressions An array containing the main query and recursive part.
         * @return static A new CTE expression instance.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>