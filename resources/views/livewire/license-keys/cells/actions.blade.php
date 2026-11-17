<div class="flex justify-end gap-1">
    <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-computer-desktop" wire:click="showActivations( {{ $row->getKey() }} )" :aria-label="__( 'Activations of :key', [ 'key' => $context['keyFor']( $row ) ] )">{{ __( 'Activations' ) }}</x-artisanpack-button>
    @if ( $context['canRevoke'] && ! $row->is_revoked )
        <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-no-symbol" wire:click="startRevoke( {{ $row->getKey() }} )" :aria-label="__( 'Revoke :key', [ 'key' => $context['keyFor']( $row ) ] )">{{ __( 'Revoke' ) }}</x-artisanpack-button>
    @endif
</div>
