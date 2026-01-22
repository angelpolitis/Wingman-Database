<?php
    /*/
	 * Project Name:    Wingman — Database — Conflict
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 04 2026
	 * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Objects namespace.
    namespace Wingman\Database\Objects;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\ConflictStrategy;
    use Wingman\Database\Expressions\BooleanExpression;

    /**
     * Represents a conflict in a database operation.
     * @package Wingman\Database\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Conflict {
        /**
         * The data for update assignments if applicable.
         * @var array
         */
        protected array $data;

        /**
         * The filter expression for conditional conflict handling.
         * @var BooleanExpression
         */
        protected ?BooleanExpression $filter;

        /**
         * The conflict resolution strategy.
         * @var ConflictStrategy
         */
        protected ConflictStrategy $strategy;

        /**
         * The columns or constraints to check for conflicts.
         * @var array
         */
        protected array $targets;

        /**
         * Creates a new column identifier.
         * @param array $targets The columns or constraints to check for conflicts.
         * @param ConflictStrategy $strategy The conflict resolution strategy.
         * @param array $data The data for update assignments if applicable.
         * @param BooleanExpression|null $filter The filter expression for conditional conflict handling.
         */
        public function __construct (array $targets, ConflictStrategy $strategy, array $data = [], ?BooleanExpression $filter = null) {
            $this->targets = $targets;
            $this->strategy = $strategy;
            $this->data = $data;
            $this->filter = $filter;
        }

        /**
         * Gets the data for update assignments if applicable.
         * @return array The data for update assignments.
         */
        public function getData () : array {
            return $this->data;
        }

        /**
         * Gets the filter expression for conditional conflict handling.
         * @return BooleanExpression|null The filter expression.
         */
        public function getFilter () : ?BooleanExpression {
            return $this->filter;
        }
        
        /**
         * Gets the conflict resolution strategy.
         * @return ConflictStrategy The conflict resolution strategy.
         */
        public function getStrategy () : ConflictStrategy {
            return $this->strategy;
        }

        /**
         * Gets the columns or constraints to check for conflicts.
         * @return array The columns or constraints to check for conflicts.
         */
        public function getTargets () : array {
            return $this->targets;
        }
    }
?>