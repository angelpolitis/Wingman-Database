<?php
    /*/
	 * Project Name:    Wingman — Database — Case Builder
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 15 2026
	 * Last Modified:   Jan 15 2026
    /*/

    # Use the Database.Builders namespace.
    namespace Wingman\Database\Builders;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Expressions\CaseExpression;
    use Wingman\Database\Expressions\Predicate;
    use Wingman\Database\Interfaces\Expression;

    /**
     * A builder for constructing SQL CASE expressions.
     * @package Wingman\Database\Builders
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CaseBuilder {
        /**
         * The conditions for the CASE statement.
         * @var Expression[]
         */
        protected array $conditions = [];
        
        /**
         * The default result for the CASE statement.
         * @var Expression|null
         */
        protected ?Expression $default = null;

        /**
         * The results corresponding to the conditions.
         * @var Expression[]
         */
        protected array $results = [];

        /**
         * The subject expression for the CASE statement.
         * @var CaseExpression
         */
        protected ?Expression $subject;

        /**
         * Creates a new case builder.
         * @param mixed|null $subject The optional subject expression for the CASE statement.
         */
        public function __construct (mixed $subject = null) {
            $this->subject = $subject ? QueryBuilder::ensureExpression($subject) : null;
        }

        /**
         * Adds a WHEN condition and its corresponding result to the CASE statement.
         * @param mixed $condition The condition expression or predicate.
         * @param mixed $result The result expression for the condition.
         * @return static The case builder.
         */
        public function when (mixed $condition, mixed $result) : static {
            $condition = QueryBuilder::ensureExpression($condition);
            if (is_null($this->subject)) {
                if (!($condition instanceof Predicate)) {
                    throw new InvalidArgumentException("When no subject is provided, conditions must be predicates.");
                }
            }
            $this->results[] = QueryBuilder::ensureExpression($result);
            $this->conditions[] = QueryBuilder::ensureExpression($condition);
            return $this;
        }
    
        /**
         * Sets the default result for the CASE statement.
         * @param mixed $result The default result expression.
         * @return static The case builder.
         */
        public function else (mixed $result) : static {
            $this->default = QueryBuilder::ensureExpression($result);
            return $this;
        }
    
        /**
         * Finalises the CASE expression and returns it.
         * @param string|null $alias The optional alias for the CASE expression.
         * @return CaseExpression The constructed CASE expression.
         */
        public function end (?string $alias = null) : CaseExpression {
            $expression = new CaseExpression($this->subject, $this->conditions, $this->results, $this->default);
            if ($alias) $expression->alias($alias);
            return $expression;
        }
    }
?>