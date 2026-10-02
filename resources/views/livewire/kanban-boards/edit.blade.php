{{--
    Kanban board editor. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Edit.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-8" data-kanban-board-form>
    <div class="flex flex-col gap-4">
        <x-artisanpack-header :title="__( 'Edit :name', [ 'name' => $board->name ] )" :level="1" separator>
            <x-slot:actions>
                @if ( null !== $indexUrl )
                    <x-artisanpack-button variant="ghost" icon="o-arrow-left" :link="$indexUrl" :label="__( 'All boards' )" />
                @endif
                @if ( null !== $boardUrl )
                    <x-artisanpack-button variant="outline" icon="o-arrow-top-right-on-square" :link="$boardUrl" :label="__( 'Open board' )" data-open-board />
                @endif
                @unless ( $readOnly )
                    <x-artisanpack-button color="primary" icon="o-check" wire:click="save" wire:loading.attr="disabled" spinner="save" :label="__( 'Save board' )" data-save />
                @endunless
            </x-slot:actions>
        </x-artisanpack-header>

        @if ( $readOnly )
            <x-artisanpack-alert color="info" icon="o-eye" :title="__( 'You can view this board but not change it.' )" role="status" />
        @endif

        @if ( $errors->any() )
            <x-artisanpack-alert color="error" icon="o-exclamation-circle" role="alert" :title="__( 'The board was not saved. Fix the highlighted fields.' )" />
        @endif

        <section class="flex flex-col gap-4" aria-labelledby="board-details-heading">
            <h2 id="board-details-heading" class="text-lg font-semibold">{{ __( 'Details' ) }}</h2>

            <fieldset class="grid gap-4 md:grid-cols-2" @disabled( $readOnly ) data-board-details>
                <x-artisanpack-input id="board-name" :label="__( 'Name' )" wire:model="form.name" maxlength="255" required />
                <x-artisanpack-input id="board-key" :label="__( 'Key' )" :hint="__( 'Integrations refer to the key, so it cannot be changed.' )" :value="$board->key" readonly />

                <div class="md:col-span-2">
                    <x-artisanpack-textarea id="board-description" :label="__( 'Description' )" rows="2" wire:model="form.description" />
                </div>

                <x-artisanpack-toggle id="board-active" :label="__( 'Switched on' )" :hint="__( 'Orders are only routed onto active boards.' )" wire:model="form.is_active" />

                @if ( $board->is_default )
                    <div class="flex flex-col gap-1" data-board-is-default>
                        <x-artisanpack-badge :value="__( 'Default board' )" color="primary" />
                        <span class="text-sm opacity-75">{{ __( 'To change the default, make another board the default from the boards list.' ) }}</span>
                    </div>
                @else
                    <x-artisanpack-toggle id="board-default" :label="__( 'Make this the default board' )" :hint="__( 'The default board catches orders no other board took. The current default stops being one.' )" wire:model="form.is_default" />
                @endif
            </fieldset>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="board-routing-heading" data-board-routing>
            <h2 id="board-routing-heading" class="text-lg font-semibold">{{ __( 'Routing' ) }}</h2>
            <p class="text-sm opacity-75">{{ __( 'When an order is placed or edited, it goes onto this board if it matches every rule.' ) }}</p>

            @if ( $fallbackNote )
                <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-fallback-note>
                    {{ __( 'This is the default board, but it has routing rules, so it only takes matching orders. Orders no board matches go nowhere.' ) }}
                </x-artisanpack-alert>
            @endif

            @if ( null !== $complexJson )
                <div class="flex flex-col gap-2" data-complex-rules>
                    <x-artisanpack-alert color="info" icon="o-information-circle" role="status">
                        {{ __( 'These rules use "any" or "not" groups, which this screen cannot edit. They are kept as they are when you save.' ) }}
                    </x-artisanpack-alert>
                    <pre class="overflow-x-auto rounded-box bg-base-200 p-3 text-sm"><code>{{ $complexJson }}</code></pre>
                    @unless ( $readOnly )
                        <div>
                            <x-artisanpack-button variant="outline" size="sm" wire:click="replaceComplexRules" :label="__( 'Replace with simple rules' )" />
                        </div>
                    @endunless
                </div>
            @else
                <fieldset @disabled( $readOnly )>
                    <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" prefix="routing-rule" />
                </fieldset>
            @endif
        </section>
    </div>

    @livewire( 'artisanpack-ecommerce-admin-kanban-board-columns', [ 'board' => $board ], key( 'kanban-board-columns-' . $board->id ) )

    @livewire( 'artisanpack-ecommerce-admin-kanban-board-automations', [ 'board' => $board ], key( 'kanban-board-automations-' . $board->id ) )
</div>
