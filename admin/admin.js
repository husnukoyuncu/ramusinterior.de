(() => {
  'use strict';

  const API = 'api.php';
  const LANGS = [
    { code: 'de', label: 'DE' },
    { code: 'en', label: 'EN' },
    { code: 'tr', label: 'TR' },
    { code: 'ru', label: 'RU' },
    { code: 'ar', label: 'AR' },
  ];
  const PALETTE = ['#8a6a3f', '#4f6f4f', '#3f6285', '#6f5e92', '#a8492f', '#5e7a8a', '#8a5e6f', '#6f8a5e'];

  const state = {
    view: 'list',
    projects: [],
    categories: [],
    query: '',
    filter: 'all',
    draft: null,
    draftLang: 'de',
    catDraftLabels: emptyLangMap(),
    editingCategory: null,
    editCatLabels: emptyLangMap(),
    loading: true,
  };

  function emptyLangMap() {
    const o = {};
    LANGS.forEach((l) => { o[l.code] = ''; });
    return o;
  }

  const $topbar = document.getElementById('topbar');
  const $content = document.getElementById('content');
  const $navProjects = document.getElementById('nav-projects');
  const $navCategories = document.getElementById('nav-categories');
  const $toastHost = document.getElementById('toast-host');

  function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function toast(message) {
    const el = document.createElement('div');
    el.className = 'toast';
    el.textContent = message;
    $toastHost.appendChild(el);
    setTimeout(() => el.remove(), 3200);
  }

  async function api(action, { method = 'POST', body, formData, query } = {}) {
    let url = API;
    const params = new URLSearchParams(query || {});
    if (params.toString()) url += '?' + params.toString();
    const opts = { method };
    if (formData) {
      opts.body = formData;
    } else if (method !== 'GET') {
      opts.headers = { 'Content-Type': 'application/json' };
      opts.body = JSON.stringify(body || {});
      if (action) {
        url += (url.includes('?') ? '&' : '?') + 'action=' + encodeURIComponent(action);
      }
    }
    const res = await fetch(url, opts);
    if (res.status === 401) {
      window.location.href = 'login.php';
      throw new Error('Oturum süresi doldu.');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.status === 'error') {
      throw new Error(data.message || 'Bir hata oluştu.');
    }
    return data;
  }

  async function loadAll() {
    state.loading = true;
    try {
      const [p, c] = await Promise.all([
        api(null, { method: 'GET', query: { resource: 'projects' } }),
        api(null, { method: 'GET', query: { resource: 'categories' } }),
      ]);
      state.projects = p.projects || [];
      state.categories = c.categories || [];
    } catch (e) {
      toast('Veri yüklenemedi: ' + e.message);
    }
    state.loading = false;
    render();
  }

  function categoryLabel(slug) {
    const c = state.categories.find((x) => x.slug === slug);
    return c ? c.label : slug;
  }

  function categoryColor(slug) {
    const idx = state.categories.findIndex((x) => x.slug === slug);
    return idx === -1 ? '#8a8378' : PALETTE[idx % PALETTE.length];
  }

  function emptyContent() {
    return emptyLangMap();
  }

  /** Best available display name for a project: given lang, else DE, else whatever's there. */
  function projectName(p, lang) {
    if (!p || !p.name) return '';
    if (typeof p.name === 'string') return p.name;
    return p.name[lang] || p.name.de || Object.values(p.name).find(Boolean) || '';
  }

  function openEditor(project) {
    const draft = JSON.parse(JSON.stringify(project));
    draft.content = Object.assign(emptyContent(), draft.content || {});
    draft.name = typeof draft.name === 'string'
      ? Object.assign(emptyLangMap(), { de: draft.name })
      : Object.assign(emptyLangMap(), draft.name || {});
    state.draft = draft;
    state.draftLang = 'de';
    state.view = 'editor';
    render();
  }

  function newProject() {
    state.draft = {
      id: null,
      category: state.categories[0] ? state.categories[0].slug : '',
      name: emptyLangMap(), location: '', area: '', year: '',
      active: true, images: [], content: emptyContent(),
    };
    state.draftLang = 'de';
    state.view = 'editor';
    render();
  }

  function cancelEdit() {
    state.draft = null;
    state.view = 'list';
    render();
  }

  function syncEditableIntoDraft() {
    const el = document.getElementById('rt-editable');
    if (el && state.draft) state.draft.content[state.draftLang] = el.innerHTML;
  }

  function syncNameIntoDraft() {
    const el = document.getElementById('f-name');
    if (el && state.draft) state.draft.name[state.draftLang] = el.value;
  }

  async function saveDraft() {
    syncEditableIntoDraft();
    syncNameIntoDraft();
    const d = state.draft;
    if (!d.category) { toast('Lütfen bir kategori seçin.'); return; }
    if (!d.name.de.trim()) { toast('Lütfen en azından Almanca proje adını girin.'); return; }
    try {
      await api('save_project', { body: { project: d } });
      toast('Proje kaydedildi.');
      state.draft = null;
      state.view = 'list';
      await loadAll();
    } catch (e) {
      toast('Kaydedilemedi: ' + e.message);
    }
  }

  async function deleteDraft() {
    if (!state.draft || state.draft.id == null) return;
    if (!confirm('Bu projeyi silmek istediğinize emin misiniz?')) return;
    try {
      await api('delete_project', { body: { id: state.draft.id } });
      toast('Proje silindi.');
      state.draft = null;
      state.view = 'list';
      await loadAll();
    } catch (e) {
      toast('Silinemedi: ' + e.message);
    }
  }

  async function toggleActive(id) {
    const p = state.projects.find((x) => x.id === id);
    if (!p) return;
    const updated = Object.assign({}, p, { active: !p.active });
    try {
      await api('save_project', { body: { project: updated } });
      await loadAll();
    } catch (e) {
      toast('Güncellenemedi: ' + e.message);
    }
  }

  async function uploadFiles(files) {
    for (const file of files) {
      if (!file.type.startsWith('image/')) continue;
      const fd = new FormData();
      fd.append('file', file);
      try {
        const res = await api('upload_image', { formData: fd, query: { action: 'upload_image' } });
        state.draft.images.push(res.path);
        render();
      } catch (e) {
        toast('Görsel yüklenemedi: ' + e.message);
      }
    }
  }

  function removeImage(idx) {
    state.draft.images.splice(idx, 1);
    render();
  }

  async function addCategory() {
    if (!state.catDraftLabels.de.trim()) { toast('Lütfen en azından Almanca adı girin.'); return; }
    try {
      await api('save_category', { body: { labels: state.catDraftLabels } });
      state.catDraftLabels = emptyLangMap();
      await loadAll();
      state.view = 'categories';
      render();
    } catch (e) {
      toast('Eklenemedi: ' + e.message);
    }
  }

  function startEditCategory(slug) {
    const c = state.categories.find((x) => x.slug === slug);
    if (!c) return;
    state.editingCategory = slug;
    state.editCatLabels = Object.assign(emptyLangMap(), c.labels || { de: c.label });
    render();
  }

  function cancelEditCategory() {
    state.editingCategory = null;
    render();
  }

  async function saveEditCategory() {
    const slug = state.editingCategory;
    const labels = state.editCatLabels;
    if (!labels.de.trim()) { toast('Lütfen en azından Almanca adı girin.'); return; }
    try {
      await api('save_category', { body: { slug, labels } });
      state.editingCategory = null;
      toast('Kategori güncellendi.');
      await loadAll();
      state.view = 'categories';
      render();
    } catch (e) {
      toast('Güncellenemedi: ' + e.message);
    }
  }

  async function removeCategory(slug) {
    try {
      await api('delete_category', { body: { slug } });
      await loadAll();
      state.view = 'categories';
      render();
    } catch (e) {
      toast(e.message);
    }
  }

  function execCmd(cmd, val) {
    const el = document.getElementById('rt-editable');
    if (!el) return;
    el.focus();
    document.execCommand(cmd, false, val || null);
  }

  /* ---------- render ---------- */

  function render() {
    $navProjects.classList.toggle('on', state.view === 'list' || state.view === 'editor');
    $navCategories.classList.toggle('on', state.view === 'categories');
    document.getElementById('nav-projects-badge').textContent = String(state.projects.length);
    document.getElementById('nav-categories-badge').textContent = String(state.categories.length);

    if (state.view === 'list') renderList();
    else if (state.view === 'editor') renderEditor();
    else renderCategories();
  }

  function filteredProjects() {
    const q = state.query.trim().toLowerCase();
    return state.projects.filter((p) =>
      (state.filter === 'all' || p.category === state.filter) &&
      (!q || projectName(p, 'de').toLowerCase().includes(q) || p.location.toLowerCase().includes(q))
    );
  }

  function renderList() {
    const activeCount = state.projects.filter((p) => p.active).length;
    $topbar.innerHTML = `
      <div style="flex:1;display:flex;flex-direction:column;line-height:1.2;">
        <h1 class="topbar-title">Projeler</h1>
        <span class="topbar-sub">${state.projects.length} proje · ${activeCount} aktif · ${state.projects.length - activeCount} pasif</span>
      </div>
      <button class="btn btn-dark" data-action="new-project"><span class="icon" style="font-size:19px;">add</span> Yeni Proje</button>
    `;

    const projects = filteredProjects();
    const chips = ['all', ...state.categories.map((c) => c.slug)].map((slug) => {
      const label = slug === 'all' ? 'Tümü' : categoryLabel(slug);
      const on = state.filter === slug;
      return `<button class="chip${on ? ' on' : ''}" data-action="filter" data-slug="${esc(slug)}">${esc(label)}</button>`;
    }).join('');

    const rows = projects.map((p) => `
      <div class="table-row" data-action="open-project" data-id="${p.id}">
        <div class="row-name">
          <div class="row-thumb">${p.images && p.images[0] ? `<img src="../${esc(p.images[0])}" alt="">` : '<span class="icon">image</span>'}</div>
          <span class="title">${esc(projectName(p, 'de'))}</span>
        </div>
        <span class="cat-pill" style="color:${categoryColor(p.category)};">${esc(categoryLabel(p.category))}</span>
        <span class="cell-meta">${esc(p.location)}</span>
        <span class="cell-meta">${p.area ? esc(p.area) + ' m²' : '—'}</span>
        <span class="cell-meta">${esc(p.year)}</span>
        <div class="status-cell" data-action="stop-propagation">
          <button class="pill-btn ${p.active ? 'pill-active' : 'pill-passive'}" data-action="toggle-active" data-id="${p.id}">
            <span class="dot"></span>${p.active ? 'Aktif' : 'Pasif'}
          </button>
        </div>
      </div>
    `).join('');

    $content.innerHTML = `
      <div class="wrap-list">
        <div class="toolbar-row">
          <div class="search-box">
            <span class="icon">search</span>
            <input type="text" id="search-input" placeholder="Proje ara…" value="${esc(state.query)}">
          </div>
          <div class="chip-row">${chips}</div>
        </div>
        <div class="card table">
          <div class="table-head"><span>Proje</span><span>Kategori</span><span>Konum</span><span>Alan</span><span>Yıl</span><span style="text-align:right;">Durum</span></div>
          ${rows || '<div class="empty-state">Bu filtreye uygun proje bulunamadı.</div>'}
        </div>
      </div>
    `;

    document.getElementById('search-input').addEventListener('input', (e) => {
      state.query = e.target.value;
      renderList();
    });
  }

  function renderEditor() {
    const d = state.draft;
    const title = projectName(d, state.draftLang) || 'Yeni Proje';
    $topbar.innerHTML = `
      <button class="back-btn" data-action="cancel-edit"><span class="icon" style="font-size:20px;">arrow_back</span> Projeler</button>
      <div style="flex:1;font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(title)}</div>
      <button class="pill-btn ${d.active ? 'pill-active' : 'pill-passive'}" data-action="toggle-draft-active"><span class="dot"></span>${d.active ? 'Aktif' : 'Pasif'}</button>
      <button class="btn btn-outline" data-action="cancel-edit">İptal</button>
      <button class="btn btn-dark" data-action="save-draft"><span class="icon" style="font-size:18px;">check</span> Kaydet</button>
    `;

    const galleryItems = d.images.map((src, i) => `
      <div class="gallery-item">
        ${i === 0 ? '<span class="gallery-cover-badge">KAPAK</span>' : ''}
        <button class="gallery-remove" data-action="remove-image" data-idx="${i}"><span class="icon" style="font-size:16px;">close</span></button>
        <img src="../${esc(src)}" alt="">
      </div>
    `).join('');

    const rtButtons = [
      { cmd: 'bold', icon: 'format_bold', title: 'Kalın' },
      { cmd: 'italic', icon: 'format_italic', title: 'İtalik' },
      { cmd: 'h3', icon: 'title', title: 'Başlık' },
      { cmd: 'insertUnorderedList', icon: 'format_list_bulleted', title: 'Liste' },
      { cmd: 'link', icon: 'link', title: 'Bağlantı' },
      { cmd: 'removeFormat', icon: 'format_clear', title: 'Biçimi temizle' },
    ].map((b) => `<button class="rt-btn" type="button" title="${b.title}" data-action="rt-cmd" data-cmd="${b.cmd}"><span class="icon">${b.icon}</span></button>`).join('');

    const langTabs = LANGS.map((l) => `<button class="lang-tab${state.draftLang === l.code ? ' on' : ''}" data-action="lang-tab" data-lang="${l.code}">${l.label}</button>`).join('');
    const draftLangLabel = (LANGS.find((l) => l.code === state.draftLang) || {}).label || '';

    const catOptions = state.categories.map((c) => `<option value="${esc(c.slug)}" ${c.slug === d.category ? 'selected' : ''}>${esc(c.label)}</option>`).join('');

    $content.innerHTML = `
      <div class="wrap-editor">
        <div class="editor-col">
          <div class="card panel">
            <div class="field-grid">
              <label class="field small"><span class="field-label">Konum</span><input type="text" id="f-location" placeholder="Berlin" value="${esc(d.location)}"></label>
              <label class="field small"><span class="field-label">Alan (m²)</span><input type="text" inputmode="numeric" id="f-area" placeholder="200" value="${esc(d.area)}"></label>
              <label class="field small"><span class="field-label">Yıl</span><input type="text" inputmode="numeric" id="f-year" placeholder="2020" value="${esc(d.year)}"></label>
            </div>
          </div>

          <div class="card panel">
            <div class="panel-head">
              <div>
                <div class="panel-title">İmaj Galeri</div>
                <div class="panel-hint">Görselleri sürükleyip bırakın · ilk görsel kapak olur</div>
              </div>
            </div>
            <div class="gallery-grid">
              ${galleryItems}
              <div class="gallery-add" id="gallery-add">
                <span class="icon">add_photo_alternate</span>
                <span class="label">Görsel ekle</span>
              </div>
              <input type="file" id="gallery-input" accept="image/*" multiple style="display:none;">
            </div>
          </div>

          <div class="card" style="overflow:hidden;">
            <div style="padding:18px 24px 0;"><div class="panel-title">Başlık ve İçerik</div></div>
            <div class="lang-tabs">${langTabs}</div>
            <div style="padding:16px 24px 0;">
              <label class="field">
                <span class="field-label">Proje Adı (${esc(draftLangLabel)})</span>
                <input type="text" id="f-name" placeholder="Örn. LVM Versicherungsbüro" value="${esc(d.name[state.draftLang] || '')}">
              </label>
            </div>
            <div class="rt-toolbar">${rtButtons}</div>
            <div contenteditable="true" class="rt-editable" id="rt-editable" data-ph="Proje açıklamasını buraya yazın…"></div>
          </div>
        </div>

        <div class="rail">
          <div class="card panel" style="gap:0;">
            <div class="field-label" style="margin-bottom:12px;">Yayın Durumu</div>
            <div class="seg">
              <button class="seg-btn ${d.active ? 'active-on' : ''}" data-action="set-active" data-val="1">Aktif</button>
              <button class="seg-btn ${!d.active ? 'passive-on' : ''}" data-action="set-active" data-val="0">Pasif</button>
            </div>
            <div class="status-hint">${d.active ? 'Proje web sitesinde görünür ve ziyaretçilere açıktır.' : 'Proje taslak olarak saklanır, sitede yayınlanmaz.'}</div>
          </div>

          <div class="card panel" style="gap:0;">
            <div class="panel-head" style="margin-bottom:12px;">
              <span class="field-label">Kategori</span>
              <button class="cat-manage-link" data-action="go-categories">Yönet</button>
            </div>
            <select id="f-category">${catOptions}</select>
          </div>

          ${d.id != null ? '<button class="delete-btn" data-action="delete-draft"><span class="icon" style="font-size:18px;">delete</span> Projeyi sil</button>' : ''}
        </div>
      </div>
    `;

    const editable = document.getElementById('rt-editable');
    editable.innerHTML = d.content[state.draftLang] || '';

    ['f-location', 'f-area', 'f-year'].forEach((id) => {
      document.getElementById(id).addEventListener('input', (e) => {
        const key = id.slice(2);
        d[key] = e.target.value;
      });
    });
    document.getElementById('f-name').addEventListener('input', (e) => { d.name[state.draftLang] = e.target.value; });
    document.getElementById('f-category').addEventListener('change', (e) => { d.category = e.target.value; });

    const galleryAdd = document.getElementById('gallery-add');
    const galleryInput = document.getElementById('gallery-input');
    galleryAdd.addEventListener('click', () => galleryInput.click());
    galleryInput.addEventListener('change', (e) => { uploadFiles(e.target.files); galleryInput.value = ''; });
    galleryAdd.addEventListener('dragover', (e) => { e.preventDefault(); galleryAdd.classList.add('dragover'); });
    galleryAdd.addEventListener('dragleave', () => galleryAdd.classList.remove('dragover'));
    galleryAdd.addEventListener('drop', (e) => {
      e.preventDefault();
      galleryAdd.classList.remove('dragover');
      uploadFiles(e.dataTransfer.files);
    });
  }

  function renderCategories() {
    $topbar.innerHTML = `
      <div style="flex:1;display:flex;flex-direction:column;line-height:1.2;">
        <h1 class="topbar-title">Kategoriler</h1>
        <span class="topbar-sub">Proje kategorilerini ekleyin veya kaldırın</span>
      </div>
    `;

    const langFields = (idPrefix, values) => LANGS.map((l) => `
      <label class="field small">
        <span class="field-label">${l.label}</span>
        <input type="text" id="${idPrefix}-${l.code}" placeholder="${l.label}" value="${esc(values[l.code] || '')}">
      </label>
    `).join('');

    const rows = state.categories.map((c) => {
      const n = state.projects.filter((p) => p.category === c.slug).length;
      const inUse = n > 0;

      if (state.editingCategory === c.slug) {
        return `
          <div class="cat-row cat-row-edit">
            <span class="cat-icon"><span class="icon">sell</span></span>
            <div class="cat-add-lang-grid" style="flex:1;">${langFields('edit-cat', state.editCatLabels)}</div>
            <button class="btn btn-outline" data-action="cancel-edit-category">İptal</button>
            <button class="btn btn-dark" data-action="save-edit-category">Kaydet</button>
          </div>
        `;
      }

      return `
        <div class="cat-row">
          <span class="cat-icon"><span class="icon">sell</span></span>
          <div style="flex:1;">
            <div class="cat-name">${esc(c.label)}</div>
            <div class="cat-usage">${n} proje</div>
          </div>
          <button class="cat-edit" data-action="edit-category" data-slug="${esc(c.slug)}" title="Kategoriyi düzenle">
            <span class="icon" style="font-size:18px;">edit</span>
          </button>
          <button class="cat-remove" data-action="remove-category" data-slug="${esc(c.slug)}" ${inUse ? 'disabled' : ''} title="${inUse ? 'Kullanımda olan kategori silinemez' : 'Kategoriyi sil'}">
            <span class="icon" style="font-size:18px;">delete</span>
          </button>
        </div>
      `;
    }).join('');

    $content.innerHTML = `
      <div class="wrap-cats">
        <div class="card cat-add-row">
          <div class="panel-hint" style="margin-bottom:2px;">Her dil için kategori adını girin (en azından Almanca gerekli)</div>
          <div class="cat-add-lang-grid">${langFields('new-cat', state.catDraftLabels)}</div>
          <button class="btn btn-dark" style="align-self:flex-start;" data-action="add-category"><span class="icon" style="font-size:18px;">add</span> Ekle</button>
        </div>
        <div class="card">${rows}</div>
      </div>
    `;

    LANGS.forEach((l) => {
      const newInput = document.getElementById('new-cat-' + l.code);
      if (newInput) newInput.addEventListener('input', (e) => { state.catDraftLabels[l.code] = e.target.value; });
      const editInput = document.getElementById('edit-cat-' + l.code);
      if (editInput) editInput.addEventListener('input', (e) => { state.editCatLabels[l.code] = e.target.value; });
    });
  }

  /* ---------- event delegation ---------- */

  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    const action = target.dataset.action;

    if (action === 'stop-propagation') { e.stopPropagation(); return; }

    switch (action) {
      case 'go-list': state.view = 'list'; render(); break;
      case 'go-categories': state.view = 'categories'; render(); break;
      case 'new-project': newProject(); break;
      case 'cancel-edit': cancelEdit(); break;
      case 'save-draft': saveDraft(); break;
      case 'delete-draft': deleteDraft(); break;
      case 'filter': state.filter = target.dataset.slug; renderList(); break;
      case 'open-project': {
        const p = state.projects.find((x) => x.id === Number(target.dataset.id));
        if (p) openEditor(p);
        break;
      }
      case 'toggle-active':
        e.stopPropagation();
        toggleActive(Number(target.dataset.id));
        break;
      case 'toggle-draft-active':
        state.draft.active = !state.draft.active;
        render();
        break;
      case 'set-active':
        state.draft.active = target.dataset.val === '1';
        render();
        break;
      case 'remove-image': removeImage(Number(target.dataset.idx)); break;
      case 'lang-tab':
        syncEditableIntoDraft();
        syncNameIntoDraft();
        state.draftLang = target.dataset.lang;
        renderEditor();
        break;
      case 'rt-cmd': {
        e.preventDefault();
        const cmd = target.dataset.cmd;
        if (cmd === 'h3') execCmd('formatBlock', '<h3>');
        else if (cmd === 'link') { const u = window.prompt('Bağlantı adresi (URL):', 'https://'); if (u) execCmd('createLink', u); }
        else execCmd(cmd);
        break;
      }
      case 'add-category': addCategory(); break;
      case 'edit-category': startEditCategory(target.dataset.slug); break;
      case 'cancel-edit-category': cancelEditCategory(); break;
      case 'save-edit-category': saveEditCategory(); break;
      case 'remove-category':
        if (target.disabled) break;
        removeCategory(target.dataset.slug);
        break;
      default: break;
    }
  });

  window.addEventListener('error', (e) => {
    if ($content) {
      $content.innerHTML = `<div class="empty-state">Bir hata oluştu: ${esc(e.message)}. Sayfayı yenileyip tekrar deneyin.</div>`;
    }
  });

  render(); // show the empty list/skeleton immediately, before data arrives
  loadAll();
})();
