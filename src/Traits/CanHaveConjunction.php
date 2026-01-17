<?php
    /*/
     * Project Name:    Wingman — Database — Can Have Conjunction Trait
     * Created by:      Angel Politis
     * Creation Date:   Jan 04 2026
     * Last Modified:   Jan 04 2026
    /*/

    # Use the Database.Traits namespace.
    namespace Wingman\Database\Traits;
    
    /**
     * Trait that provides functionality for handling conjunctions.
     * @package Wingman\Database\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanHaveConjunction {
        /**
         * The conjunction of an instance.
         * @var string
         */
        protected string $conjunction = "AND";

        /**
         * Gets the conjunction of an instance.
         * @return string|null The conjunction.
         */
        public function getConjunction () : string {
            return $this->conjunction;
        }

        /**
         * Sets the conjunction of an instance.
         * @param string $conjunction The conjunction to set.
         * @return static The instance itself for method chaining.
         */
        public function setConjunction (string $conjunction) : static {
            $this->conjunction = $conjunction;
            return $this;
        }
    }
?>