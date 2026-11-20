{{--
    Webhooks list cell (#136): a disabled subscription gets a visible
    Re-enable button; Rotate secret, Edit and Delete are icon buttons named
    for the subscription.
--}}
<div class="ec-row-actions ec-webhooks-actions">
    @if ( $context['canUpdate'] )
        @unless ( $row->is_active )
            <x-artisanpack-button variant="outline" color="neutral" size="xs" leading-icon="refresh-cw" wire:click="reEnable( {{ $row->getKey() }} )" :aria-label="__( 'Re-enable :name', [ 'name' => $row->name ] )" data-re-enable="{{ $row->getKey() }}">{{ __( 'Re-enable' ) }}</x-artisanpack-button>
        @endunless
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="key" wire:click="confirmRotate( {{ $row->getKey() }} )" :aria-label="__( 'Rotate the secret of :name', [ 'name' => $row->name ] )" />
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="edit( {{ $row->getKey() }} )" :aria-label="__( 'Edit :name', [ 'name' => $row->name ] )" />
    @endif
    @if ( $context['canDelete'] )
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $row->getKey() }} )" :aria-label="__( 'Delete :name', [ 'name' => $row->name ] )" />
    @endif
</div>
