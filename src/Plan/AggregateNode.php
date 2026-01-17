<?php
    /*/
	 * Project Name:    Wingman — Database — Aggregate Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 26 2025
	 * Last Modified:   Jan 06 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents an aggregate operation in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class AggregateNode extends UnaryNode implements ExpressionCarrier {
        /**
         * The aggregate expressions of a node.
         * @var Expression[]
         */
        protected array $aggregates = [];

        /**
         * The group by columns/expressions of a node.
         * @var array<ColumnIdentifier|Expression>
         */
        protected array $groups = [];

        /**
         * Creates a new aggregate node.
         * @param PlanNode $input The preceding node.
         * @param Expression[] $aggregates Array of Expression objects (AggregateExpression or Raw).
         * @param array<ColumnIdentifier|Expression> $groups Array of columns/expressions for the GROUP BY clause.
         */
        public function __construct (PlanNode $input, array $aggregates = [], array $groups = []) {
            parent::__construct($input);
            $this->aggregates = $aggregates;
            $this->groups = $groups;
        }

        /**
         * Explains an aggregate node as a string.
         * @param int $depth The depth of the node in the plan tree for indentation.
         * @return string The explanation of the aggregate node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $aggs = [];
            foreach ($this->aggregates as $agg) {
                $sql = $agg->explain();
                if ($agg instanceof Aliasable && $alias = $agg->getAlias()) {
                    $sql .= " AS {$alias}";
                }
                $aggs[] = $sql;
            }
            $label = "Aggregate [" . implode(", ", $aggs) . "]";
            if (!empty($this->groups)) {
                $groupStrings = array_map(fn ($g) => ($g instanceof Expression) ? $g->explain() : (string) $g, $this->groups);
                $label .= " GROUP BY [" . implode(", ", $groupStrings) . "]";
            }
            return "{$indent}{$label}\n" . $this->input->explain($depth + 1);
        }

        /**
         * Gets the aggregate expressions.
         * @return Expression[] The aggregate expressions.
         */
        public function getAggregates () : array {
            return $this->aggregates;
        }

        /**
         * Gets all expressions (aggregates + group by) of a node.
         * @return Expression[] The expressions.
         */
        public function getExpressions () : array {
            $expressionGroups = array_filter($this->groups, fn ($group) => $group instanceof Expression);
            return array_merge($this->aggregates, $expressionGroups);
        }

        /**
         * Gets the group by columns/expressions.
         * @return array The group by columns/expressions.
         */
        public function getGroups () : array {
            return $this->groups;
        }

        /**
         * Sets the expressions (aggregates + group by) of a node.
         * @param Expression[] $expressions The new expressions.
         * @return static The updated node.
         */
        public function setExpressions (array $expressions) : static {
            $aggCount = count($this->aggregates);
            
            # 1. Update Aggregates (the SELECT part).
            $this->aggregates = array_slice($expressions, 0, $aggCount);
            
            # 2. Update Groups (the GROUP BY part).
            $exprIndex = $aggCount;
            foreach ($this->groups as $key => $group) {
                if ($group instanceof Expression) {
                    if (isset($expressions[$exprIndex])) {
                        $this->groups[$key] = $expressions[$exprIndex];
                        $exprIndex++;
                    }
                }
            }

            return $this;
        }

        /**
         * Creates a clone of the current node with updated expressions.
         * @param Expression[] $expressions The new expressions.
         * @return static The cloned node with updated expressions.
         */
        public function withExpressions (array $expressions) : static {
            $clone = clone $this;
            $clone->setExpressions($expressions);
            return $clone;
        }
    }
?>