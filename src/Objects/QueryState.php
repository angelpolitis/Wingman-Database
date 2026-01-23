<?php
    /*/
	 * Project Name:    Wingman — Database — Query State
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 02 2026
	 * Last Modified:   Jan 23 2026
    /*/

    # Use the Database.Objects namespace.
    namespace Wingman\Database\Objects;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Builders\QueryBuilder;
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Enums\QueryType;
    use Wingman\Database\Enums\SetOperation;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\CteExpression;
    use Wingman\Database\Expressions\JoinExpression;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Expression;

    /**
     * Represents the internal state of a database query being constructed.
     * @package Wingman\Database\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class QueryState {
        /**
         * The global alias for the query (when used as a subquery).
         * @var string|null
         */
        protected ?string $alias = null;
        
        /**
         * The data bucket for assignments in an UPDATE query.
         * @var Expression[]
         */
        protected array $assignments = [];

        /**
         * The data buckets in the query state.
         * @var array
         */
        protected static array $buckets = [
            "selects",
            "sources",
            "wheres",
            "joins",
            "havings",
            "orders",
            "groups",
            "setOperations",
            "returns",
            "assignments",
            "values",
            "columns",
            "ctes"
        ];

        /**
         * The data bucket for columns in an INSERT query.
         * @var string[]
         */
        protected array $columns = [];

        /**
         * The conflict resolution strategy for mutation queries.
         * @var Conflict|null
         */
        protected ?Conflict $conflict = null;

        /**
         * Whether conflicts should be ignored during mutation queries.
         * @var bool
         */
        protected bool $conflictsIgnored = false;

        /**
         * The data bucket for Common Table Expressions (CTEs).
         * @var CteExpression[]
         */
        protected array $ctes = [];

        /**
         * Whether the query is a DELETE query.
         * @var bool
         */
        protected bool $delete = false;

        /**
         * The mutation target for DELETE queries (name or alias).
         * @var string|null
         */
        protected ?string $deleteTarget = null;

        /**
         * Whether a query is marked as DISTINCT.
         * @var bool
         */
        protected bool $distinct = false;

        /**
         * Whether to allow global updates (without WHERE clause).
         * @var bool
         */
        protected bool $globalUpdateAllowed = false;

        /**
         * The data bucket for GROUP BY clauses.
         * @var array
         */
        protected array $groups = [];

        /**
         * The data bucket for HAVING clauses.
         * @var array
         */
        protected array $havings = [];

        /**
         * Whether a query is recursive (for CTEs).
         * @var bool
         */
        protected bool $isRecursive = false;

        /**
         * The data bucket for JOIN clauses.
         * @var array
         */
        protected array $joins = [];
        
        /**
         * The LIMIT value for a query.
         * @var int|null
         */
        protected ?int $limit = null;

        /**
         * Whether to skip locked rows during a query.
         * @var bool
         */
        protected bool $lockedSkipped = false;

        /**
         * The lock timeout for a query (in seconds).
         * @var int|null
         */
        protected ?int $lockTimeout = null;

        /**
         * The lock type for a query.
         * @var LockType
         */
        protected LockType $lockType = LockType::None;

        /**
         * The OFFSET value for a query.
         * @var int
         */
        protected int $offset = 0;

        /**
         * The data bucket for ORDER BY clauses.
         * @var array
         */
        protected array $orders = [];
        
        /**
         * The data bucket for columns to be returned after execution.
         * @var array
         */
        protected array $returns = [];

        /**
         * The data bucket for SELECT clauses.
         * @var array
         */
        protected array $selects = [];

        /**
         * The set operations (UNION, INTERSECT, etc.) for a query.
         * @var array<array{type: SetOperation, query: QueryBuilder}>
         */
        protected array $setOperations = [];

        /**
         * The sources of a query; can be table names, [table => alias], or Query objects (subqueries):
         * E.g.: `['users', 'p' => 'profiles', 'stats' => $subQuery]`
         * @var array<QueryExpression|TableIdentifier>
         */
        protected array $sources = [];

        /**
         * The mutation target for INSERT/UPDATE queries.
         * @var TableIdentifier|null
         */
        protected ?TableIdentifier $targetTable = null;

        /**
         * The data bucket for values in an INSERT query.
         * @var array|QueryExpression
         */
        protected array|QueryExpression $values = [];
        
        /**
         * The data bucket for WHERE clauses.
         * @var array
         */
        protected array $wheres = [];

        /**
         * Adds an assignment for an UPDATE query.
         * @param string $column The column name.
         * @param Expression $value The value to assign.
         * @return static The state.
         */
        public function addAssignment (string $column, Expression $value) : static {
            $this->assignments[$column] = $value;
            return $this;
        }

        /**
         * Adds assignments for an UPDATE query.
         * @param (string|Expression)[] $assignments The assignments as `[column => value]`.
         * @return static The state.
         */
        public function addAssignments (array $assignments) : static {
            $this->assignments = array_merge($this->assignments, $assignments);
            return $this;
        }

        /**
         * Adds a Common Table Expression (CTE) to the query.
         * @param CteExpression $expression The CTE expression to add.
         * @return static The state.
         */
        public function addCte (CteExpression $expression) : static {
            $this->ctes[$expression->getName()] = $expression;
            return $this;
        }

        /**
         * Adds a GROUP BY clause to the query.
         * @param Expression $group The grouping expression to add.
         * @return static The state.
         */
        public function addGroup (Expression $group) : static {
            $this->groups[] = $group;
            return $this;
        }

        /**
         * Adds a HAVING clause to the query.
         * @param Expression $having The HAVING expression to add.
         * @return static The state.
         */
        public function addHaving (Expression $having) : static {
            $this->havings[] = $having;
            return $this;
        }

        /**
         * Adds a JOIN clause to the query.
         * @param JoinExpression $join The join expression to add.
         * @return static The state.
         */
        public function addJoin (JoinExpression $join) : static {
            $this->joins[] = $join;
            return $this;
        }

        /**
         * Adds an ORDER BY clause to the query.
         * @param Expression $order The ordering expression to add.
         * @return static The state.
         */
        public function addOrder (Expression $order) : static {
            $this->orders[] = $order;
            return $this;
        }

        /**
         * Adds columns to be returned after the query execution.
         * @param array $columns The column name(s) to add.
         * @return static The state.
         */
        public function addReturns (array $columns) : static {
            $this->returns = array_merge($this->returns, $columns);
            return $this;
        }

        /**
         * Adds a SELECT clause to the query.
         * @param Expression $select The select expression to add.
         * @return static The state.
         */
        public function addSelect (Expression $select) : static {
            $this->selects[] = $select;
            return $this;
        }

        /**
         * Adds multiple SELECT clauses to the query.
         * @param Expression[] $selects The select expressions to add.
         * @return static The state.
         */
        public function addSelects (array $selects) : static {
            $this->selects = array_merge($this->selects, $selects);
            return $this;
        }

        /**
         * Adds a set operation to the query.
         * @param SetOperation $operation The set operation to perform.
         * @param QueryBuilder $query The query to union with.
         * @return static The state.
         */
        public function addSetOperation (SetOperation $operation, QueryBuilder $query) : static {
            $this->setOperations[] = ["type" => $operation, "query" => $query];
            return $this;
        }

        /**
         * Adds multiple set operations to the query.
         * @param SetOperation $operation The set operation to perform.
         * @param QueryBuilder[] $operands The set operations to perform.
         * @return static The state.
         */
        public function addSetOperations (SetOperation $operation, array $operands) : static {
            foreach ($operands as $operand) {
                $this->setOperations[] = ["type" => $operation, "query" => new QueryExpression($operand)];
            }
            return $this;
        }

        /**
         * Adds a source to the FROM clause.
         * @param QueryBuilder|string|array|Expression $table The table name, subquery, or array of sources.
         * @param string|null $alias Optional alias for the table.
         * @return static The state.
         * @throws InvalidArgumentException If an invalid an source type is provided or alias is missing for subqueries.
         */
        public function addSource (QueryBuilder|string|array|Expression $table, ?string $alias = null) : static {
            $inputs = is_array($table) ? $table : [$alias => $table];
        
            foreach ($inputs as $key => $value) {
                $currentAlias = is_string($key) ? $key : $alias;
                $this->sources[] = static::normaliseSource($value, $currentAlias);
            }
            return $this;
        }

        /**
         * Adds a value to be inserted in an INSERT query.
         * @param mixed $value The value to add.
         * @return static The state.
         */
        public function addValue (mixed $value) : static {
            $this->values[] = $value;
            return $this;
        }

        /**
         * Adds a WHERE clause to the query.
         * @param Expression $where The WHERE expression to add.
         * @return static The state.
         */
        public function addWhere (Expression $where) : static {
            $this->wheres[] = $where;
            return $this;
        }

        /**
         * Adds multiple WHERE clauses to the query.
         * @param Expression[] $wheres The WHERE expressions to add.
         * @return static The state.
         */
        public function addWheres (array $wheres) : static {
            $this->wheres = array_merge($this->wheres, $wheres);
            return $this;
        }

        /**
         * Checks whether conflicts are ignored during mutation queries.
         * @return bool Whether conflicts are ignored.
         */
        public function areConflictsIgnored () : bool {
            return $this->conflictsIgnored;
        }

        /**
         * Gets the global alias for a query (when used as a subquery).
         * @return string|null The alias or `null` if not set.
         */
        public function getAlias () : ?string {
            return $this->alias;
        }

        /**
         * Gets the assignments for an UPDATE query.
         * @return array The list of assignments as `[column => value]`.
         */
        public function getAssignments () : array {
            return $this->assignments;
        }

        /**
         * Gets the list of data buckets in a query state.
         * @return array The list of bucket names.
         */
        public static function getBuckets () : array {
            return static::$buckets;
        }

        /**
         * Gets the columns involved in a query (for INSERT).
         * @return array The list of column names.
         */
        public function getColumns () : array {
            return $this->columns;
        }

        /**
         * Gets the conflict resolution strategy for mutation queries.
         * @return Conflict|null The Conflict object or `null` if not set.
         */
        public function getConflict () : ?Conflict {
            return $this->conflict;
        }

        /**
         * Gets the Common Table Expressions (CTEs) defined in a query.
         * @return CteExpression[] The list of CteExpression objects.
         */
        public function getCtes () : array {
            return $this->ctes;
        }

        /**
         * Gets the target table name for DELETE queries.
         * @return string|null The target table name or `null` if not set.
         */
        public function getDeleteTarget () : ?string {
            return $this->deleteTarget;
        }

        /**
         * Gets the GROUP BY clauses defined in a query.
         * @return array The list of grouping expressions.
         */
        public function getGroups () : array {
            return $this->groups;
        }

        /**
         * Gets the HAVING clauses defined in a query.
         * @return array The list of HAVING expressions.
         */
        public function getHavings () : array {
            return $this->havings;
        }

        /**
         * Gets the JOIN clauses defined in a query.
         * @return JoinExpression[] The list of JoinExpression objects.
         */
        public function getJoins () : array {
            return $this->joins;
        }

        /**
         * Gets the LIMIT value defined in a query.
         * @return int|null The limit or `null` if not set.
         */
        public function getLimit () : ?int {
            return $this->limit;
        }

        /**
         * Gets the lock timeout for a query (in seconds).
         * @return int|null The lock timeout or `null` if not set.
         */
        public function getLockTimeout () : ?int {
            return $this->lockTimeout;
        }

        /**
         * Gets the lock type for a query.
         * @return LockType The lock type.
         */
        public function getLockType () : LockType {
            return $this->lockType;
        }

        /**
         * Gets the OFFSET value defined in a query.
         * @return int The offset.
         */
        public function getOffset () : int {
            return $this->offset;
        }

        /**
         * Gets the ORDER BY clauses defined in a query.
         * @return array The list of ordering expressions.
         */
        public function getOrders () : array {
            return $this->orders;
        }

        /**
         * Gets the primary table from the FROM clause.
         * @return string|null The primary table name or `null` if not set.
         */
        public function getPrimaryTable () : ?string {
            if (empty($this->sources)) return null;

            $first = reset($this->sources);

            $table = null;

            if ($first instanceof Aliasable) {
                $table = $first->getAlias();
            }

            if (!$table && $first instanceof TableIdentifier) {
                $table = $first->getName();
            }
                
            return $table;
        }

        /**
         * Gets the type of the query based on its state.
         * @return QueryType The determined query type.
         */
        public function getQueryType () : QueryType {
            if (!empty($this->values)) return QueryType::Insert;
            if (!empty($this->assignments)) return QueryType::Update;
            if ($this->delete) return QueryType::Delete;
            return QueryType::Select;
        }

        /**
         * Collects all table and column references from all parts of the query state.
         * @return array A unique list of references.
         */
        public function getReferences () : array {
            $references = [];

            $allParts = array_merge(
                $this->selects,
                $this->sources,
                $this->wheres,
                $this->havings,
                $this->groups,
                $this->orders
            );

            foreach ($this->joins as $join) {
                $allParts[] = $join->source;
                foreach ($join->on as $condition) {
                    $allParts[] = $condition;
                }
            }

            foreach ($this->ctes as $cte) {
                $allParts[] = $cte;
            }

            foreach ($allParts as $part) {
                if ($part instanceof Expression) {
                    $references = array_merge($references, $part->getReferences());
                }
            }

            return array_unique($references);
        }

        /**
         * Gets the columns to be returned after the query execution.
         * @return array The list of column names.
         */
        public function getReturns () : array {
            return $this->returns;
        }

        /**
         * Gets the SELECT clauses defined in a query.
         * @return array The list of select expressions.
         */
        public function getSelects () : array {
            return $this->selects;
        }

        /**
         * Gets the set operations defined in a query.
         * @return array The list of set operations.
         */
        public function getSetOperations () : array {
            return $this->setOperations;
        }

        /**
         * Gets the sources defined in the FROM clause.
         * @return array<QueryExpression|TableIdentifier> The list of source expressions.
         */
        public function getSources () : array {
            return $this->sources;
        }

        /**
         * Gets the target table for mutation queries (INSERT/UPDATE).
         * @return TableIdentifier|null The target table name or `null` if not set.
         */
        public function getTargetTable () : ?TableIdentifier {
            return $this->targetTable;
        }

        /**
         * Gets the values to be inserted in an INSERT query.
         * @return array|QueryExpression The values array or a Query builder for subqueries.
         */
        public function getValues () : array|QueryExpression {
            return $this->values;
        }

        /**
         * Gets the WHERE clauses defined in a query.
         * @return array The list of WHERE expressions.
         */
        public function getWheres () : array {
            return $this->wheres;
        }

        /**
         * Checks whether there are any columns defined for the query.
         * @return bool Whether columns exist.
         */
        public function hasColumns () : bool {
            return !empty($this->columns);
        }

        /**
         * Checks whether there are any assignments defined for an UPDATE query.
         * @return bool Whether assignments exist.
         */
        public function hasAssignments () : bool {
            return !empty($this->assignments);
        }

        /**
         * Checks whether there are any conflict resolution strategies defined.
         * @return bool Whether conflict strategies exist.
         */
        public function hasConflict (): bool {
            return $this->conflict !== null;
        }

        /**
         * Checks whether there are any Common Table Expressions (CTEs) defined.
         * @return bool Whether CTEs exist.
         */
        public function hasCtes (): bool {
            return !empty($this->ctes);
        }

        /**
         * Checks whether there are any GROUP BY clauses defined.
         * @return bool Whether groupings exist.
         */
        public function hasGroups () : bool {
            return !empty($this->groups);
        }

        /**
         * Checks whether there are any HAVING clauses defined.
         * @return bool Whether HAVING clauses exist.
         */
        public function hasHavings (): bool {
            return !empty($this->havings);
        }

        /**
         * Checks whether there are any ORDER BY clauses defined.
         * @return bool Whether orderings exist.
         */
        public function hasOrders (): bool {
            return !empty($this->orders);
        }

        /**
         * Checks whether there are any columns to be returned after the query execution.
         * @return bool Whether return columns exist.
         */
        public function hasReturns () : bool {
            return !empty($this->returns);
        }

        /**
         * Checks whether there are any SELECT clauses defined.
         * @return bool Whether select clauses exist.
         */
        public function hasSelects () : bool {
            return !empty($this->selects);
        }

        /**
         * Checks whether there are any set operations defined.
         * @return bool Whether set operations exist.
         */
        public function hasSetOperations () : bool {
            return !empty($this->setOperations);
        }

        /**
         * Checks whether there are any sources defined in the FROM clause.
         * @return bool Whether sources exist.
         */
        public function hasSources () : bool {
            return !empty($this->sources);
        }

        /**
         * Checks whether there are any values to be inserted in an INSERT query.
         * @return bool Whether values exist.
         */
        public function hasValues () : bool {
            return !empty($this->values);
        }

        /**
         * Checks whether there are any WHERE clauses defined.
         * @return bool Whether WHERE clauses exist.
         */
        public function hasWheres () : bool {
            return !empty($this->wheres);
        }

        /**
         * Checks whether a query is marked as DISTINCT.
         * @return bool Whether the query is DISTINCT.
         */
        public function isDistinct () : bool {
            return $this->distinct;
        }

        /**
         * Checks whether a query is recursive (for CTEs).
         * @return bool Whether the query is recursive.
         */
        public function isRecursive () : bool {
            return $this->isRecursive;
        }
        
        /**
         * Normalises a raw input into a structured source object.
         * @param QueryBuilder|string|Expression $value The raw input value.
         * @param string|null $alias Optional alias for the source.
         * @return Expression The normalised source expression.
         */
        public static function normaliseSource (QueryBuilder|string|Expression $value, ?string $alias = null) : Expression {
            $source = null;

            if ($value instanceof QueryBuilder) {
                if ($alias === null) {
                    throw new InvalidArgumentException("An alias must be provided for subquery sources.");
                }
                $source = new QueryExpression($value, $alias);
            }
            elseif ($value instanceof Expression) {
                $source = $value;
            }
            elseif (is_string($value)) {
                $source = TableIdentifier::from($value);
            }

            if ($source instanceof Aliasable && $alias) {
                $source->alias($alias);
            }

            if (!$source) {
                throw new InvalidArgumentException("Invalid source type provided.");
            }

            return $source;
        }

        /**
         * Sets the global alias for a query (when used as a subquery).
         * @param string|null $alias The alias to set.
         * @return static The state.
         */
        public function setAlias (?string $alias) : static {
            $this->alias = $alias;
            return $this;
        }

        /**
         * Sets the assignments for an UPDATE query.
         * @param array<string, Expression> $assignments The assignments as `[column => value]`.
         * @return static The state.
         */
        public function setAssignments (array $assignments) : static {
            $this->assignments = $assignments;
            return $this;
        }

        /**
         * Sets the columns involved in a query (for INSERT).
         * @param array $columns The list of column names to set.
         * @return static The state.
         */
        public function setColumns (array $columns) : static {
            $this->columns = $columns;
            return $this;
        }

        /**
         * Sets the conflict resolution strategy for mutation queries.
         * @param Conflict $conflict The Conflict object to set.
         * @return static The state.
         */
        public function setConflict (Conflict $conflict) : static {
            $this->conflict = $conflict;
            return $this;
        }

        /**
         * Sets whether conflicts should be ignored during mutation queries.
         * @param bool $ignored Whether to ignore conflicts.
         * @return static The state.
         */
        public function setConflictsIgnored (bool $ignored) : static {
            $this->conflictsIgnored = $ignored;
            return $this;
        }

        /**
         * Sets the Common Table Expressions (CTEs) defined in a query.
         * @param CteExpression[] $ctes The list of CteExpression objects to set.
         * @return static The state.
         */
        public function setCtes (array $ctes) : static {
            $this->ctes = $ctes;
            return $this;
        }

        /**
         * Sets whether the query is a DELETE query.
         * @param bool $delete Whether the query is DELETE.
         * @return static The state.
         */
        public function setDelete (bool $delete) : static {
            $this->delete = $delete;
            return $this;
        }

        /**
         * Sets the target table name for DELETE queries.
         * @param string $table The target table name.
         * @return static The state.
         */
        public function setDeleteTarget (string $table) : static {
            $this->deleteTarget = $table;
            return $this;
        }

        /**
         * Sets whether a query is DISTINCT.
         * @param bool $distinct Whether the query is DISTINCT.
         * @return static The state.
         */
        public function setDistinct (bool $distinct) : static {
            $this->distinct = $distinct;
            return $this;
        }

        /**
         * Sets whether global updates are allowed (without WHERE clause).
         * @param bool $allowed Whether to allow global updates.
         * @return static The state.
         */
        public function setGlobalUpdatesAllowed (bool $allowed) : static {
            $this->globalUpdateAllowed = $allowed;
            return $this;
        }

        /**
         * Sets the HAVING clauses for the query.
         * @param Expression[] $havings The list of HAVING expressions to set.
         * @return static The state.
         */
        public function setHavings (array $havings) : static {
            $this->havings = $havings;
            return $this;
        }

        /**
         * Sets the LIMIT and OFFSET for the query.
         * @param int|null $limit The limit value or `null` for no limit.
         * @param int $offset The offset value.
         * @return static The state.
         */
        public function setLimitOffset (?int $limit, int $offset = 0) : static {
            $this->limit = $limit;
            $this->offset = $offset;
            return $this;
        }

        /**
         * Sets the JOIN clauses for the query.
         * @param JoinExpression[] $joins The list of JoinExpression objects to set.
         * @return static The state.
         */
        public function setJoins (array $joins) : static {
            $this->joins = $joins;
            return $this;
        }

        /**
         * Sets the lock timeout for the query.
         * @param int|null $seconds The lock timeout in seconds or `null` for no timeout.
         * @return static The state.
         */
        public function setLockTimeout (?int $seconds) : static {
            $this->lockTimeout = $seconds;
            return $this;
        }

        /**
         * Sets the lock type for the query.
         * @param LockType $type The lock type to set.
         * @return static The state.
         */
        public function setLockType (LockType $type) : static {
            $this->lockType = $type;
            return $this;
        }

        /**
         * Sets whether to skip locked rows during a query.
         * @param bool $skipped Whether to skip locked rows.
         * @return static The state.
         */
        public function setLockedSkipped (bool $skipped) : static {
            $this->lockedSkipped = $skipped;
            return $this;
        }

        /**
         * Sets the ORDER BY clauses for the query.
         * @param Expression[] $orders The list of ordering expressions to set.
         * @return static The state.
         */
        public function setOrders (array $orders) : static {
            $this->orders = $orders;
            return $this;
        }

        /**
         * Sets the columns to be returned after the query execution.
         * @param Expression[] $returns The list of column names to set.
         * @return static The state.
         */
        public function setReturns (array $returns) : static {
            $this->returns = $returns;
            return $this;
        }

        /**
         * Sets the SELECT clauses for the query.
         * @param Expression[] $orders The list of ordering expressions to set.
         * @return static The state.
         */
        public function setSelects (array $selects) : static {
            $this->selects = $selects;
            return $this;
        }

        /**
         * Sets the sources for the FROM clause.
         * @param Expression[] $sources The list of source expressions to set.
         * @return static The state.
         */
        public function setSources (array $sources) : static {
            $this->sources = $sources;
            return $this;
        }

        /**
         * Sets the target table for mutation queries (INSERT/UPDATE/DELETE).
         * @param string|TableIdentifier $table The target table name or TableIdentifier.
         * @return static The state.
         */
        public function setTargetTable (string|TableIdentifier $table) : static {
            if ($table instanceof TableIdentifier) {
                $this->targetTable = $table;
                return $this;
            }
            $this->targetTable = TableIdentifier::from($table);
            return $this;
        }

        /**
         * Sets the set operations for the query.
         * @param array $operations The list of set operations to set.
         * @return static The state.
         */
        public function setSetOperations (array $operations) : static {
            $this->setOperations = $operations;
            return $this;
        }

        /**
         * Sets the values to be inserted in an INSERT query.
         * @param array|QueryExpression $values The values array or a subquery.
         * @return static The state.
         */
        public function setValues (array|QueryExpression $values) : static {
            $this->values = $values;
            return $this;
        }

        /**
         * Sets the WHERE clauses for the query.
         * @param array $wheres The list of WHERE expressions to set.
         * @return static The state.
         */
        public function setWheres (array $wheres) : static {
            $this->wheres = $wheres;
            return $this;
        }

        /**
         * Checks whether to skip locked rows during a query.
         * @return bool Whether to skip locked rows.
         */
        public function skipsLocked () : bool {
            return $this->lockedSkipped;
        }
    }
?>