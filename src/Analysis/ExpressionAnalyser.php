<?php
    /*/
     * Project Name:    Wingman — Database — Expression Analyser
     * Created by:      Angel Politis
     * Creation Date:   Jan 18 2026
     * Last Modified:   Jan 18 2026
    /*/

    # Use the Database.Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use SplObjectStorage;
    use Wingman\Database\Enums\Component;
    use Wingman\Database\Expressions\LiteralExpression;
    use Wingman\Database\Expressions\QueryExpression;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Interfaces\ExpressionCarrier;
    use Wingman\Database\Interfaces\SQLDialect;

    /**
     * Performs analysis on expressions to extract useful information.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ExpressionAnalyser {
        /**
         * The expression being analysed.
         * @var Expression
         */
        protected Expression $expression;

        /**
         * Creates a new expression analyser.
         * @param Expression $expression The expression.
         */
        public function __construct (Expression $expression) {
            $this->expression = $expression;
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
                $planAnalyser = new PlanAnalyser($expression->getPlan());
                $planAnalyser->analyse(); 
                $results[] = $planAnalyser->getBindingTree();
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
         * Gets all bindings in the lexical order defined by an SQL dialect.
         * @param SQLDialect|string $dialect The SQL dialect or its class name.
         * @return Binding[] An array of bindings in the order defined by the SQL dialect.
         */
        public function getBindings (SQLDialect|string $dialect) : array {
            $dialect = ($dialect instanceof SQLDialect) ? $dialect : new $dialect();
            $group = $this->getBindingTree();
            return $group->flatten($dialect->getSelectOrder(), $dialect->getSelectOrder());
        }

        /**
         * Generates a hierarchical binding group representing lexical bindings.
         * @return BindingGroup The root binding group containing all lexical bindings.
         */
        public function getBindingTree () : BindingGroup {
            $rootGroup = new BindingGroup();
            $visited = new SplObjectStorage();
            $literals = $this->extractLiterals($this->expression, $visited);
            
            foreach ($literals as $item) {
                # We assume standalone expressions are logically part of the WHERE component.
                $rootGroup->add(Component::Where, $item);
            }

            return $rootGroup;
        }
    }
?>