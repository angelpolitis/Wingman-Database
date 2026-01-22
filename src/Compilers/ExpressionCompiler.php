<?php
    /*/
	 * Project Name:    Wingman — Database — Expression Compiler
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 18 2026
	 * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Compilers namespace.
    namespace Wingman\Database\Compilers;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Database\Expressions\AggregateExpression;
    use Wingman\Database\Expressions\BooleanExpression;
    use Wingman\Database\Expressions\CaseExpression;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\ComparisonExpression;
    use Wingman\Database\Expressions\ExistsExpression;
    use Wingman\Database\Expressions\InExpression;
    use Wingman\Database\Expressions\JoinExpression;
    use Wingman\Database\Expressions\LiteralExpression;
    use Wingman\Database\Expressions\NullExpression;
    use Wingman\Database\Expressions\Predicate;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Expressions\RawExpression;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Interfaces\SQLDialect;

    /**
     * Compiles expressions into SQL fragments.
     * @package Wingman\Database\Compilers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ExpressionCompiler {
        /**
         * SQL dialect for database-specific syntax.
         */
        protected SQLDialect $dialect;

        /**
         * Creates a new expression compiler.
         * @param SQLDialect $dialect The SQL dialect to use.
         */
        public function __construct (SQLDialect $dialect) {
            $this->dialect = $dialect;
        }

        /**
         * Compiles a given expression into its SQL representation.
         * @param Expression $expression The expression to compile.
         * @return string The SQL fragment.
         */
        public function compile (Expression $expression, bool $aliasAllowed = false) : string {
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
         * Compiles an AggregateExpression into SQL.
         * @param AggregateExpression $expression The aggregate expression.
         * @return string The SQL fragment.
         */
        public function compileAggregate (AggregateExpression $expression) : string {
            $function = $expression->getFunction();
            $operands = array_map(fn ($operand) => $this->compile($operand), $expression->getExpressions());
            
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
        public function compileBooleanExpression (Expression $expression, bool $aliasAllowed = false, ?string $parentConjunction = null) : string {
            if (!($expression instanceof BooleanExpression)) {
                return $this->compile($expression, $aliasAllowed);
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
         * Compiles a CaseExpression into SQL.
         * @param CaseExpression $expression The case expression.
         * @return string The SQL fragment.
         */
        public function compileCaseExpression (CaseExpression $expression) : string {
            $parts = [];
            $subject = $expression->getSubject();
            $subjectSql = $subject ? $this->compile($subject) : null;
            $caseHeader = $subjectSql ? "CASE $subjectSql" : "CASE";
            $parts[] = $caseHeader;

            $conditions = $expression->getConditions();
            $results = $expression->getResults();

            foreach ($conditions as $index => $condition) {
                $conditionSql = $this->compile($condition);
                $resultSql = $this->compile($results[$index]);
                $parts[] = "WHEN {$conditionSql} THEN {$resultSql}";
            }

            if ($default = $expression->getDefault()) {
                $defaultSql = $this->compile($default);
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
        public function compileColumn (ColumnIdentifier $column) : string {
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
        public function compileComparisonExpression (ComparisonExpression $expression) : string {
            $operator = strtoupper($expression->getOperator());
            $operands = array_map(fn ($operand) => $this->compile($operand), $expression->getExpressions());
            return implode(" {$operator} ", $operands);
        }

        /**
         * Compiles an EXISTS expression into SQL.
         * @param ExistsExpression $expression The EXISTS expression.
         * @return string The compiled SQL fragment.
         * @throws RuntimeException If the subquery is not a PlanNode.
         */
        public function compileExistsExpression (ExistsExpression $expression) : string {
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
         * Compiles an InExpression into SQL.
         * @param InExpression $expr The IN expression.
         * @return string The SQL fragment.
         */
        public function compileInExpression (InExpression $expr) : string {
            $operand = $this->dialect->quoteIdentifier($expr->getOperand());
            $values = $expr->getValue();
            $op = $expr->isNegated() ? "NOT IN" : "IN";
        
            if ($values instanceof QueryExpression) {
                $subSql = $this->compile($values);
                return "{$operand} {$op} ({$subSql})";
            }
        
            $placeholders = implode(", ", array_fill(0, count($values), '?'));
            
            return "{$operand} {$op} ({$placeholders})";
        }
        
        /**
         * Compiles a JOIN clause from the join bucket entry.
         * @param JoinExpression $join The join definition.
         * @param Predicate[] $predicates List of predicates associated with the join.
         * @return string The compiled JOIN SQL fragment.
         */
        public function compileJoinExpression (JoinExpression $join, array $predicates) : string {
            $type = $join->getType()->value;
            $right = $join->getSource();
            $conditions = $join->getConditions();
            $conjunction = $join->getConjunction();

            # 1. Compile the right-hand source (table or subquery).
            if ($right instanceof TableIdentifier) {
                $sourceSQL = $this->compile($right, true);
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
                $conditionSQLs[] = $this->compile($condition);
            }
            foreach ($predicates as $predicate) {
                $conditionSQLs[] = $this->compile($predicate);
            }
            $onClause = !empty($conditionSQLs) ? " ON " . implode(" {$conjunction} ", $conditionSQLs) : "";

            return "{$type} JOIN {$sourceSQL}{$onClause}";
        }
        
        /**
         * Compiles a LiteralExpression into SQL.
         * @param LiteralExpression $expression The literal expression.
         * @param bool $aliasAllowed Whether to include aliases in the output.
         * @return string The SQL fragment.
         */
        public function compileLiteral (LiteralExpression $expression, bool $aliasAllowed = false) : string {
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
        public function compileNullExpression (NullExpression $expr) : string {
            $column = $this->dialect->quoteIdentifier($expr->getOperand());
            $op = $expr->isNegated() ? "IS NOT NULL" : "IS NULL";
            return "{$column} {$op}";
        }

        /**
         * Compiles a raw expression into SQL.
         * @param RawExpression $expr The raw expression.
         * @return string The SQL fragment.
         */
        public function compileRawExpression (RawExpression $expr) : string {
            return $expr->getValue();
        }

        /**
         * Compiles multiple source expressions into a FROM clause.
         * @param Expression[] $sources The source expressions.
         * @return string The compiled FROM clause.
         */
        public function compileSourceExpressions (array $sources) : string {
            $parts = [];
            foreach ($sources as $source) {
                $parts[] = $this->compile($source, true);
            }
            return "FROM " . implode(", ", $parts);
        }

        /**
         * Compiles a subquery into SQL.
         * @param QueryExpression $query The subquery expression.
         * @return string The compiled SQL fragment.
         * @param bool $aliasAllowed Whether to include aliases in the output.
         */
        public function compileSubquery (QueryExpression $query, bool $aliasAllowed = false) : string {
            $sql = PlanCompiler::withDialect($this->dialect)->compile($query->getPlan());
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
        public function compileTable (TableIdentifier $table) : string {
            return $this->dialect->quoteIdentifier($table->getName());
        }
    }
?>