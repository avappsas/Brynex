{{-- ════════════════════════════════════════════════════════════════
     MODAL PANEL BENEFICIARIOS — Lista + CRUD dentro de modal grande
════════════════════════════════════════════════════════════════ --}}

<div id="modalListaBeneficiarios" class="bmodal-backdrop" onclick="cerrarPanelBen(event)">
    <div class="blist-panel">

        {{-- Header --}}
        <div class="blist-header">
            <div style="display:flex;align-items:center;gap:.85rem;">
                <div style="width:44px;height:44px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#fff;">Grupo Familiar / Beneficiarios</h3>
                    <p style="margin:0;font-size:.78rem;color:rgba(255,255,255,.7);">Titular: <strong>{{ $cliente->primer_nombre }} {{ $cliente->primer_apellido }}</strong> · CC {{ $cliente->cedula }}</p>
                </div>
            </div>
            <div style="display:flex;gap:.6rem;align-items:center;">
                <button type="button" onclick="benModalAbrir()" style="display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.35);color:#fff;border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Nuevo
                </button>
                <button type="button" onclick="cerrarPanelBen()" style="background:rgba(255,255,255,.15);border:none;border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Body: grid de cards --}}
        <div class="blist-body">
            @php $bens = $cliente->beneficiarios; @endphp

            @if($bens->isEmpty())
            <div class="bpanel-empty">
                <div class="bpanel-empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                </div>
                <p class="bpanel-empty-msg">No hay beneficiarios registrados</p>
                <p class="bpanel-empty-sub">Registre el grupo familiar: cónyuge, hijos, sobrinos u otros dependientes.</p>
                <button type="button" onclick="benModalAbrir()" style="margin-top:.75rem;display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:10px;padding:.5rem 1.1rem;font-size:.82rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(37,99,235,.35);">
                    + Agregar primer beneficiario
                </button>
            </div>
            @else
            <div class="ben-grid">
                @foreach($bens as $ben)
                @php
                    $parentColors = [
                        'hijo'     => ['bg'=>'#dbeafe','color'=>'#1d4ed8','icon'=>'👦'],
                        'hija'     => ['bg'=>'#fce7f3','color'=>'#be185d','icon'=>'👧'],
                        'cónyuge'  => ['bg'=>'#fef3c7','color'=>'#b45309','icon'=>'💑'],
                        'conyugue' => ['bg'=>'#fef3c7','color'=>'#b45309','icon'=>'💑'],
                        'esposa'   => ['bg'=>'#fce7f3','color'=>'#be185d','icon'=>'💑'],
                        'esposo'   => ['bg'=>'#fce7f3','color'=>'#be185d','icon'=>'💑'],
                        'padre'    => ['bg'=>'#dcfce7','color'=>'#15803d','icon'=>'👨'],
                        'madre'    => ['bg'=>'#dcfce7','color'=>'#15803d','icon'=>'👩'],
                        'sobrino'  => ['bg'=>'#e0e7ff','color'=>'#4338ca','icon'=>'🧒'],
                        'sobrina'  => ['bg'=>'#fce7f3','color'=>'#be185d','icon'=>'🧒'],
                    ];
                    $pKey = strtolower(trim($ben->parentesco ?? ''));
                    $pc   = $parentColors[$pKey] ?? ['bg'=>'#f1f5f9','color'=>'#475569','icon'=>'👤'];

                    // Nombre legible: primera palabra + primer apellido
                    $partesNombre   = explode(' ', trim($ben->nombres));
                    $primerNombre   = $partesNombre[0] ?? '';
                    $segundoNombre  = $partesNombre[1] ?? '';
                    $primerApellido = $partesNombre[2] ?? ($partesNombre[1] ?? '');
                    // Mostrar: "NOMBRE1 NOMBRE2" o "NOMBRE APELLIDO" (2 primeras palabras)
                    $nombreMostrar  = $primerNombre . (isset($partesNombre[1]) ? ' ' . $partesNombre[1] : '');
                @endphp
                <div class="ben-card">
                    <div class="ben-card-top">
                        <div class="ben-avatar" style="background:{{ $pc['bg'] }};color:{{ $pc['color'] }};">{{ $pc['icon'] }}</div>
                        <div class="ben-info">
                            {{-- Mostrar las primeras 2 palabras del nombre --}}
                            <p class="ben-nombre" title="{{ $ben->nombres }}">{{ $nombreMostrar }}</p>
                            <span class="ben-parentesco" style="background:{{ $pc['bg'] }};color:{{ $pc['color'] }};">{{ ucfirst($ben->parentesco ?: '—') }}</span>
                        </div>
                        {{-- Solo ícono editar en la card; eliminar va dentro del modal --}}
                        <button type="button" class="ben-btn-ico" title="Editar / Ver detalle"
                            onclick='benModalAbrir({{ $ben->toJson() }})'>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                        </button>
                    </div>
                    <div class="ben-card-body">
                        @if($ben->n_documento)
                        <div class="ben-data-row">
                            <span class="ben-data-lbl">{{ $ben->tipo_doc ?? 'Doc.' }}</span>
                            <span class="ben-data-val">{{ $ben->n_documento }}</span>
                        </div>
                        @endif
                        @if($ben->fecha_nacimiento)
                        <div class="ben-data-row">
                            <span class="ben-data-lbl">Nacimiento</span>
                            <span class="ben-data-val">{{ $ben->fecha_nacimiento->format('d/m/Y') }} <em class="ben-edad">({{ $ben->edad }} años)</em></span>
                        </div>
                        @endif
                    </div>
                    {{-- Footer de card: botón adjuntar documento --}}
                    <div class="ben-card-footer">
                        <button type="button" class="ben-btn-adjuntar"
                            onclick='adjuntarDocBen({{ json_encode(["nombres"=>$ben->nombres,"tipo_doc"=>$ben->tipo_doc,"n_documento"=>$ben->n_documento]) }})'>
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                            Adjuntar documento
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     MODAL EDITAR / NUEVO BENEFICIARIO
══════════════════════════════════════════════════════ --}}
<div id="benModal" class="bmodal-backdrop" style="z-index:1080;" onclick="benModalCerrar(event)">
    <div class="bmodal-panel">
        <div class="bmodal-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div class="bmodal-header-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 id="benModalTitulo" class="bmodal-title">Nuevo Beneficiario</h4>
                    <p class="bmodal-subtitle-text">Titular: <strong>{{ $cliente->primer_nombre }} {{ $cliente->primer_apellido }}</strong> · CC {{ $cliente->cedula }}</p>
                </div>
            </div>
            <button type="button" class="bmodal-close" onclick="benModalCerrar()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="benForm" method="POST" action="{{ route('admin.clientes.beneficiarios.store', $cliente->cedula) }}">
            @csrf
            <input type="hidden" name="_method" id="benMetodo" value="POST">
            <div class="bmodal-body">
                <div class="bmodal-field full">
                    <label class="bmodal-lbl">NOMBRES Y APELLIDOS *</label>
                    <input type="text" name="nombres" id="benNombres" required class="bmodal-inp" placeholder="Ej: SANTIAGO GARCÍA PÉREZ" style="text-transform:uppercase;">
                </div>
                <div class="bmodal-grid-2">
                    <div class="bmodal-field">
                        <label class="bmodal-lbl">PARENTESCO</label>
                        <input type="text" name="parentesco" id="benParentesco" class="bmodal-inp" placeholder="Hijo, Cónyuge, Sobrino…" list="listParentesco">
                        <datalist id="listParentesco">
                            <option value="Hijo"><option value="Hija"><option value="Cónyuge">
                            <option value="Esposa"><option value="Esposo">
                            <option value="Padre"><option value="Madre">
                            <option value="Sobrino"><option value="Sobrina">
                        </datalist>
                    </div>
                    <div class="bmodal-field">
                        <label class="bmodal-lbl">TIPO DE DOCUMENTO</label>
                        <select name="tipo_doc" id="benTipoDoc" class="bmodal-inp">
                            <option value="">Seleccione…</option>
                            <option value="RC">RC — Registro Civil</option>
                            <option value="TI">TI — Tarjeta de Identidad</option>
                            <option value="CC">CC — Cédula de Ciudadanía</option>
                            <option value="CE">CE — Cédula de Extranjería</option>
                            <option value="PA">PA — Pasaporte</option>
                            <option value="NUIP">NUIP</option>
                        </select>
                    </div>
                </div>
                <div class="bmodal-grid-2">
                    <div class="bmodal-field">
                        <label class="bmodal-lbl">NÚMERO DE DOCUMENTO</label>
                        <input type="text" name="n_documento" id="benNDoc" class="bmodal-inp" placeholder="Sin puntos ni guiones">
                    </div>
                    <div class="bmodal-field">
                        <label class="bmodal-lbl">FECHA DE NACIMIENTO</label>
                        <input type="date" name="fecha_nacimiento" id="benFNac" class="bmodal-inp">
                    </div>
                </div>
                <div class="bmodal-grid-2">
                    <div class="bmodal-field">
                        <label class="bmodal-lbl">FECHA EXPEDICIÓN DOC.</label>
                        <input type="date" name="fecha_expedicion" id="benFExp" class="bmodal-inp">
                    </div>
                    <div class="bmodal-field">
                        <label class="bmodal-lbl">OBSERVACIÓN</label>
                        <input type="text" name="observacion" id="benObs" class="bmodal-inp" placeholder="Opcional…">
                    </div>
                </div>
            </div>

            {{-- Footer: Eliminar (izq) | Cancelar + Guardar (der) --}}
            <div class="bmodal-footer">
                {{-- Botón Eliminar solo en modo edición --}}
                <div id="benDeleteWrap" style="display:none;flex:1;">
                    <form id="benDeleteForm" method="POST" onsubmit="return confirm('¿Eliminar este beneficiario permanentemente?');">
                        @csrf
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="ben-btn-del-modal">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                            Eliminar
                        </button>
                    </form>
                </div>
                <button type="button" class="bmodal-btn-cancel" onclick="benModalCerrar()">Cancelar</button>
                <button type="submit" class="bmodal-btn-save">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ESTILOS --}}
