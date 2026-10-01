<x-artisanpack-alert
    {{ $attributes }}
    color="warning"
    icon="o-exclamation-triangle"
    :title="__( 'This product is read-only' )"
    :description="$warning"
    role="status"
/>
