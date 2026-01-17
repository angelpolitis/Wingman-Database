<?php
    /*/
     * Project Name:    Wingman — Database — Can Return Trait
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Traits namespace.
    namespace Wingman\Database\Traits;

    # Import the following classes to the current scope.
    use Wingman\Database\Expressions\RawExpression;
    use Wingman\Database\Expressions\ColumnIdentifier;

    /**
     * Trait that provides functionality for handling RETURNING clauses in queries.
     * @package Wingman\Database\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanReturn {
        /**
         * Specifies the columns to return after the insert operation.
         * @param string|array|RawExpression ...$columns The columns to return.
         * @return static The builder.
         */
        public function return (string|array|RawExpression ...$columns) : static {
            $columns = (count($columns) === 1 && is_array($columns[0])) ? $columns[0] : $columns;
            
            $standardised = [];
        
            foreach ($columns as $key => $column) {
                # 1. Handle Aliases: ['alias' => 'column']
                $alias = is_string($key) ? $key : null;
        
                # 2. Handle standard items: ['column', RawExpression]
                $standardised[] = is_string($column) ? new ColumnIdentifier($column, $alias) : $column;
            }
        
            $this->state->addReturns($standardised);
            
            return $this;
        }
    }
?>