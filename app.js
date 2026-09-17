let datosPrestamos = [];
let datosHistorial = [];
let estadoFiltroPill = 'todos';
let idEquipoAEditar = null;
let gruposPlegados = {};

document.addEventListener('DOMContentLoaded', () => {
    // Establecer fecha por defecto (Hoy)
    document.getElementById('fechaInput').value = new Date().toISOString().split('T')[0];
    cargarDatos();
});

// Cargar datos desde api.php
async function cargarDatos() {
    try {
        const res = await fetch('api.php?action=get_all');
        const data = await res.json();
        if (data.status === 'success') {
            datosPrestamos = data.prestamos || [];
            datosHistorial = data.historial || [];
            actualizarMetricas();
            renderizar();
            renderizarHistorial();
        }
    } catch (err) {
        console.error('Error al cargar datos:', err);
    }
}

// Alternar visibilidad de Prestatario en el formulario principal
function togglePrestatarioField() {
    const situacion = document.getElementById('situacionSelect').value;
    const prestatarioGroup = document.getElementById('prestatarioGroup');
    if (situacion === 'prestado') {
        prestatarioGroup.style.display = 'flex';
    } else {
        prestatarioGroup.style.display = 'flex'; // Siempre accesible pero opcional
    }
}

// Guardar o Editar Equipo
async function guardarEquipo(e) {
    e.preventDefault();

    const id = document.getElementById('equipoId').value;
    const producto = document.getElementById('productoInput').value.trim();
    const codigo = document.getElementById('codigoInput').value.trim();
    const categoria = document.getElementById('categoriaSelect').value;
    const estadoDisponibilidad = document.getElementById('situacionSelect').value;
    const prestatario = document.getElementById('prestatarioInput').value.trim();
    const estadoFisico = document.getElementById('estadoFisicoSelect').value;
    const observaciones = document.getElementById('observacionesInput').value.trim();
    const fecha = document.getElementById('fechaInput').value;

    const payload = {
        id,
        producto,
        codigo,
        categoria,
        estadoDisponibilidad,
        prestatario,
        estadoFisico,
        observaciones,
        fecha
    };

    const action = id ? 'edit_prestamo' : 'add_prestamo';

    try {
        const res = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (result.status === 'success') {
            cancelarEdicion();
            cargarDatos();
        } else {
            alert(result.message || 'Error al guardar.');
        }
    } catch (err) {
        alert('Error en la comunicación con el servidor.');
    }
}

// Modal Personalizado para Prestar
function prestarEquipo(id) {
    idEquipoAEditar = id;
    document.getElementById('prestatarioModalInput').value = '';
    document.getElementById('modalPrestar').classList.add('active');
}

function cerrarModalPrestar() {
    document.getElementById('modalPrestar').classList.remove('active');
    idEquipoAEditar = null;
}

async function confirmarPrestamoRapido() {
    if (!idEquipoAEditar) return;

    // Si el usuario deja en blanco el prestatario, se registra sin problemas
    const prestatarioNombre = document.getElementById('prestatarioModalInput').value.trim();

    const equipo = datosPrestamos.find(item => item.id == idEquipoAEditar);
    if (equipo) {
        equipo.estadoDisponibilidad = 'prestado';
        equipo.prestatario = prestatarioNombre;
        equipo.fecha = new Date().toISOString().split('T')[0];

        try {
            await fetch('api.php?action=edit_prestamo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(equipo)
            });
            cerrarModalPrestar();
            cargarDatos();
        } catch (err) {
            alert('Error al procesar el préstamo.');
        }
    }
}

// Devolver Equipo
async function devolverEquipo(id) {
    if (!confirm('¿Confirmas que este equipo ha sido devuelto al almacén?')) return;

    try {
        await fetch('api.php?action=devolver_prestamo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, fechaDevolucion: new Date().toISOString().split('T')[0] })
        });
        cargarDatos();
    } catch (err) {
        alert('Error al devolver el equipo.');
    }
}

