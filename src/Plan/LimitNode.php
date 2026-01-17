<?php
    /*/
	 * Project Name:    Wingman — Database — Limit Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;

    /**
     * Represents a limit node.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LimitNode extends UnaryNode {
        /**
         * The limit value.
         * @var int
         */
        protected int $limit;

        /**
         * The offset value.
         * @var int
         */
        protected int $offset;

        /**
         * Creates a new limit node.
         * @param PlanNode $input The input plan node.
         * @param int $limit The limit value.
         * @param int $offset The offset value (default is 0).
         */
        public function __construct (PlanNode $input, int $limit, int $offset = 0) {
            parent::__construct($input);
            $this->limit = $limit;
            $this->offset = $offset;
        }

        /**
         * Explains a limit node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the limit node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            return "{$indent}Limit ({$this->limit}, Offset: {$this->offset})" . PHP_EOL
                . $this->input->explain($depth + 1);
        }

        /**
         * Gets the limit value.
         * @return int The limit value.
         */
        public function getLimit () : int {
            return $this->limit;
        }

        /**
         * Gets the offset value.
         * @return int The offset value.
         */
        public function getOffset () : int {
            return $this->offset;
        }
    }
?>