{{--
    License keys (#127). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index.

    Every key issued with a digital product on the shared Resource table
    (#79): who has it, its order, how many activations are used (with a
    short bar that turns warning when every slot is taken), when it
    expires and whether it is still valid. The Activations drawer lists the
    machines a key is active on, frees a slot (Deactivate, confirmed in an
    alert dialog) or revokes the key (alert dialog, optional reason).

    Keys are printed only through $keyFor, which masks them to their last
    four characters for users without licenseKey.view. Keys, machines and
    IP addresses are left-to-right monospace in either direction.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index;
@endphp
<div class="ec-stack" data-license-keys-index>
    <x-artisanpack-page-header :title="__( 'License keys' )" :description="__( 'Keys issued with digital products, and the machines they’re active on.' )" :level="1" />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'key',
        'emptyTitle'       => __( 'No license keys yet' ),
        'emptyDescription' => __( 'Keys issued with digital products appear here.' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\LicenseKey $key ): string => $keyFor( $key ),
        'cellContext'      => [ 'keyFor' => $keyFor, 'canRevoke' => $canRevoke ],
        'searchHint'       => $searchHint,
    ] )

    <x-artisanpack-drawer wire:model="viewing" :title="__( 'Activations' )" side="end" size="md" :close-label="__( 'Close' )">
        @if ( null !== $viewingKey )
            @php
                $viewingOrder   = $viewingKey->orderItem?->order;
                $viewingProduct = (string) ( $viewingKey->orderItem?->product_snapshot['name'] ?? '' );
                $viewingDetails = array_values( array_filter( [
                    $viewingProduct,
                    Index::customer( $viewingKey ),
                    null === $viewingOrder ? __( 'Deleted order' ) : '#' . $viewingOrder->order_number,
                ], static fn ( string $part ): bool => '' !== $part ) );
                $viewingLevel   = Index::activationLevel( $viewingKey );
                $viewingFull    = Index::activationsFull( $viewingKey );
                $viewingRevoked = $viewingKey->is_revoked;
            @endphp
            <div class="ec-stack" data-activations="{{ $viewingKey->id }}">
                @include( 'ecommerce-admin::partials.live-region', [ 'message' => $drawerStatus ] )

                <div class="ec-license-keys-summary">
                    <bdi dir="ltr" class="ec-license-keys-key" data-drawer-key>{{ $keyFor( $viewingKey ) }}</bdi>
                    @if ( [] !== $viewingDetails )
                        <span class="ec-muted">{{ implode( ' · ', $viewingDetails ) }}</span>
                    @endif
                    @if ( $viewingRevoked )
                        <div class="ec-license-keys-revoked">
                            <x-artisanpack-badge size="sm" dot color="error">{{ __( 'Revoked' ) }}</x-artisanpack-badge>
                            @if ( filled( $viewingKey->meta['revoked_reason'] ?? null ) )
                                <span class="ec-muted" data-revoked-reason>{{ __( 'Revoked: :reason', [ 'reason' => $viewingKey->meta['revoked_reason'] ] ) }}</span>
                            @endif
                        </div>
                    @endif
                </div>

                @if ( null === $viewingKey->activations_limit )
                    <p class="ec-body" data-activations-unlimited>{{ trans_choice( ':count activation, no limit.|:count activations, no limit.', $viewingKey->activations_count, [ 'count' => $viewingKey->activations_count ] ) }}</p>
                @elseif ( null === $viewingLevel )
                    {{-- A limit of zero allows no activations; it isn't "no limit". --}}
                    <p class="ec-body" data-activations-none>{{ __( 'This key allows no activations.' ) }}</p>
                @else
                    <x-artisanpack-progress
                        :value="(int) $viewingKey->activations_count"
                        :max="(int) $viewingKey->activations_limit"
                        :color="$viewingFull ? 'warning' : 'primary'"
                        :label="__( ':used of :limit activations used.', [ 'used' => $viewingKey->activations_count, 'limit' => $viewingKey->activations_limit ] )"
                        class="ec-w-{{ $viewingLevel }}"
                        data-activations-progress
                    />
                @endif

                @if ( $viewingKey->activations->isEmpty() )
                    <p class="ec-muted" data-no-activations>{{ __( 'This key has not been activated on any machine.' ) }}</p>
                @else
                    <div class="apui-table ec-license-keys-machines" data-surface="default">
                        <div data-scope="table" data-part="scroll" data-density="compact">
                            <table data-scope="table" data-part="table">
                                <caption class="apui-sr-only">{{ __( 'Machines this key is active on' ) }}</caption>
                                <thead>
                                    <tr>
                                        <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Machine' ) }}</th>
                                        <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Activated' ) }}</th>
                                        <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Last seen' ) }}</th>
                                        @if ( $canViewKeys )
                                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'IP address' ) }}</th>
                                        @endif
                                        @if ( $canRevoke )
                                            <th data-scope="table" data-part="head-cell" scope="col"><span class="apui-sr-only">{{ __( 'Actions' ) }}</span></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ( $viewingKey->activations as $activation )
                                        <tr wire:key="activation-{{ $activation->id }}" data-activation="{{ $activation->id }}">
                                            <td data-scope="table" data-part="cell"><bdi dir="ltr" class="ec-mono ec-break">{{ $canViewKeys ? $activation->machine_fingerprint : $machineFor( $activation ) }}</bdi></td>
                                            <td data-scope="table" data-part="cell">{{ null === $activation->activated_at ? '—' : LocalizedDate::format( $activation->activated_at ) }}</td>
                                            <td data-scope="table" data-part="cell">
                                                @if ( null === $activation->last_seen_at )
                                                    —
                                                @else
                                                    <time datetime="{{ $activation->last_seen_at->toIso8601String() }}" title="{{ LocalizedDate::format( $activation->last_seen_at ) }}">{{ $activation->last_seen_at->diffForHumans() }}</time>
                                                @endif
                                            </td>
                                            @if ( $canViewKeys )
                                                <td data-scope="table" data-part="cell"><bdi dir="ltr" class="ec-mono">{{ $activation->ip_address }}</bdi></td>
                                            @endif
                                            @if ( $canRevoke )
                                                <td data-scope="table" data-part="cell" class="ec-license-keys-cell-end">
                                                    <x-artisanpack-button
                                                        variant="ghost"
                                                        color="error"
                                                        size="xs"
                                                        wire:click="startDeactivate( {{ (int) $activation->id }} )"
                                                        wire:loading.attr="disabled"
                                                        :aria-label="__( 'Deactivate :machine', [ 'machine' => $machineFor( $activation ) ] )"
                                                        data-deactivate="{{ $activation->id }}"
                                                    >{{ __( 'Deactivate' ) }}</x-artisanpack-button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            @if ( null !== $viewingKey && $canRevoke && ! $viewingKey->is_revoked )
                <x-artisanpack-button
                    variant="outline"
                    color="error"
                    leading-icon="circle-x"
                    wire:click="startRevoke( {{ (int) $viewingKey->id }} )"
                    :aria-label="__( 'Revoke :key', [ 'key' => $keyFor( $viewingKey ) ] )"
                    data-drawer-revoke
                >{{ __( 'Revoke key' ) }}</x-artisanpack-button>
            @endif
            <x-artisanpack-button color="neutral" wire:click="$set( 'viewing', false )" data-drawer-close>{{ __( 'Close' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deactivatingMachine )
        <x-artisanpack-dialog
            wire:model="deactivating"
            role="alertdialog"
            size="sm"
            :title="__( 'Deactivate :machine?', [ 'machine' => $machineFor( $deactivatingMachine ) ] )"
            :description="__( 'Free this activation slot? The software on that machine will stop validating.' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-deactivate-confirm
        >
            <x-slot:footer>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="closeDeactivate" data-autofocus>{{ __( 'Keep it active' ) }}</x-artisanpack-button>
                <x-artisanpack-button color="error" wire:click="deactivate( {{ \Illuminate\Support\Js::from( $deactivateToken ) }} )" wire:loading.attr="disabled">{{ __( 'Deactivate' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @if ( null !== $revokingKey )
        <x-artisanpack-dialog
            wire:model="revoking"
            role="alertdialog"
            color="error"
            icon="key"
            :title="__( 'Revoke :key?', [ 'key' => $keyFor( $revokingKey ) ] )"
            :description="__( 'The key stops validating on every machine at once. This cannot be undone.' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
        >
            <form wire:submit="revoke( {{ \Illuminate\Support\Js::from( $revokeToken ) }} )" id="license-revoke-form" class="ec-form-stack" data-revoke-form>
                <x-artisanpack-textarea
                    id="license-revoke-reason"
                    :label="__( 'Reason (optional)' )"
                    :hint="__( 'E.g. a chargeback or a refund.' )"
                    :max-length="500"
                    wire:model="revokeReason"
                    :rows="3"
                />
            </form>

            <x-slot:footer>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="closeRevoke">{{ __( 'Keep key' ) }}</x-artisanpack-button>
                <x-artisanpack-button type="submit" form="license-revoke-form" color="error" wire:loading.attr="disabled">{{ __( 'Revoke key' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
