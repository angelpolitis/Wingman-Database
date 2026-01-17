<?php
    /*/
     * Project Name:    Wingman — Database — Scope
     * Created by:      Angel Politis
     * Creation Date:   Jan 07 2026
     * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use SplObjectStorage;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Enums\ScopeDependencyType;
    use Wingman\Database\Enums\ScopeType;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\LiteralExpression;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Expressions\RawExpression;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Plan\AggregateNode;
    use Wingman\Database\Plan\BinaryNode;
    use Wingman\Database\Plan\FilterNode;
    use Wingman\Database\Plan\HavingNode;
    use Wingman\Database\Plan\JoinNode;
    use Wingman\Database\Plan\ProjectNode;
    use Wingman\Database\Plan\SourceNode;
    use Wingman\Database\Plan\UnaryNode;

    /**
     * Immutable analysis results regarding scopes in a query plan.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Scope {
        /**
         * The child scopes of a scope.
         * @var Scope[]
         */
        protected array $children = [];

        /**
         * The edges of a scope to other scopes.
         * @var SplObjectStorage<static, ScopeEdge>
         */
        protected SplObjectStorage $edges;

        /**
         * The aliases defined locally within a scope.
         * @var string[]
         */
        protected array $localAliases = [];

        /**
         * The parent index of nodes within a scope.
         * @var SplObjectStorage<PlanNode, PlanNode>
         */
        protected SplObjectStorage $nodeParentIndex;

        /**
         * The parent scope of a scope.
         * @var Scope|null
         */
        protected ?Scope $parent = null;

        /**
         * The type of a scope.
         * @var ScopeType
         */
        protected ScopeType $type = ScopeType::Root;

        /**
         * The root node of a scope.
         * @var PlanNode
         */
        protected PlanNode $root;

        /**
         * The literals used within a scope.
         * @var mixed
         */
        protected array $literals = [];

        /**
         * The issues found in a scope.
         * @var array
         */
        protected array $issues = [];

        /**
         * Indicates whether a scope has been analysed.
         * @var bool
         */
        protected bool $analysed = false;

        /**
         * Indicates whether a scope is logically correlated.
         * @var bool
         */
        protected bool $logicallyCorrelated = false;

        /**
         * Indicates whether a scope is physically correlated.
         * @var bool
         */
        protected bool $physicallyCorrelated = false;

        /**
         * Indicates whether a scope is in an EXISTS context.
         * @var bool
         */
        protected bool $inExistsScope = false;

        /**
         * Indicates whether a scope is part of a set operation operation.
         * @var bool
         */
        protected bool $setOperationBranch = false;

        /**
         * Indicates whether a scope is part of a Common Table Expression (CTE).
         * @var bool
         */
        protected bool $cte = false;

        /**
         * The external references used within a scope.
         * @var array
         */
        protected array $externalReferences = []; // Table/Alias names from outer scopes used here

        /**
         * The projected columns defined in the scope.
         * @var array
         */
        protected array $projectedColumns = [];

        /**
         * The redundant columns identified in a scope.
         * @var array
         */
        protected array $redundantColumns = [];

        /**
         * The columns required by the parent scope.
         * @var array
         */
        protected array $requiredByParent = [];

        /**
         * The external references used within a scope.
         * @var array
         */
        protected array $untrustedReferences = [];

        /**
         * The column references used within a scope.
         * @var ColumnIdentifier[]
         */
        protected array $references = [];

        /**
         * The column references grouped by table used within a scope.
         * @var array<string, ColumnIdentifier[]>
         */
        protected array $referenceGroups = [];

        /**
         * The projection nodes within a scope.
         * @var ProjectNode[]
         */
        protected array $projectNodes = [];

        /**
         * Creates a new scope for a given plan node.
         * @param PlanNode $node The root plan node of the scope.
         */
        public function __construct (PlanNode $node) {
            $this->root = $node;
            $this->edges = new SplObjectStorage();
            $this->nodeParentIndex = new SplObjectStorage();
            $this->indexParents($node);
        }

        #####################################################################################
        ################################# PROTECTED METHODS #################################
        #####################################################################################
        
        /**
         * Collects literal values from the plan tree starting from a given node.
         * @param PlanNode|null $node The starting plan node. Defaults to the root node.
         * @param SplObjectStorage|null $visited A storage to track visited nodes.
         * @return array An array of literal values found in the plan tree.
         */
        protected function collectLiterals (?PlanNode $node = null, ?SplObjectStorage $visited = null) : array {
            $node = $node ?? $this->root;
            $visited = $visited ?? new SplObjectStorage();
            
            if (isset($visited[$node])) return [];
            $visited[$node] = true;

            $literals = [];

            # 1. Extract literals from the current node's internal expressions.
            if ($node instanceof FilterNode || $node instanceof HavingNode) {
                $literals = array_merge($literals, $this->extractLiteralsFromExpression($node->getPredicate()));
            } 
            elseif ($node instanceof ProjectNode || $node instanceof AggregateNode) {
                $literals = array_merge($literals, $this->extractLiteralsFromExpression($node->getExpressions()));
            }
            elseif ($node instanceof JoinNode) {
                $literals = array_merge($literals, $this->extractLiteralsFromExpression($node->getExpression()));
            }

            # 2. Stop at source nodes because sub-queries are separate scopes.
            if ($node instanceof SourceNode) return $literals;

            # 3. Recurse into child nodes.
            if ($node instanceof UnaryNode) {
                $literals = array_merge($literals, $this->collectLiterals($node->getInput(), $visited));
            } 
            elseif ($node instanceof BinaryNode) {
                $literals = array_merge($literals, $this->collectLiterals($node->getLeft(), $visited));
                $literals = array_merge($literals, $this->collectLiterals($node->getRight(), $visited));
            }

            return $literals;
        }

        /**
         * Extracts the projected column identity from various expression types.
         * @param mixed $expression The projected expression.
         * @return string|null The projected column identity or `null` if not applicable.
         */
        protected function extractProjectedColumn (mixed $expression) : ?string {
            $outputIdentity = null;

            # 1. Handle column identifiers directly.
            if ($expression instanceof ColumnIdentifier) {
                $outputIdentity = $expression->getQualifiedName();
            }
        
            # 2. Handle aliasable expressions that have an alias.
            elseif ($expression instanceof Aliasable && ($alias = $expression->getAlias()) !== null) {
                $outputIdentity = $alias;
            }

            # 3. Handle literal and raw expressions.
            elseif ($expression instanceof RawExpression || $expression instanceof LiteralExpression) {
                $outputIdentity = (string) $expression->getValue();
            }
            # 4. Acknowledge the existence of complex expressions.
            elseif ($expression instanceof Expression) {
                $outputIdentity = "(expression)"; 
            }

            return $outputIdentity;
        }

        /**
         * Finds the parent node of a given plan node within a scope.
         * @param PlanNode $node The plan node whose parent is to be found.
         * @return PlanNode|null The parent plan node or `null` if not found.
         */
        protected function findParentNode (PlanNode $node) : ?PlanNode {
            return $this->nodeParentIndex[$node] ?? null;
        }

        /**
         * Gets the children of a given plan node.
         * @param PlanNode $node The plan node whose children are to be retrieved.
         * @return PlanNode[] An array of child plan nodes.
         */
        protected function getChildrenOfNode (PlanNode $node) : array {
            $children = [];
            if ($node instanceof BinaryNode) {
                $children[] = $node->getLeft();
                $children[] = $node->getRight();
            }
            elseif ($node instanceof SourceNode && $node->getSource() instanceof QueryExpression) {
                $children[] = $node->getSource()->getPlan();
            }
            elseif ($node instanceof UnaryNode) {
                $children[] = $node->getInput();
            }
            return $children;
        }

        /**
         * Indexes the parent nodes of all plan nodes within a scope.
         * @param PlanNode $node The current plan node being indexed.
         * @param PlanNode|null $parent The parent plan node of the current node.
         */
        protected function indexParents (PlanNode $node, ?PlanNode $parent = null) : void {
            if ($parent) {
                $this->nodeParentIndex[$node] = $parent;
            }

            foreach ($this->getChildrenOfNode($node) as $child) {
                $this->indexParents($child, $node);
            }
        }

        /**
         * Parses column references from a raw SQL expression.
         * @param RawExpression $expression The raw SQL expression.
         * @return array An array of ColumnIdentifier objects representing the references.
         */
        protected function parseReferencesFromRaw (RawExpression $expression) : array {
            # 1. Skip strings in single quotes
            # 2. Match: [optional_part.][table.]column
            # 3. Handles optional backticks or double quotes
            $pattern = '/(?:\'(?:[^\'\\\\]|\\\\.)*\'|)(*SKIP)(*F)|(?:[`" ]?([a-zA-Z_]\w*)[`" ]?\.)?(?:[`" ]?([a-zA-Z_]\w*)[`" ]?\.)[`" ]?([a-zA-Z_]\w*)[`" ]?/x';

            if (!preg_match_all($pattern, $expression->getValue(), $matches, PREG_SET_ORDER)) {
                return [];
            }

            $references = [];
            foreach ($matches as $match) {
                # $match[1] = Database/Schema (Optional)
                # $match[2] = Table (Required in this specific pattern)
                # $match[3] = Column (Required)
                $table = $match[2];
                $column = $match[3];
                $schema = $match[1] ?: null;

                if ($this->isAliasDefinedInAncestors($table) || in_array($table, $this->getLocalAliases())) {
                    $references[] = (new ColumnIdentifier($column, $table))->setSchema($schema);
                    $this->addUntrustedReference($column, $table);
                }
            }
            return $references;
        }

        ##################################################################################
        ################################# PUBLIC METHODS #################################
        ##################################################################################

        /**
         * Adds a child scope.
         * @param Scope $child The child scope to add.
         * @return static The current scope.
         */
        public function addChild (Scope $child) : static {
            $this->children[] = $child;
            $child->setParent($this);
            return $this;
        }

        /**
         * Adds an edge to another scope.
         * @param Scope $to The target scope.
         * @param ScopeDependencyType $type The type of dependency.
         * @return static The current scope.
         */
        public function addEdge (Scope $to, ScopeDependencyType $type) : static {
            $this->edges[$to] = new ScopeEdge($this, $to, $type);
            return $this;
        }

        /**
         * Adds external references used in a scope.
         * @param string $tableAlias The table or alias name from the outer scope.
         * @param array $columns The columns referenced from that table/alias.
         * @return static The current scope.
         */
        public function addExternalReferences (string $tableAlias, array $columns) : static {
            if (!isset($this->externalReferences[$tableAlias])) {
                $this->externalReferences[$tableAlias] = [];
            }
            $this->externalReferences[$tableAlias] = array_unique(array_merge($this->externalReferences[$tableAlias], $columns));
            return $this;
        }

        /**
         * Adds an issue to a scope.
         * @param string $issue The issue description.
         * @return static The current scope.
         */
        public function addIssue (string $issue) : static {
            $this->issues[] = $issue;
            return $this;
        }

        /**
         * Adds a projected column to a scope.
         * @param string $column The name of the projected column.
         * @return static The current scope.
         */
        public function addProjectedColumn (string $column) : static {
            if (isset($this->projectedColumns[$column])) return $this;
            $this->projectedColumns[$column] = $column;
            return $this;
        }

        /**
         * Adds a reference that is considered untrusted.
         * @param string $column The column name.
         * @param string $table The table name.
         * @return static The current scope.
         */
        public function addUntrustedReference (string $column, string $table) : static {
            $this->untrustedReferences["$table.$column"] = true;
            return $this;
        }

        /**
         * Calculates the redundant columns in a scope.
         * @return static The current scope.
         * 
         * Logic:
         * 1. Root query "requires" everything in its final Projection.
         * 2. Parent scopes pass down "requirements" to their children.
         * 3. Children compare their "projections" against those "requirements".
         */
        public function calculateRedundantColumns () : static {
            # 1. EXISTS optimisation: All projected columns are redundant (SELECT 1 is enough).
            if ($this->inExistsScope) {
                $this->redundantColumns = $this->projectedColumns;
                return $this;
            }

            # 2. ROOT & UNION protection: These columns are the final output; they cannot be redundant.
            if ($this->type === ScopeType::Root || $this->type === ScopeType::SetBranch) {
                $this->redundantColumns = [];
                return $this;
            }

            $required = [];
            foreach ($this->requiredByParent as $col) {
                $required[$col] = true;
            }
        
            $redundant = [];
            foreach ($this->projectedColumns as $col) {
                if (!isset($required[$col])) {
                    $redundant[] = $col;
                }
            }
        
            $this->redundantColumns = $redundant;
        
            return $this;
        }

        /**
         * Analyses a scope to collect various metadata.
         * @return static The current scope.
         */
        public function analyse () : static {
            if ($this->analysed) return $this;

            $visited = new SplObjectStorage();
            $projectNodes = [];
            $references = [];
            $referenceGroups = [];
            $projectedColumns = [];

            $collectReferences = function (mixed $expr) use (&$references, &$referenceGroups) {
                $refs = $this->extractReferencesFromExpression($expr);
                foreach ($refs as $table => $columns) {
                    foreach ($columns as $colName => $identifier) {
                        $referenceGroups[$table][$colName] = $identifier;
                        $references["$table.$colName"] = $identifier;
                    }
                }
            };

            $traverse = function (PlanNode $node) use (&$traverse, &$visited, &$projectNodes, &$collectReferences, &$projectedColumns) {
                if (isset($visited[$node])) return;
                $visited[$node] = true;

                if ($node instanceof ProjectNode) {
                    $projectNodes[] = $node;

                    foreach ($node->getExpressions() as $expr) {
                        $collectReferences($expr);
                        $projectedColumns[] = $this->extractProjectedColumn($expr);
                    }
                }

                if ($node instanceof FilterNode || $node instanceof HavingNode) {
                    $expr = $node->getPredicate();
                    $collectReferences($expr);
                }

                if ($node instanceof JoinNode) {
                    foreach ($node->getExpressions() as $expr) {
                        $collectReferences($expr);
                    }
                    $traverse($node->getLeft());
                    $traverse($node->getRight());
                }

                if ($node instanceof UnaryNode) {
                    $traverse($node->getInput());
                }
            };

            $traverse($this->root);

            $this->analysed = true;
            $this->projectNodes = $projectNodes;
            $this->referenceGroups = $referenceGroups;
            $this->references = array_values($references);
            $this->projectedColumns = $projectedColumns;
            
            $this->literals = $this->collectLiterals($this->root);

            return $this;
        }

        /**
         * Marks a column as required by the parent scope.
         * @param string $identity The identity of the required column.
         * @return static The current scope.
         */
        public function demandColumn (string $identity) : static {
            $this->requiredByParent[$identity] = $identity;
            return $this;
        }
        
        /**
         * Extracts literal values from an expression and its sub-expressions.
         * @param mixed $expression The expression to extract literals from.
         * @return array An array of literal values.
         */
        public function extractLiteralsFromExpression (mixed $expression) : array {
            $literals = [];

            if ($expression instanceof LiteralExpression) {
                $literals[] = $expression->getValue();
            }
            elseif ($expression instanceof ExpressionCarrier) {
                foreach ($expression->getExpressions() as $sub) {
                    $literals = array_merge($literals, $this->extractLiteralsFromExpression($sub));
                }
            }
            elseif (is_array($expression)) {
                foreach ($expression as $item) {
                    $literals = array_merge($literals, $this->extractLiteralsFromExpression($item));
                }
            }

            return $literals;
        }

        /**
         * Extracts column references from various expression types.
         * @param mixed $expression The expression to extract from.
         * @return array An associative array mapping table names to arrays of column names.
         */
        public function extractReferencesFromExpression (mixed $expression) : array {
            $references = [];

            $collect = function ($expression) use (&$references, &$collect) {
                if ($expression instanceof RawExpression) {
                    $refs = $this->parseReferencesFromRaw($expression);
                    foreach ($refs as $ref) {
                        $table = $ref->getTable();
                        $column = $ref->getName();
                        $references[$table][$column] = $ref;
                    }
                }
                elseif ($expression instanceof ExpressionCarrier) {
                    foreach ($expression->getExpressions() as $sub) {
                        $collect($sub);
                    }
                }
                elseif ($expression instanceof ColumnIdentifier) {
                    $table = $expression->getTable();
                    if ($table !== null) {
                        $column = $expression->getName();
                        $references[$table][$column] = $expression;
                    }
                }
                elseif (is_string($expression)) {
                    $parts = explode('.', $expression, 2);
                    if (count($parts) === 2) {
                        [$table, $column] = $parts;
                        $references[$table][$column] = new ColumnIdentifier($column, $table);
                    }
                }
            };

            $collect($expression);

            return $references;
        }

        /**
         * Gets the child scope analyses.
         * @return array The child scope analyses.
         */
        public function getChildren () : array {
            return $this->children;
        }
        
        /**
         * Retrieves the columns required by the parent scope.
         * @return array An array of required column identities.
         */
        public function getDemandedColumns () : array {
            return $this->requiredByParent;
        }

        /**
         * Gets the edges to other scopes.
         * @return iterable<ScopeEdge> The edges.
         */
        public function getEdges () : iterable {
            foreach ($this->edges as $scope) {
                yield $this->edges[$scope];
            }
        }

        /**
         * Gets the execution dependencies of a scope.
         * @return Scope[] The execution dependencies.
         */
        public function getExecutionDependencies () : array {
            $dependencies = [];
            foreach ($this->getEdges() as $edge) {
                if ($edge->getType() !== ScopeDependencyType::Contains) {
                    $dependencies[] = $edge->getTarget();
                }
            }
            return $dependencies;
        }

        /**
         * Gets the issues found in a scope.
         * @return array The issues.
         */
        public function getIssues () : array {
            return $this->issues;
        }

        /**
         * Gets the literal values used within a scope.
         * @return array An array of literal values.
         */
        public function getLiterals () : array {
            $this->analyse();
            return $this->literals;
        }

        /**
         * Gets the local aliases defined within a scope.
         * @return array The local aliases.
         */
        public function getLocalAliases () : array {
            if (!empty($this->localAliases)) return $this->localAliases;

            $aliases = [];

            $collect = function (PlanNode $node) use (&$collect, &$aliases) {
                if ($node instanceof SourceNode) {
                    $aliases[] = $node->getAlias() ?: $node->getSource()->getAlias() ?: $node->getSource()->getName();
                }
                elseif ($node instanceof JoinNode) {
                    $collect($node->getLeft());
                    $collect($node->getRight());
                }
                elseif ($node instanceof UnaryNode) {
                    $collect($node->getInput());
                }
            };

            $collect($this->getRoot());

            return $this->localAliases = $aliases;
        }

        /**
         * Gets the parent scope.
         * @return Scope|null The parent scope or `null` if top-level.
         */
        public function getParent () : ?Scope {
            return $this->parent;
        }
        
        /**
         * Gets the parent node of a given plan node within a scope.
         * @param PlanNode $node The plan node whose parent is to be found.
         * @return PlanNode|null The parent plan node or `null` if not found.
         */
        public function getParentNodeOf (PlanNode $node) : ?PlanNode {
            return $this->nodeParentIndex[$node] ?? null;
        }

        /**
         * Gets the projection columns in a scope.
         * @return array The projection columns.
         */
        public function getProjectedColumns () : array {
            return $this->projectedColumns;
        }

        /**
         * Gets all project nodes within a scope.
         * @return ProjectNode[] An array of project nodes.
         */
        public function getProjections () : array {
            $this->analyse();
            return $this->projections ?? [];
        }

        /**
         * Gets the redundant columns in a scope.
         * @return array The redundant columns.
         */
        public function getRedundantColumns () : array {
            return $this->redundantColumns;
        }

        /**
         * Gets the referenced columns per table used within a scope.
         * @return array<string, ColumnIdentifier[]>
         */
        public function getReferenceGroups () : array {
            $this->analyse();
            return $this->referenceGroups;
        }

        /**
         * Gets all column references used within a scope.
         * @return ColumnIdentifier[] An array of column references.
         */
        public function getReferences () : array {
            $this->analyse();
            return $this->references;
        }

        /**
         * Gets the root plan node of a scope.
         * @return PlanNode The root plan node.
         */
        public function getRoot () : PlanNode {
            return $this->root;
        }

        /**
         * Gets the source of a scope.
         * @return Source The source.
         */
        public function getSource () : Source {
            return Source::fromNode($this->getRoot());
        }
    
        /**
         * Gets the type of a scope.
         * @return ScopeType The scope type.
         */
        public function getType () : ScopeType {
            return $this->type;
        }

        /**
         * Gets the untrusted references in a scope.
         * @return array The untrusted references.
         */
        public function getUntrustedReferences () : array {
            return array_keys($this->untrustedReferences);
        }

        /**
         * Indicates whether a scope has a parent.
         * @return bool Whether the scope has a parent.
         */
        public function hasParent () : bool {
            return $this->parent !== null;
        }
        
        /**
         * Indicates whether a scope has untrusted references.
         * @return bool Whether the scope has untrusted references.
         */
        public function hasUntrustedReferences () : bool {
            return !empty($this->untrustedReferences);
        }

        /**
         * Checks whether an alias is defined in any ancestor scopes.
         * @param string $alias The table or alias name to check.
         * @return bool Whether the alias is defined in any ancestor scope.
         */
        public function isAliasDefinedInAncestors (string $alias) : bool {
            $ancestor = $this->parent;

            while ($ancestor !== null) {
                if (in_array($alias, $ancestor->getLocalAliases(), true)) {
                    return true;
                }
                $ancestor = $ancestor->getParent();
            }

            return false;
        }

        
        /**
         * Indicates whether a column is correlated in the scope.
         * @param ColumnIdentifier|string $column The column identifier or name.
         * @param string|null $table The table or alias name (if column is a string).
         * @return bool Whether the column is correlated.
         */
        public function isColumnCorrelated (ColumnIdentifier|string $column, ?string $table = null) : bool {
            if ($column instanceof ColumnIdentifier) {
                $table = $column->getTable() ?? "";
                $column = $column->getName();
            }

            $fullName = $table ? "$table.$column" : $column;
        
            # 1. It's not correlated if it's projected here.
            if (isset($this->projectedColumns[$column])) return false;
        
            # 2. It's correlated if it's an external reference.
            if (isset($this->externalReferences[$table]) && in_array($column, $this->externalReferences[$table], true)) {
                return true;
            }
        
            # 3. It's untrusted if it's marked as such.
            return isset($this->untrustedReferences[$fullName]);
        }
        

        /**
         * Indicates whether a scope is a CTE.
         * @return bool Whether the scope is a CTE.
         */
        public function isCte () : bool {
            return $this->cte;
        }

        /**
         * Indicates whether a scope is logically correlated.
         * @return bool Whether the scope is logically correlated.
         */
        public function isLogicallyCorrelated () : bool {
            return $this->logicallyCorrelated;
        }

        /**
         * Indicates whether the scope is within an EXISTS clause.
         * @return bool Whether the scope is within an EXISTS clause.
         */
        public function isInExistsScope () : bool {
            return $this->inExistsScope;
        }

        /**
         * Indicates whether a scope is physically correlated.
         * @return bool Whether the scope is physically correlated.
         */
        public function isPhysicallyCorrelated () : bool {
            return $this->physicallyCorrelated;
        }

        /**
         * Indicates whether a scope is a set operation branch.
         * @return bool Whether the scope is a set operation branch.
         */
        public function isSetOperationBranch () : bool {
            return $this->setOperationBranch;
        }
        
        /**
         * Indicates whether a table alias is resolved within the scope.
         * @param string $name The table or alias name to check.
         * @return bool Whether the alias is resolved within the scope.
         */
        public function resolvesAlias (string $name) : bool {
            $source = $this->getSource();

            foreach ($source->getNodes() as $source) {
                if ($source->getAlias() === $name) return true;

                $src = $source->getSource();

                if ($src->getAlias() === $name) return true;

                if ($src instanceof QueryExpression) continue;
                
                if ($src->getName() === $name) return true;
            }
        
            return false;
        }
        
        /**
         * Indicates whether a table alias is resolved in any ancestor scopes.
         * @param string $name The table or alias name to check.
         * @return bool Whether the alias is resolved in any ancestor scope.
         */
        public function resolvesAliasInAncestors (string $name) : bool {
            $scope = $this->getParent();

            while ($scope) {
                if ($scope->resolvesAlias($name)) return true;
                
                $scope = $scope->getParent();
            }

            return false;
        }


        /**
         * Sets the logical correlation status of a scope.
         * @param bool $status Whether the scope is logically correlated.
         * @return static The current scope.
         */
        public function setLogicalCorrelationStatus (bool $status) : static {
            $this->logicallyCorrelated = $status;
            return $this;
        }

        /**
         * Indicates whether the scope is within an EXISTS clause.
         * @return bool Whether the scope is within an EXISTS clause.
         */
        public function setExistsScopeStatus (bool $status) : static {
            $this->inExistsScope = $status;
            return $this;
        }

        /**
         * Sets the parent scope.
         * @param Scope|null $parent The parent scope or `null` if top-level.
         */
        public function setParent (?Scope $parent) : static {
            $this->parent = $parent;
            return $this;
        }

        /**
         * Sets the physical correlation status of a scope.
         * @param bool $status Whether the scope is physically correlated.
         * @return static The current scope.
         */
        public function setPhysicalCorrelationStatus (bool $status) : static {
            $this->physicallyCorrelated = $status;
            return $this;
        }

        /**
         * Sets the redundant columns in a scope.
         * @param array $columns The redundant columns.
         * @return static The current scope.
         */
        public function setRedundantColumns (array $columns) : static {
            $this->redundantColumns = $columns;
            return $this;
        }

        /**
         * Sets the type of a scope.
         * @param ScopeType $type The scope type.
         * @return static The current scope.
         */
        public function setType (ScopeType $type) : static {
            $this->type = $type;
            return $this;
        }
    }
?>