<?php
    /*/
     * Project Name:    Wingman — Database — Expression Parser
     * Created by:      Angel Politis
     * Creation Date:   Jan 18 2026
     * Last Modified:   Jan 20 2026
    /*/

    # Use the Database namespace.
    namespace Wingman\Database;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\BooleanExpression;
    use Wingman\Database\Expressions\ColumnIdentifier;
    use Wingman\Database\Expressions\ComparisonExpression;
    use Wingman\Database\Expressions\InExpression;
    use Wingman\Database\Expressions\LiteralExpression;
    use Wingman\Database\Expressions\RawExpression;
    use Wingman\Database\Interfaces\Expression;

    /**
     * Represents an expression parser.
     * @package Wingman\Database
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ExpressionParser {
        /**
         * Builds a comparison segment.
         * @param mixed $column The column identifier.
         * @param string $operator The comparison operator.
         * @param mixed $value The value to compare against.
         * @return Expression The resulting comparison expression.
         */
        protected function buildSegment (mixed $column, string $operator, mixed $value) : mixed {
            $left = $this->parseValue($column, true);
            $operator = strtoupper($operator);

            # 1. Special Case: NULL handling (converts = to IS and != to IS NOT)
            if ($value === null) {
                $op = ($operator === "!=" || $operator === "<>") ? "IS NOT" : "IS";
                return new ComparisonExpression($left, $op, new LiteralExpression(null));
            }

            # 2. Special Case: IN handling
            if ($operator === "IN" || (is_array($value) && !isset($value["raw"]))) {
                return new InExpression($left, array_map(fn($v) => $this->parseValue($v), (array)$value));
            }

            # 3. General Case: Other comparisons.
            $right = $this->parseValue($value);

            return new ComparisonExpression($left, $operator, $right);
        }

        /**
         * Parses a value into an expression.
         * @param mixed $value The value to parse.
         * @param bool $forceIdentifier Whether to force parsing as an identifier.
         * @return Expression The resulting expression.
         */
        protected function parseValue (mixed $value, bool $forceIdentifier = false) : mixed {
            if ($value instanceof Expression) return $value;

            if (!is_string($value)) {
                return new LiteralExpression($value);
            }

            # 1. Raw Expression: {{expression}}.
            if (preg_match('/^\{\{(.*)\}\}$/', $value, $matches)) {
                return new RawExpression($matches[1]);
            }

            # 2. Identifier: @column (optionally @table.column).
            if (str_starts_with($value, '@')) {
                $name = substr($value, 1);
                if (str_contains($name, '.')) {
                    [$table, $col] = explode('.', $name, 2);
                    return new ColumnIdentifier($col, $table);
                }
                return new ColumnIdentifier($name);
            }

            # 3. Forced Identifier (keys in associative arrays).
            if ($forceIdentifier) {
                return new ColumnIdentifier($value);
            }

            # 4. Literal.
            return new LiteralExpression($value);
        }

        /**
         * Determines whether a value is a valid SQL operator.
         * @param mixed $value The value to check.
         * @return bool Whether the value is a valid operator.
         */
        public static function isOperator (mixed $value) : bool {
            if (!is_string($value)) return false;
            $operators = ['=', "!=", "<>", '<', '>', "<=", ">=", "LIKE", "NOT LIKE", "IN", "NOT IN", "IS", "IS NOT"];
            return in_array(strtoupper($value), $operators);
        }

        /**
         * Parses criteria arrays into an expression.
         * @param array ...$criteriaGroups The criteria groups to parse.
         * @return Expression The resulting expression.
         */
        public function parseCriteria (array ...$criteriaGroups) : Expression {
            $expressions = [];

            foreach ($criteriaGroups as $criteria) {
                # 1. Handle the "Operator" case: ["@age", ">", "val"]
                if (isset($criteria[0], $criteria[1], $criteria[2]) && static::isOperator($criteria[1])) {
                    $expressions[] = $this->buildSegment($criteria[0], $criteria[1], $criteria[2]);
                    continue;
                }

                # 2. Handle the "Short Equality" case: ["@age", 25]
                if (isset($criteria[0]) && array_key_exists(1, $criteria) && count($criteria) === 2) {
                    $expressions[] = $this->buildSegment($criteria[0], '=', $criteria[1]);
                    continue;
                }

                # 3. Handle the "Associative" case: ["status" => "active", "type" => 1]
                foreach ($criteria as $column => $value) {
                    if (!str_starts_with($column, '@')) $column = "@$column";
                    $expressions[] = $this->buildSegment($column, '=', $value);
                }
            }

            if (count($expressions) === 1) return $expressions[0];

            return new BooleanExpression($expressions, "AND");
        }
    }
?>