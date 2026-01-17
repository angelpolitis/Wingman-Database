<?php
    /*/
	 * Project Name:    Wingman — Database — Join Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\JoinExpression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a join node in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JoinNode extends BinaryNode implements ExpressionCarrier {
        /**
         * The join expression containing conditions and type.
         * @var JoinExpression
         */
        protected JoinExpression $expression;

        /**
         * Creates a new join node.
         * @param PlanNode $left The left-side plan (the accumulated tree).
         * @param PlanNode $right The right-side plan (the table or subquery being joined).
         * @param JoinExpression $expression The expression containing conditions and type.
         */
        public function __construct (PlanNode $left, PlanNode $right, JoinExpression $expression) {
            parent::__construct($left, $right);
            $this->expression = $expression;
        }

        /**
         * Explains a join node as a string.
         * @param int $depth The depth of the node in the plan tree (for indentation).
         * @return string The explanation string.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $indent2 = str_pad("", ($depth + 1) * 3);
            $indent3 = str_pad("", ($depth + 2) * 3);
            
            $type = $this->expression->getType()->name;

            $on = array_map(fn ($c) => "{$indent3}" . $c->explain(), $this->expression->getConditions());
            $conjunction = $this->expression->getConjunction();
            $conditions = implode(" {$conjunction}\n", $on);

            return "{$indent}Join ($type):" . PHP_EOL
                . "{$indent2}Left:" . PHP_EOL . $this->left->explain($depth + 2)
                . "{$indent2}Right:" . PHP_EOL . $this->right->explain($depth + 2)
                . "{$indent2}Conditions:\n$conditions\n";
        }

        public function findSources () : array {
            $result = [];

            $walk = function (PlanNode $node) use (&$walk, &$result) {

                // Base source (left-most)
                if ($node instanceof SourceNode) {
                    $result[] = [
                        'source'     => $node,
                        'expression' => null,
                    ];
                    return;
                }

                if ($node instanceof JoinNode) {
                    // Left side continues the join chain
                    $walk($node->getLeft());

                    // Right side is the newly joined source
                    $right = $node->getRight();
                    if ($right instanceof SourceNode) {
                        $result[] = [
                            'source'     => $right,
                            'expression' => $node->getExpression(),
                        ];
                        return;
                    }

                    // Safety: if RHS is not a SourceNode, descend
                    $walk($right);
                    return;
                }

                if ($node instanceof UnaryNode) {
                    $walk($node->getInput());
                    return;
                }
            };

            $walk($this);

            return $result;
        }

        /**
         * Gets the expression of a join node.
         * @return JoinExpression The join expression.
         */
        public function getExpression () : JoinExpression {
            return $this->expression;
        }

        /**
         * Gets the expressions of a join node.
         * @return JoinExpression[] The expressions.
         */
        public function getExpressions () : array {
            return [$this->expression];
        }

        /**
         * Sets the expressions of a join node.
         * @param JoinExpression[] $expressions The new expressions.
         * @return static The updated join node.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("JoinNode expects exactly one expression.");
            }
            $this->expression = $expressions[0];
            return $this;
        }

        /**
         * Creates a new join node with updated expressions.
         * @param JoinExpression[] $expressions The new expressions.
         * @return static The new join node.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>