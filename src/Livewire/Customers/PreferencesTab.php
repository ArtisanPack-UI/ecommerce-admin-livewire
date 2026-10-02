<?php

/**
 * Customer notification preferences tab.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerNotificationPreference;
use ArtisanPackUI\Ecommerce\Services\NotificationPreferenceService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A customer's notification preferences (spec §7.4): one switch per channel
 * and category, read through the engine's `NotificationPreferenceService`.
 *
 * Staff with `customer.update` can override them (for example after a
 * customer asks by phone to stop review requests). Transactional messages
 * (order confirmations, receipts, password resets) cannot be turned off, so
 * they are shown locked and never written.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class PreferencesTab extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;

    /**
     * The customer id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $customerId = 0;

    /**
     * Switch state: channel => category => enabled.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, bool>>
     */
    public array $preferences = [];

    /**
     * Loads and authorizes the customer.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The customer.
     *
     * @return void
     */
    public function mount( Customer $customer ): void
    {
        $this->authorizeEcommerce( 'view', $customer );

        $this->customerId = (int) $customer->id;
        $this->fill( [ 'preferences' => $this->stored( $customer ) ] );
    }

    /**
     * Re-checks access on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->customer() );
    }

    /**
     * Saves every switch except the transactional ones.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function savePreferences(): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'update', $customer );

        $service = app( NotificationPreferenceService::class );
        $rows    = [];

        foreach ( $service->channels() as $channel ) {
            foreach ( CustomerNotificationPreference::CATEGORIES as $category ) {
                if ( CustomerNotificationPreference::CATEGORY_TRANSACTIONAL === $category ) {
                    continue;
                }

                $rows[] = [
                    'channel'    => $channel,
                    'category'   => $category,
                    'is_enabled' => filter_var( $this->preferences[ $channel ][ $category ] ?? false, FILTER_VALIDATE_BOOLEAN ),
                ];
            }
        }

        $service->update( $customer, $rows );

        $this->preferences = $this->stored( $customer );

        $this->dispatch( CustomerTabRegistry::CUSTOMER_UPDATED_EVENT );
        $this->toastSuccess( __( 'Notification preferences saved.' ) );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $channels = app( NotificationPreferenceService::class )->channels();

        return view( 'ecommerce-admin::livewire.customers.tabs.preferences', [
            'channels'   => array_map( static fn ( string $channel ): array => [ 'key' => $channel, 'label' => self::channelLabel( $channel ) ], $channels ),
            'categories' => array_map(
                static fn ( string $category ): array => [
                    'key'    => $category,
                    'label'  => self::categoryLabel( $category ),
                    'locked' => CustomerNotificationPreference::CATEGORY_TRANSACTIONAL === $category,
                ],
                CustomerNotificationPreference::CATEGORIES,
            ),
            'canUpdate'  => $this->canEcommerce( 'update', $this->customer() ),
        ] );
    }

    /**
     * A category's label.
     *
     * @since 1.0.0
     *
     * @param  string  $category  Category key.
     *
     * @return string
     */
    public static function categoryLabel( string $category ): string
    {
        return match ( $category ) {
            'transactional'    => __( 'Order and account messages' ),
            'shipping-updates' => __( 'Shipping updates' ),
            'review-requests'  => __( 'Review requests' ),
            'marketing'        => __( 'Marketing' ),
            'abandoned-cart'   => __( 'Abandoned cart reminders' ),
            'back-in-stock'    => __( 'Back-in-stock alerts' ),
            default            => Str::headline( $category ),
        };
    }

    /**
     * A channel's label.
     *
     * @since 1.0.0
     *
     * @param  string  $channel  Channel key.
     *
     * @return string
     */
    public static function channelLabel( string $channel ): string
    {
        return match ( $channel ) {
            'mail'     => __( 'Email' ),
            'sms'      => __( 'SMS' ),
            'database' => __( 'In-app' ),
            default    => Str::headline( $channel ),
        };
    }

    /**
     * The stored preferences as switch state.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The customer.
     *
     * @return array<string, array<string, bool>>
     */
    protected function stored( Customer $customer ): array
    {
        $state = [];

        foreach ( app( NotificationPreferenceService::class )->all( $customer ) as $row ) {
            $state[ (string) $row['channel'] ][ (string) $row['category'] ] = (bool) $row['is_enabled'];
        }

        return $state;
    }

    /**
     * The customer.
     *
     * @since 1.0.0
     *
     * @return Customer
     */
    protected function customer(): Customer
    {
        return Customer::query()->findOrFail( $this->customerId );
    }
}
