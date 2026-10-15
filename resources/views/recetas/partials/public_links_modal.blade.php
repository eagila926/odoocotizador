@php
  $flashRecipeLinks = session('receta_public_links', []);
@endphp

<div class="modal fade" id="modalRecetaPublicLinks" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enlace publico de receta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success mb-3">
          El PDF se descargo. Estos enlaces pueden abrirse sin iniciar sesion.
        </div>
        <label for="recetaPublicLinksText" class="form-label fw-semibold">
          <span id="recetaPublicLinksCount"></span>
        </label>
        <textarea id="recetaPublicLinksText" class="form-control" rows="7" readonly></textarea>
        <div class="form-text">Cada enlace corresponde a una receta y aparece en una línea separada.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="btnCopyAllRecetaLinks">
          <i class="bi bi-clipboard me-1"></i> Copiar todos
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.recetaPublicLinksFromFlash = @json($flashRecipeLinks);

  window.parseRecetaPublicLinksHeader = function(response) {
    const encoded = response.headers.get('X-Receta-Public-Links');
    if (!encoded) return [];

    try {
      return JSON.parse(atob(encoded)) || [];
    } catch (e) {
      return [];
    }
  };

  window.showRecetaPublicLinks = function(links) {
    if (!Array.isArray(links) || links.length === 0) return;

    const modalEl = document.getElementById('modalRecetaPublicLinks');
    const textEl = document.getElementById('recetaPublicLinksText');
    const countEl = document.getElementById('recetaPublicLinksCount');
    const lineas = links
      .filter(link => link.url)
      .map(link => `${link.nombre || `Receta #${link.id || ''}`}: ${link.url}`);
    if (!modalEl || !textEl || lineas.length === 0) return;

    textEl.value = lineas.join('\n');
    textEl.rows = Math.min(Math.max(lineas.length, 3), 12);
    if (countEl) {
      countEl.textContent = `${lineas.length} enlace${lineas.length === 1 ? '' : 's'} público${lineas.length === 1 ? '' : 's'}`;
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  window.downloadPdfResponse = async function(response, defaultFilename) {
    const blob = await response.blob();
    const contentDisposition = response.headers.get('Content-Disposition') || '';
    const filenameMatch = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(contentDisposition);
    let filename = defaultFilename || 'receta.pdf';
    if (filenameMatch) filename = decodeURIComponent(filenameMatch[1] || filenameMatch[2]);

    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
      window.navigator.msSaveOrOpenBlob(blob, filename);
      return;
    }

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  };

  window.submitPdfFormWithPublicLinks = async function(form, options) {
    const settings = options || {};
    const response = await fetch(form.action, {
      method: form.method || 'POST',
      body: new FormData(form),
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const contentType = response.headers.get('Content-Type') || '';
    if (!response.ok || !contentType.includes('application/pdf')) {
      const text = await response.text();
      let message = settings.errorMessage || 'No se pudo generar el PDF.';
      try {
        const json = JSON.parse(text);
        const errors = json.errors || {};
        message = Object.values(errors).flat().join(' ') || json.message || message;
      } catch (e) {
        message = text || message;
      }
      throw new Error(message);
    }

    const links = window.parseRecetaPublicLinksHeader(response);
    await window.downloadPdfResponse(response, settings.defaultFilename || 'receta.pdf');
    window.showRecetaPublicLinks(links);
    return links;
  };

  document.addEventListener('click', function(e) {
    const copyAllBtn = e.target.closest('#btnCopyAllRecetaLinks');
    if (copyAllBtn) {
      const textEl = document.getElementById('recetaPublicLinksText');
      const texto = textEl?.value || '';
      if (!texto) return;

      const copiar = navigator.clipboard?.writeText
        ? navigator.clipboard.writeText(texto)
        : new Promise((resolve, reject) => {
            textEl.focus();
            textEl.select();
            document.execCommand('copy') ? resolve() : reject();
          });

      copiar.then(() => {
        copyAllBtn.innerHTML = '<i class="bi bi-check2 me-1"></i> Copiados';
        setTimeout(() => {
          copyAllBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i> Copiar todos';
        }, 1500);
      });
      return;
    }

    const copyBtn = e.target.closest('[data-copy-url]');
    if (!copyBtn) return;

    const url = copyBtn.dataset.copyUrl;
    navigator.clipboard?.writeText(url).then(() => {
      copyBtn.textContent = 'Copiado';
      setTimeout(() => copyBtn.textContent = 'Copiar', 1200);
    });
  });

  document.addEventListener('DOMContentLoaded', function() {
    window.showRecetaPublicLinks(window.recetaPublicLinksFromFlash);
  });
</script>
