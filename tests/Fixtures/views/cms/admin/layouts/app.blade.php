<!DOCTYPE html>
<html>
<head>
    <title>@yield( 'title' ) &middot; CMS</title>
    @stack( 'styles' )
</head>
<body>
<div class="cms-admin-fixture">
    <main class="cms-admin__content">
        @yield( 'content' )
    </main>
</div>
@stack( 'scripts' )
</body>
</html>
