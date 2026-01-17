<?php
    /*/
	 * Project Name:    Wingman — Database — Case Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 15 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents a CASE expression.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CaseExpression implements Expression, Aliasable, ExpressionCarrier {
        use CanHaveAlias;

        /**
         * The conditions for the case.
         * @var Expression[]
         */
        protected array $conditions;

        /**
         * The default result for the case.
         * @var Expression
         */
        protected Expression $default;

        /**
         * The results corresponding to the conditions.
         * @var Expression[]
         */
        protected array $results;

        /**
         * The column or expression a case function is applied to.
         * @var Expression
         */
        protected Expression $subject;

        /**
         * Creates a new case expression.
         * @param Expression|null $subject The optional subject expression for the case statement.
         * @param Expression[] $conditions The conditions for the case.
         * @param Expression[] $results The results corresponding to the conditions.
         * @param Expression $default The default result for the case.
         * @param string|null $alias The optional alias for the case expression.
         */
        public function __construct (?Expression $subject = null, array $conditions, array $results, Expression $default, ?string $alias = null) {
            $this->subject = $subject;
            $this->conditions = $conditions;
            $this->results = $results;
            $this->default = $default;
            $this->alias($alias);
        }

        /**
         * Explains a case expression as a string.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The explanation of the case expression.
         */
        public function explain (int $depth = 0) : string {
            $aliasPart = $this->getAlias() ? " as " . $this->getAlias() : "";
            $subjectPart = $this->subject ? $this->subject->explain() : "no subject";
            return "CASE ({$subjectPart}{$aliasPart})";
        }

        /**
         * Gets the conditions of a case.
         * @return Expression[] The conditions.
         */
        public function getConditions () : array {
            return $this->conditions;
        }
        
        /**
         * Gets the default result of a case.
         * @return Expression The default result.
         */
        public function getDefault () : Expression {
            return $this->default;
        }

        /**
         * Gets the expressions of a case.
         * @return Expression[] The sub-expressions.
         */
        public function getExpressions () : array {
            $branches = [];
            foreach ($this->conditions as $index => $condition) {
                $branches[] = $condition;
                $branches[] = $this->results[$index];
            }
            return [$this->subject, ...$branches, $this->default];
        }

        /**
         * Gets the references used in a case expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            $references = [];
    
            if ($this->subject instanceof Expression) {
                $references = array_merge($references, $this->subject->getReferences());
            }

            foreach ($this->getExpressions() as $expression) {
                if ($expression instanceof Expression) {
                    $references = array_merge($references, $expression->getReferences());
                }
            }

            return array_unique($references);
        }

        /**
         * Gets the results of a case.
         * @return Expression[] The results.
         */
        public function getResults () : array {
            return $this->results;
        }
        
        /**
         * Gets the column or expression of a case.
         * @return Expression The column or expression.
         */
        public function getSubject () : Expression {
            return $this->subject;
        }

        /**
         * Determines whether a case expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the sub-expressions of a case.
         * @param Expression[] $expressions The sub-expressions to set.
         * @return static The case.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function setExpressions (array $expressions) : static {
            if (count($expressions) !== 1) {
                throw new InvalidArgumentException("CaseExpression expects exactly one sub-expression.");
            }
            $this->subject = $expressions[0];
            return $this;
        }

        /**
         * Creates a copy of the case with new sub-expressions.
         * @param Expression[] $expressions The new sub-expressions.
         * @return static The new case.
         * @throws InvalidArgumentException If the number of expressions is not exactly one.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>