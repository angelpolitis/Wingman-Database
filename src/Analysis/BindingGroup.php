<?php
    /*/
     * Project Name:    Wingman — Database — Binding Group
     * Created by:      Angel Politis
     * Creation Date:   Jan 11 2026
     * Last Modified:   Jan 11 2026
    /*/

    # Use the Database.Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use Wingman\Database\Enums\Component;

    /**
     * Represents a group of bindings categorised in bucket.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class BindingGroup {
        /**
         * The buckets of a binding group.
         * @var array<Component, array<Binding|static>>
         */
        protected array $buckets = [];

        /**
         * Adds a binding or nested binding group to a specific component bucket.
         * @param Component $component The component to which the item belongs.
         * @param Binding|self $item The binding or nested binding group to add.
         * @return static The binding group.
         */
        public function add (Component $component, Binding|self $item) : static {
            $this->buckets[$component->name][] = $item;
            return $this;
        }

        /**
         * Flattens a binding group recursively into a single array of binding values based on the specified order.
         * @param array<Component> $order The order in which to flatten the bindings.
         * @param array<Component>|null $subqueryOrder The default order to use for nested groups (if `null`, uses the current order).
         * @return array<mixed> The flattened array of binding values.
         */
        public function flatten (array $order, ?array $subqueryOrder = null) : array {
            $bindings = [];
            $childOrder = $subqueryOrder ?? $order;
        
            foreach ($order as $component) {
                foreach ($this->getBucket($component) as $item) {
                    if ($item instanceof Binding) {
                        $bindings[] = $item->getValue();
                    }
                    elseif ($item instanceof BindingGroup) {
                        $bindings = array_merge($bindings, $item->flatten($childOrder));
                    }
                }
            }
        
            return $bindings;
        }

        /**
         * Retrieves all items in a specific component bucket.
         * @param Component $component The component whose bucket to retrieve.
         * @return array<Binding|static> The items in the specified bucket.
         */
        public function getBucket (Component $component) : array {
            return $this->buckets[$component->name] ?? [];
        }
        
        /**
         * Retrieves all buckets in a binding group.
         * @return array<Component, array<Binding|static>> The buckets of the binding group.
         */
        public function getBuckets () : array {
            return $this->buckets;
        }
    }
?>