{{--
    Keyboard and screen-reader support that spans components (#61):

    - Reorders. A move button carries `data-reorder` (up / down),
      `data-reorder-list`, `data-reorder-item` (the item's name), and either
      `data-reorder-key` (a stable item key) or `data-reorder-index`. After
      the update, focus moves to the same arrow on the moved item — or the
      other arrow when that one is now disabled at the top or bottom — and
      "Moved :item to position :position of :total." is announced.
    - Focus return. A control with `data-focus-return="key"` (Cancel, Save,
      Confirm in an inline confirmation or edit row) sends focus back to the
      `data-focus-key="key"` control that opened it once the update removes
      the confirmation, if focus would otherwise fall to the page.

    The standalone layout includes it once; under cms-framework each page
    pushes it with the Livewire assets.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div
    id="ecommerce-admin-announcer"
    class="sr-only"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    data-reorder-message="{{ __( 'Moved :item to position :position of :total.' ) }}"
></div>
<script>
    ( () => {
        if ( window.ecommerceAdminAccessibility ) {
            return;
        }

        window.ecommerceAdminAccessibility = true;

        let pending = null;

        const attribute = ( name, value ) => '[' + name + '="' + CSS.escape( String( value ) ) + '"]';

        const announce = ( text ) => {
            const region = document.getElementById( 'ecommerce-admin-announcer' );

            if ( ! region ) {
                return;
            }

            // Clear first so the same sentence is read again.
            region.textContent = '';
            setTimeout( () => { region.textContent = text; }, 50 );
        };

        const focusLost = () => ! document.activeElement || document.activeElement === document.body || ! document.activeElement.isConnected;

        const moveButton = ( move, direction ) => {
            const list = attribute( 'data-reorder-list', move.list );

            if ( null !== move.key ) {
                return document.querySelector( attribute( 'data-reorder', direction ) + list + attribute( 'data-reorder-key', move.key ) );
            }

            const index = Number( move.index ) + ( 'up' === move.direction ? -1 : 1 );

            return document.querySelector( attribute( 'data-reorder', direction ) + list + attribute( 'data-reorder-index', index ) );
        };

        const settleMove = ( move ) => {
            const same = moveButton( move, move.direction );
            const other = moveButton( move, 'up' === move.direction ? 'down' : 'up' );

            if ( ! same && ! other ) {
                return;
            }

            const target = same && ! same.disabled ? same : other;
            const ups = Array.from( document.querySelectorAll( attribute( 'data-reorder', 'up' ) + attribute( 'data-reorder-list', move.list ) ) );
            const position = ups.indexOf( moveButton( move, 'up' ) ) + 1;

            target?.focus();

            if ( position > 0 ) {
                const region = document.getElementById( 'ecommerce-admin-announcer' );
                const message = region?.dataset.reorderMessage ?? '';

                // Functions, so `$&` and the like in a record name stay literal.
                announce( message.replace( ':position', () => position ).replace( ':total', () => ups.length ).replace( ':item', () => move.item ) );
            }
        };

        // A click that never reaches the server must not steer focus on some
        // later, unrelated update (a real-time refresh, say).
        const PENDING_TTL = 3000;

        const settle = () => {
            const current = pending;

            pending = null;

            if ( ! current || Date.now() - current.at > PENDING_TTL ) {
                return;
            }

            if ( 'move' === current.type ) {
                settleMove( current );

                return;
            }

            if ( focusLost() ) {
                document.querySelector( attribute( 'data-focus-key', current.key ) )?.focus();
            }
        };

        document.addEventListener( 'click', ( event ) => {
            const move = event.target.closest( '[data-reorder]' );

            if ( move ) {
                pending = {
                    type: 'move',
                    direction: move.dataset.reorder,
                    list: move.dataset.reorderList ?? '',
                    key: move.dataset.reorderKey ?? null,
                    index: move.dataset.reorderIndex ?? 0,
                    item: move.dataset.reorderItem ?? '',
                    at: Date.now(),
                };

                return;
            }

            const back = event.target.closest( '[data-focus-return]' );

            if ( back ) {
                pending = { type: 'return', key: back.dataset.focusReturn, at: Date.now() };
            }
        }, true );

        const listen = () => window.Livewire.hook( 'commit', ( { succeed } ) => {
            succeed( () => requestAnimationFrame( () => requestAnimationFrame( settle ) ) );
        } );

        if ( window.Livewire ) {
            listen();
        } else {
            document.addEventListener( 'livewire:init', listen );
        }
    } )();
</script>
