{{-- El layout ya pinta session('success') y session('warning'); aquí van los
     errores, que no tienen bloque propio. --}}
@if(session('error'))
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:0.7rem 0.9rem;border-radius:10px;font-size:0.85rem;margin-bottom:1rem;">
        ⚠️ {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:0.7rem 0.9rem;border-radius:10px;font-size:0.85rem;margin-bottom:1rem;">
        <strong>Revisa estos campos:</strong>
        <ul style="margin:0.35rem 0 0 1.1rem;padding:0;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
