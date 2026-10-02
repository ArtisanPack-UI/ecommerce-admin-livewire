{{--
    A customer's notification preferences. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\PreferencesTab.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<form wire:submit="savePreferences" class="flex flex-col gap-4" data-customer-preferences>
    <p class="opacity-75">
        {{ $canUpdate
            ? __( 'These are the customer\'s own choices. Change them only when the customer asks you to. Order and account messages are always sent.' )
            : __( 'These are the customer\'s own choices. Order and account messages are always sent.' ) }}
    </p>

    <div class="overflow-x-auto">
        <table class="table table-sm">
            <caption class="sr-only">{{ __( 'Notification preferences' ) }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __( 'Messages' ) }}</th>
                    @foreach ( $channels as $channel )
                        <th scope="col">{{ $channel['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ( $categories as $category )
                    <tr wire:key="preference-{{ $category['key'] }}">
                        <th scope="row" class="font-normal">{{ $category['label'] }}</th>
                        @foreach ( $channels as $channel )
                            <td>
                                @if ( $category['locked'] )
                                    <x-artisanpack-badge :value="__( 'Always on' )" class="badge-sm" color="neutral" />
                                @else
                                    <x-artisanpack-toggle
                                        id="preference-{{ $channel['key'] }}-{{ $category['key'] }}"
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

    @if ( $canUpdate )
        <div>
            <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" spinner="savePreferences" :label="__( 'Save preferences' )" />
        </div>
    @endif
</form>
