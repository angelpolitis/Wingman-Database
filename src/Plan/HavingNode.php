<?php
    /*/
	 * Project Name:    Wingman — Database — Having Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\BooleanExpression;
    use Wingman\Database\Expressions\ExistsExpression;
    use Wingman\Database\Expressions\Predicate;

    /**
     * Represents a HAVING clause in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class HavingNode extends UnaryNode {
        /**
         * The predicate applied after aggregation.
         * @var Predicate
         */
        protected $predicate;

        /**
         * Creates a new having node.
         * @param PlanNode $input The preceding node (usually an AggregateNode).
         * @param Predicate $predicate The having logic.
         */
        public function __construct (PlanNode $input, Predicate $predicate) {
            parent::__construct($input);
            $this->predicate = $predicate;
        }

        /**
         * Explains a having node as a string.
         * @param int $depth The depth of the node in the plan tree for indentation.
         * @return string The explanation of the having node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            
            $desc = $this->predicate->explain($depth);
            $desc = match (true) {
                $this->predicate instanceof BooleanExpression,
                $this->predicate instanceof ExistsExpression => $desc,
                default => "({$desc})"
            };
        
            return "{$indent}Having $desc" . PHP_EOL . $this->input->explain($depth + 1);
        }

        /**
         * Gets the expressions of a having node.
         * @return Predicate[] The expressions.
         */
        public function getExpressions () : array {
            return [$this->predicate];
        }

        /**
         * Gets the predicate of a having node.
         * @return Predicate The predicate.
         */
        public function getPredicate () : Predicate {
            return $this->predicate;
        }

        /**
         * Indicates whether a having predicate is sargable.
         * @return bool Whether the predicate is sargable.
         */
        public function isSargable () : bool {
            return $this->predicate->isSargable();
        }

        /**
         * Sets the expressions of a having node.
         * @param Predicate[] $expressions The expressions.
         * @return static The updated having node.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("HavingNode expects exactly one expression.");
            }
            $this->predicate = $expressions[0];
            return $this;
        }

        /**
         * Creates a new having node with updated expressions.
         * @param Predicate[] $expressions The new expressions.
         * @return static The new having node.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>