<?php

    /*/
	 * Project Name:    Wingman — Database — Insert Node
	 * Created by:      Angel Politis
	 * Creation Date:   Dec 30 2025
	 * Last Modified:   Jan 13 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Enums\ConflictStrategy;
    use Wingman\Database\Expressions\BooleanExpression;

    /**
     * Represents an upsert operation in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UpsertNode extends UnaryNode {
        /**
         * The input plan node (InsertNode).
         * @var InsertNode
         */
        protected PlanNode $input;

        /**
         * The conflict resolution strategy.
         * @var ConflictStrategy
         */
        protected ConflictStrategy $strategy;

        /**
         * The assignments for update on conflict.
         * @var array<string, Expression>
         */
        protected array $updateAssignments = [];

        /**
         * The columns to check for conflicts.
         * @var string[]|null
         */
        protected ?array $conflictColumns = null;

        /**
         * An optional filter for the update operation.
         * @var BooleanExpression|null
         */
        protected ?BooleanExpression $filter;

        /**
         * Creates a new upsert node.
         * @param InsertNode $insertNode The insert node.
         * @param array<string, Expression> $updateAssignments The assignments for update on conflict.
         * @param string[]|null $conflictColumns The columns to check for conflicts.
         * @param ConflictStrategy|null $strategy The conflict resolution strategy.
         * @param BooleanExpression|null $filter An optional filter for the update operation.
         */
        public function __construct (InsertNode $insertNode, array $updateAssignments = [], ?array $conflictColumns = null, ?ConflictStrategy $strategy = null, ?BooleanExpression $filter = null) {
            parent::__construct($insertNode);
            $this->updateAssignments = $updateAssignments;
            $this->conflictColumns = $conflictColumns;
            $this->strategy = $strategy ?? ConflictStrategy::Update;
            $this->filter = $filter;
        }

        /**
         * Gets the conflict columns.
         * @return string[]|null The conflict columns or null if all columns are considered.
         */
        public function getConflictColumns () : ?array {
            return $this->conflictColumns;
        }

        /**
         * Gets the optional filter for the update operation.
         * @return BooleanExpression|null The filter expression or null if none is set.
         */
        public function getFilter (): ?BooleanExpression {
            return $this->filter;
        }

        /**
         * Gets the input insert node.
         * @return InsertNode The input insert node.
         */
        public function getInput () : InsertNode {
            return $this->input;
        }

        /**
         * Gets the conflict resolution strategy.
         * @return ConflictStrategy The conflict resolution strategy.
         */
        public function getStrategy () : ConflictStrategy {
            return $this->strategy;
        }

        /**
         * Gets the assignments for update on conflict.
         * @return array<string, Expression> The update assignments.
         */
        public function getUpdateAssignments (): array {
            return $this->updateAssignments;
        }

        /**
         * Explains an upsert node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the upsert node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $indent2 = str_pad("", ($depth + 1) * 3);
            $strategyName = $this->strategy->name;
            $targets = $this->conflictColumns ? '[' . implode(", ", $this->conflictColumns) . ']' : '*';
            
            $out = "{$indent}UPSERT (Strategy: {$strategyName}, Targets: $targets)" . PHP_EOL;

            $out .= "{$indent2}ON CONFLICT " . $this->strategy->value;
            
            if ($this->strategy === ConflictStrategy::Update) {
                $out .= " [" . implode(", ", array_keys($this->updateAssignments)) . ']';

                if ($this->filter) {
                    $out .= " WHERE " . $this->filter->explain();
                }

                $out .= PHP_EOL;
            }

            return $out . $this->input->explain($depth + 1);
        }
        
        /**
         * Creates a new upsert node with the given input.
         * @param PlanNode $input The new input plan node.
         * @return static A new upsert node instance.
         * @throws InvalidArgumentException If the input is not an InsertNode.
         */
        public function withInput (PlanNode $input) : static {
            if (!$input instanceof InsertNode) {
                throw new InvalidArgumentException("UpsertNode input must be an InsertNode.");
            }
            return new static($input, $this->updateAssignments, $this->conflictColumns, $this->strategy, $this->filter);
        }
    }
?>