<?php
    /*/
	 * Project Name:    Wingman — Database — Plan Compiler
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 27 2025
	 * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Compilers namespace.
    namespace Wingman\Database\Compilers;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use SplObjectStorage;
    use Wingman\Database\Analysis\PlanWalker;
    use Wingman\Database\Enums\Component;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Expressions\RawExpression;
    use Wingman\Database\Interfaces\Conjunctive;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Interfaces\SQLDialect;
    use Wingman\Database\Plan\AggregateNode;
    use Wingman\Database\Plan\CteNode;
    use Wingman\Database\Plan\DeleteNode;
    use Wingman\Database\Plan\FilterNode;
    use Wingman\Database\Plan\HavingNode;
    use Wingman\Database\Plan\InsertNode;
    use Wingman\Database\Plan\JoinNode;
    use Wingman\Database\Plan\LimitNode;
    use Wingman\Database\Plan\LockNode;
    use Wingman\Database\Plan\NullNode;
    use Wingman\Database\Plan\ProjectNode;
    use Wingman\Database\Plan\ReturnNode;
    use Wingman\Database\Plan\SetOperationNode;
    use Wingman\Database\Plan\SortNode;
    use Wingman\Database\Plan\SourceNode;
    use Wingman\Database\Plan\UpdateNode;
    use Wingman\Database\Plan\UpsertNode;

    /**
     * Compiles a query plan into SQL.
     * @package Wingman\Database\Compilers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PlanCompiler {
        /**
         * SQL dialect for database-specific syntax.
         */
        protected SQLDialect $dialect;

        /**
         * The walker of a plan compiler.
         * @var PlanWalker
         */
        protected PlanWalker $walker;

        /**
         * The expression compiler of a plan compiler.
         * @var ExpressionCompiler
         */
        protected ExpressionCompiler $expressionCompiler;

        /**
         * Creates a new plan compiler.
         * @param SQLDialect $dialect The SQL dialect to use.
         */
        public function __construct (SQLDialect $dialect) {
            $this->dialect = $dialect;
            $this->walker = new PlanWalker();
            $this->expressionCompiler = new ExpressionCompiler($dialect, $this);
            $this->dialect->setCompiler($this->expressionCompiler);
        }

        /**
         * Compiles a bulk WHERE clause for multiple rows based on primary keys.
         * @param array $rows List of associative arrays representing rows.
         * @param array $keys List of primary key column names.
         * @return string The compiled SQL WHERE fragment.
         */
        protected function compileBulkWhere (array $rows, array $keys) : string {
            $rowConditions = [];
            foreach ($rows as $row) {
                $pkGroup = [];
                foreach ($keys as $pk) {
                    $pkGroup[] = $this->dialect->quoteIdentifier($pk) . " = ?";
                }
                $rowConditions[] = "(" . implode(" AND ", $pkGroup) . ")";
            }
            
            return "(" . implode(" OR ", $rowConditions) . ")";
        }

        /**
         * Compiles a list of conditions into a SQL fragment.
         * @param array $conditions List of condition expressions.
         * @return string The compiled SQL fragment.
         */
        protected function compileConditions (array $conditions) : string {
            $parts = [];
            foreach ($conditions as $i => $condition) {
                $sql = $this->expressionCompiler->compile($condition);
                
                if ($i === 0) {
                    $parts[] = $sql;
                    continue;
                }
                
                $conjunction = strtoupper(match (true) {
                    $condition instanceof Conjunctive => $conjunction = $condition->getConjunction(),
                    default => "AND"
                });
        
                $parts[] = "{$conjunction} {$sql}";
            }
            return implode(' ', $parts);
        }

        /**
         * Compiles a CTE node into SQL.
         * @param CteNode $node The CTE node to compile.
         * @return string The compiled SQL query with CTEs.
         */
        protected function compileCte (CteNode $node) : string {
            $definitions = [];

            foreach ($node->getExpressions() as $expression) {
                $name = $expression->getAlias() ?: $expression->getName();
                $name = $this->dialect->quoteIdentifier($name);

                $anchor = $expression->getQuery();
                $anchorSql = $this->expressionCompiler->compileSubquery($anchor);

                $recursive = $expression->getRecursivePart();

                if ($recursive) {
                    $recursiveSql = $this->expressionCompiler->compileSubquery($recursive);
                    $unionType = $expression->getUnionType();
                    $definitions[] = "$name AS ($anchorSql $unionType $recursiveSql)";
                }
                else $definitions[] = "$name AS ({$anchorSql})";
            }

            $prefix = $node->isRecursive() ? "WITH RECURSIVE" : "WITH";
            $definitions = implode(", ", $definitions);
            $mainQuery = $this->compile($node->getInput());

            return "$prefix $definitions $mainQuery";
        }

        /**
         * Compiles a delete node into SQL.
         * @param DeleteNode $node The delete node to compile.
         * @return string The compiled SQL query.
         * @throws InvalidArgumentException If the delete target is a subquery.
         */
        protected function compileDelete (DeleteNode $node) : string {
            if ($node->getTable() instanceof QueryExpression) {
                throw new InvalidArgumentException("DELETE target cannot be a subquery.");
            }

            $this->walker->walk($node->getInput());

            $sources = $this->compileSources();
            
            $where = !$this->walker->isEmpty(Component::Where)
                ? [$this->compileConditions($this->walker->getBucket(Component::Where))] 
                : [];
        
            $joins = $this->compileJoins(
                $this->walker->getBucket(Component::Joins),
                $this->walker->getJoinFilterMap()
            );

            $limit = $this->walker->getBucket(Component::Limit);

            $bucket = new SplObjectStorage();
            $bucket[Component::Where] = $where;
            $bucket[Component::Joins] = $joins;
            $bucket[Component::Limit] = $limit[0] ?? null;
            $bucket[Component::Offset] = $limit[1] ?? 0;
            
            return $this->dialect->delete($node->getTable(), $sources, $bucket);
        }

        /**
         * Compiles a list of groupings into a SQL fragment.
         * @param array $groupings List of grouping expressions.
         * @return string The compiled SQL fragment.
         */
        protected function compileGroupings (array $groupings) : string {
            $parts = [];
            foreach ($groupings as $grouping) {
                $parts[] = $this->expressionCompiler->compile($grouping);
            }
            return implode(", ", $parts);
        }

        /**
         * Compiles multiple JOIN clauses into SQL.
         * @param array $expressions List of join definitions.
         * @param SplObjectStorage $joinFilterMap Mapping of joins to their filters.
         * @return string The compiled JOIN SQL fragments.
         */
        protected function compileJoins (array $expressions, SplObjectStorage $joinFilterMap) : string {
            $parts = [];
            foreach ($expressions as $expression) {
                $parts[] = $this->expressionCompiler->compileJoinExpression($expression, $joinFilterMap[$expression] ?? []);
            }
            return implode(' ', $parts);
        }

        /**
         * Compiles an insert node into SQL.
         * @param InsertNode $node The insert node to compile.
         * @return string The compiled SQL query.
         */
        protected function compileInsert (InsertNode $node) : string {
            $source = $node->getSource();
            $columns = $node->getColumns();
            
            $payload = [
                "columns" => $columns,
                "ignore"  => $node->ignoresConflicts()
            ];
        
            if ($source instanceof PlanNode) {
                $payload["subquery"] = $this->compileSubplan($source);
            }
            else {
                $placeholderGroups = [];
                $columnCount = count($columns);
                $rowPlaceholders = '(' . implode(", ", array_fill(0, $columnCount, '?')) . ')';
                
                foreach ($source as $row) $placeholderGroups[] = $rowPlaceholders;
                $payload["values"] = $placeholderGroups;
            }
        
            return $this->dialect->insert($node->getTable(), $payload);
        }

        /**
         * Compiles LIMIT and OFFSET into SQL.
         * @param array $limitInfo Array with limit and offset values.
         * @return string The SQL fragment.
         */
        protected function compileLimitOffset (array $limitInfo) : string {
            $limit = $limitInfo[0] ?? null;
            $offset = $limitInfo[1] ?? 0;
            return $this->dialect->compileLimitOffset($limit, $offset);
        }

        /**
         * Compiles a NullNode into SQL.
         * @param NullNode $node The null node to compile.
         * @return string The compiled SQL fragment.
         */
        protected function compileNull (NullNode $node) : string {
            return "(SELECT 1 WHERE 1=0) AS dead_branch";
        }

        /**
         * Compiles a list of orderings into a SQL fragment.
         * @param array $orderings List of ordering expressions.
         * @return string The compiled SQL fragment.
         */
        protected function compileOrderings (array $orderings) : string {
            $parts = [];
            /** @var OrderExpression */
            foreach ($orderings as $ordering) {
                $parts[] = $this->dialect->compileOrder(
                    $this->expressionCompiler->compile($ordering->getTarget()), 
                    $ordering->getDirection(), 
                    $ordering->getPrecedence()
                );
            }
            return implode(", ", $parts);
        }

        /**
         * Compiles the SELECT projections into SQL.
         * @return string The compiled SELECT clause.
         */
        protected function compileProjections () : string {
            $selectType = $this->walker->isDistinct() ? "SELECT DISTINCT " : "SELECT ";
            $projections = [];
            
            /** @var Expression */
            foreach ($this->walker->getBucket(Component::Projections) as $expression) {
                $projections[] = $this->expressionCompiler->compile($expression, true);
            }
            
            $projections = empty($projections) ? '*' : implode(", ", $projections);

            return $selectType . $projections;
        }

        /**
         * Compiles a retrun node into SQL.
         * @param ReturnNode $node The return node to compile.
         * @return string The compiled SQL query with RETURNING clause.
         */
        protected function compileReturn (ReturnNode $node) : string {
            $innerSql = $this->compile($node->getInput());
            return $this->dialect->returning($innerSql, $node->getColumns());
        }

        /**
         * Compiles a SELECT statement from the populated buckets.
         * @param PlanNode $node The root node of the SELECT plan.
         * @return string The compiled SQL query.
         */
        protected function compileSelect (PlanNode $node) : string {
            $this->walker->walk($node);

            $parts = [];
            $setOperationFound = false;

            if (!$this->walker->isEmpty(Component::Projections)) {
                $sources = $this->walker->getBucket(Component::Sources);

                if (count($sources) === 1 && $sources[0] instanceof QueryExpression && $sources[0]->getPlan() instanceof SetOperationNode) {
                    $parts[] = $this->compileSetOperation($sources[0]->getPlan());
                    $setOperationFound = true;
                }
            }
            
            if (!$setOperationFound) {
                $parts[] = $this->compileProjections();

                if (!$this->walker->isEmpty(Component::Sources)) {
                    $parts[] = $this->compileSources();
                }

                if (!$this->walker->isEmpty(Component::Joins)) {
                    $parts[] = $this->compileJoins(
                        $this->walker->getBucket(Component::Joins),
                        $this->walker->getJoinFilterMap()
                    );
                }

                if (!$this->walker->isEmpty(Component::Where)) {
                    $parts[] = "WHERE " . $this->compileConditions($this->walker->getBucket(Component::Where));
                }

                if (!$this->walker->isEmpty(Component::GroupBy)) {
                    $parts[] = "GROUP BY " . $this->compileGroupings($this->walker->getBucket(Component::GroupBy));
                }

                if (!$this->walker->isEmpty(Component::Having)) {
                    $parts[] = "HAVING " . $this->compileConditions($this->walker->getBucket(Component::Having));
                }
            }    

            if (!$this->walker->isEmpty(Component::OrderBy)) {
                $parts[] = "ORDER BY " . $this->compileOrderings($this->walker->getBucket(Component::OrderBy));
            }

            if (!$this->walker->isEmpty(Component::Limit)) {
                $parts[] = $this->compileLimitOffset($this->walker->getBucket(Component::Limit));
            }
            
            if (!$this->walker->isEmpty(Component::Lock)) {
                [$type, $timeout, $lockedSkipped] = $this->walker->getBucket(Component::Lock);
                $parts[] = $this->dialect->compileLock($type, $timeout, $lockedSkipped);
            }
            
            return implode(' ', $parts);
        }

        /**
         * Compiles a set operation (UNION, INTERSECT, etc.) between two queries.
         * @param SetOperationNode $node The set operation node.
         * @return string The compiled SQL query.
         */
        protected function compileSetOperation (SetOperationNode $node) : string {
            $leftSql = $this->compileSubplan($node->getLeft());
            $rightSql = $this->compileSubplan($node->getRight());
        
            return $this->dialect->setOperation($leftSql, $rightSql, $node->getOperation());
        }

        /**
         * Compiles the FROM sources into SQL.
         * @return string The compiled FROM clause.
         */
        protected function compileSources () : string {
            return $this->expressionCompiler->compileSourceExpressions($this->walker->getBucket(Component::Sources));
        }

        /**
         * Compiles a subplan into SQL.
         * @param PlanNode $plan The subplan node.
         * @return string The compiled SQL fragment.
         */
        protected function compileSubplan (PlanNode $plan) : string {
            return (new static($this->dialect))->compile($plan);
        }

        /**
         * Compiles an update node into SQL.
         * @param UpdateNode $node The update node to compile.
         * @return string The compiled SQL query.
         * @throws InvalidArgumentException If the update table is a subquery.
         */
        protected function compileUpdate (UpdateNode $node) : string {
            if ($node->isBulk()) {
                $rows = $node->getData();
                $keys = $node->getPrimaryKeys();
                $columns = $node->getUpdatableColumns();

                $payload = [
                    "columns" => $columns,
                    "keys" => $keys,
                    "values" => $rows,
                    "where" => $this->compileBulkWhere($rows, $keys)
                ];

                return $this->dialect->updateBulk($node->getTable(), $payload);
            }

            if ($node->getTable() instanceof QueryExpression) {
                throw new InvalidArgumentException("UpdateNode table cannot be a subquery.");
            }
        
            $payload = [
                "columns" => array_keys($node->getAssignments())
            ];
        
            $this->walker->walk($node->getInput());
        
            $compiledBuckets = new SplObjectStorage();
        
            if (!$this->walker->isEmpty(Component::Where)) {
                $compiledBuckets[Component::Where] = $this->compileConditions($this->walker->getBucket(Component::Where));
            }
        
            if (!$this->walker->isEmpty(Component::Joins)) {
                $compiledBuckets[Component::Joins] = $this->compileJoins(
                    $this->walker->getBucket(Component::Joins),
                    $this->walker->getJoinFilterMap()
                );
            }
        
            return $this->dialect->update($node->getTable(), $payload, $compiledBuckets);
        }

        /**
         * Compiles an upsert (insert with conflict resolution) operation.
         * @param UpsertNode $node The insert node.
         * @param array $updateAssignments The assignments to perform on conflict.
         * @return string The compiled SQL query.
         */
        protected function compileUpsert (UpsertNode $node) : string {
            $insertNode = $node->getInput();
            $columns = $insertNode->getColumns();
            $source = $insertNode->getSource();
            
            $payload = [
                "columns" => $columns,
                "update" => [],
                "strategy" => $node->getStrategy()
            ];

            $this->walker->walk($node->getInput());
        
            if ($source instanceof PlanNode) {
                $payload["subquery"] = $this->compileSubplan($source);
            }
            else {
                $rowPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                $payload["values"] = array_fill(0, count($source), $rowPlaceholders);
            }
        
            $updateAssignments = $node->getUpdateAssignments();
            foreach ($updateAssignments as $col => $val) {
                $payload["update"][] = $col;
                
                if ($val instanceof RawExpression) {
                    $payload["update_values"][$col] = $this->dialect->compileConflictReference($val->getValue());
                }
                else {
                    $payload["update_values"][$col] = '?';
                }
            }
        
            return $this->dialect->upsert($insertNode->getTable(), $payload);
        }

        /**
         * Compiles a plan into an SQL string.
         * @param PlanNode $node The root node of the plan tree.
         * @return string The compiled SQL query.
         */
        public function compile (PlanNode $node) : string {
            return match (true) {
                $node instanceof SourceNode,
                $node instanceof ProjectNode,
                $node instanceof FilterNode,
                $node instanceof LimitNode,
                $node instanceof LockNode,
                $node instanceof SortNode,
                $node instanceof JoinNode,
                $node instanceof HavingNode,
                $node instanceof AggregateNode => $this->compileSelect($node),
                $node instanceof SetOperationNode => $this->compileSetOperation($node),
                $node instanceof ReturnNode => $this->compileReturn($node),
                $node instanceof CteNode => $this->compileCte($node),
                $node instanceof InsertNode => $this->compileInsert($node),
                $node instanceof UpdateNode => $this->compileUpdate($node),
                $node instanceof UpsertNode  => $this->compileUpsert($node),
                $node instanceof DeleteNode => $this->compileDelete($node),
                $node instanceof NullNode => $this->compileNull($node),
                default => throw new InvalidArgumentException("Unknown node type: " . get_class($node))
            };
        }

        /**
         * Creates a new plan compiler with the specified SQL dialect.
         * @param SQLDialect $dialect The SQL dialect to use.
         * @return static A new instance of PlanCompiler.
         */
        public static function withDialect (SQLDialect $dialect) : static {
            return new static($dialect);
        }
    }
?>