// Eliminar Equipo
async function eliminarEquipo(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este registro?')) return;

    try {
        await fetch('api.php?action=delete_prestamo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        cargarDatos();
    } catch (err) {
        alert('Error al eliminar.');
    }
}

// Cargar Datos en el Formulario para Editar
function cargarParaEditar(id) {
    const item = datosPrestamos.find(i => i.id == id);
    if (!item) return;

    document.getElementById('equipoId').value = item.id;
    document.getElementById('productoInput').value = item.producto;
    document.getElementById('codigoInput').value = item.codigo || '';
    document.getElementById('categoriaSelect').value = item.categoria || '';
    document.getElementById('situacionSelect').value = item.estadoDisponibilidad || 'disponible';
    document.getElementById('prestatarioInput').value = item.prestatario || '';
    document.getElementById('estadoFisicoSelect').value = item.estadoFisico || 'operativo';
    document.getElementById('observacionesInput').value = item.observaciones || '';
    document.getElementById('fechaInput').value = item.fecha;

    document.getElementById('formTitle').innerText = '✏️ Editar Equipo';
    document.getElementById('btnSubmitForm').innerText = 'Actualizar Cambios 💾';
    document.getElementById('btnCancelEdit').style.display = 'inline-block';
    
    togglePrestatarioField();
}

function cancelarEdicion() {
    document.getElementById('equipoForm').reset();
    document.getElementById('equipoId').value = '';
    document.getElementById('fechaInput').value = new Date().toISOString().split('T')[0];
    document.getElementById('formTitle').innerText = '➕ Registrar Equipo';
    document.getElementById('btnSubmitForm').innerText = 'Guardar Equipo 💾';
    document.getElementById('btnCancelEdit').style.display = 'none';
    togglePrestatarioField();
}

// Métricas del Dashboard
function actualizarMetricas() {
    const total = datosPrestamos.length;
    const almacen = datosPrestamos.filter(i => (i.estadoDisponibilidad || 'disponible') === 'disponible').length;
    const prestados = datosPrestamos.filter(i => i.estadoDisponibilidad === 'prestado').length;

    let alertas = 0;
    const hoy = new Date();
    datosPrestamos.forEach(i => {
        if (i.estadoDisponibilidad === 'prestado' && i.fecha) {
            const dias = Math.floor((hoy - new Date(i.fecha)) / (1000 * 60 * 60 * 24));
            if (dias >= 6) alertas++;
        }
    });

    document.getElementById('statTotal').innerText = total;
    document.getElementById('statAlmacen').innerText = almacen;
    document.getElementById('statPrestados').innerText = prestados;
    document.getElementById('statAlertas').innerText = alertas;
}

// Filtrar y Ordenar
function filtrarEstado(estado, btn) {
    estadoFiltroPill = estado;
    document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderizar();
}

function toggleGrupo(nombreGrupo) {
    gruposPlegados[nombreGrupo] = !gruposPlegados[nombreGrupo];
    renderizar();
}

// Renderizar Tabla Inventario (Agrupado por Producto)
function renderizar() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const sort = document.getElementById('sortSelect').value;
    const tbody = document.getElementById('tablaInventarioBody');
    tbody.innerHTML = '';

    let filtrados = datosPrestamos.filter(item => {
        const disp = item.estadoDisponibilidad || 'disponible';
        
        // Filtro por Pill
        if (estadoFiltroPill === 'disponible' && disp !== 'disponible') return false;
        if (estadoFiltroPill === 'prestado' && disp !== 'prestado') return false;
        if (estadoFiltroPill === 'averiado' && item.estadoFisico !== 'averiado') return false;
        
        if (estadoFiltroPill === 'alerta') {
            if (disp !== 'prestado' || !item.fecha) return false;
            const dias = Math.floor((new Date() - new Date(item.fecha)) / (1000 * 60 * 60 * 24));
            if (dias < 6) return false;
        }

        // Buscador
        if (search) {
            const matchProd = (item.producto || '').toLowerCase().includes(search);
            const matchCod = (item.codigo || '').toLowerCase().includes(search);
            const matchPrest = (item.prestatario || '').toLowerCase().includes(search);
            const matchObs = (item.observaciones || '').toLowerCase().includes(search);
            return matchProd || matchCod || matchPrest || matchObs;
        }
        return true;
    });

    // Ordenación
    filtrados.sort((a, b) => {
        if (sort === 'producto-asc') return a.producto.localeCompare(b.producto);
        if (sort === 'producto-desc') return b.producto.localeCompare(a.producto);
        if (sort === 'fecha-desc') return new Date(b.fecha) - new Date(a.fecha);
        if (sort === 'fecha-asc') return new Date(a.fecha) - new Date(b.fecha);
        return 0;
    });

    // Agrupar por nombre de Producto
    const grupos = {};
    filtrados.forEach(item => {
        const p = item.producto;
        if (!grupos[p]) grupos[p] = [];
        grupos[p].push(item);
    });

    if (Object.keys(grupos).length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted);">No hay equipos registrados.</td></tr>';
        return;
    }

    const hoy = new Date();

    Object.keys(grupos).forEach(nombreProducto => {
        const items = grupos[nombreProducto];
        const estaPlegado = !!gruposPlegados[nombreProducto];

        const enAlmacenCount = items.filter(i => (i.estadoDisponibilidad || 'disponible') === 'disponible').length;
        const prestadosCount = items.filter(i => i.estadoDisponibilidad === 'prestado').length;

        // Fila Encabezado del Grupo
        const trGroup = document.createElement('tr');
        trGroup.className = 'group-header-row';
        trGroup.onclick = () => toggleGrupo(nombreProducto);
        trGroup.innerHTML = `
            <td colspan="8" class="group-header-cell">
                <span class="group-arrow">${estaPlegado ? '▶' : '▼'}</span>
                <strong>${nombreProducto}</strong>
                <span class="badge-count">${items.length} ${items.length === 1 ? 'unidad' : 'unidades'}</span>
                <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-left:12px;">
                    (${enAlmacenCount} en almacén 🟢 | ${prestadosCount} prestados 🤝)
                </span>
                <span style="float:right; font-size:0.8rem; font-weight:700; color:var(--text-muted);">
                    ${estaPlegado ? 'Clic para desplegar ▲' : 'Clic para plegar ▼'}
                </span>
            </td>
        `;
        tbody.appendChild(trGroup);

        if (!estaPlegado) {
            items.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'child-row';

                const disp = item.estadoDisponibilidad || 'disponible';
                
                // Badges
                let dispBadge = `<span class="badge badge-disponible">🟢 En Almacén</span>`;
                if (disp === 'prestado') {
                    const quien = item.prestatario ? `a ${item.prestatario}` : '(Sin nombre)';
                    dispBadge = `<span class="badge badge-prestado">🤝 ${quien}</span>`;
                }

                let fisicoBadge = `<span class="badge badge-fisico-operativo">🟢 Operativo</span>`;
                if (item.estadoFisico === 'desperfecto') fisicoBadge = `<span class="badge badge-fisico-desperfecto">🟡 Desperfecto</span>`;
                if (item.estadoFisico === 'averiado') fisicoBadge = `<span class="badge badge-fisico-averiado">🔴 Averiado</span>`;

                // Cálculo de Alerta
                let alertaBadge = `<span class="badge normal">-</span>`;
                if (disp === 'prestado' && item.fecha) {
                    const dias = Math.floor((hoy - new Date(item.fecha)) / (1000 * 60 * 60 * 24));
                    if (dias >= 12) {
                        alertaBadge = `<span class="badge alerta-12">⚠️ +12d (${dias}d)</span>`;
                    } else if (dias >= 6) {
                        alertaBadge = `<span class="badge alerta-6">⚠️ +6d (${dias}d)</span>`;
                    }
                }

                // Botón Acción
                let accionBtn = '';
                if (disp === 'disponible') {
                    accionBtn = `<button class="btn-prestar-quick" onclick="prestarEquipo('${item.id}')">🤝 Prestar</button>`;
                } else {
                    accionBtn = `<button class="btn-prestar-quick" style="border-color:#a7f3d0; color:#047857;" onclick="devolverEquipo('${item.id}')">↩️ Devolver</button>`;
                }

                tr.innerHTML = `
                    <td><strong>${item.producto}</strong></td>
                    <td><code>${item.codigo || '-'}</code></td>
                    <td><span class="badge-cat">${item.categoria || '-'}</span></td>
                    <td>${fisicoBadge}</td>
                    <td>${dispBadge}</td>
                    <td><strong>${item.fecha || '-'}</strong></td>
                    <td>${alertaBadge}</td>
                    <td>
                        <div class="actions-wrapper">
                            ${accionBtn}
                            <button class="btn-icon" onclick="cargarParaEditar('${item.id}')" title="Editar">✏️</button>
                            <button class="btn-icon" onclick="eliminarEquipo('${item.id}')" title="Eliminar">🗑️</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    });
}

// Renderizar Historial
function renderizarHistorial() {
    const tbody = document.getElementById('tablaHistorialBody');
    tbody.innerHTML = '';

    if (datosHistorial.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">El historial está vacío.</td></tr>';
        return;
    }

    datosHistorial.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${item.producto}</strong></td>
            <td><code>${item.codigo || '-'}</code></td>
            <td><span class="badge-cat">${item.categoria || '-'}</span></td>
            <td><strong>${item.prestatario || '-'}</strong></td>
            <td>${item.fecha || '-'}</td>
            <td><strong>${item.fechaDevolucion || '-'}</strong></td>
            <td><span class="badge devuelto">Devuelto 🟢</span></td>
        `;
        tbody.appendChild(tr);
    });
}