<style>
@keyframes bmodal-fade-in  { from{opacity:0} to{opacity:1} }
@keyframes bmodal-slide-up { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }

.bmodal-backdrop { display:none;position:fixed;inset:0;z-index:1060;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;animation:bmodal-fade-in .2s ease; }
.blist-panel { background:#fff;border-radius:20px;width:92%;max-width:820px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;animation:bmodal-slide-up .25s cubic-bezier(.16,1,.3,1); }
.blist-header { display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;flex-shrink:0;background:linear-gradient(135deg,#1e40af,#2563eb); }
.blist-body { overflow-y:auto;padding:1.5rem;flex:1; }

/* Cards grid */
.ben-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem; }
.ben-card { background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:box-shadow .2s,transform .2s;display:flex;flex-direction:column; }
.ben-card:hover { transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.10); }
.ben-card-top { display:flex;align-items:center;gap:.65rem;padding:.85rem .85rem .6rem;border-bottom:1px solid #f1f5f9; }
.ben-avatar { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ben-info { flex:1;min-width:0; }
.ben-nombre { margin:0;font-size:.88rem;font-weight:700;color:#0f172a;white-space:normal;word-break:break-word;line-height:1.25; }
.ben-parentesco { display:inline-block;margin-top:.2rem;padding:.12rem .5rem;border-radius:99px;font-size:.67rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase; }
.ben-btn-ico { width:28px;height:28px;border-radius:7px;border:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#475569;flex-shrink:0;transition:all .15s; }
.ben-btn-ico:hover { background:#e2e8f0; }
.ben-card-body { padding:.65rem .85rem;flex:1; }
.ben-data-row { display:flex;justify-content:space-between;align-items:center;padding:.18rem 0;border-bottom:1px solid #f8fafc; }
.ben-data-row:last-child { border-bottom:0; }
.ben-data-lbl { font-size:.68rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em; }
.ben-data-val { font-size:.78rem;font-weight:500;color:#334155; }
.ben-edad { font-size:.68rem;color:#94a3b8;font-style:normal; }

/* Footer adjuntar */
.ben-card-footer { padding:.55rem .85rem;border-top:1px solid #f1f5f9;background:#fafbff; }
.ben-btn-adjuntar { width:100%;display:flex;align-items:center;justify-content:center;gap:.4rem;background:transparent;border:1.5px dashed #cbd5e1;border-radius:8px;padding:.4rem;font-size:.75rem;font-weight:600;color:#7c3aed;cursor:pointer;transition:all .15s; }
.ben-btn-adjuntar:hover { background:#f5f3ff;border-color:#7c3aed; }

/* Empty state */
.bpanel-empty { text-align:center;padding:3rem 2rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:16px;color:#94a3b8; }
.bpanel-empty-icon { margin-bottom:1rem;opacity:.6; }
.bpanel-empty-msg { font-size:1.05rem;font-weight:600;color:#475569;margin:0; }
.bpanel-empty-sub { font-size:.82rem;color:#94a3b8;margin:.4rem 0 0;max-width:380px;margin-inline:auto; }

/* Sub-modal formul. */
.bmodal-panel { background:#fff;border-radius:20px;width:90%;max-width:540px;box-shadow:0 25px 50px rgba(0,0,0,.25);overflow:hidden;animation:bmodal-slide-up .25s cubic-bezier(.16,1,.3,1); }
.bmodal-header { display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;background:linear-gradient(135deg,#1e40af,#2563eb); }
.bmodal-header-icon { width:36px;height:36px;background:rgba(255,255,255,.18);border-radius:10px;display:flex;align-items:center;justify-content:center; }
.bmodal-title { margin:0;font-size:.95rem;font-weight:700;color:#fff; }
.bmodal-subtitle-text { margin:.1rem 0 0;font-size:.72rem;color:rgba(255,255,255,.75); }
.bmodal-close { background:rgba(255,255,255,.15);border:none;border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;transition:background .15s; }
.bmodal-close:hover { background:rgba(255,255,255,.3); }
.bmodal-body { padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:.9rem; }
.bmodal-grid-2 { display:grid;grid-template-columns:1fr 1fr;gap:.85rem; }
.bmodal-field { display:flex;flex-direction:column;gap:.25rem; }
.bmodal-field.full { grid-column:1/-1; }
.bmodal-lbl { font-size:.67rem;font-weight:700;color:#475569;letter-spacing:.05em; }
.bmodal-inp { padding:.45rem .7rem;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.83rem;color:#0f172a;background:#f8fafc;transition:border-color .2s,box-shadow .2s; }
.bmodal-inp:focus { outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);background:#fff; }
.bmodal-footer { display:flex;align-items:center;gap:.65rem;padding:.9rem 1.5rem;background:#f8fafc;border-top:1px solid #e2e8f0; }
.bmodal-btn-cancel { padding:.45rem 1rem;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;color:#475569;font-size:.82rem;font-weight:500;cursor:pointer;transition:all .15s; }
.bmodal-btn-cancel:hover { background:#f1f5f9; }
.bmodal-btn-save { display:inline-flex;align-items:center;gap:.35rem;padding:.45rem 1.15rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(37,99,235,.35);transition:all .2s; }
.bmodal-btn-save:hover { transform:translateY(-1px); }
.ben-btn-del-modal { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;background:#fff;border:1.5px solid #fca5a5;border-radius:8px;color:#dc2626;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .15s; }
.ben-btn-del-modal:hover { background:#fee2e2; }
</style>

<script>
const benBaseUrl = "{{ url('/BryNex/public/admin') }}";

function abrirPanelBeneficiarios() {
    document.getElementById('modalListaBeneficiarios').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function cerrarPanelBen(event) {
    if (event && event.target !== document.getElementById('modalListaBeneficiarios')) return;
    document.getElementById('modalListaBeneficiarios').style.display = 'none';
    document.body.style.overflow = '';
}

function benModalAbrir(ben) {
    const form   = document.getElementById('benForm');
    const delWrap = document.getElementById('benDeleteWrap');
    form.reset();

    if (ben && ben.id) {
        document.getElementById('benModalTitulo').innerText = 'Editar Beneficiario';
        document.getElementById('benMetodo').value = 'PUT';
        form.action = benBaseUrl + '/beneficiarios/' + ben.id;

        document.getElementById('benNombres').value    = ben.nombres || '';
        document.getElementById('benParentesco').value = ben.parentesco || '';
        document.getElementById('benTipoDoc').value    = ben.tipo_doc || '';
        document.getElementById('benNDoc').value       = ben.n_documento || '';
        document.getElementById('benFNac').value       = ben.fecha_nacimiento ? ben.fecha_nacimiento.split('T')[0] : '';
        document.getElementById('benFExp').value       = ben.fecha_expedicion ? ben.fecha_expedicion.split('T')[0] : '';
        document.getElementById('benObs').value        = ben.observacion || '';

        // Mostrar botón Eliminar y configurar su action
        delWrap.style.display = 'block';
        document.getElementById('benDeleteForm').action = benBaseUrl + '/beneficiarios/' + ben.id;
    } else {
        document.getElementById('benModalTitulo').innerText = 'Nuevo Beneficiario';
        document.getElementById('benMetodo').value = 'POST';
        form.action = "{{ route('admin.clientes.beneficiarios.store', $cliente->cedula) }}";
        delWrap.style.display = 'none';
    }

    document.getElementById('benModal').style.display = 'flex';
}
function benModalCerrar(event) {
    if (event && event.target !== document.getElementById('benModal')) return;
    document.getElementById('benModal').style.display = 'none';
}

// Abre el modal de documentos pre-cargando el beneficiario
function adjuntarDocBen(ben) {
    // Cierra el panel de beneficiarios temporalmente y abre docs
    document.getElementById('modalListaBeneficiarios').style.display = 'none';

    // Abre el panel de documentos
    if (typeof abrirPanelDocumentos === 'function') {
        abrirPanelDocumentos(ben); // pasa el beneficiario para pre-seleccionarlo
    }
}
</script>
