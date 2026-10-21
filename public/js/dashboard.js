(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const providerIds = new Set(['crtsh', 'mnemonic', 'nhtsa', 'worldbank', 'openlibrary', 'wayback']);
  const state = {
    csrf: '', user: null, providers: [], categories: [], category: 0, subcategory: '',
    view: 'catalog', search: '', visible: 24, provider: null, authMode: 'login',
    historyPage: 1, historyBusy: false, historyVersion: 0, queryBusy: false,
    sessionVersion: 0, catalogLoaded: false, authBusy: false, replayBusy: false
  };
  const dateFormat = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' });

  function node(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = String(text);
    return element;
  }
  function message(id, text, kind = '') {
    const element = $(id);
    element.textContent = text;
    element.className = `notice ${kind}`;
    element.hidden = !text;
  }
  function safeUrl(value) {
    try {
      const url = new URL(value);
      return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch (_) { return null; }
  }
  function externalLink(text, url, className) {
    const link = node('a', className, text);
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    return link;
  }
  function formatDate(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value || 'Unbekannter Zeitpunkt') : dateFormat.format(date);
  }
  function scrollTo(element) {
    element.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }
  async function request(action, options = {}) {
    const url = new URL('api.php', location.href);
    url.searchParams.set('action', action);
    for (const [key, value] of Object.entries(options.params || {})) url.searchParams.set(key, String(value));
    const config = { credentials: 'same-origin', mode: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } };
    if (options.body) {
      config.method = 'POST';
      config.headers['Content-Type'] = 'application/json';
      config.body = JSON.stringify({ ...options.body, csrf: state.csrf });
    }
    let response;
    try { response = await fetch(url.href, config); }
    catch (_) { throw new Error('Keine Verbindung zum Server. Bitte überprüfe deine Verbindung und versuche es erneut.'); }
    let data;
    try { data = await response.json(); }
    catch (_) { throw new Error('Der Server hat eine ungültige Antwort geliefert. Bitte versuche es erneut.'); }
    if (!response.ok || !data.ok) {
      const error = new Error(typeof data.error === 'string' ? data.error : 'Die Anfrage konnte nicht verarbeitet werden.');
      error.status = response.status;
      if ([401, 403].includes(response.status) && !['login', 'register', 'session'].includes(action)) {
        showAuth('Deine Sitzung ist abgelaufen oder der Zugriff wurde verweigert. Bitte melde dich erneut an.');
        state.csrf = '';
        try { const session = await request('session'); state.csrf = session.csrf || ''; } catch (_) { /* Login can retry session initialization. */ }
      }
      throw error;
    }
    return data;
  }
  function closeSidebar() {
    $('sidebar').classList.remove('open');
    $('sidebar-scrim').hidden = true;
    $('menu-toggle').setAttribute('aria-expanded', 'false');
    if (matchMedia('(max-width: 700px)').matches) $('sidebar').inert = true;
  }
  function showAuth(text = '') {
    state.sessionVersion++;
    state.historyVersion++;
    state.user = null;
    state.providers = [];
    state.categories = [];
    state.provider = null;
    state.catalogLoaded = false;
    state.queryBusy = false;
    state.replayBusy = false;
    state.historyBusy = false;
    state.csrf = '';
    $('auth-view').hidden = false;
    $('app-view').hidden = true;
    $('boot').hidden = true;
    $('password').value = '';
    $('password').setCustomValidity('');
    for (const id of ['tool-grid', 'provider-grid', 'category-nav', 'subcategory-nav', 'query-fields', 'history-list']) $(id).replaceChildren();
    $('result-output').textContent = '';
    $('query-section').hidden = true;
    $('user-email').textContent = '';
    closeSidebar();
    message('auth-message', text, text ? 'error' : '');
  }
  function setAuthMode(mode) {
    state.authMode = mode;
    const register = mode === 'register';
    $('auth-title').textContent = register ? 'Ein neuer Blick beginnt.' : 'Willkommen zurück.';
    $('auth-description').textContent = register ? 'Erstelle dein Konto für strukturierte Open-Source-Recherche.' : 'Melde dich an, um deinen geschützten Arbeitsbereich zu öffnen.';
    $('auth-submit').textContent = register ? 'Konto erstellen →' : 'Anmelden →';
    $('password').autocomplete = register ? 'new-password' : 'current-password';
    $('password-hint').hidden = false;
    $('registration-hint').hidden = !register;
    for (const [id, active] of [['login-tab', !register], ['register-tab', register]]) {
      $(id).classList.toggle('selected', active);
      $(id).setAttribute('aria-pressed', String(active));
    }
    message('auth-message', '');
  }
  function prepareCatalog(tree) {
    let total = 0;
    if (tree && typeof tree.description === 'string') $('catalog-note').textContent = tree.description;
    state.categories = (tree && Array.isArray(tree.children) ? tree.children : []).map((category) => {
      const tools = [];
      function walk(item, subcategory) {
        if (item.type === 'url') {
          if (item.pricing === 'free' && safeUrl(item.url)) tools.push({ ...item, subcategory });
          return;
        }
        for (const child of item.children || []) walk(child, subcategory);
      }
      for (const child of category.children || []) walk(child, child.type === 'url' ? 'Allgemein' : child.name);
      total += tools.length;
      return { name: category.name, tools, subcategories: [...new Set(tools.map((tool) => tool.subcategory))] };
    }).filter((category) => category.tools.length);
    state.category = 0;
    state.subcategory = '';
    state.search = '';
    state.visible = 24;
    $('tool-search').value = '';
    $('stat-tools').textContent = total.toLocaleString('de-DE');
    $('catalog-count').textContent = total.toLocaleString('de-DE');
    $('stat-categories').textContent = String(state.categories.length);
    $('category-total').textContent = String(state.categories.length);
    renderCategories();
    renderFilters();
    renderTools();
  }
  function renderCategories() {
    $('category-nav').replaceChildren(...state.categories.map((category, index) => {
      const button = node('button', `category-button${index === state.category ? ' selected' : ''}`);
      button.type = 'button';
      button.setAttribute('aria-pressed', String(index === state.category));
      button.append(node('span', '', category.name), node('span', 'count-badge', category.tools.length));
      button.addEventListener('click', () => {
        state.category = index;
        state.subcategory = '';
        state.search = '';
        state.visible = 24;
        $('tool-search').value = '';
        setView('catalog');
        renderCategories();
        renderFilters();
        renderTools();
        closeSidebar();
      });
      return button;
    }));
  }
  function renderFilters() {
    const category = state.categories[state.category];
    const names = ['', ...(category ? category.subcategories : [])];
    $('subcategory-nav').replaceChildren(...names.map((name) => {
      const button = node('button', `filter-chip${state.subcategory === name ? ' selected' : ''}`, name || 'Alle Werkzeuge');
      button.type = 'button';
      button.setAttribute('aria-pressed', String(state.subcategory === name));
      button.addEventListener('click', () => { state.subcategory = name; state.visible = 24; renderFilters(); renderTools(); });
      return button;
    }));
  }
  function emptyState(title, description) {
    const empty = node('div', 'empty-state');
    empty.append(node('strong', '', title), node('span', '', description));
    return empty;
  }
  function renderTools() {
    const category = state.categories[state.category];
    $('selected-category').textContent = category ? category.name : 'Werkzeugkatalog';
    $('selected-subcategory').textContent = state.subcategory || 'Alle Unterkategorien';
    const search = state.search.toLocaleLowerCase('de-DE');
    const tools = category ? category.tools.filter((tool) => (!state.subcategory || tool.subcategory === state.subcategory) && `${tool.name} ${tool.description || ''} ${tool.subcategory}`.toLocaleLowerCase('de-DE').includes(search)) : [];
    $('tool-count').textContent = `${tools.length.toLocaleString('de-DE')} Werkzeuge`;
    $('more-tools').hidden = tools.length <= state.visible;
    const cards = tools.slice(0, state.visible).map((tool) => {
      const card = node('article', 'tool-card');
      const top = node('div', 'tool-card-top');
      const title = node('div', 'tool-title');
      const heading = node('h3');
      heading.append(externalLink(tool.name, safeUrl(tool.url), ''));
      title.append(heading, node('span', '', tool.subcategory));
      const icon = node('span', 'tool-monogram', tool.name.replace(/[^a-zA-Z0-9]/g, '').slice(0, 2).toUpperCase() || 'OS');
      icon.setAttribute('aria-hidden', 'true');
      const arrow = node('span', 'external-icon', '↗');
      arrow.setAttribute('aria-hidden', 'true');
      top.append(icon, title, arrow);
      const bottom = node('div', 'tool-card-bottom');
      bottom.append(node('span', 'tag', 'KOSTENLOS'));
      if (tool.localInstall || /\(T\)/.test(tool.name)) bottom.append(node('span', 'tag local', 'LOKALE INSTALLATION'));
      const integration = typeof tool.integration === 'string' ? tool.integration : tool.integration && tool.integration.provider;
      const provider = state.providers.find((item) => item.id === integration);
      if (provider) {
        const button = node('button', 'integrated-button', 'API-Abfrage');
        button.type = 'button';
        button.addEventListener('click', () => openProvider(provider));
        bottom.append(button);
      }
      bottom.append(externalLink('Öffnen ↗', safeUrl(tool.url), 'tool-open'));
      card.append(top, node('p', 'tool-description', tool.description || tool.bestFor || 'Öffentliches Werkzeug für deine Recherche.'), bottom);
      return card;
    });
    $('tool-grid').replaceChildren(...(cards.length ? cards : [emptyState('Keine Werkzeuge gefunden', 'Passe die Suche oder die Unterkategorie an.')]));
  }
  function renderProviders() {
    $('provider-grid').replaceChildren(...state.providers.map((provider) => {
      const button = node('button', 'provider-card');
      button.type = 'button';
      button.append(node('span', 'eyebrow', `${provider.category} / ${provider.subcategory}`), node('strong', '', provider.name), node('span', '', provider.description), node('span', 'provider-action', 'Recherche starten ↗'));
      button.addEventListener('click', () => openProvider(provider));
      return button;
    }));
  }
  function setView(view) {
    state.view = view;
    for (const name of ['catalog', 'providers', 'history']) {
      $(`${name}-section`).hidden = view !== name;
      $(`${name}-nav`).classList.toggle('active', view === name);
      $(`${name}-nav`).setAttribute('aria-current', view === name ? 'page' : 'false');
    }
    const titles = { catalog: 'Werkzeugkatalog', providers: 'API-Abfragen', history: 'Meine Historie' };
    $('breadcrumb-current').textContent = titles[view];
    $('query-section').hidden = true;
    closeSidebar();
    if (view === 'history') loadHistory(1);
  }
  async function loadDashboard() {
    const version = state.sessionVersion;
    $('boot').hidden = false;
    $('auth-view').hidden = true;
    try {
      const data = await request('catalog');
      if (version !== state.sessionVersion || !state.user) return;
      state.providers = (data.providers || []).filter((provider) => providerIds.has(provider.id));
      prepareCatalog(data.catalog);
      renderProviders();
      state.catalogLoaded = true;
      $('user-email').textContent = state.user.email;
      $('app-view').hidden = false;
      $('boot').hidden = true;
      message('app-message', '');
      setView('catalog');
      $('main-content').focus({ preventScroll: true });
    } catch (error) {
      if (version === state.sessionVersion) {
        showAuth(error.message);
        try { const session = await request('session'); state.csrf = session.csrf || ''; } catch (_) { /* Display the original error. */ }
      }
    }
  }
  function setResult(kind, text, metadata = '') {
    $('result-window').dataset.state = kind;
    $('result-state').textContent = ({ ready: 'BEREIT', loading: 'WIRD GELADEN', success: 'ERFOLGREICH', error: 'FEHLER', replay: 'WIEDERHERGESTELLT' })[kind] || 'ERGEBNIS';
    $('result-meta').textContent = metadata;
    $('result-output').textContent = typeof text === 'string' ? text : JSON.stringify(text, null, 2);
    $('query-status').textContent = `${$('result-state').textContent}. ${metadata}`;
    $('result-window').setAttribute('aria-busy', String(kind === 'loading'));
  }
  function renderEntry(entry, replay = false) {
    const failed = entry.success === false || entry.success === 0 || entry.success === '0';
    const status = entry.http_status == null ? 'ohne HTTP-Status' : `HTTP ${entry.http_status}`;
    setResult(failed ? 'error' : replay ? 'replay' : 'success', entry.response, `${replay ? 'Gespeichertes Ergebnis · ' : ''}${formatDate(entry.created_at)} · ${status} · ${entry.category} / ${entry.subcategory} · #${entry.id}${failed ? ' · Fehlgeschlagene Abfrage' : ''}`);
  }
  function openProvider(provider, inputs = null, entry = null, focus = true) {
    if (!state.user || state.queryBusy || state.replayBusy) return;
    state.provider = provider;
    $('query-title').textContent = `${provider.name} · Abfrage`;
    $('provider-name').textContent = provider.name;
    $('provider-description').textContent = provider.description || '';
    const docs = safeUrl(provider.docs);
    $('provider-docs').hidden = !docs;
    if (docs) $('provider-docs').href = docs;
    else $('provider-docs').removeAttribute('href');
    $('provider-limits').textContent = typeof provider.limits === 'object' ? JSON.stringify(provider.limits) : provider.limits || '';
    $('query-fields').replaceChildren(...(provider.fields || []).map((field, index) => {
      const wrapper = node('div', field.type === 'textarea' ? 'wide' : '');
      const label = node('label', '', field.label || field.name);
      const id = `provider-field-${index}`;
      label.htmlFor = id;
      if (field.required) label.append(node('span', '', ' *'));
      let input;
      if (Array.isArray(field.options)) {
        input = node('select');
        if (!field.required) { const option = node('option', '', 'Bitte wählen'); option.value = ''; input.append(option); }
        for (const item of field.options) { const option = node('option', '', item.label); option.value = item.value; input.append(option); }
      } else if (field.type === 'textarea') {
        input = node('textarea');
      } else {
        input = node('input');
        input.type = ['text', 'email', 'url', 'number', 'password', 'date', 'search', 'tel'].includes(field.type) ? field.type : 'text';
      }
      input.id = id;
      input.name = field.name;
      input.required = Boolean(field.required);
      if (field.placeholder) input.setAttribute('placeholder', field.placeholder);
      input.autocomplete = 'off';
      if (inputs && Object.prototype.hasOwnProperty.call(inputs, field.name)) input.value = String(inputs[field.name] ?? '');
      wrapper.append(label, input);
      return wrapper;
    }));
    $('query-submit').disabled = false;
    $('query-submit').textContent = 'Abfrage ausführen ↗';
    $('query-section').hidden = false;
    if (entry) renderEntry(entry, true);
    else setResult('ready', 'Das Ergebnis deiner Abfrage erscheint hier.', 'Noch keine Abfrage ausgeführt. Es werden erst beim Absenden Daten übertragen.');
    if (focus) {
      scrollTo($('query-section'));
      $('result-output').focus({ preventScroll: true });
    }
  }
  async function loadHistory(page) {
    if (!state.user) return;
    const version = ++state.historyVersion;
    const sessionVersion = state.sessionVersion;
    state.historyBusy = true;
    $('history-prev').disabled = true;
    $('history-next').disabled = true;
    $('refresh-history').disabled = true;
    $('history-list').replaceChildren(emptyState('Historie wird geladen …', 'Deine gespeicherten Recherchen werden abgerufen.'));
    try {
      const data = await request('history', { params: { page } });
      if (version !== state.historyVersion || sessionVersion !== state.sessionVersion) return;
      state.historyPage = data.page;
      const groups = new Map();
      for (const entry of data.entries || []) {
        const category = entry.category || 'Allgemein';
        const subcategory = entry.subcategory || 'Allgemein';
        if (!groups.has(category)) groups.set(category, new Map());
        const children = groups.get(category);
        if (!children.has(subcategory)) children.set(subcategory, []);
        children.get(subcategory).push(entry);
      }
      const sections = [];
      for (const [category, children] of groups) {
        const section = node('section', 'history-category');
        section.append(node('h3', '', category));
        for (const [subcategory, entries] of children) {
          const group = node('div', 'history-subcategory');
          group.append(node('h4', '', subcategory));
          for (const entry of entries) {
            const row = node('div', 'history-entry');
            const details = node('div', 'history-entry-details');
            const provider = state.providers.find((item) => item.id === entry.provider);
            const failed = entry.success === false || entry.success === 0 || entry.success === '0';
            details.append(node('strong', '', provider ? provider.name : entry.provider), node('span', `tag${failed ? ' failed' : ''}`, failed ? 'FEHLER' : 'ERFOLGREICH'), node('p', '', `${formatDate(entry.created_at)} · HTTP ${entry.http_status ?? '—'} · #${entry.id}`), node('p', '', JSON.stringify(entry.inputs)));
            const button = node('button', 'button secondary', 'Wiederherstellen ↗');
            button.type = 'button';
            button.addEventListener('click', () => replay(entry.id, button));
            row.append(details, button);
            group.append(row);
          }
          section.append(group);
        }
        sections.push(section);
      }
      $('history-list').replaceChildren(...(sections.length ? sections : [emptyState('Platz für deine erste Erkenntnis.', 'Führe eine API-Abfrage aus. Deine Recherche erscheint anschließend hier.')]));
      $('history-page').textContent = `Seite ${data.page}`;
      $('history-prev').disabled = data.page <= 1;
      $('history-next').disabled = !data.has_more;
    } catch (error) {
      if (sessionVersion === state.sessionVersion && version === state.historyVersion) {
        $('history-list').replaceChildren(emptyState('Historie nicht verfügbar', error.message));
        $('history-prev').disabled = state.historyPage <= 1;
      }
    } finally {
      if (version === state.historyVersion) {
        state.historyBusy = false;
        $('refresh-history').disabled = false;
      }
    }
  }
  async function replay(id, button) {
    if (state.queryBusy || state.replayBusy) return;
    const version = state.sessionVersion;
    state.replayBusy = true;
    button.disabled = true;
    button.textContent = 'Wird geladen …';
    message('app-message', '');
    try {
      const data = await request('replay', { params: { id } });
      if (version !== state.sessionVersion) return;
      const provider = state.providers.find((item) => item.id === data.entry.provider);
      if (!provider) throw new Error('Diese Datenquelle ist nicht mehr verfügbar.');
      state.replayBusy = false;
      openProvider(provider, data.entry.inputs, data.entry);
    } catch (error) {
      if (version === state.sessionVersion) message('app-message', error.message, 'error');
    } finally {
      if (version === state.sessionVersion) {
        state.replayBusy = false;
        button.disabled = false;
        button.textContent = 'Wiederherstellen ↗';
      }
    }
  }
  function validatePassword() {
    const input = $('password');
    const bytes = new TextEncoder().encode(input.value).length;
    const valid = bytes >= 12 && bytes <= 128;
    input.setCustomValidity(valid || !input.value ? '' : 'Das Passwort muss 12–128 Byte (UTF-8) lang sein. Umlaute und Emojis benötigen mehrere Byte.');
    return valid;
  }
  $('password').addEventListener('input', validatePassword);
  $('login-tab').addEventListener('click', () => { if (!state.authBusy) setAuthMode('login'); });
  $('register-tab').addEventListener('click', () => { if (!state.authBusy) setAuthMode('register'); });
  $('auth-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (state.authBusy) return;
    if (!validatePassword()) {
      $('password').reportValidity();
      return;
    }
    state.authBusy = true;
    const mode = state.authMode;
    $('auth-submit').disabled = true;
    $('auth-submit').textContent = 'Bitte warten …';
    message('auth-message', '');
    try {
      if (!state.csrf) { const session = await request('session'); state.csrf = session.csrf; }
      const data = await request(mode, { body: { email: $('email').value.trim(), password: $('password').value } });
      $('password').value = '';
      $('password').setCustomValidity('');
      if (mode === 'register') {
        setAuthMode('login');
        message('auth-message', `${data.message || 'Dein Konto wurde erstellt.'} Dein Konto ist zunächst inaktiv (active = 0). Bitte warte auf die Freischaltung durch einen Administrator.`, 'success');
      } else {
        state.user = data.user;
        state.csrf = data.csrf;
        state.sessionVersion++;
        await loadDashboard();
      }
    } catch (error) { message('auth-message', error.message, 'error'); }
    finally {
      state.authBusy = false;
      $('auth-submit').disabled = false;
      $('auth-submit').textContent = state.authMode === 'register' ? 'Konto erstellen →' : 'Anmelden →';
    }
  });
  $('logout').addEventListener('click', async () => {
    $('logout').disabled = true;
    try {
      await request('logout', { body: {} });
      showAuth();
      const session = await request('session');
      state.csrf = session.csrf;
    } catch (error) { if (state.user) message('app-message', error.message, 'error'); else message('auth-message', error.message, 'error'); }
    finally { $('logout').disabled = false; }
  });
  for (const view of ['catalog', 'providers', 'history']) $(`${view}-nav`).addEventListener('click', () => setView(view));
  $('tool-search').addEventListener('input', () => { state.search = $('tool-search').value; state.visible = 24; renderTools(); });
  $('more-tools').addEventListener('click', () => { state.visible += 24; renderTools(); });
  $('close-query').addEventListener('click', () => {
    $('query-section').hidden = true;
    $(`${state.view}-nav`).focus();
  });
  $('query-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (state.queryBusy || !state.provider || !state.user) return;
    const inputs = Object.fromEntries(new FormData($('query-form')).entries());
    const provider = state.provider;
    const version = state.sessionVersion;
    state.queryBusy = true;
    $('query-submit').disabled = true;
    $('query-submit').textContent = 'Abfrage läuft …';
    setResult('loading', 'Öffentliche Quelle wird abgefragt …', `${provider.name} · Bitte warten. Die Abfrage wird serverseitig protokolliert.`);
    try {
      const data = await request('query', { body: { provider: provider.id, inputs } });
      if (version === state.sessionVersion) renderEntry(data.entry);
    } catch (error) {
      if (version === state.sessionVersion) setResult('error', error.message, 'Die Abfrage konnte nicht erfolgreich abgeschlossen werden. Es erfolgt keine automatische Wiederholung.');
    } finally {
      if (version === state.sessionVersion) {
        state.queryBusy = false;
        $('query-submit').disabled = false;
        $('query-submit').textContent = 'Abfrage ausführen ↗';
      }
    }
  });
  $('refresh-history').addEventListener('click', () => { if (!state.historyBusy) loadHistory(state.historyPage); });
  $('history-prev').addEventListener('click', () => { if (!state.historyBusy && state.historyPage > 1) loadHistory(state.historyPage - 1); });
  $('history-next').addEventListener('click', () => { if (!state.historyBusy) loadHistory(state.historyPage + 1); });
  $('menu-toggle').addEventListener('click', () => {
    const open = !$('sidebar').classList.contains('open');
    $('sidebar').classList.toggle('open', open);
    $('sidebar').inert = !open;
    $('sidebar-scrim').hidden = !open;
    $('menu-toggle').setAttribute('aria-expanded', String(open));
    if (open) $('catalog-nav').focus();
  });
  $('sidebar-scrim').addEventListener('click', closeSidebar);
  const mobileMedia = matchMedia('(max-width: 700px)');
  mobileMedia.addEventListener('change', () => { closeSidebar(); $('sidebar').inert = mobileMedia.matches; });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { closeSidebar(); $('menu-toggle').focus(); }
    if (event.key === '/' && state.user && state.view === 'catalog' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && !event.ctrlKey && !event.metaKey && !event.altKey) {
      event.preventDefault();
      $('tool-search').focus();
    }
    if (event.key === 'Tab' && $('sidebar').classList.contains('open')) {
      const focusable = [...$('sidebar').querySelectorAll('a[href],button:not(:disabled)')];
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  async function initialize() {
    setAuthMode('login');
    closeSidebar();
    try {
      const session = await request('session');
      state.csrf = session.csrf;
      if (session.user) {
        state.user = session.user;
        state.sessionVersion++;
        await loadDashboard();
      } else {
        const csrf = state.csrf;
        showAuth();
        state.csrf = csrf;
      }
    } catch (error) { showAuth(error.message); }
  }
  initialize();
})();
