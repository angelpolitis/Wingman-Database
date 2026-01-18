<?php
    /*/
	 * Project Name:    Wingman — Database — Query Planner
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Compilers namespace.
    namespace Wingman\Database\Compilers;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Objects\QueryState;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Enums\QueryType;
    use Wingman\Database\Expressions\{
        AggregateExpression, ColumnIdentifier, RawExpression, QueryExpression, TableIdentifier
    };
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Plan\{
        AggregateNode, SourceNode, FilterNode, ProjectNode, JoinNode, SortNode, LimitNode, UpdateNode,
        DeleteNode, InsertNode, UpsertNode, CteNode, HavingNode, LockNode, ReturnNode, SetOperationNode,
        TargetNode, UnaryNode
    };

    /**
     * Compiles a QueryState into an optimised execution plan.
     * @package Wingman\Database\Compilers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class QueryPlanner {
        /**
         * Creates a table node from a source, which can be a QueryBuilder, PlanNode or TableIdentifier.
         * @param mixed $source The source, either a QueryBuilder, PlanNode or TableIdentifier.
         * @return SourceNode The created table node.
         */
        private function createSourceNode (mixed $source) : SourceNode {
            if (is_string($source)) {
                $source = new TableIdentifier($source);
            }
            return new SourceNode($source);
        }

        /**
         * Extracts aggregate expressions from the selection list.
         * @param array $selects Array of ColumnIdentifier|Expression objects.
         * @return array A list of expressions.
         */
        private function extractAggregates (array $selects) : array {
            $aggregates = [];
        
            foreach ($selects as $select) {
                # 1. Typed Aggregate Objects
                if ($select instanceof AggregateExpression) {
                    $aggregates[] = $select;
                    continue;
                }
        
                # 2. Raw Expressions
                if ($select instanceof RawExpression) {
                    $rawSql = strtoupper(trim($select->getValue(), " \t\n\r\0\x0B()"));
                    if ($this->isAggregateString($rawSql)) {
                        $aggregates[] = $select;
                    }
                    continue;
                }
        
                # 3. Recursive Carriers (Arithmetic, Cases, etc.)
                # If an expression contains an aggregate (e.g., SUM(a) + SUM(b)), it must be treated as an aggregate calculation.
                if ($select instanceof ExpressionCarrier) {
                    foreach ($select->getExpressions() as $child) {
                        # Recurse one level to see if any child is an aggregate.
                        if ($this->extractAggregates([$child])) {
                            $aggregates[] = $select;
                            break;
                        }
                    }
                }
            }
        
            return $aggregates;
        }

        /**
         * Recursively finds the topmost ProjectNode in a plan tree.
         * @param PlanNode $node The root plan node to start searching from.
         * @return ProjectNode|null The found ProjectNode or null if none exists.
         */
        private function findTopProjection (PlanNode $node) : ?ProjectNode {
            if ($node instanceof ProjectNode) {
                return $node;
            }
            if ($node instanceof UnaryNode) {
                return $this->findTopProjection($node->getInput());
            }
            return null;
        }

        /**
         * Validates raw SQL strings for aggregate functions at the start.
         * @param string $sql The raw SQL string to check.
         */
        private function isAggregateString (string $sql) : bool {
            $sql = str_replace(' ', "", $sql);
            $patterns = ["SUM", "COUNT", "AVG", "MIN", "MAX", "GROUP_CONCAT", "STDEV"];
            foreach ($patterns as $pattern) {
                if (str_starts_with($sql, "$pattern(")) return true;
            }
            return false;
        }

        #################################################################################
        ################################# PLAN BUILDERS #################################
        #################################################################################

        /**
         * Builds the main execution plan based on the query type.
         * @param QueryState $state The state of the query.
         * @return PlanNode The constructed execution plan node.
         * @throws RuntimeException If the query type contains invalid combinations (e.g., RETURN with SELECT).
         */
        protected function buildExecutionPlan (QueryState $state) : PlanNode {
            $type = $state->getQueryType();
        
            $plan = match ($type) {
                QueryType::Delete => $this->buildExecutionPlanForDelete($state),
                QueryType::Insert => $this->buildExecutionPlanForInsert($state),
                QueryType::Select => $this->buildExecutionPlanForSelect($state),
                QueryType::Update => $this->buildExecutionPlanForUpdate($state),
                default => throw new RuntimeException("Unsupported query type for execution plan construction."),
            };
        
            if ($state->hasReturns()) {
                $plan = new ReturnNode($plan, $state->getReturns());
            }

            if ($state->hasCtes()) {
                $plan = new CteNode($plan, $state->getCtes(), $state->isRecursive());
            }
        
            return $plan;
        }

        /**
         * Builds an execution plan for DELETE queries.
         * @param QueryState $state The state of the DELETE query.
         * @return PlanNode The constructed execution plan node for the DELETE operation.
         * @throws RuntimeException If no target table is defined for the DELETE operation.
         */
        protected function buildExecutionPlanForDelete (QueryState $state) : PlanNode {
            $this->validateStateForDelete($state);

            $targetTable = $state->getSources()[0];
            $targetAlias = $state->getDeleteTarget() ?? $targetTable->getAlias();
            if (!$targetAlias) {
                throw new RuntimeException("Unable to determine the table alias for DELETE operation.");
            }

            $node = new SourceNode($targetTable);

            foreach ($state->getJoins() as $join) {
                $rightNode = $this->createSourceNode($join->getSource());
                $node = new JoinNode($node, $rightNode, $join);
            }

            foreach ($state->getWheres() as $expression) {
                $node = new FilterNode($node, $expression);
            }

            return new DeleteNode($node, $targetAlias);
        }

        /**
         * Builds an execution plan for INSERT queries, including UPSERT logic.
         * @param QueryState $state The state of the INSERT query.
         * @return PlanNode The constructed execution plan node for the INSERT operation.
         * @throws RuntimeException If column inference fails or if there are mismatches in column counts.
         */
        protected function buildExecutionPlanForInsert (QueryState $state) : PlanNode {
            $this->validateStateForInsert($state);

            # 1. Build the source; if it's a subquery, compile it.
            $values = $state->getValues();
            $source = $values instanceof QueryExpression ? $this->compileInternally($values->getState()) : $values;

            # 2. Infer columns from the subquery's projection if not explicitly defined.
            $columns = $state->getColumns();
            if ($values instanceof QueryExpression) {
                $projectNode = $this->findTopProjection($source);
                if ($projectNode === null) {
                    throw new RuntimeException("Unable to populate the target table from another table without a projection.");
                }
                if (empty($columns)) {
                    if (!$projectNode->isWildcard()) {
                        $columns = [];
                        $wildcardFound = false;
                        foreach ($projectNode->getExpressions() as $expression) {
                            if ($expression instanceof ColumnIdentifier && $expression->getName() === '*') {
                                $wildcardFound = true;
                                continue;
                            }
                            if ($wildcardFound) {
                                throw new RuntimeException("Unable to infer column names for INSERT from subquery projection containing wildcard (*) and other expressions.");
                            }
                            if (!($expression instanceof Aliasable)) {
                                throw new RuntimeException("Unable to infer column names for INSERT from subquery projection without aliases.");
                            }
                            $identifier = $expression->getAlias();
                            if (!$identifier && $expression instanceof ColumnIdentifier) {
                                $identifier = $expression->getName();
                            }
                            if (!$identifier) {
                                throw new RuntimeException("Unable to infer column names for INSERT from subquery projection without aliases.");
                            }
                            $columns[] = $identifier;
                        }
                        if ($wildcardFound) {
                            throw new RuntimeException("Unable to infer column names for INSERT from subquery projection containing wildcard (*) and other expressions.");
                        }
                    }
                }
                else {
                    # Validate that the number of columns matches the projection.
                    $projCount = count($projectNode->getExpressions());
                    if (count($columns) !== $projCount) {
                        throw new RuntimeException("Number of columns in INSERT does not match number of expressions in subquery projection.");
                    }
                }
            }
        
            # 3. Create an InsertNode.
            $plan = new InsertNode(
                new TargetNode($state->getTargetTable()),
                $columns,
                $source,
                $state->areConflictsIgnored()
            );
        
            # 4. Handle UPSERT logic if conflicts are defined.
            if ($state->hasConflict()) {
                $conflict = $state->getConflict();
                $plan = new UpsertNode($plan, $conflict->getData(), $conflict->getTargets(), $conflict->getStrategy(), $conflict->getFilter());
            }

            return $plan;
        }

        /**
         * Builds an execution plan for SELECT queries.
         * @param QueryState $state The state of the SELECT query.
         * @return PlanNode The constructed execution plan node for the SELECT operation.
         */
        protected function buildExecutionPlanForSelect (QueryState $state) : PlanNode {
            $this->validateStateForSelect($state);
        
            # 1. Build the Core Data Stream
            if (!$state->hasSources() && $state->hasSetOperations()) {
                # Case: A shell query for set operations. 
                # Extract the first set operation and build the tree from there.
                $setOperations = $state->getSetOperations();
                $firstSetOperation = array_shift($setOperations);
                $node = $this->compileInternally($firstSetOperation["query"]->getState());
            }
            else {
                # Case: Standard query.
                # Build the source logic WITHOUT a ProjectNode here.
                $sources = $state->getSources();
                if (empty($sources)) {
                    throw new RuntimeException("SELECT query must have at least one source if no set operations are defined.");
                }
                $node = $this->createSourceNode($sources[0]);
        
                foreach ($state->getJoins() as $join) {
                    $node = new JoinNode($node, $this->createSourceNode($join->getSource()), $join);
                }
        
                foreach ($state->getWheres() as $expression) {
                    $node = new FilterNode($node, $expression);
                }
        
                $aggregates = $this->extractAggregates($state->getSelects());
                if ($state->hasGroups() || !empty($aggregates)) {
                    $node = new AggregateNode($node, $aggregates, $state->getGroups());
                }
        
                foreach ($state->getHavings() as $expression) {
                    $node = new HavingNode($node, $expression);
                }
            }
        
            # 2. Chain Set Operations
            if ($state->hasSetOperations()) {
                $setOperations = $setOperations ?? $state->getSetOperations();
                foreach ($setOperations as $setOperation) {
                    # Recursively compile the branch (which will return its own ProjectNode).
                    $rightNode = $this->compileInternally($setOperation["query"]->getState());
                    $node = new SetOperationNode($node, $rightNode, $setOperation["type"]);
                }
        
                # 3. Handle the "Outer Wrapper"
                # If the set operation is aliased or filtered at the top level, wrap it in a SourceNode.
                if ($state->getAlias() !== null) {
                    $node = new SourceNode(new QueryExpression($node, $state), $state->getAlias());
                }
            }
        
            # 4. Apply Outer Filters (Where clauses applied to the Set Operation/Table wrapper)
            # This prevents them from being pushed down into a single set operation branch.
            if ($state->hasSetOperations() && $state->hasWheres()) {
                foreach ($state->getWheres() as $expression) {
                    $node = new FilterNode($node, $expression);
                }
            }
        
            # 5. Global Shaping (Order/Limit).
            if ($state->hasOrders()) {
                $node = new SortNode($node, $state->getOrders());
            }

            # 6. Final Projection (THE ONLY ProjectNode created by this method)
            $node = new ProjectNode($node, $state->getSelects() ?: [new ColumnIdentifier('*')], $state->isDistinct());
        
            # 7. Apply Limit & Locking
            if (($limit = $state->getLimit()) !== null) {
                $node = new LimitNode($node, $limit, $state->getOffset());
            }
            if (($lock = $state->getLockType()) !== LockType::None) {
                $node = new LockNode($node, $lock, $state->getLockTimeout(), $state->skipsLocked());
            }
        
            return $node;
        }

        /**
         * Builds an execution plan for UPDATE queries.
         * @param QueryState $state The state of the UPDATE query.
         * @return PlanNode The constructed execution plan node for the UPDATE operation.
         */
        protected function buildExecutionPlanForUpdate (QueryState $state) : PlanNode {
            $this->validateStateForUpdate($state);
            
            $targetTable = $state->getTargetTable();
            $node = new SourceNode(new TableIdentifier($targetTable));
        
            foreach ($state->getJoins() as $join) {
                $rightNode = $this->createSourceNode($join->getSource());
                $node = new JoinNode($node, $rightNode, $join);
            }
            
            foreach ($state->getWheres() as $expression) {
                $node = new FilterNode($node, $expression);
            }
            
            return new UpdateNode($node, $state->getAssignments());
        }

        ###############################################################################
        ################################## RESOLVERS ##################################
        ###############################################################################

        /**
         * Recursively resolves expressions and subqueries within an expression.
         * @param Expression $expression The expression to resolve.
         * @return Expression The resolved expression.
         */
        protected function resolveExpression (Expression $expression): Expression {
            # 1. Handle subqueries directly; its inner QueryBuilder must be compiled into a plan.
            if ($expression instanceof QueryExpression) {
                if ($expression->isCompiled()) {
                    return $expression; 
                }
                $plan = $this->compileInternally($expression->getState());
                $expression->setPlan($plan);
                return $expression;
            }

            # 2. Handle carriers (Joins, Windows, In-Expressions, etc.)
            if ($expression instanceof ExpressionCarrier) {
                $subs = $expression->getExpressions();
                $needsResolution = false;
        
                foreach ($subs as $sub) {
                    switch (true) {
                        case $sub instanceof QueryExpression && !$sub->isCompiled():
                        case $sub instanceof ExpressionCarrier:
                            $needsResolution = true;
                            break 2;
                    }
                }
        
                if ($needsResolution) {
                    foreach ($subs as $key => $sub) {
                        $subs[$key] = $this->resolveExpression($sub);
                    }
                    $expression->setExpressions($subs);
                }
            }
            
            return $expression;
        }
        
        /**
         * Recursively resolves expressions within INSERT values.
         * @param QueryState $state The query state containing the insert values.
         */
        protected function resolveInsertValues (QueryState $state): void {
            $values = $state->getValues();
        
            # Subquery as a value (e.g., INSERT INTO ... SELECT ...).
            if ($values instanceof Expression) {
                $state->setValues($this->resolveExpression($values));
                return;
            }
        
            # Standard multi-row array values.
            if (!is_array($values)) return;

            foreach ($values as $rk => $row) {
                if (!is_array($row)) continue;
                foreach ($row as $ck => $value) {
                    if (!($value instanceof Expression)) continue;
                    $values[$rk][$ck] = $this->resolveExpression($value);
                }
            }
            $state->setValues($values);
        }

        /**
         * Recursively resolves all expressions and subqueries within a query state.
         * @param QueryState $state The query state to resolve.
         */
        protected function resolveQueryState (QueryState $state) : void {
            foreach (QueryState::getBuckets() as $bucket) {
                # 'values' requires special recursive handling for multi-row inserts.
                if ($bucket === "values") {
                    $this->resolveInsertValues($state);
                    continue;
                }
        
                # Dynamically build method names: e.g., getWheres / setWheres.
                $getter = "get" . ucfirst($bucket);
                $setter = "set" . ucfirst($bucket);
        
                # Proceed only process if both methods exist.
                if (!method_exists($state, $getter) || !method_exists($state, $setter)) continue;

                $items = $state->$getter();
                
                # If the bucket is an array of expressions, resolve them.
                if (!is_array($items)) continue;

                foreach ($items as $key => $item) {
                    if (!($item instanceof Expression)) continue;
                    $items[$key] = $this->resolveExpression($item);
                }
                $state->$setter($items);
            }
        }

        ############################################################################
        ################################ VALIDATORS ################################
        ############################################################################

        /**
         * Validates the query state for DELETE operations.
         * @param QueryState $state The state of the query to validate.
         * @throws RuntimeException If the state contains invalid combinations for DELETE.
         */
        protected function validateStateForDelete (QueryState $state) : void {
            if ($state->getQueryType() !== QueryType::Delete) {
                throw new RuntimeException("Validation failed: The query must be of type DELETE.");
            }
        
            if ($state->hasAssignments()) {
                throw new RuntimeException("Validation failed: DELETE queries cannot contain assignments (SET).");
            }
        
            if ($state->hasValues()) {
                throw new RuntimeException("Validation failed: DELETE queries cannot contain VALUES.");
            }
        
            if ($state->hasColumns()) {
                throw new RuntimeException("Validation failed: DELETE queries cannot define a column list.");
            }
        
            if ($state->hasSelects()) {
                throw new RuntimeException("Validation failed: DELETE queries cannot have a SELECT projection. Use RETURNING if you need data back.");
            }
        
            if ($state->hasSetOperations()) {
                throw new RuntimeException("Validation failed: DELETE queries cannot contain UNION operations.");
            }
        
            if ($state->hasGroups() || $state->hasHavings()) {
                throw new RuntimeException("Validation failed: DELETE queries cannot contain GROUP BY or HAVING clauses.");
            }
        
            if ($state->getOffset() > 0) {
                throw new RuntimeException("Validation failed: DELETE queries cannot contain OFFSET clauses.");
            }
        }

        /**
         * Validates the query state for INSERT operations.
         * @param QueryState $state The state of the query to validate.
         * @throws RuntimeException If the state contains invalid combinations for INSERT.
         */
        protected function validateStateForInsert (QueryState $state) : void {
            if ($state->getQueryType() !== QueryType::Insert) {
                throw new RuntimeException("Validation failed: The query must be of type INSERT.");
            }

            if (!$state->hasValues()) {
                throw new RuntimeException("Validation failed: INSERT requires either VALUES or a SELECT source.");
            }
        
            if (!empty($state->getAssignments())) {
                throw new RuntimeException("Validation failed: INSERT queries cannot contain assignments (SET).");
            }
        
            if (!empty($state->getWheres())) {
                throw new RuntimeException("Validation failed: Top-level WHERE clauses are not allowed in INSERT.");
            }
        
            if (!empty($state->getJoins())) {
                throw new RuntimeException("Validation failed: Top-level JOIN clauses are not allowed in INSERT.");
            }
        
            if ($state->hasSetOperations()) {
                throw new RuntimeException("Validation failed: Top-level set operations are not allowed. Put unions inside the SELECT source.");
            }
        
            if (!empty($state->getGroups()) || !empty($state->getHavings())) {
                throw new RuntimeException("Validation failed: Top-level GROUP BY or HAVING is not allowed in INSERT.");
            }
        
            if ($state->getLimit() !== null || $state->getOffset() > 0) {
                throw new RuntimeException("Validation failed: INSERT queries cannot contain LIMIT or OFFSET.");
            }
        
            $values = $state->getValues();

            if (!empty($values) && is_array($values) && $state->hasColumns()) {
                foreach ($values as $row) {
                    if (is_array($row) && count($row) !== count($state->getColumns())) {
                        throw new RuntimeException("Validation failed: Number of values does not match number of columns in INSERT.");
                    }
                }
            }
        }

        /**
         * Validates the query state for SELECT operations.
         * @param QueryState $state The state of the query to validate.
         * @throws RuntimeException If the state contains invalid combinations for SELECT.
         */
        protected function validateStateForSelect (QueryState $state) : void {
            if ($state->getQueryType() !== QueryType::Select) {
                throw new RuntimeException("Validation failed: The query must be of type SELECT.");
            }
        
            if ($state->hasAssignments()) {
                throw new RuntimeException("Validation failed: SELECT queries cannot contain assignments (SET).");
            }
        
            if ($state->hasValues()) {
                throw new RuntimeException("Validation failed: SELECT queries cannot contain VALUES.");
            }
        
            if ($state->hasColumns()) {
                throw new RuntimeException("Validation failed: SELECT queries cannot define an INSERT-style column list.");
            }
            
            if (!$state->hasSelects() && !$state->hasSetOperations() && !$state->hasSources()) {
                throw new RuntimeException("Validation failed: SELECT query has no columns, no set operations, and no FROM source.");
            }
            
            if ($state->hasReturns()) {
                throw new RuntimeException("Validation failed: SELECT queries do not support RETURNING clauses.");
            }
        }

        /**
         * Validates the query state for UPDATE operations.
         * @param QueryState $state The state of the query to validate.
         * @throws RuntimeException If the state contains invalid combinations for UPDATE.
         */
        protected function validateStateForUpdate (QueryState $state) : void {
            if ($state->getQueryType() !== QueryType::Update) {
                throw new RuntimeException("Validation failed: The query must be of type UPDATE.");
            }
            
            if (empty($state->getAssignments())) {
                throw new RuntimeException("Validation failed: No assignments defined for UPDATE operation.");
            }
        
            if ($state->hasSetOperations()) {
                throw new RuntimeException("Validation failed: UPDATE queries cannot contain set operations.");
            }
        
            if ($state->getOffset() > 0) {
                throw new RuntimeException("Validation failed: UPDATE queries cannot contain OFFSET clauses.");
            }
        
            if ($state->hasValues()) {
                throw new RuntimeException("Validation failed: UPDATE queries cannot contain VALUES. Use assignments instead.");
            }
        
            if ($state->hasColumns()) {
                throw new RuntimeException("Validation failed: UPDATE queries cannot define a column list like an INSERT.");
            }

            if ($state->hasSelects()) {
                throw new RuntimeException("Validation failed: UPDATE queries cannot have a SELECT projection. Use RETURNING if you need data back.");
            }

            if ($state->hasGroups() || $state->hasHavings()) {
                throw new RuntimeException("Validation failed: UPDATE queries cannot contain top-level GROUP BY or HAVING clauses.");
            }
        }

        ######################################################
        #################  COMPILER METHODS  #################
        ######################################################

        /**
         * Compiles a non-root query state into an optimised execution plan.
         * @param QueryState $state The state of the query to compile.
         * @return PlanNode The compiled and optimised execution plan.
         */
        protected function compileInternally (QueryState $state) : PlanNode {
            $this->resolveQueryState($state);
            return $this->buildExecutionPlan($state);
        }

        /**
         * Compiles a query state into an optimised execution plan.
         * @param QueryState $state The state of the query to compile.
         * @return PlanNode The compiled and optimised execution plan.
         */
        public function compile (QueryState $state) : PlanNode {
            $this->resolveQueryState($state);
            return $this->buildExecutionPlan($state);
        }
    }
?>