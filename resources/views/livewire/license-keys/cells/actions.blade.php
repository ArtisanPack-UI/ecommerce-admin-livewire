<div class="flex justify-end gap-1">
    <x-artisanpack-button variant="ghost" size="sm" icon="o-computer-desktop" wire:click="showActivations( {{ $row->getKey() }} )" :label="__( 'Activations' )" :aria-label="__( 'Activations of :key', [ 'key' => $context['keyFor']( $row ) ] )" />
    @if ( $context['canRevoke'] && ! $row->is_revoked )
        <x-artisanpack-button variant="ghost" size="sm" icon="o-no-symbol" wire:click="startRevoke( {{ $row->getKey() }} )" :label="__( 'Revoke' )" :aria-label="__( 'Revoke :key', [ 'key' => $context['keyFor']( $row ) ] )" />
    @endif
</div>
