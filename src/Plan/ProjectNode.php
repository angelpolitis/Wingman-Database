<?php
    /*/
	 * Project Name:    Wingman — Database — Project Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 30 2025
	 * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a project node.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ProjectNode extends UnaryNode implements ExpressionCarrier {
        /**
         * Whether a projection is distinct.
         * @var bool
         */
        protected bool $distinct = false;

        /**
         * The expressions of a project node.
         * @var Expression[]
         */
        protected array $expressions;

        /**
         * Creates a new project node.
         * @param PlanNode $input The input plan node.
         * @param Expression[] $expressions The expressions to project.
         * @param bool $distinct Whether the projection is distinct.
         */
        public function __construct (PlanNode $input, array $expressions, bool $distinct = false) {
            parent::__construct($input);
            $this->expressions = $expressions;
            $this->distinct = $distinct;
        }
        
        /**
         * Explains a project node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the project node.
         */
        public function explain (int $depth = 0) : string {
            $type = $this->distinct ? "Project (Distinct)" : "Project";
            return str_pad("", $depth * 3)
                . "{$type} [" . implode(", ", array_map(fn ($e) => $e->explain(), $this->expressions)) . "]" . PHP_EOL
                . $this->input->explain($depth + 1);
        }

        /**
         * Gets a list of all output column names (aliases) created by a projection.
         * @return string[] The list of alias names.
         */
        public function getAliasNames () : array {
            $aliases = [];
            foreach ($this->expressions as $expression) {
                if ($expression instanceof Aliasable) {
                    $aliases[] = $expression->getAlias();
                }
            }
            return $aliases;
        }

        /**
         * Gets the expressions of a project node.
         * @return Expression[] The expressions to project.
         */
        public function getExpressions () : array {
            return $this->expressions;
        }

        /**
         * Checks whether a projection is distinct.
         * @return bool Whether the projection is distinct.
         */
        public function isDistinct () : bool {
            return $this->distinct;
        }

        /**
         * Checks whether the projection is a wildcard (*).
         * @return bool Whether the projection is a wildcard.
         */
        public function isWildcard () : bool {
            return count($this->expressions) === 1
                && $this->expressions[0] instanceof ColumnIdentifier
                && $this->expressions[0]->getName() === '*';
        }
    
        /**
         * Sets whether a projection is distinct.
         * @param bool $distinct Whether the projection is distinct.
         * @return static The current project node.
         */
        public function setDistinct (bool $distinct) : static {
            $this->distinct = $distinct;
            return $this;
        }

        /**
         * Sets the expressions of a project node.
         * @param Expression[] $expressions The expressions to project.
         * @return static The current project node.
         */
        public function setExpressions (array $expressions) : static {
            $this->expressions = $expressions;
            return $this;
        }

        /**
         * Creates a new project node with the given expressions.
         * @param Expression[] $expressions The expressions to project.
         * @return static A new project node with the given expressions.
         */
        public function withExpressions (array $expressions) : static {
            $node = clone $this;
            $node->setExpressions($expressions);
            return $node;
        }
    }
?>