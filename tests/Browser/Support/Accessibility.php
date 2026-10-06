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
     * All tracked in ArtisanPack-UI/livewire-ui-components#117.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const LIBRARY_ISSUES = [
        // `x-artisanpack-menu` puts non-<li> children in its <ul> and gives
        // links role="menuitem" without a role="menu" parent.
        [ 'list', '.menu' ],
        [ 'listitem', '.menu' ],
        [ 'aria-required-parent', '.menu' ],
        // `x-artisanpack-select` / `-textarea` label with a <legend>, so the
        // control itself has no accessible name.
        [ 'select-name', 'fieldset' ],
        [ 'label', 'fieldset' ],
        // Header subtitles and stat titles use text-base-content/50 (3.3:1).
        [ 'color-contrast', '.text-base-content\\/50' ],
        // The pagination view puts aria-label on a disabled <span> (and
        // double-escapes it: "&laquo; Previous").
        [ 'aria-prohibited-attr', '.pagination-controls' ],
        // `x-artisanpack-tabs` gives its content wrapper role="tablist" (with
        // no tabs in it) and draws inactive tab labels below 4.5:1.
        [ 'aria-required-children', '[role="tablist"]' ],
        [ 'color-contrast', '[role="tab"]' ],
        // `x-artisanpack-code` (Ace) leaves its input textarea unlabelled.
        [ 'label', '.ace_editor' ],
    ];

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
