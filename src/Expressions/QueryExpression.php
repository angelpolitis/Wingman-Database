<?php
    /*/
     * Project Name:    Wingman — Database — Query Expression
     * Created by:      Angel Politis
     * Creation Date:   Dec 28 2025
     * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Builders\QueryBuilder;
    use Wingman\Database\Objects\QueryState;

    /**
     * Represents a query expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class QueryExpression extends Predicate {
        /**
         * The plan of a query.
         * @var PlanNode|null
         */
        protected ?PlanNode $plan = null;

        /**
         * The state of a query.
         * @var QueryState|null
         */
        protected ?QueryState $state = null;

        /**
         * The value of a query.
         * @var QueryBuilder|null
         */
        protected ?QueryBuilder $value;

        /**
         * Creates a new query expression.
         * @param QueryBuilder|PlanNode $value The query builder or plan node.
         * @param string|QueryState|null $aliasOrState The alias or query state.
         */
        public function __construct (QueryBuilder|PlanNode $valueOrPlan, string|QueryState|null $aliasOrState = null) {
            if ($valueOrPlan instanceof PlanNode) {
                $this->plan = $valueOrPlan;
            }
            else $this->value = $valueOrPlan;

            if ($aliasOrState instanceof QueryState) {
                $this->state = $aliasOrState;
            }
            else $this->alias($aliasOrState);
        }

        /**
         * Explains a query expression.
         * @param int $depth The depth of the explanation for formatting.
         * @return string The explained query expression.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
            $plan = $this->plan ?? $this->value->getPlan();

            $string = "(" . PHP_EOL . $plan->explain($depth + 1) . "$pad)";
            if ($this->alias) {
                $string .= " AS {$this->alias}";
            }
            return $pad . $string;
        }

        /**
         * Gets the references of a query.
         * @return array An array of references the query depends on.
         */
        public function getReferences () : array {
            return $this->getState()->getReferences();
        }

        /**
         * Gets the plan of a query.
         * @return PlanNode|null The plan node of the query, or `null` if not set.
         */
        public function getPlan () : ?PlanNode {
            return $this->plan;
        }

        /**
         * Gets the state of a query.
         * @return QueryState The query state.
         */
        public function getState () : QueryState {
            return $this->state ?? $this->value->getState();
        }
        
        /**
         * Gets the value of a query.
         * @return QueryBuilder|null The query builder.
         */
        public function getValue () : ?QueryBuilder {
            return $this->value;
        }

        /**
         * Indicates if the query has been compiled into a plan.
         * @return bool Whether the query has a plan assigned.
         */
        public function isCompiled () : bool {
            return $this->plan !== null;
        }

        /**
         * Determines whether a query expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the plan of a query.
         * @param PlanNode $plan The plan node to assign to the query.
         * @return static The current instance for method chaining.
         */
        public function setPlan (PlanNode $plan) : static {
            $this->plan = $plan;
            return $this;
        }

        /**
         * Creates a new expression with the given plan.
         * @param PlanNode $plan The plan node to assign to the new instance.
         * @return static A new instance with the specified plan.
         */
        public function withPlan (PlanNode $plan) : static {
            $new = clone $this;
            $new->plan = $plan;
            return $new;
        }
    }
?>