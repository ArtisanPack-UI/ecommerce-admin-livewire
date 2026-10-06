{{--
    License keys. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index.

    Keys are printed only through $keyFor, which masks them for users
    without licenseKey.view.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index;
@endphp
<div>
    <x-artisanpack-header :title="__( 'License keys' )" :level="1" separator />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-key',
        'emptyTitle'       => __( 'No license keys yet' ),
        'emptyDescription' => __( 'Keys issued with digital products appear here.' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\LicenseKey $key ): string => $keyFor( $key ),
        'cellContext'      => [ 'keyFor' => $keyFor, 'canRevoke' => $canRevoke ],
        'searchHint'       => $searchHint,
    ] )

    <x-artisanpack-drawer wire:model="viewing" :title="__( 'Activations' )" right separator with-close-button close-on-escape class="w-full max-w-xl">
        @if ( null !== $viewingKey )
            <div class="flex flex-col gap-4" data-activations="{{ $viewingKey->id }}">
                @include( 'ecommerce-admin::partials.live-region', [ 'message' => $drawerStatus ] )
                <p class="font-mono">{{ $keyFor( $viewingKey ) }}</p>
                <p class="text-sm">
                    {{ null === $viewingKey->activations_limit
                        ? trans_choice( ':count activation, no limit.|:count activations, no limit.', $viewingKey->activations_count, [ 'count' => $viewingKey->activations_count ] )
                        : __( ':used of :limit activations used.', [ 'used' => $viewingKey->activations_count, 'limit' => $viewingKey->activations_limit ] ) }}
                </p>
                @if ( $viewingKey->is_revoked && filled( $viewingKey->meta['revoked_reason'] ?? null ) )
                    <p class="text-sm">{{ __( 'Revoked: :reason', [ 'reason' => $viewingKey->meta['revoked_reason'] ] ) }}</p>
                @endif

                @if ( $viewingKey->activations->isEmpty() )
                    <p class="opacity-75">{{ __( 'This key has not been activated on any machine.' ) }}</p>
                @else
                    {{-- A plain table, not x-artisanpack-table: see src/View/Components/ResourceTable.php for why. --}}
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <caption class="sr-only">{{ __( 'Machines this key is active on' ) }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __( 'Machine' ) }}</th>
                                    <th scope="col">{{ __( 'Activated' ) }}</th>
                                    <th scope="col">{{ __( 'Last seen' ) }}</th>
                                    @if ( $canViewKeys )
                                        <th scope="col">{{ __( 'IP address' ) }}</th>
                                    @endif
                                    @if ( $canRevoke )
                                        <th scope="col"><span class="sr-only">{{ __( 'Actions' ) }}</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $viewingKey->activations as $activation )
                                    <tr wire:key="activation-{{ $activation->id }}" data-activation="{{ $activation->id }}">
                                        <td class="font-mono text-xs break-all">{{ $canViewKeys ? $activation->machine_fingerprint : $machineFor( $activation ) }}</td>
                                        <td>{{ null === $activation->activated_at ? '' : \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $activation->activated_at ) }}</td>
                                        <td>{{ null === $activation->last_seen_at ? '' : \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $activation->last_seen_at ) }}</td>
                                        @if ( $canViewKeys )
                                            <td>{{ $activation->ip_address }}</td>
                                        @endif
                                        @if ( $canRevoke )
                                            <td class="text-end">
                                                <x-artisanpack-button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="o-power"
                                                    wire:click="startDeactivate( {{ (int) $activation->id }} )"
                                                    wire:loading.attr="disabled"
                                                    :label="__( 'Deactivate' )"
                                                    :aria-label="__( 'Deactivate :machine', [ 'machine' => $machineFor( $activation ) ] )"
                                                />
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </x-artisanpack-drawer>

    @if ( null !== $deactivatingMachine )
        <x-artisanpack-modal wire:model="deactivating" :title="__( 'Deactivate :machine?', [ 'machine' => $machineFor( $deactivatingMachine ) ] )" separator>
            <p data-deactivate-confirm>{{ __( 'Free this activation slot? The software on that machine will stop validating.' ) }}</p>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="closeDeactivate" :label="__( 'Keep it active' )" />
                <x-artisanpack-button color="error" wire:click="deactivate( {{ \Illuminate\Support\Js::from( $deactivateToken ) }} )" wire:loading.attr="disabled" :label="__( 'Deactivate' )" />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif

    @if ( null !== $revokingKey )
        @php
            $revokeTitle = __( 'Revoke :key?', [ 'key' => $keyFor( $revokingKey ) ] );
        @endphp
        <x-artisanpack-modal wire:model="revoking" :title="$revokeTitle" separator>
            <form wire:submit="revoke( {{ \Illuminate\Support\Js::from( $revokeToken ) }} )" id="license-revoke-form" class="flex flex-col gap-3" data-revoke-form>
                <p>{{ __( 'The key stops validating on every machine at once. This cannot be undone.' ) }}</p>
                <x-artisanpack-textarea id="license-revoke-reason" :label="__( 'Reason' )" :hint="__( 'E.g. a chargeback or a refund.' )" wire:model="revokeReason" rows="3" required />
            </form>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="closeRevoke" :label="__( 'Keep key' )" />
                <x-artisanpack-button type="submit" form="license-revoke-form" color="error" wire:loading.attr="disabled" :label="__( 'Revoke key' )" />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
