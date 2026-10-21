<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body>
{{ $slot ?? '' }}
@yield('content')
</body>
</html>
