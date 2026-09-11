@if ($errors->any())
    <div class="admin-flash is-error" role="alert">
        <strong>Vui lòng kiểm tra lại thông tin:</strong>
        <ul>
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
