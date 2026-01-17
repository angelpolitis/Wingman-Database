<?php
    /*/
	 * Project Name:    Wingman — Database — Join Expression
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 07 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\JoinType;
    use Wingman\Database\Expressions\TableIdentifier;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;

    /**
     * Represents an SQL Join expression.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JoinExpression implements Expression, ExpressionCarrier {
        /**
         * The conditions for the join.
         * @var array
         */
        protected array $conditions;

        /**
         * The conjunction used to combine multiple conditions.
         * @var string
         */
        protected string $conjunction = "AND";

        /**
         * The source of a join expression.
         * @var TableIdentifier|Expression
         */
        protected TableIdentifier|Expression $source;

        /**
         * The type of join.
         * @var JoinType
         */
        protected JoinType $type;

        /**
         * Creates a new join expression.
         * @param TableIdentifier|Expression $source The source of the join.
         * @param JoinType $type The type of join.
         * @param array $conditions The conditions for the join.
         */
        public function __construct (TableIdentifier|Expression $source, JoinType $type, array $conditions = []) {
            $this->source = $source;
            $this->type = $type;
            $this->conditions = $conditions;
        }
        
        /**
         * Explains a join expression as a string, reflecting the compiled plan if available.
         * @param int $depth The depth of the explanation for formatting purposes.
         * @return string The explained join expression.
         */
        public function explain (int $depth = 0) : string {
            $pad = str_pad("", $depth * 3);
            $type = strtoupper($this->type->value);

            # 1. Render the source (Table or Subquery Plan).
            $renderSource = function ($source) use ($depth) {
                if ($source instanceof QueryExpression) {
                    return $source->getPlan() 
                        ? $source->getPlan()->explain($depth + 1) 
                        : $source->explain($depth + 1);
                }
                return (string) $source;
            };

            $sourceStr = $renderSource($this->source);
            
            if (empty($this->conditions)) {
                return "{$pad}{$type} JOIN {$sourceStr}";
            }

            # 2. Render the ON conditions (resolving plans if they are nested).
            $on = array_map(function ($condition) {
                return ($condition instanceof Expression) ? $condition->explain(0) : (string) $condition;
            }, $this->conditions);
            
            $conditionStr = implode(" {$this->conjunction} ", $on);

            return "{$pad}{$type} JOIN {$sourceStr} ON {$conditionStr}";
        }


        /**
         * Gets the conditions of a join expression.
         * @return array The conditions of the join expression.
         */
        public function getConditions () : array {
            return $this->conditions;
        }

        /**
         * Gets the conjunction used to combine multiple conditions.
         * @return string The conjunction used in the join expression.
         */
        public function getConjunction () : string {
            return $this->conjunction;
        }

        /**
         * Gets all expressions involved in a join expression.
         * @return array An array of expressions involved in the join.
         */
        public function getExpressions () : array {
            $expressions = [];
        
            # 1. Add the source if it is an expression (e.g., QueryExpression).
            if ($this->source instanceof Expression) {
                $expressions[] = $this->source;
            }
        
            # 2. Add all conditions (assuming they are Expression objects).
            foreach ($this->conditions as $condition) {
                if ($condition instanceof Expression) {
                    $expressions[] = $condition;
                }
            }
        
            return $expressions;
        }

        /**
         * Gets the alias or name of the joined table.
         * @return string|null The alias or name of the joined table, or `null` if not applicable.
         */
        public function getJoinedTable () : ?string {
            if ($this->source instanceof TableIdentifier) {
                return $this->source->getAlias() ?: $this->source->getName();
            }
            if ($this->source instanceof QueryExpression) {
                return $this->source->getAlias();
            }
            return null;
        }

        /**
         * Gets all table references used in a join expression.
         * @return array An array of table names referenced in the join expression.
         */
        public function getReferences () : array {
            $references = [];

            # 1. Reference from the joined table itself.
            if ($this->source instanceof TableIdentifier) {
                $references[] = $this->source->getName();
            }
            
            # 2. Get references from all child expressions (Source subquery + ON conditions).
            foreach ($this->getExpressions() as $expression) {
                $references = array_merge($references, $expression->getReferences());
            }

            return array_unique($references);
        }

        /**
         * Gets the source of a join expression.
         * @return TableIdentifier|Expression The source of the join expression.
         */
        public function getSource () : TableIdentifier|Expression {
            return $this->source;
        }

        /**
         * Gets the type of join.
         * @return JoinType The type of join.
         */
        public function getType () : JoinType {
            return $this->type;
        }

        /**
         * Determines whether a join expression is sargable.
         * @return bool Whether the expression is sargable.
         */
        public function isSargable () : bool {
            return false;
        }

        /**
         * Sets the expressions involved in a join expression.
         * @param array $expressions An array of expressions to set.
         * @return static The expression.
         */
        public function setExpressions (array $expressions) : static {
            # 1. If the original source was an Expression, the first element is the new source.
            if ($this->source instanceof Expression) {
                $this->source = array_shift($expressions);
            }

            # 2. Remaining expressions are the resolved conditions.
            $this->conditions = $expressions;

            return $this;
        }

        /**
         * Creates a clone of a join expression with new expressions.
         * @param array $expressions An array of expressions to set.
         * @return static The expression.
         */
        public function withExpressions (array $expressions) : static {
            $expression = clone $this;
            return $expression->setExpressions($expressions);
        }
    }
?>