<?php
    /*/
     * Project Name:    Wingman — Database — Predicate
     * Created by:      Angel Politis
     * Creation Date:   Jan 05 2026
     * Last Modified:   Jan 05 2026
    /*/

    # Use the Database.Expressions namespace.
    namespace Wingman\Database\Expressions;

    # Import the following classes to the current scope.
    use Wingman\Database\Interfaces\Aliasable;
    use Wingman\Database\Interfaces\Conjunctive;
    use Wingman\Database\Interfaces\Expression;
    use Wingman\Database\Traits\CanHaveAlias;
    use Wingman\Database\Traits\CanHaveConjunction;

    /**
     * Represents an expression that can participate in boolean logic.
     * @package Wingman\Database\Expressions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class Predicate implements Expression, Aliasable, Conjunctive {
        use CanHaveAlias, CanHaveConjunction;
    }
?>