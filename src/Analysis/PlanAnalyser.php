<?php
    /*/
     * Project Name:    Wingman — Database — Plan Analyser
     * Created by:      Angel Politis
     * Creation Date:   Jan 07 2026
     * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use SplObjectStorage;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Enums\Component;
    use Wingman\Database\Enums\ScopeDependencyType;
    use Wingman\Database\Enums\ScopeType;
    use Wingman\Database\Expressions\ExistsExpression;
    use Wingman\Database\Expressions\LiteralExpression;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Interfaces\SQLDialect;
    use Wingman\Database\Plan\BinaryNode;
    use Wingman\Database\Plan\CteNode;
    use Wingman\Database\Plan\FilterNode;
    use Wingman\Database\Plan\HavingNode;
    use Wingman\Database\Plan\InsertNode;
    use Wingman\Database\Plan\JoinNode;
    use Wingman\Database\Plan\LimitNode;
    use Wingman\Database\Plan\ProjectNode;
    use Wingman\Database\Plan\SetOperationNode;
    use Wingman\Database\Plan\SortNode;
    use Wingman\Database\Plan\SourceNode;
    use Wingman\Database\Plan\UnaryNode;

    /**
     * Performs analysis on query plans to extract useful information.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PlanAnalyser {
        /**
         * The root node of the query plan being analysed.
         * @var PlanNode
         */
        protected PlanNode $plan;

        /**
         * A registry mapping plan nodes to their analysis scopes.
         * @var SplObjectStorage<PlanNode, Scope>
         */
        protected SplObjectStorage $registry;

        /**
         * A registry mapping CTE aliases to their scopes.
         * @var array<string, Scope>
         */
        protected array $cteRegistry = [];

        /**
         * Creates a new plan analyser.
         * @param PlanNode $plan The root node of the query plan.
         */
        public function __construct (PlanNode $plan) {
            $this->plan = $plan;
            $this->registry = new SplObjectStorage();
        }

        ################################################################
        ######################## Scope Building ########################
        ################################################################

        /**
         * Recursively builds scopes for executable parts of a plan.
         * @param PlanNode $root The root node of the executable plan.
         */
        protected function buildExecutableScopes (PlanNode $root) : void {
            # 1. Recursive builder function.
            $build = function (PlanNode $node, ?Scope $parent = null, ScopeType $scopeType = ScopeType::Root) use (&$build) {
                if ($node instanceof SetOperationNode && $parent !== null) {
                    $left = $node->getLeft();
                    $right = $node->getRight();

                    $build($left, $parent, ScopeType::SetBranch);
                    $build($right, $parent, ScopeType::SetBranch);

                    $leftScope = $this->registry[$left] ?? null;
                    $rightScope = $this->registry[$right] ?? null;

                    if ($leftScope) {
                        $parent->addEdge($leftScope, ScopeDependencyType::DerivesFrom);
                    }

                    if ($rightScope) {
                        $parent->addEdge($rightScope, ScopeDependencyType::DerivesFrom);
                    }

                    return;
                }

                # 2. Avoid duplicate scope creation.
                if (isset($this->registry[$node])) return;

                $scope = new Scope($node);
                $scope->setType($scopeType);

                # 3. Establish parent-child relationship.
                if ($parent) {
                    $parent->addChild($scope);
                    $parent->addEdge($scope, ScopeDependencyType::Contains);
                }

                $this->registry[$node] = $scope;

                # 4. Discover nested scopes within this scope.
                $this->discoverNestedScopes($node, $scope, $build);
            };
            $build($root);
        }

        /**
         * Discovers all query scopes and establishes parent-child relationships.
         * @param PlanNode $root The entry point of the entire plan.
         */
        protected function buildScopeHierarchy (PlanNode $root) : void {
            # 1. Discover CTE scopes FIRST (plan-level).
            if ($root instanceof CteNode) {
                $this->discoverCteScopes($root);
                $executionRoot = $root->getInput();
            }
            else $executionRoot = $root;

            # 2. Then, build the executable scopes.
            $this->buildExecutableScopes($executionRoot);
        }
        
        /**
         * Detects whether a scope is correlated to any outer scopes.
         * @param Scope $scope The scope to inspect for correlation.
         */
        protected function detectCorrelation (Scope $scope) : void {
            if (!$scope->hasParent()) return;

            $localAliases = $scope->getLocalAliases();
            $references = $scope->getReferenceGroups();
            foreach ($references as $tableAlias => $columns) {
                # If the alias is local, skip it.
                if (in_array($tableAlias, $localAliases, true)) continue;

                if (!$scope->resolvesAliasInAncestors($tableAlias)) continue;

                # Logical correlation always applies.
                $scope->setLogicalCorrelationStatus(true);
                $scope->addExternalReferences($tableAlias, $columns);

                # Physical correlation applies only for row-dependent scopes.
                if ($scope->getType() === ScopeType::Exists || $scope->getType() === ScopeType::Subquery) {
                    $scope->setPhysicalCorrelationStatus(true);
                }

                $scope->addEdge($scope->getParent(), ScopeDependencyType::CorrelatedTo);
            }
        }

        /**
         * Discovers CTE scopes from a CTE node and registers them.
         * @param CteNode $cteNode The CTE node to analyse.
         */
        protected function discoverCteScopes (CteNode $cteNode) : void {
            foreach ($cteNode->getExpressions() as $cte) {
                $alias = $cte->getAlias();
                foreach ($cte->getExpressions() as $sub) {
                    $plan = $sub->getPlan();

                    if (isset($this->registry[$plan])) continue;

                    $scope = new Scope($plan);
                    $scope->setType(ScopeType::Cte);

                    $this->registry[$plan] = $scope;
                    $this->cteRegistry[$alias] = $scope;
                }
            }
        }

        /**
         * Traverses a single scope to find boundaries (Unions, Subqueries, CTEs).
         * @param PlanNode $node The current plan node being inspected.
         * @param Scope $current The current scope being built.
         * @param callable $callback A callback to register discovered scopes.
         */
        protected function discoverNestedScopes (PlanNode $node, Scope $current, callable $callback) : void {
            # 1. Handle CTE nodes.
            if ($node instanceof CteNode) {
                foreach ($node->getExpressions() as $cte) {
                    foreach ($cte->getExpressions() as $sub) {
                        $callback($sub->getPlan(), $current, ScopeType::Cte);
                        $cteScope = $this->registry[$sub->getPlan()];
                        $current->addEdge($cteScope, ScopeDependencyType::Defines);
                    }
                }
                return;
            }

            # 2. Handle source nodes that wrap subqueries.
            if ($node instanceof SourceNode) {
                $source = $node->getSource();
                if ($source instanceof QueryExpression) {
                    $callback($source->getPlan(), $current);
                }
                else {
                    $tableName = $source->getName();
                    if (isset($this->cteRegistry[$tableName])) {
                        $cteScope = $this->cteRegistry[$tableName];
                        $current->addEdge($cteScope, ScopeDependencyType::DerivesFrom);
                    }
                }
                return;
            }

            # 3. Handle filter and having nodes that may contain subqueries.
            if ($node instanceof FilterNode || $node instanceof HavingNode) {
                $this->findScopesInExpression($node->getPredicate(), $current, $callback);
            }

            # 4. Handle join nodes that may contain subqueries in their conditions.
            if ($node instanceof JoinNode) {
                $this->discoverNestedScopes($node->getLeft(), $current, $callback);
                $this->discoverNestedScopes($node->getRight(), $current, $callback);
                return;
            }

            # 5. Keep walking down unary nodes to find nested scopes.
            if ($node instanceof UnaryNode) {
                $this->discoverNestedScopes($node->getInput(), $current, $callback);
            }
        }

        /**
         * Searches an expression for nested scopes (Exists, Subqueries).
         * @param mixed $expression The expression to search.
         * @param Scope $current The current scope context.
         * @param callable $callback A callback to register discovered scopes.
         */
        protected function findScopesInExpression (mixed $expression, Scope $current, callable $callback) : void {
            if ($expression instanceof ExistsExpression) {
                $plan = $expression->getValue()->getPlan();
                $callback($plan, $current);
                $this->registry[$plan]->setType(ScopeType::Exists);
                $this->registry[$plan]->setExistsScopeStatus(true);
            }
            elseif ($expression instanceof QueryExpression) {
                $callback($expression->getPlan(), $current);
            }
            elseif ($expression instanceof ExpressionCarrier) {
                foreach ($expression->getExpressions() as $sub) {
                    $this->findScopesInExpression($sub, $current, $callback);
                }
            }
        }

        ################################################################
        ###################### Demand Propagation ######################
        ################################################################

        /**
         * Finds the first scope in a top-down traversal of a plan.
         * @param PlanNode $node The plan node to start the search from.
         * @return Scope|null The found scope or `null` if none exists.
         */
        protected function findLogicalRoot (PlanNode $node) : ?Scope {
            if (isset($this->registry[$node])) {
                return $this->registry[$node];
            }
            
            if ($node instanceof UnaryNode) {
                return $this->findLogicalRoot($node->getInput());
            }
            if ($node instanceof BinaryNode) {
                return $this->findLogicalRoot($node->getLeft());
            }

            return null;
        }
        
        /**
         * Propagates column demand through the scope graph.
         * @param Scope $scope The current scope to propagate demand from.
         * @param array<string, true> $visited A registry of visited scopes to avoid cycles.
         */
        protected function propagateDemand (Scope $scope, array &$visited = []) : void {
            $oid = spl_object_hash($scope);
            if (isset($visited[$oid])) return;
            $visited[$oid] = true;
    
            foreach ($scope->getEdges() as $edge) {
                $target = $edge->getTarget();
                $type = $edge->getType();
    
                # If it's a correlation, we search up the tree starting from the target.
                # If it's a standard dependency, we only check the immediate target.
                $recursive = ($type === ScopeDependencyType::CorrelatedTo);

                $this->resolveDemand($scope, $target, $recursive);
    
                $this->propagateDemand($target, $visited);
            }
        }
    
        /**
         * Resolves column demand from a consumer scope to a provider scope.
         * @param Scope $consumer The scope requesting columns.
         * @param Scope $provider The scope providing columns.
         * @param bool $recursive Whether to search parent scopes if no local alias matches.
         */
        protected function resolveDemand (Scope $consumer, Scope $provider, bool $recursive) : void {
            $aliases = $provider->getLocalAliases();
            $foundAny = false;
        
            # 1. Match explicit column identifiers.
            foreach ($consumer->getReferenceGroups() as $alias => $identifiers) {
                if (!in_array($alias, $aliases, true)) continue;
                $foundAny = true; 
                foreach ($identifiers as $colName => $id) {
                    $provider->demandColumn($colName);
                }
            }
        
            # 2. Match untrusted references.
            foreach ($consumer->getUntrustedReferences() as $fullName => $true) {
                $parts = explode('.', $fullName);
                $colName = array_pop($parts);
                $tableName = array_pop($parts);
        
                if (!$tableName || !in_array($tableName, $aliases, true)) continue;

                $foundAny = true;
                $provider->demandColumn($colName);
            }
        
            # 3. Recursive Step: Only continue if we haven't found the alias yet.
            if (!$recursive || $foundAny || !$provider->hasParent()) return;
            $this->resolveDemand($consumer, $provider->getParent(), true);
        }

        #################################################################
        ###################### Bindings Extraction ######################
        #################################################################
        
        /**
         * Recursively collects lexical bindings from a plan node into binding groups.
         * @param ?PlanNode $node The current plan node being inspected.
         * @param BindingGroup $currentGroup The current binding group to populate.
         * @param SplObjectStorage $visited A registry of visited nodes to avoid cycles.
         * @param Component $context The current context component being processed.
         */
        protected function collectLexicalBindings (?PlanNode $node, BindingGroup $currentGroup, SplObjectStorage $visited, Component $context = Component::Where) : void {
            # Exit early if node is null or already visited.
            if ($node === null || isset($visited[$node])) return;
            $visited[$node] = true;
            
            # 1. Handle sub-queries by creating new binding groups.
            if ($node instanceof SourceNode) {
                $source = $node->getSource();
                if ($source instanceof QueryExpression) {
                    $subGroup = new BindingGroup();
                    $this->collectLexicalBindings($source->getPlan(), $subGroup, $visited);
                    $currentGroup->add(Component::Sources, $subGroup);
                    return; 
                }
            }
        
            # 2. Handle CTEs by creating new binding groups for each one as well as the main query.
            if ($node instanceof CteNode) {
                foreach ($node->getExpressions() as $cteExpr) {
                    foreach ($cteExpr->getExpressions() as $sub) {
                        $subGroup = new BindingGroup();
                        $this->collectLexicalBindings($sub->getPlan(), $subGroup, $visited);
                        $currentGroup->add(Component::Cte, $subGroup);
                    }
                }
                $this->collectLexicalBindings($node->getInput(), $currentGroup, $visited);
                return;
            }
        
            # 3. Handle set operations by creating separate binding groups for each branch.
            if ($node instanceof SetOperationNode) {
                $leftGroup = new BindingGroup();
                $rightGroup = new BindingGroup();

                $this->collectLexicalBindings($node->getLeft(), $leftGroup, $visited);
                $this->collectLexicalBindings($node->getRight(), $rightGroup, $visited);
                
                $currentGroup->add(Component::SetOperation, $leftGroup);
                $currentGroup->add(Component::SetOperation, $rightGroup);

                return;
            }

            # 4. Handle insert nodes with sub-query sources.
            if ($node instanceof InsertNode && $node->getSource() instanceof PlanNode) {
                $this->collectLexicalBindings($node->getSource(), $currentGroup, $visited);
                return;
            }
        
            # 5. Map all contextual nodes to their respective buckets within the current group.
            $this->mapNodeToBuckets($node, $currentGroup, $visited, $context);
        
            # 6. Recurse into child nodes.
            if ($node instanceof UnaryNode) {
                $this->collectLexicalBindings($node->getInput(), $currentGroup, $visited);
            }
            elseif ($node instanceof BinaryNode) {
                $this->collectLexicalBindings($node->getLeft(), $currentGroup, $visited, Component::Joins);
                $this->collectLexicalBindings($node->getRight(), $currentGroup, $visited, Component::Joins);
            }
        }

        /**
         * Recursively extracts literal values from an expression.
         * @param mixed $expression The expression to extract literals from.
         * @param SplObjectStorage $visited A registry of visited expressions to avoid cycles.
         * @return (Binding|BindingGroup)[] An array of extracted bindings and binding groups.
         */
        protected function extractLiterals (mixed $expression, SplObjectStorage $visited) : array {
            if ($expression === null) return [];
            $results = [];
            
            if ($expression instanceof LiteralExpression) {
                $results[] = new Binding($expression->getValue());
            }
            elseif ($expression instanceof QueryExpression) {
                $subGroup = new BindingGroup();
                $this->collectLexicalBindings($expression->getPlan(), $subGroup, $visited);
                $results[] = $subGroup;
            }
            elseif ($expression instanceof ExpressionCarrier) {
                foreach ($expression->getExpressions() as $sub) {
                    $results = array_merge($results, $this->extractLiterals($sub, $visited));
                }
            }
            elseif (is_array($expression)) {
                foreach ($expression as $item) {
                    $results = array_merge($results, $this->extractLiterals($item, $visited));
                }
            }
        
            return $results;
        }
        
        /**
         * Maps a plan node to its appropriate binding buckets within a binding group.
         * @param PlanNode $node The plan node to map.
         * @param BindingGroup $group The binding group to populate.
         * @param SplObjectStorage $visited A registry of visited nodes to avoid cycles.
         * @param Component $context The current context component being processed.
         */
        protected function mapNodeToBuckets (PlanNode $node, BindingGroup $group, SplObjectStorage $visited, Component $context) : void {
            if ($node instanceof ProjectNode) {
                foreach ($node->getExpressions() as $expression) {
                    foreach ($this->extractLiterals($expression, $visited) as $item) {
                        $group->add(Component::Projections, $item);
                    }
                }
            }
            elseif ($node instanceof FilterNode) {
                $targetBucket = ($context === Component::Joins) ? Component::Joins : Component::Where;
                
                foreach ($this->extractLiterals($node->getPredicate(), $visited) as $item) {
                    $group->add($targetBucket, $item);
                }
            }
            elseif ($node instanceof HavingNode) {
                foreach ($this->extractLiterals($node->getPredicate(), $visited) as $item) {
                    $group->add(Component::Having, $item);
                }
            }
            elseif ($node instanceof JoinNode) {
                foreach ($this->extractLiterals($node->getExpression(), $visited) as $item) {
                    $group->add(Component::Joins, $item);
                }
            }
            elseif ($node instanceof LimitNode) {
                foreach ($this->extractLiterals($node->getLimit(), $visited) as $item) {
                    $group->add(Component::Limit, $item);
                }
                foreach ($this->extractLiterals($node->getOffset(), $visited) as $item) {
                    $group->add(Component::Offset, $item);
                }
            }
            elseif ($node instanceof SortNode) {
                foreach ($node->getExpressions() as $sort) {
                    foreach ($this->extractLiterals($sort->getTarget(), $visited) as $item) {
                        $group->add(Component::OrderBy, $item);
                    }
                }
            }
        }


        ################################################################
        ######################## Public Methods ########################
        ################################################################

        /**
         * Analyses a query plan to extract scope information.
         * @return static The analyser instance for method chaining.
         */
        public function analyse () : static {
            $this->buildScopeHierarchy($this->plan);

            # Pass 1: Initial Analysis.
            foreach ($this->registry as $node) {
                $this->registry[$node]->analyse();
            }
            
            # Pass 2: Redundancy & Correlation Detection.
            foreach ($this->registry as $node) {
                $this->detectCorrelation($this->registry[$node]);
            }
        
            # Pass 3: Demand Propagation.
            $rootScope = $this->findLogicalRoot($this->plan);
            if ($rootScope) {
                $this->propagateDemand($rootScope);
            }
        
            # Pass 4: Redundancy calculation based on demand.
            foreach ($this->registry as $node) {
                $this->registry[$node]->calculateRedundantColumns();
            }
        
            return $this;
        }

        /**
         * Gets all bindings in the lexical order defined by a plan analyser's SQL dialect.
         * @param SQLDialect|string $dialect The SQL dialect or its class name.
         * @return Binding[] An array of bindings in the order defined by the SQL dialect.
         */
        public function getBindings (SQLDialect|string $dialect) : array {
            $dialect = ($dialect instanceof SQLDialect) ? $dialect : new $dialect();
            $group = $this->getBindingTree();
            return $group->flatten($dialect->getOrderForNode($this->plan), $dialect->getSelectOrder());
        }
        
        /**
         * Generates a hierarchical binding group representing lexical bindings.
         * @return BindingGroup The root binding group containing all lexical bindings.
         */
        public function getBindingTree () : BindingGroup {
            $rootGroup = new BindingGroup();
            $visited = new SplObjectStorage();
            $this->collectLexicalBindings($this->plan, $rootGroup, $visited);
            return $rootGroup;
        }

        /**
         * Retrieves all scopes discovered in the plan of an analyser.
         * @return Scope[] An array of all discovered scopes.
         */
        public function getScopes () : array {
            $scopes = [];
            foreach ($this->registry as $node) {
                $scopes[] = $this->registry[$node];
            }
            return $scopes;
        }
    }
?>