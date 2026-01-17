<?php

    /*/
	 * Project Name:    Wingman — Database — SQL Compiler
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 27 2025
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Compilers namespace.
    namespace Wingman\Database\Compilers;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use RuntimeException;
    use SplObjectStorage;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Plan\HavingNode;
    use Wingman\Database\Analysis\PlanWalker;
    use Wingman\Database\Enums\Component;
    use Wingman\Database\Expressions\{
        AggregateExpression, ComparisonExpression, InExpression, BooleanExpression,
        CaseExpression, ColumnIdentifier, ExistsExpression, JoinExpression, LiteralExpression, NullExpression,
        Predicate, QueryExpression, RawExpression, TableIdentifier, WindowExpression, OrderExpression
    };
    use Wingman\Database\Interfaces\{Aliasable, Conjunctive, Expression, SQLDialect};
    use Wingman\Database\Plan\{
        SourceNode, FilterNode, ProjectNode, JoinNode, 
        SortNode, LimitNode, AggregateNode, CteNode,
        ReturnNode, InsertNode, UpdateNode, DeleteNode,
        LockNode, NullNode, SetOperationNode, UpsertNode
    };

    /**
     * A SQL compiler that converts a query plan into a SQL string.
     * @package Wingman\Database\Compilers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SQLCompiler {
        /**
         * SQL dialect for database-specific syntax.
         */
        protected SQLDialect $dialect;

        /**
         * The walker of a compiler.
         * @var PlanWalker
         */
        protected PlanWalker $walker;

        /**
         * The compiled SQL string.
         */
        protected string $sql = "";

        /**
         * Creates a new compiler.
         * @param SQLDialect $dialect The SQL dialect to use.
         */
        public function __construct (SQLDialect $dialect) {
            $this->dialect = $dialect;
            $this->walker = new PlanWalker();
        }

        ######################################################################
        ########################## COMPILER METHODS ##########################
        ######################################################################

        /**
         * Compiles an AggregateExpression into SQL.
         * @param AggregateExpression $expression The aggregate expression.
         * @return string The SQL fragment.
         */
        protected function compileAggregate (AggregateExpression $expression) : string {
            $function = $expression->getFunction();
            $operands = array_map(fn ($operand) => $this->compileExpression($operand), $expression->getExpressions());
            
            $list = implode(", ", $operands);
            
            if ($expression->isDistinct()) {
                $list = "DISTINCT " . $list;
            }
            
            return "{$function}({$list})";
        }

        /**
         * Compiles a boolean expression into SQL.
         * @param Expression $expression The boolean expression.
         * @param bool $aliasAllowed Whether to include aliases in the output.
         * @param string|null $parentConjunction The parent conjunction for nesting logic.
         * @return string The SQL fragment.
         */
        protected function compileBooleanExpression (Expression $expression, bool $aliasAllowed = false, ?string $parentConjunction = null) : string {
            if (!($expression instanceof BooleanExpression)) {
                return $this->compileExpression($expression, $aliasAllowed);
            }
        
            $parts = [];
            $currentConjunction = strtoupper($expression->getConjunction());
            $subExpressions = $expression->getExpressions();
        
            foreach ($subExpressions as $subExpr) {
                $parts[] = $this->compileBooleanExpression($subExpr, $aliasAllowed, $currentConjunction);
            }
        
            $sql = implode(" {$currentConjunction} ", $parts);
        
            if (!empty($parentConjunction) && $currentConjunction !== $parentConjunction) {
                return "({$sql})";
            }
            
            return count($subExpressions) > 1 ? "({$sql})" : $sql;
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
         * Compiles a CaseExpression into SQL.
         * @param CaseExpression $expression The case expression.
         * @return string The SQL fragment.
         */
        protected function compileCaseExpression (CaseExpression $expression) : string {
            $parts = [];
            $subject = $expression->getSubject();
            $subjectSql = $subject ? $this->compileExpression($subject) : null;
            $caseHeader = $subjectSql ? "CASE $subjectSql" : "CASE";
            $parts[] = $caseHeader;

            $conditions = $expression->getConditions();
            $results = $expression->getResults();

            foreach ($conditions as $index => $condition) {
                $conditionSql = $this->compileExpression($condition);
                $resultSql = $this->compileExpression($results[$index]);
                $parts[] = "WHEN {$conditionSql} THEN {$resultSql}";
            }

            if ($default = $expression->getDefault()) {
                $defaultSql = $this->compileExpression($default);
                $parts[] = "ELSE {$defaultSql}";
            }

            $parts[] = "END";

            return implode(' ', $parts);
        }

        /**
         * Compiles a ColumnIdentifier into SQL.
         * @param ColumnIdentifier $column The column identifier.
         * @return string The SQL fragment.
         */
        protected function compileColumn (ColumnIdentifier $column) : string {
            $table = $column->getTable();
            $name = $column->getName();
            $table = $table ? $this->dialect->quoteIdentifier($table) . '.' : "";
            $name = $name === '*' ? '*' : $this->dialect->quoteIdentifier($name);
            return $table . $name;
        }

        /**
         * Compiles a ComparisonExpression into SQL.
         * @param ComparisonExpression $expression The comparison expression.
         * @return string The SQL fragment.
         */
        protected function compileComparisonExpression (ComparisonExpression $expression) : string {
            $operator = strtoupper($expression->getOperator());
            $operands = array_map(fn ($operand) => $this->compileExpression($operand), $expression->getExpressions());
            return implode(" {$operator} ", $operands);
        }

        /**
         * Compiles a list of conditions into a SQL fragment.
         * @param array $conditions List of condition expressions.
         * @return string The compiled SQL fragment.
         */
        protected function compileConditions (array $conditions) : string {
            $parts = [];
            foreach ($conditions as $i => $condition) {
                $sql = $this->compileExpression($condition);
                
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
                $anchorSql = $this->compileSubquery($anchor);

                $recursive = $expression->getRecursivePart();

                if ($recursive) {
                    $recursiveSql = $this->compileSubquery($recursive);
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
         * Compiles an EXISTS expression into SQL.
         * @param ExistsExpression $expression The EXISTS expression.
         * @return string The compiled SQL fragment.
         * @throws RuntimeException If the subquery is not a PlanNode.
         */
        protected function compileExistsExpression (ExistsExpression $expression) : string {
            $query = $expression->getValue();
            $subPlan = $query->getPlan();
        
            if (!($subPlan instanceof PlanNode)) {
                throw new RuntimeException("ExistsExpression must contain a compiled PlanNode.");
            }
    
            $op = $expression->isNegated() ? "NOT EXISTS" : "EXISTS";
            
            $subSql = $this->compileSubquery($query);
            return "{$op} ({$subSql})";
        }

        /**
         * Compiles an expression into a SQL fragment.
         * @param Expression $expression The expression to compile.
         * @param bool $aliasAllowed Whether to include aliases in the output.
         * @return string The compiled SQL fragment.
         * @throws RuntimeException If the expression type is unknown.
         */
        protected function compileExpression (Expression $expression, bool $aliasAllowed = false) : string {
            $string = match (true) {
                $expression instanceof QueryExpression => $this->compileSubquery($expression),
                $expression instanceof ColumnIdentifier => $this->compileColumn($expression),
                $expression instanceof TableIdentifier => $this->compileTable($expression),
                $expression instanceof LiteralExpression => $this->compileLiteral($expression),
                $expression instanceof AggregateExpression => $this->compileAggregate($expression),
                $expression instanceof RawExpression => $this->compileRawExpression($expression),
                $expression instanceof ComparisonExpression => $this->compileComparisonExpression($expression),
                $expression instanceof ExistsExpression => $this->compileExistsExpression($expression),
                $expression instanceof BooleanExpression => $this->compileBooleanExpression($expression),
                $expression instanceof InExpression => $this->compileInExpression($expression),
                $expression instanceof CaseExpression => $this->compileCaseExpression($expression),
                $expression instanceof NullExpression => $this->compileNullExpression($expression),
                default => throw new RuntimeException("Unknown Expression type: " . get_class($expression))
            };
            if ($aliasAllowed && $expression instanceof Aliasable && $alias = $expression->getAlias()) {
                return "{$string} AS " . $this->dialect->quoteIdentifier($alias);
            }
            return $string;
        }

        /**
         * Compiles an InExpression into SQL.
         * @param InExpression $expr The IN expression.
         * @return string The SQL fragment.
         */
        protected function compileInExpression (InExpression $expr) : string {
            $column = $this->dialect->quoteIdentifier($expr->getOperand());
            $values = $expr->getValue();
            $op = $expr->isNegated() ? "NOT IN" : "IN";
        
            if ($values instanceof QueryExpression) {
                $subSql = $this->compileExpression($values);
                return "{$column} {$op} ({$subSql})";
            }
        
            $placeholders = implode(", ", array_fill(0, count($values), '?'));
            
            return "{$column} {$op} ({$placeholders})";
        }

        /**
         * Compiles a list of groupings into a SQL fragment.
         * @param array $groupings List of grouping expressions.
         * @return string The compiled SQL fragment.
         */
        protected function compileGroupings (array $groupings) : string {
            $parts = [];
            foreach ($groupings as $grouping) {
                $parts[] = $this->compileExpression($grouping);
            }
            return implode(", ", $parts);
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
         * Compiles a JOIN clause from the join bucket entry.
         * @param JoinExpression $join The join definition.
         * @param Predicate[] $predicates List of predicates associated with the join.
         * @return string The compiled JOIN SQL fragment.
         */
        protected function compileJoin (JoinExpression $join, array $predicates) : string {
            $type = $join->getType()->value;
            $right = $join->getSource();
            $conditions = $join->getConditions();
            $conjunction = $join->getConjunction();

            # 1. Compile the right-hand source (table or subquery).
            if ($right instanceof TableIdentifier) {
                $sourceSQL = $this->compileExpression($right, true);
            }
            elseif ($right instanceof QueryExpression) {
                $sourceSQL = $this->compileSubquery($right);
                $alias = $join->getJoinedTable() ?: $right->getAlias();
                if ($alias) {
                    $sourceSQL .= " AS " . $this->dialect->quoteIdentifier($alias);
                }
            }

            # 2. Compile the conditions.
            $conditionSQLs = [];
            foreach ($conditions as $condition) {
                $conditionSQLs[] = $this->compileExpression($condition);
            }
            foreach ($predicates as $predicate) {
                $conditionSQLs[] = $this->compileExpression($predicate);
            }
            $onClause = !empty($conditionSQLs) ? " ON " . implode(" {$conjunction} ", $conditionSQLs) : "";

            return "{$type} JOIN {$sourceSQL}{$onClause}";
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
                $parts[] = $this->compileJoin($expression, $joinFilterMap[$expression] ?? []);
            }
            return implode(' ', $parts);
        }

        /**
         * Compiles LIMIT and OFFSET into SQL.
         * @param array $limitInfo Array with limit and offset values.
         * @return string The SQL fragment.
         */
        protected function compileLimitOffset (array $limitInfo) : string {
            $limit = $limitInfo[0] ?? null;
            $offset = $limitInfo[1] ?? 0;
            return $this->dialect->renderLimitOffset($limit, $offset);
        }

        /**
         * Compiles a LiteralExpression into SQL.
         * @param LiteralExpression $expression The literal expression.
         * @param bool $aliasAllowed Whether to include aliases in the output.
         * @return string The SQL fragment.
         */
        protected function compileLiteral (LiteralExpression $expression, bool $aliasAllowed = false) : string {
            if ($aliasAllowed && $alias = $expression->getAlias()) {
                return "? AS " . $this->dialect->quoteIdentifier($alias);
            }
            return "?";
        }

        /**
         * Compiles a NullExpression into SQL.
         * @param NullExpression $expr The NULL expression.
         * @return string The SQL fragment.
         */
        protected function compileNullExpression (NullExpression $expr) : string {
            $column = $this->dialect->quoteIdentifier($expr->getOperand());
            $op = $expr->isNegated() ? "IS NOT NULL" : "IS NULL";
            return "{$column} {$op}";
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
                $parts[] = $this->dialect->renderOrderPrecedence(
                    $this->compileExpression($ordering->getTarget()), 
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
                $projections[] = $this->compileExpression($expression, true);
            }
            
            $projections = empty($projections) ? '*' : implode(", ", $projections);

            return $selectType . $projections;
        }

        /**
         * Compiles a raw expression into SQL.
         * @param RawExpression $expr The raw expression.
         * @return string The SQL fragment.
         */
        protected function compileRawExpression (RawExpression $expr) : string {
            return $expr->getValue();
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
                $parts[] = $this->dialect->renderLock($type, $timeout, $lockedSkipped);
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
            $sources = [];
            foreach ($this->walker->getBucket(Component::Sources) as $source) {
                $sources[] = $this->compileExpression($source, true);
            }
            return "FROM " . implode(", ", $sources);
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
         * Compiles a subquery into SQL.
         * @param QueryExpression $query The subquery expression.
         * @return string The compiled SQL fragment.
         * @param bool $aliasAllowed Whether to include aliases in the output.
         */
        protected function compileSubquery (QueryExpression $query, bool $aliasAllowed = false) : string {
            $sql = $this->compileSubplan($query->getPlan());
            if ($aliasAllowed && ($alias = $query->getAlias())) {
                return "({$sql}) AS " . $this->dialect->quoteIdentifier($alias);
            }
            return $sql;
        }

        /**
         * Compiles a table identifier into SQL.
         * @param TableIdentifier $table The table identifier.
         * @param bool $includeAlias Whether to include the alias in the output.
         * @return string The SQL fragment.
         */
        protected function compileTable (TableIdentifier $table) : string {
            return $this->dialect->quoteIdentifier($table->getName());
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
                    $payload["update_values"][$col] = $this->dialect->resolveConflictReference($val->getValue());
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
    }
?>