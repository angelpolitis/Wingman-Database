<?php
    /*/
     * Project Name:    Wingman — Database — Proxies Query Trait
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Traits namespace.
    namespace Wingman\Database\Traits;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Objects\QueryState;

    /**
     * Trait that provides functionality for proxying query methods.
     * @package Wingman\Database\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait ProxiesQuery {
        /**
         * Explains the execution plan of an update query.
         * @return string The explanation of the query plan.
         */
        public function explain () : string {
            return $this->query->getPlan()->explain();
        }
        
        /**
         * Gets the execution plan of an update query.
         * @return PlanNode The execution plan node.
         */
        public function getPlan () : PlanNode {
            return $this->query->getPlan();
        }

        /**
         * Gets the state of a update query.
         * @return QueryState The current query state.
         */
        public function getState () : QueryState {
            return $this->state;
        }
    }
?>