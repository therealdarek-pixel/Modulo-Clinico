// JavaScript mínimo del sitio.
// La mayoría del HTML lo renderiza PHP. Esto solo se encarga de:
//  - toggle del password en login
//  - mostrar el dato "fecha" formateado si hace falta
//  - pequeñas interacciones (chips de filtro hacen submit, etc.)

document.addEventListener('click', (e) => {
  // Toggle de visibilidad de password (botón con data-toggle-pass="input-id")
  const tgl = e.target.closest('[data-toggle-pass]');
  if (tgl) {
    const id = tgl.getAttribute('data-toggle-pass');
    const inp = document.getElementById(id);
    if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
  }
});

// Helper global: convierte un MySQL datetime "YYYY-MM-DD HH:MM:SS" a Date.
window.parseMysql = (s) => new Date(String(s).replace(' ', 'T'));
