<?php
    /*/
     * Project Name:    Wingman — Database — Filter
     * Created by:      Angel Politis
     * Creation Date:   Jan 20 2026
     * Last Modified:   Jan 20 2026
    /*/

    # Use the Database.Objects namespace.
    namespace Wingman\Database\Objects;

    # Import the following classes to the current scope.
    use Wingman\Database\Analysis\ExpressionAnalyser;
    use Wingman\Database\ExpressionParser;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\SQLDialect;

    /**
     * Represents a filter that can be converted into an expression.
     * @package Wingman\Database\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Filter {
        /**
         * The bindings extracted from the expression of a filter per dialect used.
         * @var array<class-string, array>
         */
        protected static array $bindingsCache = [];

        /**
         * The criteria used to create a filter.
         * @var array
         */
        protected array $criteria = [];

        /**
         * The expression produced by a filter.
         * @var Expression|null
         */
        protected ?Expression $expression = null;

        /**
         * Creates a new filter.
         * @param array $criteria The criteria to convert into an expression.
         */
        public function __construct (array $criteria) {
            $this->criteria = Filter::isCriteriaList($criteria) ? $criteria : [$criteria];
            if (empty($criteria)) return;
            $parser = new ExpressionParser();
            $this->expression = $parser->parseCriteria(...$this->criteria);
        }

        /**
         * Creates a new filter.
         * @param array $criteria The criteria to convert into an expression.
         * @return static The created filter.
         */
        public static function from (array $criteria) : static {
            return new static($criteria);
        }

        /**
         * Gets the bindings for the filter's expression for a specific SQL dialect.
         * @param SQLDialect $dialect The SQL dialect.
         * @return array The bindings.
         */
        public function getBindings (SQLDialect $dialect) : array {
            if (isset(static::$bindingsCache[$dialect::class])) {
                return static::$bindingsCache[$dialect::class];
            }
            $analyser = new ExpressionAnalyser($this->expression);
            $bindings = static::$bindingsCache[$dialect::class] = $analyser->getBindings($dialect);
            return $bindings;
        }

        /**
         * Gets the criteria used to create a filter.
         * @return array The criteria.
         */
        public function getCriteria () : array {
            return $this->criteria;
        }

        /**
         * Gets the expression produced by a filter.
         * @return Expression|null The expression or `null` if none exists.
         */
        public function getExpression () : ?Expression {
            return $this->expression;
        }

        /**
         * Determines whether the given criteria is a list of criteria arrays.
         * @param array $criteria The criteria to check.
         * @return bool True if the criteria is a list, false otherwise.
         */
        public static function isCriteriaList (array $criteria) : bool {
            return array_is_list($criteria) && is_array(reset($criteria));
        }
    }
?>