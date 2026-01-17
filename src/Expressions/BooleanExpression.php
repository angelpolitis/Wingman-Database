<?php
    /*/
	 * Project Name:    Wingman — Database — Boolean Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 03 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents a boolean expression composed of multiple expressions combined with AND/OR.
     * @package Wingman\Database\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class BooleanExpression extends Predicate implements ExpressionCarrier {
        /**
         * The expressions contained in a boolean expression.
         * @var Expression[]
         */
        protected array $expressions = [];

        /**
         * The internal conjunction used between expressions.
         * @var string
         */
        protected string $internalConjunction = "AND";

        /**
         * Creates a new boolean expression.
         * @param array $expressions An array of Expression objects.
         * @param string $internal The internal conjunction (AND/OR) used between expressions.
         * @param string $leading The leading conjunction for this group.
         * @param string|null $alias An optional alias for the expression.
         */
        public function __construct (array $expressions, string $internal = "AND", string $leading = "AND", ?string $alias = null) {
            $this->expressions = $expressions;
            $this->internalConjunction = $internal;
            $this->conjunction = $leading;
            $this->alias($alias);
        }

        /**
         * Explains a boolean expression in a human-readable format.
         * @param int $depth The depth of the expression for formatting purposes (not used here).
         * @return string A string representation of the boolean expression.
         */
        public function explain (int $depth = 0) : string {
            $parts = [];
            foreach ($this->expressions as $expression) {
                $parts[] = $expression instanceof Expression ? $expression->explain($depth + 1) : (string) $expression;
            }
            
            $glue = " {$this->internalConjunction} ";
            return "(" . implode($glue, $parts) . ")";
        }

        /**
         * Gets the expressions contained in a boolean expression.
         * @return Expression[] An array of Expression objects.
         */
        public function getExpressions () : array {
            return $this->expressions;
        }

        /**
         * Gets the internal conjunction for a boolean expression.
         * @return string The internal conjunction (AND/OR).
         */
        public function getInternalConjunction () : string {
            return $this->internalConjunction;
        }

        /**
         * Gets all references used in a boolean expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            $references = [];
            foreach ($this->expressions as $expression) {
                array_push($references, ...$expression->getReferences());
            }
            return array_unique($references);
        }

        /**
         * Determines whether a boolean expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            foreach ($this->getExpressions() as $expression) {
                if ($expression instanceof Expression && !$expression->isSargable()) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Sets the internal conjunction for a boolean expression.
         * @param string $conjunction The internal conjunction (AND/OR).
         * @return static The current instance for method chaining.
         */
        public function setInternalConjunction (string $conjunction) : static {
            $this->internalConjunction = $conjunction;
            return $this;
        }

        /**
         * Sets the expressions contained in a boolean expression.
         * @param array $expressions An array of Expression objects.
         * @return static The current instance for method chaining.
         */
        public function setExpressions (array $expressions) : static {
            $this->expressions = $expressions;
            return $this;
        }

        /**
         * Creates a copy of the boolean expression with new expressions.
         * @param array $expressions An array of Expression objects.
         * @return static A new instance of BooleanExpression with the specified expressions.
         */
        public function withExpressions (array $expressions) : static {
            $new = clone $this;
            return $new->setExpressions($expressions);
        }
    }
?>