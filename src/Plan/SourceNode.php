<?php
    /*/
	 * Project Name:    Wingman — Database — Source Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 30 2025
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents a source node.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SourceNode implements PlanNode, Aliasable, ExpressionCarrier {
        use CanHaveAlias;

        /**
         * The source of a source node.
         * @var TableIdentifier|QueryExpression The source table or plan node.
         */
        protected TableIdentifier|QueryExpression $source;

        /**
         * Creates a new source node.
         * @param TableIdentifier|QueryExpression|array $source The source table or plan node.
         * @param string|null $alias The alias of the source node.
         */
        public function __construct (TableIdentifier|QueryExpression|array $source, ?string $alias = null) {
            if (is_array($source)) {
                $this->alias = $alias ?? (string) key($source) ?? current($source);
                $this->source = current($source);
                return;
            }
            if ($source instanceof TableIdentifier) {
                $this->source = $source;
                $this->alias = $alias ?? $source->getAlias() ?? $source;
                return;
            }
            
            $this->source = $source;
            $this->alias = $alias ?? $source->getAlias() ?? "sub_q";
        }

        /**
         * Explains a source node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the source node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            
            if ($this->source instanceof QueryExpression) {
                $header = sprintf("{$indent}Source (%s as %s):\n", "Subquery", $this->alias);
                return $header . $this->source->explain($depth + 1);
            }
        
            $sourceDesc = $this->source->getName();
            
            if ($sourceDesc === $this->alias) {
                return sprintf("{$indent}Source (%s)\n", $sourceDesc);
            }
        
            return sprintf("{$indent}Source (%s as %s)\n", $sourceDesc, $this->alias);
        }

        /**
         * Gets the expressions of a source node.
         * @return array An array containing the source expression.
         */
        public function getExpressions () : array {
            return [$this->source];
        }

        /**
         * Gets the source of a source node.
         * @return TableIdentifier|QueryExpression The source table or plan node.
         */
        public function getSource () : TableIdentifier|QueryExpression {
            return $this->source;
        }

        /**
         * Sets the expressions of a source node.
         * @param array $expressions An array containing the new source expression.
         * @return static The current instance.
         * @throws InvalidArgumentException If the number of expressions is not one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("SourceNode can only have one expression.");
            }
            $this->source = $expressions[0];
            return $this;
        }

        /**
         * Creates a new source node with the given expressions.
         * @param array $expressions An array containing the new source expression.
         * @return static A new source node instance.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>