{{--
    Error summary (#90), shown at the top of a form when a save fails.

    Field errors render the design-system ValidationSummary: role="alert"
    and tabindex="-1", so it takes focus after a failed save, with a
    pluralized "Not saved" title and a link per field that jumps to and
    focuses it (by id, name or wire:model). It renders nothing once the
    form saves.

    A save that fails for a reason outside the form (a conflict, a lost
    session) passes `failure` (the title), plus an optional
    `failureDescription` and one action (`actionLabel` and the Livewire
    method `actionMethod`), and gets an error Alert instead. It takes focus
    the same way. (Not `message`: an included view inherits the parent's
    variables, and @error leaves a `$message` behind.)

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( isset( $failure ) && is_string( $failure ) && '' !== $failure )
    <x-artisanpack-alert
        color="error"
        icon="triangle-alert"
        :title="$failure"
        :description="$failureDescription ?? null"
        data-error-summary
        data-error-alert
        tabindex="-1"
        x-init="$nextTick( () => $el.focus() )"
    >
        @if ( isset( $actionLabel, $actionMethod ) )
            <x-slot:actions>
                <x-artisanpack-button color="error" size="sm" leading-icon="refresh-cw" wire:click="{{ $actionMethod }}" wire:loading.attr="disabled">{{ $actionLabel }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-alert>
@elseif ( $errors->any() )
    <x-artisanpack-validation-summary
        :errors="$errors->getMessages()"
        :title="trans_choice( 'Not saved. Fix the highlighted field.|Not saved. Fix the :count highlighted fields.', count( $errors->getMessages() ), [ 'count' => count( $errors->getMessages() ) ] )"
        data-error-summary
    />
@endif
