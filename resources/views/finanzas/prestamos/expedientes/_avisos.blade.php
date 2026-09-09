@if(session('success'))
    <div style="margin-top:1rem; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:0.75rem 1rem; border-radius:10px; font-size:0.82rem;">
        ✅ {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="margin-top:1rem; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:10px; font-size:0.82rem;">
        ⚠️ {{ session('error') }}
    </div>
@endif
