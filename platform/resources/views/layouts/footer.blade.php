@php
$configuration = getConfiguration();
@endphp
<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                @if(!empty($configuration->powered_link))
                <script>
                    document.write(new Date().getFullYear())
                </script> © {{ $configuration->name }}
                @endif
            </div>
            <div class="col-sm-6">
                <div class="text-sm-end d-none d-sm-block">
                    @if(!empty($configuration->powered_by))
                    @if(!empty($configuration->powered_link))
                    <a href="{{ $configuration->powered_link }}" target="_blank">Powered by {{
                        $configuration->powered_by }}</a>
                    @else
                    Powered by {{ $configuration->powered_by }}
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>