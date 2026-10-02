{{--
    A kanban board's automations. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Automations.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<section class="flex flex-col gap-3" aria-labelledby="board-automations-heading" data-board-automations>
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="board-automations-heading" class="grow text-lg font-semibold">{{ __( 'Automations' ) }}</h2>
        @if ( $canUpdate && $hasColumns )
            <x-artisanpack-button size="sm" variant="outline" icon="o-plus" wire:click="create" :label="__( 'Add automation' )" />
        @endif
    </div>
    <p class="text-sm opacity-75">{{ __( 'Automations run when a card moves into a column, like emailing the customer when an order reaches "Shipped".' ) }}</p>

    @if ( ! $hasColumns )
        <x-artisanpack-ec-empty-state icon="o-bolt" :title="__( 'Add columns first' )" :description="__( 'Automations run when cards move between columns, so the board needs columns before it can have automations.' )" />
    @elseif ( [] === $automations )
        <x-artisanpack-ec-empty-state icon="o-bolt" :title="__( 'No automations yet' )" :description="__( 'Add one to send an email, call a webhook, or create a shipment when a card moves.' )" />
    @else
        <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300" aria-label="{{ __( 'Automations' ) }}">
            @foreach ( $automations as $automation )
                @php $summary = __( ':from to :to: :trigger', [ 'from' => $automation['from'], 'to' => $automation['to'], 'trigger' => $automation['trigger'] ] ); @endphp
                <li wire:key="automation-{{ $automation['id'] }}" class="flex flex-wrap items-center gap-3 p-3" data-automation="{{ $automation['id'] }}">
                    <div class="min-w-0 grow">
                        <div class="flex flex-wrap items-center gap-2">
                            <span>{{ $automation['from'] }}</span>
                            <x-artisanpack-icon name="o-arrow-right" class="size-4" aria-hidden="true" />
                            <span class="sr-only">{{ __( 'to' ) }}</span>
                            <span>{{ $automation['to'] }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span class="font-semibold">{{ $automation['trigger'] }}</span>
                            @unless ( $automation['is_active'] )
                                <x-artisanpack-badge :value="__( 'Switched off' )" class="badge-sm badge-ghost" data-inactive-automation />
                            @endunless
                        </div>
                        <p class="text-sm opacity-75">{{ $automation['conditions'] }}</p>
                    </div>

                    @if ( $canUpdate )
                        <div class="flex items-center gap-1">
                            <x-artisanpack-button
                                variant="ghost"
                                size="sm"
                                :icon="$automation['is_active'] ? 'o-pause' : 'o-play'"
                                wire:click="toggleActive( {{ $automation['id'] }} )"
                                :aria-label="$automation['is_active'] ? __( 'Switch off :name', [ 'name' => $summary ] ) : __( 'Switch on :name', [ 'name' => $summary ] )"
                            />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="edit( {{ $automation['id'] }} )" :aria-label="__( 'Edit :name', [ 'name' => $summary ] )" />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $automation['id'] }} )" :aria-label="__( 'Delete :name', [ 'name' => $summary ] )" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $automationId ? __( 'New automation' ) : __( 'Edit automation' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-2xl"
    >
        <form wire:submit="save" class="flex flex-col gap-4" data-automation-form>
            <div class="grid gap-4 md:grid-cols-2">
                <x-artisanpack-select
                    id="automation-from"
                    :label="__( 'When a card moves from' )"
                    :options="$columnOptions"
                    :placeholder="__( 'Any column' )"
                    placeholder-value=""
                    wire:model="form.from_column_id"
                />
                <x-artisanpack-select
                    id="automation-to"
                    :label="__( 'Into' )"
                    :options="$columnOptions"
                    :placeholder="__( 'Choose a column' )"
                    placeholder-value=""
                    wire:model="form.to_column_id"
                    required
                />
            </div>

            <x-artisanpack-select
                id="automation-trigger"
                :label="__( 'Run' )"
                :options="$triggerOptions"
                :placeholder="__( 'Choose a trigger' )"
                placeholder-value=""
                wire:model.live="form.trigger_key"
                required
            />

            @if ( '' !== (string) $form['trigger_key'] )
                <fieldset class="fieldset" data-automation-config>
                    <legend class="fieldset-legend">{{ __( 'Settings' ) }}</legend>
                    @if ( $secretIsSet )
                        <x-artisanpack-alert color="info" icon="o-key" role="status" data-secret-set>
                            {{ __( 'A signing secret is set. Leave the field empty to keep it, or type a new one to replace it.' ) }}
                        </x-artisanpack-alert>
                        <x-artisanpack-checkbox id="automation-clear-secret" :label="__( 'Remove the signing secret' )" wire:model="clearSecret" />
                    @endif
                    <x-artisanpack-ec-config-form registry="kanban-trigger" :entry="(string) $form['trigger_key']" model="form.trigger_config" wire:key="automation-config-{{ $form['trigger_key'] }}" />
                </fieldset>
            @endif

            <x-artisanpack-toggle id="automation-active" :label="__( 'Switched on' )" wire:model="form.is_active" />

            @if ( null !== $complexJson )
                <div class="flex flex-col gap-2" data-complex-conditions>
                    <x-artisanpack-alert color="info" icon="o-information-circle" role="status">
                        {{ __( 'These conditions use "any" or "not" groups, which this screen cannot edit. They are kept as they are when you save.' ) }}
                    </x-artisanpack-alert>
                    <pre class="overflow-x-auto rounded-box bg-base-200 p-3 text-sm"><code>{{ $complexJson }}</code></pre>
                    <div>
                        <x-artisanpack-button variant="outline" size="sm" wire:click="replaceComplexConditions" :label="__( 'Replace with simple conditions' )" />
                    </div>
                </div>
            @else
                <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" prefix="automation-rule" />
            @endif

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editing', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save automation' )" />
            </div>
        </form>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        <x-artisanpack-modal wire:model="confirmingDelete" :title="__( 'Delete this automation?' )" separator>
            <p data-delete-automation="{{ $deleting['id'] }}">
                {{ __( ':from to :to: :trigger. Deleting it cannot be undone.', [ 'from' => $deleting['from'], 'to' => $deleting['to'], 'trigger' => $deleting['trigger'] ] ) }}
            </p>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete" :label="__( 'Keep automation' )" />
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Delete automation' )"
                    data-delete-button
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</section>
