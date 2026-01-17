<?php
    /*/
	 * Project Name:    Wingman — Database — Insert Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\TableIdentifier;

    /**
     * Represents an insert operation in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InsertNode extends UnaryNode {
        /**
         * The target node providing the table to insert into.
         * @var TargetNode
         */
        protected PlanNode $input;

        /**
         * The columns being populated in the insert operation.
         * @var string[]
         */
        protected array $columns = [];

        /**
         * The source of data for the insert operation; can be an array of rows or the plan of a subquery.
         * @var array|PlanNode
         */
        protected array|PlanNode $source = [];

        /**
         * Indicates whether conflicts are ignored during insertion.
         * @var bool
         */
        protected bool $conflictsIgnored = false;

        /**
         * Creates a new insert node.
         * @param TargetNode $input The input plan node.
         * @param string[] $columns The columns being populated.
         * @param array[]|PlanNode $source Raw rows of data or a compiled Select Plan.
         * @param bool $conflictsIgnored Whether to use INSERT IGNORE logic.
         */
        public function __construct (TargetNode $input, array $columns = [], array|PlanNode $source = [], bool $conflictsIgnored = false) {
            parent::__construct($input);
            $this->columns = $columns;
            $this->source = $source;
            $this->conflictsIgnored = $conflictsIgnored;
        }

        /**
         * Explains an insert node.
         * @param int $depth The depth for indentation.
         * @return string The explanation string.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $indent2 = str_pad("", ($depth + 1) * 3);
            $type = $this->conflictsIgnored ? "INSERT IGNORE" : "INSERT";
            
            $out = sprintf("{$indent}$type INTO %s%s" . PHP_EOL,
                $this->getTable()->getName(), 
                $this->columns ? ' (' . implode(", ", $this->columns) . ')' : ""
            );

            if ($this->source instanceof PlanNode) {
                $out .= "{$indent2}Source (Type: Subquery) (" . PHP_EOL . $this->source->explain($depth + 2) . "{$indent2})" . PHP_EOL;
            }
            else {
                $rowCount = count($this->source);
                $out .= "{$indent2}Source (Type: Values): {$rowCount} row" . ($rowCount === 1 ? "" : "s") . PHP_EOL;
            }

            return $out;
        }

        /**
         * Gets the columns being populated in an insert operation.
         * @return string[] The column names.
         */
        public function getColumns () : array {
            return $this->columns;
        }

        /**
         * Gets the source of an insert node.
         * @return array|PlanNode The source data or query plan.
         */
        public function getSource () : array|PlanNode {
            return $this->source;
        }

        /**
         * Gets the target table of an insert operation.
         * @return TableIdentifier The target table.
         */
        public function getTable () : TableIdentifier {
            return $this->input->getTarget();
        }

        /**
         * Indicates whether conflicts are ignored during insertion.
         * @return bool Whether conflicts are ignored.
         */
        public function ignoresConflicts () : bool {
            return $this->conflictsIgnored;
        }

        /**
         * Sets the columns being populated in an insert operation.
         * @param string[] $columns The new column names.
         * @return static The current instance.
         */
        public function setColumns (array $columns) : static {
            $this->columns = $columns;
            return $this;
        }

        /**
         * Creates a new insert node with the given columns.
         * @param string[] $columns The new column names.
         * @return static The new insert node.
         */
        public function withColumns (array $columns) : static {
            $clone = clone $this;
            $clone->columns = $columns;
            return $clone;
        }

        /**
         * Creates a new insert node with the given source.
         * @param array|PlanNode $source The new source data or query plan.
         * @return static The new insert node.
         */
        public function withSource (array|PlanNode $input) : static {
            $clone = clone $this;
            $clone->source = $input;
            return $clone;
        }
    }
?>