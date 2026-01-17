<?php
    /*/
	 * Project Name:    Wingman — Database — Window Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 28 2025
	 * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Traits\CanHaveAlias;

    /**
     * Represents a window expression in a database query.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class WindowExpression implements Expression, ExpressionCarrier {
        use CanHaveAlias;

        /**
         * The function of a window expression.
         * @var string
         */
        protected string $function;

        /**
         * The arguments of a window expression.
         * @var array
         */
        protected array $arguments;

        /**
         * The partitioning columns of a window expression.
         * @var array
         */
        protected array $partitioningColumns;

        /**
         * The ordering columns of a window expression.
         * @var OrderExpression[]
         */
        protected array $orderingColumns;

        /**
         * Creates a new window expression.
         * @param string $function The window function (e.g., SUM, AVG).
         * @param array $arguments The arguments for the window function.
         * @param array $partitioningColumns The columns to partition by.
         * @param OrderExpression[] $orderingColumns The columns to order by.
         * @param string|null $alias An optional alias for the window expression.
         */
        public function __construct (string $function, array $arguments = [], array $partitioningColumns = [], array $orderingColumns = [], ?string $alias = null) {
            $this->function = $function;
            $this->arguments = $arguments;
            $this->partitioningColumns = $partitioningColumns;
            $this->orderingColumns = $orderingColumns;
            $this->alias($alias);
        }

        /**
         * Explains a window expression.
         * @param int $depth The depth of the explanation for formatting purposes (not used here).
         * @return string The explanation of the window expression.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
            
            $render = function ($value) {
                return ($value instanceof Expression) ? $value->explain() : (string) $value;
            };
        
            # 1. Render function call: e.g., ROW_NUMBER().
            $renderedArgs = implode(", ", array_map($render, $this->arguments));
            $label = strtoupper($this->function) . "({$renderedArgs}) OVER";
        
            # 2. Build the OVER clause parts.
            $overParts = [];
        
            if (!empty($this->partitioningColumns)) {
                $overParts[] = "PARTITION BY " . implode(', ', array_map($render, $this->partitioningColumns));
            }
        
            if (!empty($this->orderingColumns)) {
                $orders = array_map(fn (OrderExpression $o) => $o->explain(0), $this->orderingColumns);
                $overParts[] = "ORDER BY " . implode(', ', $orders);
            }
        
            # 3. Assemble final string
            $window = empty($overParts) ? "" : " (" . implode(' ', $overParts) . ")";
            $output = "{$pad}{$label}{$window}";
        
            if ($this->alias) {
                $output .= " AS {$this->alias}";
            }
        
            return $output;
        }

        /**
         * Gets the expressions of a window expression.
         * @return array An array of sub-expressions.
         */
        public function getExpressions () : array {
            $expressions = [];

            # 1. Collect expressions from the arguments.
            foreach ($this->arguments as $argument) {
                if ($argument instanceof Expression) $expressions[] = $argument;
            }

            # 2. Collect expressions from partitioning.
            foreach ($this->partitioningColumns as $part) {
                if ($part instanceof Expression) $expressions[] = $part;
            }

            # 3. Collect expressions from ordering.
            foreach ($this->orderingColumns as $order) {
                $expressions[] = $order;
            }

            return $expressions;
        }

        /**
         * Gets the function of a window expression.
         * @return string The window function.
         */
        public function getFunction () : string {
            return $this->function;
        }

        /**
         * Gets the partitioning columns of a window expression.
         * @return array The partitioning columns.
         */
        public function getPartitioningColumns () : array {
            return $this->partitioningColumns;
        }

        /**
         * Gets the ordering columns of a window expression.
         * @return array The ordering columns.
         */
        public function getOrderingColumns () : array {
            return $this->orderingColumns;
        }

        /**
         * Gets all references used in a window expression.
         * @return array An array of references.
         */
        public function getReferences () : array {
            $references = [];

            # 1. Process all child expressions via the carrier.
            foreach ($this->getExpressions() as $expression) {
                $references = array_merge($references, $expression->getReferences());
            }

            # 2. Catch raw string references (e.g., "users.id").
            $rawExtractor = function($item) use (&$references) {
                if (is_string($item) && str_contains($item, '.')) {
                    $references[] = explode('.', $item)[0];
                }
            };

            array_walk_recursive($this->arguments, $rawExtractor);
            array_walk_recursive($this->partitioningColumns, $rawExtractor);
            
            return array_unique($references);
        }

        /**
         * Determines whether a window expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the expressions of a window expression.
         * @param array $expressions An array of expressions to set.
         * @return static The window expression.
         */
        public function setExpressions (array $expressions) : static {
            foreach ($this->arguments as &$arg) {
                if ($arg instanceof Expression) $arg = array_shift($expressions);
            }
        
            foreach ($this->partitioningColumns as &$part) {
                if ($part instanceof Expression) $part = array_shift($expressions);
            }
        
            foreach ($this->orderingColumns as &$order) {
                $order = array_shift($expressions);
            }

            return $this;
        }

        /**
         * Creates a new window expression with updated expressions.
         * @param array $expressions An array of expressions to set.
         * @return static A new window expression with the specified expressions.
         */
        public function withExpressions (array $expressions) : static {
            $clone = clone $this;
            return $clone->setExpressions($expressions);
        }
    }
?>