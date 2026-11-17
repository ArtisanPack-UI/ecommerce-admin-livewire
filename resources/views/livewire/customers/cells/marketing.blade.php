<x-artisanpack-badge :value="$row->accepts_marketing ? __( 'Subscribed' ) : __( 'Not subscribed' )" size="sm" :color="$row->accepts_marketing ? 'success' : 'neutral'" />
