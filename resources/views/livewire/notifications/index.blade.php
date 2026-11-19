{{--
    Notification templates list (#134). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
@endphp
<div class="ec-stack" data-notifications-index>
    <x-artisanpack-page-header :title="__( 'Notifications' )" :description="__( 'Edit the emails and messages the store sends.' )" :level="1" />

    @if ( 0 === $total )
        <div class="apui-card">
            <x-artisanpack-ec-admin-empty-state
                icon="bell"
                :heading-level="2"
                :title="__( 'No notification templates' )"
                :description="__( 'No notifications are registered with the store yet.' )"
            />
        </div>
    @else
        <div class="ec-notif-list-filters" role="group" aria-label="{{ __( 'Filters' ) }}" data-notification-filters>
            <div class="ec-notif-list-filter">
                <x-artisanpack-select
                    id="notifications-channel"
                    size="sm"
                    :label="__( 'Channel' )"
                    :options="ControlOptions::from( $channelOptions )"
                    :placeholder="__( 'Any' )"
                    wire:model.live="channel"
                />
            </div>
            <div class="ec-notif-list-filter">
                <x-artisanpack-select
                    id="notifications-locale"
                    size="sm"
                    :label="__( 'Language' )"
                    :options="ControlOptions::from( $localeOptions )"
                    :placeholder="__( 'Any' )"
                    wire:model.live="locale"
                />
            </div>
            @if ( $filtersActive )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="x" wire:click="resetFilters" data-clear-filters>{{ __( 'Clear filters' ) }}</x-artisanpack-button>
            @endif
        </div>

        @if ( $groups->isEmpty() )
            <div class="apui-card">
                <x-artisanpack-ec-admin-empty-state
                    size="sm"
                    icon="search"
                    :heading-level="2"
                    :title="__( 'No matches' )"
                    :description="__( 'No templates match these filters.' )"
                >
                    <x-artisanpack-button variant="outline" color="neutral" leading-icon="x" wire:click="resetFilters" data-empty-clear-filters>{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                </x-artisanpack-ec-admin-empty-state>
            </div>
        @else
            <div class="ec-notif-list-groups">
                @foreach ( $groups as $group )
                    <section class="apui-card" wire:key="notification-group-{{ $group['key'] }}" aria-labelledby="notification-group-{{ $loop->index }}" data-template-group="{{ $group['key'] }}">
                        <div data-scope="card" data-part="header">
                            <div class="ec-notif-list-heading">
                                <h2 id="notification-group-{{ $loop->index }}" data-scope="card" data-part="title">{{ $group['label'] }}</h2>
                                <bdi dir="ltr" class="ec-mono ec-cell-muted" data-template-key>{{ $group['key'] }}</bdi>
                            </div>
                        </div>

                        <div data-scope="card" data-part="body" class="ec-card-body-flush">
                            <div class="apui-table">
                                <div data-scope="table" data-part="scroll">
                                    <table data-scope="table" data-part="table">
                                        <caption class="apui-sr-only">{{ __( ':template templates', [ 'template' => $group['label'] ] ) }}</caption>
                                        <thead>
                                            <tr>
                                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Channel' ) }}</th>
                                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Language' ) }}</th>
                                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Status' ) }}</th>
                                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Last edited' ) }}</th>
                                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Actions' ) }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ( $group['templates'] as $template )
                                                @php
                                                    $channelLabel = Index::channelLabel( (string) $template->channel );
                                                    $localeLabel  = Index::localeLabel( (string) $template->locale );
                                                @endphp
                                                <tr data-scope="table" data-part="row" wire:key="notification-template-{{ $template->id }}" data-template="{{ $template->id }}">
                                                    <td data-scope="table" data-part="cell">
                                                        <span class="ec-notif-list-channel" data-channel="{{ $template->channel }}">
                                                            <x-artisanpack-icon :name="Index::channelIcon( (string) $template->channel )" aria-hidden="true" />{{ $channelLabel }}
                                                        </span>
                                                    </td>
                                                    <td data-scope="table" data-part="cell">{{ $localeLabel }}</td>
                                                    <td data-scope="table" data-part="cell">
                                                        <x-artisanpack-badge dot :color="$template->is_active ? 'success' : 'neutral'" :data-template-state="$template->is_active ? 'active' : 'off'">{{ $template->is_active ? __( 'Active' ) : __( 'Off' ) }}</x-artisanpack-badge>
                                                    </td>
                                                    <td data-scope="table" data-part="cell" class="ec-cell-muted ec-nowrap">
                                                        @php( $templateEdited = Index::wasEdited( $template ) )
                                                        @if ( $templateEdited && null !== $template->updated_at )
                                                            <time datetime="{{ $template->updated_at->toIso8601String() }}">{{ LocalizedDate::format( $template->updated_at ) }}</time>
                                                        @elseif ( $templateEdited )
                                                            <span data-edited-unknown>{{ __( 'Unknown' ) }}</span>
                                                        @else
                                                            <span data-never-edited>{{ __( 'Never' ) }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-scope="table" data-part="cell" data-numeric>
                                                        <x-artisanpack-button
                                                            variant="ghost"
                                                            color="neutral"
                                                            size="xs"
                                                            leading-icon="pencil"
                                                            :href="route( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $template->id ] )"
                                                            :aria-label="__( 'Edit :template (:channel, :language)', [ 'template' => $group['label'], 'channel' => $channelLabel, 'language' => $localeLabel ] )">{{ __( 'Edit' ) }}</x-artisanpack-button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    @endif
</div>
