{{--
    Validation summary for a form: announced (role="alert") when a save
    fails, so screen-reader users learn about errors next to fields they
    cannot see. Pass `message` to override the default text.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( $errors->any() )
    <x-artisanpack-alert
        color="error"
        icon="o-exclamation-circle"
        role="alert"
        :title="$message ?? trans_choice( 'Not saved. Fix the highlighted field.|Not saved. Fix the :count highlighted fields.', $errors->count(), [ 'count' => $errors->count() ] )"
        data-error-summary
    />
@endif
