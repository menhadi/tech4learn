<meta charset="utf-8" />
<title>@yield('title') | {{ $configuration->name ?? '' }} </title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="Premium Multipurpose " name="description" />
<meta content="ExamFrame" name="author" />
<!-- App favicon -->
@if (isset($configuration->favicon))
    <link rel="shortcut icon" href="{{ asset('storage/' . $configuration->favicon) }}" type="image/x-icon">
@else
    <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}" type="image/x-icon">
@endif
