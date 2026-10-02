{{--
    Notification templates list. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Index;
@endphp
<div>
    <x-artisanpack-header :title="__( 'Notifications' )" :subtitle="__( 'Edit the emails and messages the store sends.' )" :level="1" separator />

    @if ( 0 === $total )
        <x-artisanpack-ec-empty-state
            icon="o-bell"
            :title="__( 'No notification templates' )"
            :description="__( 'No notifications are registered with the store yet.' )"
        />
    @else
        <div class="mb-4 flex flex-wrap items-end gap-3" role="group" aria-label="{{ __( 'Filters' ) }}">
            <x-artisanpack-select
                id="notifications-channel"
                :label="__( 'Channel' )"
                :options="$channelOptions"
                :placeholder="__( 'Any' )"
                placeholder-value=""
                wire:model.live="channel"
            />
            <x-artisanpack-select
                id="notifications-locale"
                :label="__( 'Language' )"
                :options="$localeOptions"
                :placeholder="__( 'Any' )"
                placeholder-value=""
                wire:model.live="locale"
            />
            @if ( $filtersActive )
                <x-artisanpack-button variant="ghost" size="sm" icon="o-x-mark" wire:click="resetFilters" :label="__( 'Clear filters' )" />
            @endif
        </div>

        @if ( $groups->isEmpty() )
            <x-artisanpack-ec-empty-state
                icon="o-magnifying-glass"
                :title="__( 'No matches' )"
                :description="__( 'No templates match these filters.' )"
            />
        @endif

        <div class="flex flex-col gap-6">
            @foreach ( $groups as $group )
                <section wire:key="notification-group-{{ $group['key'] }}" aria-labelledby="notification-group-{{ $loop->index }}" data-template-group="{{ $group['key'] }}">
                    <h2 id="notification-group-{{ $loop->index }}" class="text-lg font-semibold">
                        {{ $group['label'] }}
                        <span class="ms-2 text-sm font-normal opacity-75">{{ $group['key'] }}</span>
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <caption class="sr-only">{{ __( ':template templates', [ 'template' => $group['label'] ] ) }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __( 'Channel' ) }}</th>
                                    <th scope="col">{{ __( 'Language' ) }}</th>
                                    <th scope="col">{{ __( 'Status' ) }}</th>
                                    <th scope="col">{{ __( 'Last edited' ) }}</th>
                                    <th scope="col" class="text-end">{{ __( 'Actions' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $group['templates'] as $template )
                                    <tr wire:key="notification-template-{{ $template->id }}" data-template="{{ $template->id }}">
                                        <td>{{ Index::channelLabel( (string) $template->channel ) }}</td>
                                        <td>{{ Index::localeLabel( (string) $template->locale ) }}</td>
                                        <td>
                                            @if ( $template->is_active )
                                                <x-artisanpack-badge :value="__( 'Active' )" color="success" class="badge-sm" />
                                            @else
                                                <x-artisanpack-badge :value="__( 'Off' )" class="badge-sm badge-ghost" />
                                            @endif
                                        </td>
                                        <td>
                                            @if ( null !== $template->updated_at )
                                                <time datetime="{{ $template->updated_at->toIso8601String() }}">{{ LocalizedDate::format( $template->updated_at ) }}</time>
                                            @else
                                                <span class="opacity-75">{{ __( 'Never' ) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <x-artisanpack-button
                                                variant="ghost"
                                                size="sm"
                                                icon="o-pencil"
                                                :link="route( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $template->id ] )"
                                                :label="__( 'Edit' )"
                                                :aria-label="__( 'Edit :template (:locale)', [ 'template' => $group['label'], 'locale' => $template->locale ] )"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
