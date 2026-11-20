{{--
    License keys cell (#127): Activations opens the drawer; Revoke (only for
    keys not yet revoked, and users who may revoke) asks to confirm. Both
    are named for their key.
--}}
<div class="ec-row-actions ec-row-actions-end">
    <x-artisanpack-button
        variant="outline"
        color="neutral"
        size="xs"
        wire:click="showActivations( {{ $row->getKey() }} )"
        :aria-label="__( 'Activations of :key', [ 'key' => $context['keyFor']( $row ) ] )"
        data-show-activations="{{ $row->getKey() }}"
    >{{ __( 'Activations' ) }}</x-artisanpack-button>
    @if ( $context['canRevoke'] && ! $row->is_revoked )
        <x-artisanpack-button
            variant="ghost"
            color="error"
            size="xs"
            wire:click="startRevoke( {{ $row->getKey() }} )"
            :aria-label="__( 'Revoke :key', [ 'key' => $context['keyFor']( $row ) ] )"
            data-revoke-key="{{ $row->getKey() }}"
        >{{ __( 'Revoke' ) }}</x-artisanpack-button>
    @endif
</div>
