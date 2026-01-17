<?php
    /*/
	 * Project Name:    Wingman — Database — Lock Node
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 17 2026
	 * Last Modified:   Jan 17 2026
    /*/

    # Use the Database.Plan namespace.
    namespace Wingman\Database\Plan;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\LockType;
    use Wingman\Database\Interfaces\PlanNode;

    /**
     * Represents an aggregate operation in a query plan.
     * @package Wingman\Database\Plan
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LockNode extends UnaryNode {
        /**
         * The type of lock.
         * @var LockType
         */
        protected LockType $type;

        /**
         * The wait timeout in seconds (null for default, 0 for no wait).
         * @var int|null
         */
        protected ?int $waitTimeout = null;

        /**
         * Whether locked rows are skipped.
         * @var bool
         */
        protected bool $lockedSkipped = false;

        /**
         * Creates a new lock node.
         * @param PlanNode $input The input plan node.
         * @param LockType $type The type of lock.
         * @param int|null $waitTimeout The wait timeout in seconds (null for default, 0 for no wait).
         * @param bool $lockedSkipped Whether locked rows are skipped.
         */
        public function __construct (PlanNode $input, LockType $type, ?int $waitTimeout = null, bool $lockedSkipped = false) {
            parent::__construct($input);
            $this->type = $type;
            $this->waitTimeout = $waitTimeout;
            $this->lockedSkipped = $lockedSkipped;
        }

        /**
         * Explains a Lock node.
         * @param int $depth The depth of the explanation (for indentation).
         * @return string The explanation of the Lock node.
         */
        public function explain (int $depth = 0) : string {
            $indent = str_pad("", $depth * 3);
            $meta = [];

            if ($this->lockedSkipped) {
                $meta[] = "SKIP LOCKED";
            }

            if ($this->waitTimeout === 0) {
                $meta[] = "NOWAIT";
            }
            elseif ($this->waitTimeout > 0) {
                $meta[] = "WAIT {$this->waitTimeout}s";
            }

            $modifierStr = !empty($meta) ? " (" . implode(", ", $meta) . ")" : "";
            
            $out = "{$indent}LOCK [{$this->type->name}]{$modifierStr}" . PHP_EOL;

            $out .= $this->input->explain($depth + 1);

            return $out;
        }

        /**
         * Gets the type of lock.
         * @return LockType The lock type.
         */
        public function getType () : LockType {
            return $this->type;
        }

        /**
         * Gets the wait timeout.
         * @return int|null The wait timeout in seconds (null for default, 0 for no wait).
         */
        public function getWaitTimeout () : ?int {
            return $this->waitTimeout;
        }

        /**
         * Indicates whether locked rows are skipped.
         * @return bool True if locked rows are skipped, false otherwise.
         */
        public function skipsLocked () : bool {
            return $this->lockedSkipped;
        }
    }
?>