// Vaciar Historial
async function vaciarHistorial() {
    if (!confirm('¿Seguro que deseas borrar todo el historial de devoluciones?')) return;
    try {
        await fetch('api.php?action=vaciar_historial');
        cargarDatos();
    } catch (err) {
        alert('Error al vaciar el historial.');
    }
}

// Pestañas
function cambiarTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    if (tab === 'inventario') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tabInventario').classList.add('active');
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('tabHistorial').classList.add('active');
    }
}

// Tema Oscuro / Claro
function toggleTheme() {
    document.body.classList.toggle('dark-theme');
    const btn = document.getElementById('btnThemeToggle');
    btn.innerText = document.body.classList.contains('dark-theme') ? '☀️' : '🌙';
}

// Copias de Seguridad
function descargarBackup() {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify({ prestamos: datosPrestamos, historial: datosHistorial }, null, 2));
    const a = document.createElement('a');
    a.href = dataStr;
    a.download = `backup_inventario_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
}

async function restaurarBackup(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (event) => {
        try {
            const json = JSON.parse(event.target.result);
            if (json.prestamos && json.historial) {
                await fetch('api.php?action=importar_backup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(json)
                });
                alert('Backup restaurado con éxito.');
                cargarDatos();
            } else {
                alert('Estructura de backup no válida.');
            }
        } catch (err) {
            alert('Error al leer el archivo JSON.');
        }
    };
    reader.readAsText(file);
}

function exportarCSV() {
    let csv = "ID,Producto,Codigo,Categoria,EstadoDisponibilidad,Prestatario,EstadoFisico,Fecha,Observaciones\n";
    datosPrestamos.forEach(i => {
        csv += `"${i.id}","${i.producto}","${i.codigo || ''}","${i.categoria || ''}","${i.estadoDisponibilidad || 'disponible'}","${i.prestatario || ''}","${i.estadoFisico || ''}","${i.fecha || ''}","${i.observaciones || ''}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `inventario_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
}