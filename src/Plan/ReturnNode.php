<?php
    /*/
	 * Project Name:    Wingman — Database — Return Node
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\ColumnIdentifier;

    /**
     * Represents a plan node that specifies which columns to return after an operation.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ReturnNode extends UnaryNode {
        /**
         * The columns of a node.
         * @var ColumnIdentifier[]
         */
        protected array $columns;

        /**
         * Creates a new return node.
         * @param PlanNode $input The input plan node.
         * @param ColumnIdentifier[] $columns The columns to return.
         */
        public function __construct (PlanNode $input, array $columns = []) {
            parent::__construct($input);
            $this->columns = $columns ?: new ColumnIdentifier('*');
        }

        /**
         * Explains a return node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the return node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $cols = implode(", ", $this->columns);
            return "{$indent}RETURN [{$cols}]" . PHP_EOL . $this->input->explain($depth + 1);
        }

        /**
         * Gets the columns of the node.
         * @return ColumnIdentifier[] The columns.
         */
        public function getColumns () : array {
            return $this->columns;
        }
    }
?>