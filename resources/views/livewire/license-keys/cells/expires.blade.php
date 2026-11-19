{{--
    License keys cell (#127): when the key expires, or "Never", in muted text.
--}}
<span class="ec-license-keys-muted">{{ null === $row->expires_at ? __( 'Never' ) : \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $row->expires_at ) }}</span>
