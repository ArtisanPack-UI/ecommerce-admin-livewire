<?php

/**
 * License keys screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys;

use ArtisanPackUI\Ecommerce\Models\LicenseActivation;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Services\LicenseService;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\LicenseKeysQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The license keys table (spec §7.2): key, product, order, customer,
 * activations, expiry, and status; searched by key, order, or customer.
 *
 * - Keys are shown in full only to users with `licenseKey.view`. Users who
 *   may only revoke (`licenseKey.revoke`) can open the screen but see each
 *   key masked to its last group, and cannot search by key.
 * - A drawer lists a key's activations (machine, first and last seen, IP).
 * - Revoking takes a reason and a one-time action token, and goes through
 *   the engine's `LicenseService::revoke()`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    /**
     * The key whose activations are open.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $viewingId = null;

    /**
     * Whether the activations drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $viewing = false;

    /**
     * The key waiting for revoke confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $revokingId = null;

    /**
     * Whether the revoke dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $revoking = false;

    /**
     * Why the key is revoked.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $revokeReason = '';

    /**
     * The activation (of the key in the open drawer) waiting for the
     * deactivate confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deactivatingId = null;

    public bool $deactivating = false;

    /**
     * What the drawer's live region last announced.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $drawerStatus = '';

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeTable();
    }

    /**
     * Opens a key's activations.
     *
     * @since 1.0.0
     *
     * @param  int  $id  License key id.
     *
     * @return void
     */
    public function showActivations( int $id ): void
    {
        $this->authorizeTable();

        $this->viewingId    = LicenseKey::query()->whereKey( $id )->exists() ? $id : null;
        $this->viewing      = null !== $this->viewingId;
        $this->drawerStatus = '';
        $this->closeDeactivate();
    }

    /**
     * Asks to confirm freeing an activation slot of the key in the open
     * drawer. The activation is looked up by id on that key only.
     *
     * @since 1.0.0
     *
     * @param  int  $activationId  The activation.
     *
     * @return void
     */
    public function startDeactivate( int $activationId ): void
    {
        $this->authorizeEcommerceAbility( 'licenseKey.revoke' );

        $activation = $this->drawerActivation( $activationId );

        if ( null === $activation ) {
            return;
        }

        $this->deactivatingId = (int) $activation->id;
        $this->deactivating   = true;
    }

    /**
     * Frees the activation slot: the engine removes the activation and
     * lowers the key's count. Works on revoked and expired keys too.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The one-time token minted with the confirmation.
     *
     * @return void
     */
    public function deactivate( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'licenseKey.revoke' );

        $license = null === $this->viewingId ? null : LicenseKey::query()->find( $this->viewingId );
        $id      = $this->deactivatingId;

        $this->closeDeactivate();

        if ( null === $license || null === $id ) {
            return;
        }

        $this->authorizeEcommerce( 'revoke', $license );

        $activation = $license->activations()->whereKey( $id )->first();

        $removed = null === $activation ? false : $this->withActionToken(
            $token,
            'deactivate',
            static fn (): bool => app( LicenseService::class )->deactivate( $license, (string) $activation->machine_fingerprint ),
            $activation,
        );

        if ( null === $removed ) {
            return;
        }

        if ( false === $removed ) {
            $this->drawerStatus = __( 'That machine was already deactivated.' );
            $this->toastWarning( $this->drawerStatus );

            return;
        }

        $this->drawerStatus = __( 'Activation slot freed.' );
        $this->toastSuccess( $this->drawerStatus );
    }

    /**
     * Closes the deactivate confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeDeactivate(): void
    {
        $this->deactivating   = false;
        $this->deactivatingId = null;
    }

    /**
     * Asks to confirm revoking a key.
     *
     * @since 1.0.0
     *
     * @param  int  $id  License key id.
     *
     * @return void
     */
    public function startRevoke( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'licenseKey.revoke' );

        $key = LicenseKey::query()->find( $id );

        $this->resetErrorBag();
        $this->revokingId   = null === $key || $key->is_revoked ? null : (int) $key->id;
        $this->revoking     = null !== $this->revokingId;
        $this->revokeReason = '';
    }

    /**
     * Revokes the key waiting for confirmation, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the dialog.
     *
     * @return void
     */
    public function revoke( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'licenseKey.revoke' );

        $key = null === $this->revokingId || ! $this->revoking ? null : LicenseKey::query()->find( $this->revokingId );

        if ( null === $key ) {
            $this->closeRevoke();

            return;
        }

        $this->authorizeEcommerce( 'revoke', $key );

        $this->validate(
            [ 'revokeReason' => [ 'required', 'string', 'max:500' ] ],
            [],
            [ 'revokeReason' => __( 'reason' ) ],
        );

        $revoked = $this->withActionToken(
            $token,
            'revoke',
            fn (): LicenseKey => app( LicenseService::class )->revoke( $key, sanitizeText( $this->revokeReason ) ),
            $key,
        );

        $this->closeRevoke();

        if ( null !== $revoked ) {
            $this->revokeReason = '';
            $this->toastSuccess( __( 'License key :key revoked.', [ 'key' => $this->displayKey( $revoked ) ] ) );
        }
    }

    /**
     * Closes the revoke dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeRevoke(): void
    {
        $this->revoking   = false;
        $this->revokingId = null;
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $viewing  = null === $this->viewingId || ! $this->viewing ? null : LicenseKey::query()->with( [ 'activations' => static fn ( $query ) => $query->orderByDesc( 'last_seen_at' ) ] )->find( $this->viewingId );
        $revoking = null === $this->revokingId || ! $this->revoking ? null : LicenseKey::query()->find( $this->revokingId );

        $deactivating = null === $viewing || null === $this->deactivatingId || ! $this->deactivating ? null : $viewing->activations->firstWhere( 'id', $this->deactivatingId );

        return view( 'ecommerce-admin::livewire.license-keys.index', $this->resourceTableData() + [
            'canViewKeys'         => $this->canViewKeys(),
            'deactivatingMachine' => $deactivating,
            'deactivateToken'     => null === $deactivating ? null : $this->actionToken( 'deactivate', $deactivating ),
            'machineFor'          => fn ( LicenseActivation $activation ): string => $this->machineLabel( $activation ),
            'canRevoke'           => Authorization::allows( auth()->user(), 'licenseKey.revoke' ),
            'searchHint'          => $this->canViewKeys() ? __( 'Search by order, customer, or a full license key.' ) : __( 'Search by order or customer.' ),
            'viewingKey'          => $viewing,
            'revokingKey'         => $revoking,
            'revokeToken'         => null === $revoking ? null : $this->actionToken( 'revoke', $revoking ),
            'keyFor'              => fn ( LicenseKey $key ): string => $this->displayKey( $key ),
        ] );
    }

    /**
     * A key with every group but the last replaced by dots.
     *
     * @since 1.0.0
     *
     * @param  string  $key  License key.
     *
     * @return string
     */
    public static function mask( string $key ): string
    {
        $groups = explode( '-', $key );
        $last   = (string) array_pop( $groups );

        if ( [] === $groups ) {
            return str_repeat( '•', max( 0, mb_strlen( $last ) - 4 ) ) . mb_substr( $last, -4 );
        }

        return implode( '-', array_map( static fn ( string $group ): string => str_repeat( '•', mb_strlen( $group ) ), $groups ) ) . '-' . $last;
    }

    /**
     * The key's status: `active`, `revoked`, or `expired`.
     *
     * @since 1.0.0
     *
     * @param  LicenseKey  $key  License key.
     *
     * @return string
     */
    public static function status( LicenseKey $key ): string
    {
        return match ( true ) {
            $key->is_revoked   => 'revoked',
            $key->isExpired()  => 'expired',
            default            => 'active',
        };
    }

    /**
     * Status labels.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'active'  => __( 'Active' ),
            'revoked' => __( 'Revoked' ),
            'expired' => __( 'Expired' ),
        ];
    }

    /**
     * The customer a key was sold to: their name, else the order email.
     *
     * @since 1.0.0
     *
     * @param  LicenseKey  $key  License key with its order and customer loaded.
     *
     * @return string
     */
    public static function customer( LicenseKey $key ): string
    {
        $order    = $key->orderItem?->order;
        $customer = $order?->customer;
        $name     = trim( (string) ( $customer->first_name ?? '' ) . ' ' . (string) ( $customer->last_name ?? '' ) );

        return '' !== $name ? $name : (string) ( $customer->email ?? $order->email ?? '' );
    }

    /**
     * The activation `$id` of the key in the open drawer, or null when no
     * drawer is open.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The activation.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When it isn't one of that key's activations.
     *
     * @return LicenseActivation|null
     */
    protected function drawerActivation( int $id ): ?LicenseActivation
    {
        $license = null === $this->viewingId || ! $this->viewing ? null : LicenseKey::query()->find( $this->viewingId );

        return $license?->activations()->whereKey( $id )->firstOrFail();
    }

    /**
     * A machine as this user may see it: its fingerprint with
     * `licenseKey.view`, otherwise a masked fingerprint and its activation
     * date (revoke-only users don't see fingerprints or IP addresses).
     *
     * @since 1.0.0
     *
     * @param  LicenseActivation  $activation  The activation.
     *
     * @return string
     */
    protected function machineLabel( LicenseActivation $activation ): string
    {
        $fingerprint = (string) $activation->machine_fingerprint;

        if ( $this->canViewKeys() ) {
            return $fingerprint;
        }

        $masked = mb_strlen( $fingerprint ) > 4 ? '…' . mb_substr( $fingerprint, -4 ) : '…';

        return null === $activation->activated_at
            ? $masked
            : __( ':machine, activated :date', [ 'machine' => $masked, 'date' => LocalizedDate::format( $activation->activated_at ) ] );
    }

    /**
     * The key as this user may see it: in full with `licenseKey.view`,
     * otherwise masked to its last group.
     *
     * @since 1.0.0
     *
     * @param  LicenseKey  $key  License key.
     *
     * @return string
     */
    protected function displayKey( LicenseKey $key ): string
    {
        return $this->canViewKeys() ? (string) $key->key : self::mask( (string) $key->key );
    }

    /**
     * Whether the user may see keys in full.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function canViewKeys(): bool
    {
        return Authorization::allows( auth()->user(), 'licenseKey.view' );
    }

    /**
     * The screen needs `licenseKey.view`, or `licenseKey.revoke` (keys masked).
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $user = auth()->user();

        if ( ! Authorization::allows( $user, 'licenseKey.view' ) && ! Authorization::allows( $user, 'licenseKey.revoke' ) ) {
            $this->denyEcommerce();
        }
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'license-keys';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new LicenseKeysQuery( $this->canViewKeys() );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'License keys' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.license-keys.cells.';

        return [
            [
                'key'    => 'key',
                'label'  => __( 'Key' ),
                'view'   => $cells . 'key',
                'export' => fn ( LicenseKey $key ): string => $this->displayKey( $key ),
            ],
            [
                'key'   => 'product',
                'label' => __( 'Product' ),
                'value' => static fn ( LicenseKey $key ): string => (string) ( $key->orderItem?->product_snapshot['name'] ?? '' ),
            ],
            [
                'key'    => 'order',
                'label'  => __( 'Order' ),
                'view'   => $cells . 'order',
                'export' => static fn ( LicenseKey $key ): string => (string) ( $key->orderItem?->order?->order_number ?? '' ),
            ],
            [
                'key'   => 'customer',
                'label' => __( 'Customer' ),
                'value' => static fn ( LicenseKey $key ): string => self::customer( $key ),
            ],
            [
                'key'      => 'activations',
                'label'    => __( 'Activations' ),
                'sortable' => true,
                'class'    => 'tabular-nums',
                'value'    => static fn ( LicenseKey $key ): string => null === $key->activations_limit
                    ? (string) $key->activations_count
                    : __( ':used of :limit', [ 'used' => $key->activations_count, 'limit' => $key->activations_limit ] ),
            ],
            [
                'key'      => 'expires',
                'label'    => __( 'Expires' ),
                'sortable' => true,
                'value'    => static fn ( LicenseKey $key ): string => null === $key->expires_at ? __( 'Never' ) : LocalizedDate::format( $key->expires_at ),
                'export'   => static fn ( LicenseKey $key ): string => $key->expires_at?->format( DATE_ATOM ) ?? '',
            ],
            [
                'key'    => 'status',
                'label'  => __( 'Status' ),
                'view'   => $cells . 'status',
                'export' => static fn ( LicenseKey $key ): string => self::statuses()[ self::status( $key ) ],
            ],
            [
                'key'        => 'actions',
                'label'      => __( 'Actions' ),
                'class'      => 'text-end',
                'view'       => $cells . 'actions',
                'exportable' => false,
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableFilters(): array
    {
        $options = [];

        foreach ( self::statuses() as $id => $name ) {
            $options[] = [ 'id' => $id, 'name' => $name ];
        }

        return [
            [ 'key' => 'status', 'label' => __( 'Status' ), 'type' => 'select', 'options' => $options ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [ $this->exportBulkAction() ];
    }
}
