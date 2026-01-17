<?php
    /*/
     * Project Name:    Wingman — Database — Scope Edge
     * Created by:      Angel Politis
     * Creation Date:   Jan 08 2026
     * Last Modified:   Jan 08 2026
    /*/

    # Use the Database\Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\ScopeDependencyType;
    
    /**
     * Represents a directed edge between two scopes in a query analysis graph.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class ScopeEdge {
        /**
         * Creates a new scope edge from one scope to another with a specified dependency type.
         * @param Scope $from The source scope of the edge.
         * @param Scope $to The target scope of the edge.
         * @param ScopeDependencyType $type The type of dependency represented by the edge.
         */
        public function __construct (
            protected Scope $from,
            protected Scope $to,
            protected ScopeDependencyType $type
        ) {}

        /**
         * Gets the source of a scope edge.
         * @return Scope The source scope.
         */
        public function getSource () : Scope {
            return $this->from;
        }

        /**
         * Gets the target of a scope edge.
         * @return Scope The target scope.
         */
        public function getTarget () : Scope {
            return $this->to;
        }

        /**
         * Gets the type of dependency represented by a scope edge.
         * @return ScopeDependencyType The dependency type.
         */
        public function getType () : ScopeDependencyType {
            return $this->type;
        }
    }
?>