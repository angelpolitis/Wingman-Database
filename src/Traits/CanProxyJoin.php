<?php
    /*/
     * Project Name:    Wingman — Database — Can Proxy Join Trait
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Traits namespace.
    namespace Wingman\Database\Traits;

    # Import the following classes to the current scope.
    use Wingman\Database\Builders\QueryBuilder;
    use Wingman\Database\Enums\JoinType;
    
    /**
     * Trait that provides functionality for proxying join methods to the underlying query.
     * @package Wingman\Database\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanProxyJoin {
        /**
         * Adds a cross join to a query.
         * @param QueryBuilder|string|array $table The table to join.
         * @return static The current update builder instance.
         */
        public function crossJoin (QueryBuilder|string|array $table) : static {
            return $this->join($table, [], [], JoinType::Cross);
        }
    
        /**
         * Adds a full join to a query.
         * @param QueryBuilder|string|array $table The table to join.
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex joins.
         * @param string|array $foreignKey The foreign key(s) for the join.
         * @return static The current update builder instance.
         */
        public function fullJoin (
            QueryBuilder|string|array $table, 
            string|array|callable|null $localKey = null, 
            string|array|null $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $foreignKey, JoinType::Full);
        }
    
        /**
         * Adds an inner join to a query.
         * @param QueryBuilder|string|array $table The table to join.
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex joins.
         * @param string|array $foreignKey The foreign key(s) for the join.
         * @return static The current update builder instance.
         */
        public function innerJoin (
            QueryBuilder|string|array $table, 
            string|array|callable|null $localKey = null, 
            string|array|null $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $foreignKey, JoinType::Inner);
        }

        /**
         * Adds a join to a query.
         * @param QueryBuilder|string|array $table The table to join.
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex joins.
         * @param string|array|null $foreignKey The foreign key(s) for the join.
         * @param JoinType|string $type The type of join (default: INNER).
         * @return static The current update builder instance.
         */
        public function join (
            QueryBuilder|string|array $table, 
            string|array|callable|null $localKey = null, 
            string|array|null $foreignKey = null, 
            JoinType|string $type = JoinType::Inner
        ) : static {
            $this->query->join($table, $localKey, $foreignKey, JoinType::resolve($type));
            return $this;
        }
    
        /**
         * Adds a left join to a query.
         * @param QueryBuilder|string|array $table The table to join.
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex joins.
         * @param string|array $foreignKey The foreign key(s) for the join.
         * @return static The current update builder instance.
         */
        public function leftJoin (
            QueryBuilder|string|array $table, 
            string|array|callable|null $localKey = null, 
            string|array|null $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $foreignKey, JoinType::Left);
        }
    
        /**
         * Adds a right join to a query.
         * @param QueryBuilder|string|array $table The table to join.
         * @param string|array|callable|null $localKey The local key(s) for the join or a callback for complex joins.
         * @param string|array $foreignKey The foreign key(s) for the join.
         * @return static The current update builder instance.
         */
        public function rightJoin (
            QueryBuilder|string|array $table, 
            string|array|callable|null $localKey = null, 
            string|array|null $foreignKey = null
        ) : static {
            return $this->join($table, $localKey, $foreignKey, JoinType::Right);
        }
    }
?>