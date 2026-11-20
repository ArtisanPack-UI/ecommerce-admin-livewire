{{--
    Customer preferences tab (#110). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\PreferencesTab.

    The customer's notification choices as a grid of message types by
    channel, with a small switch in each cell named for both ("Shipping
    updates by SMS"). Transactional messages show a locked "Always on" badge
    instead of a switch: it is text, not a disabled switch, so it isn't read
    as an option. The intro reminds staff these are the customer's own
    choices.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<form wire:submit="savePreferences" class="ec-stack-sm" data-customer-preferences>
    <p class="ec-muted ec-measure">
        {{ $canUpdate
            ? __( 'These are the customer\'s own choices. Change them only when the customer asks you to; transactional messages can\'t be turned off.' )
            : __( 'These are the customer\'s own choices; transactional messages can\'t be turned off.' ) }}
    </p>

    <div class="apui-table ec-table" data-surface="default">
        <div data-scope="table" data-part="scroll">
            <table data-scope="table" data-part="table" data-preferences-grid>
                <caption class="apui-sr-only">{{ __( 'Notification preferences' ) }}</caption>
                <thead>
                    <tr>
                        <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Messages' ) }}</th>
                        @foreach ( $channels as $channel )
                            <th data-scope="table" data-part="head-cell" scope="col">{{ $channel['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $categories as $category )
                        <tr data-scope="table" data-part="row" wire:key="preference-{{ $category['key'] }}">
                            <th data-scope="table" data-part="cell" scope="row">{{ $category['label'] }}</th>
                            @foreach ( $channels as $channel )
                                <td data-scope="table" data-part="cell">
                                    @if ( $category['locked'] )
                                        <x-artisanpack-badge size="sm" color="neutral" leading-icon="lock" data-always-on>{{ __( 'Always on' ) }}</x-artisanpack-badge>
                                    @else
                                        <x-artisanpack-toggle
                                            id="preference-{{ $channel['key'] }}-{{ $category['key'] }}"
                                            size="sm"
                                            :aria-label="__( ':messages by :channel', [ 'messages' => $category['label'], 'channel' => $channel['label'] ] )"
                                            :disabled="! $canUpdate"
                                            wire:model="preferences.{{ $channel['key'] }}.{{ $category['key'] }}"
                                        />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ( $canUpdate )
        <div>
            <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="savePreferences">{{ __( 'Save preferences' ) }}</x-artisanpack-button>
        </div>
    @endif
</form>
