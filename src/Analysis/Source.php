<?php
    /*/
     * Project Name:    Wingman — Database — Source
     * Created by:      Angel Politis
     * Creation Date:   Jan 07 2026
     * Last Modified:   Jan 09 2026
    /*/

    # Use the Database Analysis namespace.
    namespace Wingman\Database\Analysis;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\PlanNode;
    use Wingman\Database\Plan\SourceNode;
    use Wingman\Database\Expressions\JoinExpression;
    use Wingman\Database\Plan\BinaryNode;
    use Wingman\Database\Plan\JoinNode;
    use Wingman\Database\Plan\UnaryNode;

    /**
     * Represents a source in a query plan, consisting of source nodes and their join expressions.
     * @package Wingman\Database\Analysis
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Source {
        /**
         * The nodes that constitute a source.
         * @var SourceNode[]
         */
        protected array $nodes;

        /**
         * The expressions that join the source nodes of a source.
         * @var JoinExpression[]
         */
        protected array $joinExpressions = [];

        /**
         * Creates a new source with the given nodes and join expressions.
         * @param SourceNode[] $nodes The nodes that constitute the source.
         * @param JoinExpression[] $joinExpressions The expressions that join the source nodes.
         */
        public function __construct (array $nodes, array $joinExpressions = []) {
            $this->nodes = $nodes;
            $this->joinExpressions = $joinExpressions;
        }

        public static function fromNode (PlanNode $node) : ?static {
            $current = $node;
            while (true) {
                if ($current instanceof SourceNode) {
                    return new static([$current]);
                }

                if ($current instanceof UnaryNode) {
                    $current = $current->getInput();
                    continue;
                }

                if ($current instanceof JoinNode) {
                    $sources = $current->findSources();
                    $nodes = [];
                    $expressions = [];
                    foreach ($sources as $source) {
                        /** @var SourceNode $sourceNode */
                        $sourceNode = $source["source"];
                        $nodes[] = $sourceNode;
                        if ($source["expression"] !== null) {
                            $expressions[] = $source["expression"];
                        }
                    }
                    return new static($nodes, $expressions);
                }

                if ($current instanceof BinaryNode) {
                    return null;
                }
            }
        }

        /**
         * Gets the join expressions that connect the source nodes of a source.
         * @return JoinExpression[] The join expressions.
         */
        public function getJoinExpressions () : array {
            return $this->joinExpressions;
        }

        /**
         * Gets the nodes that constitute a source.
         * @return SourceNode[] The source nodes.
         */
        public function getNodes () : array {
            return $this->nodes;
        }
    }
?>