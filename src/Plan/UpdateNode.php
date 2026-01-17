<?php
    /*/
	 * Project Name:    Wingman — Database — Update Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 13 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Expressions\TableIdentifier;

    /**
     * Represents an UPDATE operation in an execution plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UpdateNode extends UnaryNode {
        /**
         * An associative array of column assignments (e.g., `['column1' => 'value1', 'column2' => 'value2']`).
         * @var array
         */
        protected array $assignments;

        /**
         * An array of primary key column names for an update operation.
         * @var array
         */
        protected array $primaryKeys = [];

        /**
         * Creates a new update node.
         * @param PlanNode $input The preceding node in the plan.
         * @param array $assignments An associative array of column assignments (e.g., ['column1' => 'value1', 'column2' => 'value2']).
         */
        public function __construct (PlanNode $input, array $assignments, array $primaryKeys = []) {
            parent::__construct($input);
            $this->assignments = $assignments;
            $this->primaryKeys = $primaryKeys;
        }

        /**
         * Explains an update node.
         * @param int $depth The depth of the node in the plan tree for indentation.
         * @return string A string representation of the update node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $columnCSV = implode(", ", array_keys($this->assignments));
            $columnString = $columnCSV ? " [$columnCSV]" : "";
            return "{$indent}UPDATE SET{$columnString}" . PHP_EOL . $this->input->explain($depth + 1);
        }

        /**
         * Gets the assignments of a node.
         * @return array The assignments as an associative array.
         */
        public function getAssignments () : array {
            return $this->assignments;
        }

        /**
         * Gets the data for an update operation.
         * @return array An array of associative arrays representing the update data.
         */
        public function getData () : array {
            return $this->isBulk() ? $this->assignments : [$this->assignments];
        }

        /**
         * Gets the primary key columns for an update operation.
         * @return array An array of primary key column names.
         */
        public function getPrimaryKeys () : array {
            return $this->primaryKeys;
        }

        /**
         * Gets the target table or query expression for an update operation.
         * @return TableIdentifier|QueryExpression The target table or query expression.
         */
        public function getTable () : TableIdentifier|QueryExpression {
            $node = $this->input;
        
            while (true) {
                if ($node instanceof SourceNode) break;
        
                if ($node instanceof UnaryNode) {
                    $node = $node->getInput();
                    continue;
                }
        
                if ($node instanceof JoinNode) {
                    $node = $node->getLeft();
                    continue;
                }
            }
            return $node->getSource();
        }

        /**
         * Gets the columns being updated.
         * @return array An array of column names being updated.
         */
        public function getUpdatableColumns () : array {
            if ($this->isBulk()) {
                return array_keys($this->assignments[0]);
            }
            return array_keys($this->assignments);
        }

        /**
         * Indicates whether an update operation is bulk (multiple rows) or single-row.
         * @return bool Whether the update operation is bulk.
         */
        public function isBulk () : bool {
            return isset($this->assignments[0]) && is_array($this->assignments[0]);
        }

        /**
         * Sets the assignments of a node.
         * @param array $assignments The assignments as an associative array.
         * @return static The current node instance.
         */
        public function setAssignments (array $assignments) : static {
            $this->assignments = $assignments;
            return $this;
        }
    }
?>