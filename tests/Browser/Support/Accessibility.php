<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

use PHPUnit\Framework\Assert;

/**
 * axe checks for the browser suite (#61).
 *
 * Runs every axe rule and fails on any violation, except the ones listed in
 * {@see self::LIBRARY_ISSUES}: failures that come from livewire-ui-components
 * markup this package cannot change. Each entry names the axe rule and a
 * CSS selector; a node is ignored only when it (or an ancestor) matches.
 * Remove an entry once the library fixes it, and the suite enforces it here.
 */
final class Accessibility
{
    /**
     * Known library failures: axe rule => selector the node must sit in.
     *
     * Keep each entry as narrow as the bug: a selector that only matches the
     * library's own markup, never a generic element such as `fieldset`, and
     * name it in docs/accessibility.md ("Known limitations").
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const LIBRARY_ISSUES = [];

    /**
     * Asserts the page has no accessibility violations beyond the known
     * library ones.
     *
     * @param  object  $page  The page (a Pest browser webpage).
     *
     * @return object
     */
    public static function assertAccessible( object $page ): object
    {
        $page->assertScript( 'document.readyState', 'complete' );

        $ignored = json_encode( self::LIBRARY_ISSUES );

        /** @var array<int, array{id: string, impact: string|null, help: string, nodes: array<int, string>}> $violations */
        $violations = (array) $page->script( <<<JS
            async () => {
                const ignored = {$ignored};
                const { violations } = await window.axe.run();

                return violations
                    .map( ( violation ) => ( {
                        id: violation.id,
                        impact: violation.impact,
                        help: violation.help,
                        nodes: violation.nodes
                            .filter( ( node ) => {
                                const element = document.querySelector( node.target[ 0 ] );

                                return ! ignored.some( ( [ rule, selector ] ) => rule === violation.id && element?.closest( selector ) );
                            } )
                            .map( ( node ) => node.target.join( ' ' ) + ' :: ' + node.html.slice( 0, 200 ) ),
                    } ) )
                    .filter( ( violation ) => violation.nodes.length > 0 );
            }
            JS );

        $report = implode( "\n", array_map(
            static fn ( array $violation ): string => sprintf( "- [%s] %s (%s)\n    %s", $violation['impact'] ?? 'n/a', $violation['help'], $violation['id'], implode( "\n    ", $violation['nodes'] ) ),
            $violations,
        ) );

        Assert::assertSame( [], $violations, "Accessibility violations:\n" . $report );

        return $page;
    }
}
