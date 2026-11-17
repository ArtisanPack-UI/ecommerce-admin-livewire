{{--
    The command palette (spec §8.1, #94) with its open button, for pointer
    users and anyone who does not know the shortcut. The design-system
    Spotlight renders both: the button where this partial sits (a field-like
    trigger with its shortcut; an icon button on phones), and the dialog,
    teleported to the end of the page.

    An empty query lists the actions the person may run, filtered as they
    type; a search fetches every provider they can see, grouped by provider
    (see AdminScreenController::spotlight()). Results open as plain links,
    as in 1.x: the admin's pages don't run under wire:navigate.

    Mounted once per page: in the standalone layout's top bar, or above the
    page under cms-framework's layout, which mounts none of its own.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight;
@endphp
@if ( AdminSpotlight::mountable() )
    @php
        $actionGroup = AdminSpotlight::actionGroup( auth()->user() );
    @endphp
    <x-artisanpack-spotlight
        :url="\Illuminate\Support\Facades\Route::has( AdminSpotlight::ADMIN_ROUTE ) ? route( AdminSpotlight::ADMIN_ROUTE, absolute: false ) : null"
        :groups="null === $actionGroup ? [] : [ $actionGroup ]"
        :shortcut="AdminSpotlight::paletteShortcut()"
        :navigate="false"
        :label="__( 'Search and run commands' )"
        :placeholder="__( 'Search orders, products, customers, or type a command…' )"
        :empty-label="__( 'Nothing found.' )"
        :loading-label="__( 'Searching…' )"
        :close-label="__( 'Close' )"
        :classes="[ 'trigger' => 'ec-admin-search-trigger' ]"
        data-spotlight-open
    >
        <x-slot:trigger>
            <x-artisanpack-icon name="search" />
            <span class="ec-admin-search-trigger__label">{{ __( 'Search' ) }}</span>
        </x-slot:trigger>

        <x-slot:empty>
            <x-artisanpack-empty-state
                size="sm"
                icon="search"
                :heading-level="3"
                :title="__( 'Nothing found.' )"
                :description="__( 'Try an order number, a SKU, or a customer’s email.' )"
            />
        </x-slot:empty>

        <x-slot:footer>
            <span class="ec-admin-spotlight-legend"><span data-scope="spotlight" data-part="shortcut" aria-hidden="true"><kbd>↑</kbd><kbd>↓</kbd></span><span class="apui-sr-only">{{ __( 'Up and Down arrows' ) }} </span>{{ __( 'to move' ) }}</span>
            <span class="ec-admin-spotlight-legend"><span data-scope="spotlight" data-part="shortcut" aria-hidden="true"><kbd>↵</kbd></span><span class="apui-sr-only">{{ __( 'Enter' ) }} </span>{{ __( 'to open' ) }}</span>
            <span class="ec-admin-spotlight-legend"><span data-scope="spotlight" data-part="shortcut" aria-hidden="true"><kbd>Esc</kbd></span><span class="apui-sr-only">{{ __( 'Escape' ) }} </span>{{ __( 'to close' ) }}</span>
        </x-slot:footer>
    </x-artisanpack-spotlight>
@endif
