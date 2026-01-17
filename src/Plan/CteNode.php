<?php
    /*/
	 * Project Name:    Wingman — Database — Cte Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\CteExpression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a Common Table Expression (CTE) node.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CteNode extends UnaryNode implements ExpressionCarrier {
        /**
         * The CTE expressions.
         * @var CteExpression[]
         */
        protected array $expressions;

        /**
         * Whether a CTE is recursive.
         * @var bool
         */
        protected bool $recursive = false;

        /**
         * Creates a new CTE node.
         * @param PlanNode $input The input plan node.
         * @param CteExpression[] $expressions The CTE expressions.
         * @param bool $recursive Whether the CTE is recursive.
         */
        public function __construct (PlanNode $input, array $expressions, bool $recursive = false) {
            parent::__construct($input);
            $this->expressions = $expressions;
            $this->recursive = $recursive;
        }

        /**
         * Explains a CTE node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the CTE node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $type = $this->recursive ? "RECURSIVE CTE" : "CTE";
            $out = "{$indent}{$type} Expressions:\n";
        
            foreach ($this->expressions as $alias => $expression) {
                $out .= "{$indent}{$indent}'{$alias}' => \n";
                
                $out .= $expression->explain($depth + 1);
            }
        
            $out .= PHP_EOL . "{$indent}Main Query:" . PHP_EOL  . $this->input->explain($depth + 1);
        
            return $out;
        }

        /**
         * Gets the expressions of a node.
         * @return CteExpression[] The CTE expressions.
         */
        public function getExpressions () : array {
            return $this->expressions;
        }

        /**
         * Indicates whether a CTE is recursive.
         * @return bool Whether the CTE is recursive.
         */
        public function isRecursive () : bool {
            return $this->recursive;
        }

        /**
         * Sets the expressions of a node.
         * @param CteExpression[] $expressions The CTE expressions.
         * @return static The current node for chaining.
         */
        public function setExpressions (array $expressions) : static {
            $this->expressions = $expressions;
            return $this;
        }

        /**
         * Creates a clone of the current node with new expressions.
         * @param CteExpression[] $expressions The new CTE expressions.
         * @return static The cloned node with updated expressions.
         */
        public function withExpressions(array $expressions): static {
            $clone = clone $this;
            $clone->setExpressions($expressions);
            return $clone;
        }
    }
?>