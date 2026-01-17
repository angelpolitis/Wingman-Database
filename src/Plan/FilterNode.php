<?php
    /*/
	 * Project Name:    Wingman — Database — Filter Node
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
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a filter operation in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class FilterNode extends UnaryNode implements ExpressionCarrier {
        /**
         * The filter predicate.
         * @var Predicate
         */
        protected $predicate;

        /**
         * Creates a new filter node.
         * @param PlanNode $input The input node.
         * @param Predicate $predicate The filter predicate.
         */
        public function __construct (PlanNode $input, Predicate $predicate) {
            parent::__construct($input);
            $this->predicate = $predicate;
        }

        /**
         * Explains a filter node as a string.
         * @param int $depth The depth of the node in the plan tree for indentation.
         * @return string The explanation of the filter node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            
            $desc = $this->predicate->explain($depth);
            $desc = match (true) {
                $this->predicate instanceof BooleanExpression,
                $this->predicate instanceof ExistsExpression => $desc,
                default => "({$desc})"
            };

            return "{$indent}Filter $desc" . PHP_EOL . $this->input->explain($depth + 1);
        }

        /**
         * Gets the expressions of a filter node.
         * @return Predicate[] The expressions.
         */
        public function getExpressions () : array {
            return [$this->predicate];
        }

        /**
         * Gets the predicate of a filter node.
         * @return Predicate The predicate.
         */
        public function getPredicate () : Predicate {
            return $this->predicate;
        }

        /**
         * Indicates whether the filter predicate is sargable.
         * @return bool Whether the predicate is sargable.
         */
        public function isSargable () : bool {
            return $this->predicate->isSargable();
        }

        /**
         * Sets the expressions of a filter node.
         * @param Predicate[] $expressions The expressions.
         * @return static The updated filter node.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("FilterNode expects exactly one expression.");
            }
            $this->predicate = $expressions[0];
            return $this;
        }

        /**
         * Creates a new filter node with updated expressions.
         * @param Predicate[] $expressions The new expressions.
         * @return static The new filter node.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>