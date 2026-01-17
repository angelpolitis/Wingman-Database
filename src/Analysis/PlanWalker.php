<?php
    /*/
     * Project Name:    Wingman — Database — Plan Walker
     * Created by:      Angel Politis
     * Creation Date:   Jan 07 2026
     * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use ReflectionClass;
    use SplObjectStorage;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Enums\Component;
    use Wingman\Database\Expressions\JoinExpression;
    use Wingman\Database\Expressions\Predicate;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Plan\AggregateNode;
    use Wingman\Database\Plan\BinaryNode;
    use Wingman\Database\Plan\CteNode;
    use Wingman\Database\Plan\FilterNode;
    use Wingman\Database\Plan\HavingNode;
    use Wingman\Database\Plan\InsertNode;
    use Wingman\Database\Plan\JoinNode;
    use Wingman\Database\Plan\LimitNode;
    use Wingman\Database\Plan\LockNode;
    use Wingman\Database\Plan\NullNode;
    use Wingman\Database\Plan\ProjectNode;
    use Wingman\Database\Plan\SortNode;
    use Wingman\Database\Plan\SourceNode;
    use Wingman\Database\Plan\UnaryNode;

    /**
     * Walks a query plan to populate SQL buckets for query compilation.
     * @package Wingman\Database\Compilers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PlanWalker {
        /**
         * An associative array of SQL buckets to build the final query.
         * @var array
         */
        protected array $buckets;

        /**
         * Indicates whether a distinct projection has been encountered.
         * @var bool
         */
        protected bool $distinct;

        /**
         * A mapping of filter predicates to the join expressions they belong.
         * @var SplObjectStorage<JoinExpression, Predicate[]>
         */
        protected SplObjectStorage $joinFilterMap;

        /**
         * Creates a new plan walker.
         */
        public function __construct () {
            $this->reset();
        }

        /**
         * Adds a value to a specific bucket.
         * @param Component $component The component representing the bucket.
         * @param mixed $value The value to add to the bucket.
         * @param string|null $key An optional key to associate with the value.
         * @return static The walker.
         */
        protected function addToBucket (Component $component, mixed $value, ?string $key = null) : static {
            if (is_null($key)) {
                $this->buckets[$component->name][] = $value;
                return $this;
            }
            $this->buckets[$component->name][$key] = $value;
            return $this;
        }

        /**
         * Indicates whether a value exists in a specific bucket.
         * @param Component $component The component representing the bucket.
         * @param mixed $value The value to check for existence.
         * @return bool Whether the value exists in the bucket.
         */
        protected function existsInBucket (Component $component, mixed $value) : bool {
            return in_array($value, $this->buckets[$component->name], true);
        }

        /**
         * Resets a walker's buckets to their initial empty state.
         * @return static The walker.
         */
        protected function reset () : static {
            $this->buckets = [];
            foreach (Component::cases() as $case) {
                $this->buckets[$case->name] = [];
            }
            $this->distinct = false;
            $this->joinFilterMap = new SplObjectStorage();
            return $this;
        }

        /**
         * Visits an Aggregate node and populates SELECT and GROUP BY buckets.
         * @param AggregateNode $node The Aggregate node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitAggregateNode (AggregateNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            foreach ($node->getAggregates() as $expression) {
                if ($this->existsInBucket(Component::Projections, $expression)) continue;
                $this->addToBucket(Component::Projections, $expression);
            }
            foreach ($node->getGroups() as $group) {
                $this->addToBucket(Component::GroupBy, $group);
            }
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits a CTE node and populates the CTE bucket.
         * @param CteNode $node The CTE node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitCteNode (CteNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            foreach ($node->getExpressions() as $expression) {
                $name = $expression->getAlias() ?: $expression->getName();
                $this->addToBucket(Component::Cte, $expression, $name);
            }
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits a Filter node and populates the WHERE bucket.
         * @param FilterNode $node The Filter node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitFilterNode (FilterNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            if ($join) {
                $this->joinFilterMap[$join] = array_merge($this->joinFilterMap[$join] ?? [], [$node->getPredicate()]);
            }
            else {
                $this->addToBucket(Component::Where, $node->getPredicate());
            }
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits a Having node and populates the HAVING bucket.
         * @param HavingNode $node The Having node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitHavingNode (HavingNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $this->addToBucket(Component::Having, $node->getPredicate());
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits an Insert node and walks its input and source.
         * @param InsertNode $node The Insert node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitInsertNode (InsertNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $this->walk($node->getInput(), $join, $omitSources);
            $source = $node->getSource();
            if ($source instanceof PlanNode) {
                $this->walk($source, $join, $omitSources);
            }
        }

        /**
         * Visits a Join node and populates the JOIN bucket.
         * @param JoinNode $node The Join node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitJoinNode (JoinNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $expression = $node->getExpression();
            $this->walk($node->getLeft(), $expression, $omitSources);
            $this->addToBucket(Component::Joins, $expression);
            $this->walk($node->getRight(), $expression, true);
        }

        /**
         * Visits a Limit node and populates the LIMIT bucket.
         * @param LimitNode $node The Limit node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitLimitNode (LimitNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $this->addToBucket(Component::Limit, $node->getLimit());
            $this->addToBucket(Component::Limit, $node->getOffset());
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits a Lock node and continues walking its input without adding to buckets.
         * @param LockNode $node The Lock node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitLockNode (LockNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $this->addToBucket(Component::Lock, $node->getType());
            $this->addToBucket(Component::Lock, $node->getWaitTimeout());
            $this->addToBucket(Component::Lock, $node->skipsLocked());
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits a Null node and populates the FROM bucket with NULL.
         * @param NullNode $node The Null node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitNullNode (NullNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $this->addToBucket(Component::Sources, null);
        }
        
        /**
         * Visits a Project node and populates the SELECT bucket.
         * @param ProjectNode $node The Project node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitProjectNode (ProjectNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $this->distinct = $node->isDistinct();
            foreach ($node->getExpressions() as $expression) {
                $this->addToBucket(Component::Projections, $expression);
            }
            $this->walk($node->getInput(), $join, $omitSources);
        }

        /**
         * Visits a Sort node and populates the ORDER BY bucket.
         * @param SortNode $node The Sort node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitSortNode (SortNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            foreach ($node->getExpressions() as $expression) {
                $this->addToBucket(Component::OrderBy, $expression);
            }
            $this->walk($node->getInput(), $join, $omitSources);
        }
        
        /**
         * Visits a Source node and populates the FROM bucket.
         * @param SourceNode $node The Table node to visit.
         * @param JoinExpression|null $join The join expression context (if any).
         * @param bool $omitSources Whether to omit source nodes during the walk.
         */
        protected function visitSourceNode (SourceNode $node, ?JoinExpression $join = null, bool $omitSources = false) : void {
            $source = $node->getSource();
            $this->addToBucket(Component::Sources, $source);
        }

        /**
         * Gets a specific filled bucket after walking the plan.
         * @param Component $component The component representing the bucket.
         * @return mixed The filled bucket.
         */
        public function getBucket (Component $component) : mixed {
            return $this->buckets[$component->name];
        }

        /**
         * Gets the filled buckets after walking the plan.
         * @return array The filled buckets.
         */
        public function getBuckets () : array {
            return $this->buckets;
        }

        /**
         * Gets the mapping of join expressions to their filter predicates.
         * @return SplObjectStorage<JoinExpression, Predicate[]> The join map.
         */
        public function getJoinFilterMap () : SplObjectStorage {
            return $this->joinFilterMap;
        }

        /**
         * Indicates whether a distinct projection was encountered.
         * @return bool Whether distinct projection is set.
         */
        public function isDistinct () : bool {
            return $this->distinct;
        }

        /**
         * Indicates whether a specific bucket is empty.
         * @param Component $component The component representing the bucket.
         * @return bool Whether the bucket is empty.
         */
        public function isEmpty (Component $component) : bool {
            return empty($this->buckets[$component->name]);
        }
        
        /**
         * Walks a plan node and populates the SQL buckets.
         * @param PlanNode $node The plan node to walk.
         * @param JoinExpression $filterContext The context for filter nodes (default is WHERE).
         */
        public function walk (PlanNode $node, ?JoinExpression $filterContext = null, bool $omitSources = false) : void {
            $method = "visit" . (new ReflectionClass($node))->getShortName();
            if ($omitSources && $node instanceof SourceNode) return;
        
            # Call the appropriate visit method based on the node type.
            if (method_exists($this, $method)) $this->$method($node, $filterContext, $omitSources);

            # Recurse into child nodes as needed.
            elseif ($node instanceof UnaryNode) {
                $this->walk($node->getInput(), $filterContext, $omitSources);
            }
            elseif ($node instanceof BinaryNode) {
                $this->addToBucket(Component::Sources, new QueryExpression($node));
            }
        }
    }
?>