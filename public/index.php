<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <meta name="description" content="Dein geschützter Arbeitsbereich für strukturierte Open-Source-Recherche.">
  <title>OSINT Framework · Research Workspace</title>
  <link rel="stylesheet" href="css/dashboard.css">
  <script src="js/dashboard.js" defer></script>
</head>
<body>
  <noscript>Bitte aktiviere JavaScript, um den geschützten Recherche-Arbeitsbereich zu verwenden.</noscript>
  <a class="skip-link" href="#main-content">Zum Inhalt</a>
  <div id="boot" class="boot" role="status"><span class="brand-mark" aria-hidden="true">O</span><p>Arbeitsbereich wird vorbereitet …</p></div>
  <section id="auth-view" class="auth-layout" hidden aria-labelledby="auth-title">
    <div class="auth-story">
      <a class="brand" href="./"><span class="brand-mark" aria-hidden="true">O</span><span>OSINT<span class="brand-light"> / FRAMEWORK</span></span></a>
      <div class="story-copy"><span class="eyebrow"><span class="status-dot"></span> OPEN SOURCE INTELLIGENCE</span><h1>Aus Informationen<br>wird <span class="gradient-text">Erkenntnis.</span></h1><p>Dein Ausgangspunkt für fundierte Recherche. Entdecke freie Werkzeuge, verbinde öffentliche Quellen und behalte den Überblick.</p><div class="story-features"><span>01 <strong>Quellen entdecken</strong></span><span>02 <strong>Gezielt recherchieren</strong></span><span>03 <strong>Ergebnisse nachvollziehen</strong></span></div></div>
      <p class="story-footer">Öffentliche Quellen. Klare Zusammenhänge. Verantwortungsvolle Recherche.</p>
    </div>
    <div class="auth-panel">
      <div class="auth-card"><span class="eyebrow">DEIN RESEARCH WORKSPACE</span><h2 id="auth-title">Willkommen zurück.</h2><p id="auth-description" class="muted">Melde dich an, um deinen geschützten Arbeitsbereich zu öffnen.</p>
        <div class="segmented auth-tabs" role="group" aria-label="Kontozugang"><button id="login-tab" type="button" class="selected" aria-pressed="true">Anmelden</button><button id="register-tab" type="button" aria-pressed="false">Registrieren</button></div>
        <div id="auth-message" class="notice" role="status" hidden></div>
        <form id="auth-form">
          <label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" autocomplete="username" placeholder="name@beispiel.de" required maxlength="254">
          <label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required minlength="12" maxlength="128" placeholder="Dein Passwort">
          <p id="password-hint" class="field-hint" hidden>12–128 Zeichen. Verwende ein einzigartiges Passwort.</p>
          <p id="registration-hint" class="notice" hidden>Neue Konten sind zunächst inaktiv (active = 0). Ein Administrator muss dein Konto freischalten, bevor du dich anmelden kannst.</p>
          <button id="auth-submit" class="button primary full" type="submit">Anmelden <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-security"><span aria-hidden="true">◇</span> Geschützter Zugang · persönliche Recherchehistorie</p>
      </div>
    </div>
  </section>
  <div id="app-view" class="app-layout" hidden>
    <button id="sidebar-scrim" class="sidebar-scrim" type="button" aria-label="Navigation schließen" hidden></button>
    <aside id="sidebar" class="sidebar" aria-label="Hauptnavigation">
      <a class="brand" href="./"><span class="brand-mark" aria-hidden="true">O</span><span>OSINT<span class="brand-light"> FRAMEWORK</span></span></a>
      <div class="workspace-label"><span class="status-dot"></span> RESEARCH WORKSPACE</div>
      <div class="nav-heading">ARBEITSBEREICH</div>
      <button id="catalog-nav" class="nav-item active" type="button"><span aria-hidden="true">▦</span> Werkzeugkatalog <span id="catalog-count" class="count-badge"></span></button>
      <button id="providers-nav" class="nav-item" type="button"><span aria-hidden="true">⌘</span> API-Abfragen <span class="count-badge">6</span></button>
      <button id="history-nav" class="nav-item" type="button"><span aria-hidden="true">↶</span> Meine Historie</button>
      <div class="nav-heading categories-heading">KATEGORIEN <span id="category-total"></span></div>
      <nav id="category-nav" class="category-nav" aria-label="Werkzeugkategorien"></nav>
      <div class="sidebar-bottom"><div class="avatar" aria-hidden="true">R</div><div class="account-copy"><strong>Researcher</strong><span id="user-email"></span></div><button id="logout" class="icon-button" type="button" aria-label="Abmelden" title="Abmelden">↪</button></div>
    </aside>
    <main id="main-content" class="main-content" tabindex="-1">
      <header class="topbar"><div class="breadcrumb"><button id="menu-toggle" class="icon-button menu-toggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Navigation öffnen">☰</button><span>Workspace</span><span aria-hidden="true">/</span><strong id="breadcrumb-current">Werkzeugkatalog</strong></div><span class="private-badge"><span class="status-dot"></span> Privater Arbeitsbereich</span></header>
      <div class="content-wrap">
        <div id="app-message" class="notice" role="status" hidden></div>
        <section class="page-intro"><div><span class="eyebrow">ENTDECKEN. ANALYSIEREN. VERSTEHEN.</span><h1 id="page-title">Deine nächste Erkenntnis<br>beginnt <span class="gradient-text">hier.</span></h1><p id="page-description">Die richtigen Werkzeuge für deine nächste Recherche – an einem Ort.</p></div><div class="intro-orbit" aria-hidden="true"><span class="orbit-ring"></span><span class="orbit-core">◎</span><span class="orbit-label">CONNECT THE DOTS</span></div></section>
        <section id="stats" class="stats-grid" aria-label="Katalogübersicht"><div class="stat-card"><span class="stat-icon cyan" aria-hidden="true">▦</span><div><strong id="stat-tools">—</strong><span>Freie Werkzeuge</span></div><span class="stat-detail">KURATIERT</span></div><div class="stat-card"><span class="stat-icon purple" aria-hidden="true">⌘</span><div><strong>6</strong><span>API-Integrationen</span></div><span class="stat-detail">DIREKT ABFRAGEN</span></div><div class="stat-card"><span class="stat-icon green" aria-hidden="true">◇</span><div><strong id="stat-categories">—</strong><span>Recherchebereiche</span></div><span class="stat-detail">STRUKTURIERT</span></div></section>
        <section id="catalog-section" aria-labelledby="catalog-title">
          <div class="section-heading"><div><span class="eyebrow">TOOL EXPLORER</span><h2 id="catalog-title">Werkzeugkatalog</h2></div><span class="subtle-label">Frei zugänglich. Vielseitig einsetzbar.</span></div>
          <p id="catalog-note" class="notice">Dokumentationsbasierter Katalog. Die Live-Erreichbarkeit der Anbieter wurde nicht geprüft. Bitte kontrolliere Verfügbarkeit und aktuelle Nutzungsbedingungen vor dem Einsatz.</p>
          <div class="explorer-toolbar"><label class="search-box" for="tool-search"><span aria-hidden="true">⌕</span><input id="tool-search" type="search" placeholder="Werkzeuge in dieser Kategorie suchen …" autocomplete="off"><kbd aria-hidden="true">/</kbd></label><span id="tool-count" class="muted" role="status"></span></div>
          <div id="subcategory-nav" class="filter-chips" role="group" aria-label="Unterkategorien"></div>
          <div class="category-description"><h3 id="selected-category"></h3><span id="selected-subcategory" class="muted"></span></div>
          <div id="tool-grid" class="tool-grid"></div><button id="more-tools" class="button secondary load-more" type="button" hidden>Weitere Werkzeuge anzeigen</button>
        </section>
        <section id="providers-section" aria-labelledby="providers-title" hidden><div class="section-heading"><div><span class="eyebrow">API WORKSPACE</span><h2 id="providers-title">API-Abfragen</h2></div><span class="subtle-label">6 öffentliche Datenquellen</span></div><p class="muted">Wähle eine Quelle. Erst mit „Abfrage ausführen“ werden Daten übertragen. Die Live-Erreichbarkeit der Anbieter wurde nicht geprüft.</p><div id="provider-grid" class="provider-grid"></div></section>
        <section id="query-section" class="query-section" aria-labelledby="query-title" hidden>
          <div class="section-heading"><div><span class="eyebrow">RESEARCH CONSOLE</span><h2 id="query-title">Abfrage</h2></div><button id="close-query" class="icon-button" type="button" aria-label="Abfragebereich schließen">×</button></div>
          <div id="result-window" class="result-window" aria-labelledby="result-title"><div class="result-header"><div><span class="status-dot"></span><strong id="result-title">Ergebnis</strong></div><span id="result-state" class="result-state">BEREIT</span></div><p id="result-meta" class="result-meta">Noch keine Abfrage ausgeführt.</p><pre id="result-output" tabindex="0" aria-label="Abfrageergebnis">Das Ergebnis deiner Abfrage erscheint hier.</pre><p id="query-status" class="sr-only" role="status" aria-live="polite"></p></div>
          <div class="query-form-panel"><div class="query-source"><div><h3 id="provider-name"></h3><p id="provider-description" class="muted"></p></div><a id="provider-docs" class="text-link" target="_blank" rel="noopener noreferrer">Dokumentation ↗</a></div><p id="provider-limits" class="field-hint"></p><form id="query-form"><div id="query-fields" class="query-fields"></div><p class="transmission-notice"><span aria-hidden="true">ⓘ</span> Beim Absenden werden deine Eingaben an den ausgewählten externen Anbieter übertragen. Abfragen, Eingaben und Ergebnisse werden serverseitig deinem Konto zugeordnet protokolliert. Deine Historie ist nur für dein Konto abrufbar. Keine vertraulichen Daten eingeben.</p><button id="query-submit" class="button primary" type="submit">Abfrage ausführen <span aria-hidden="true">↗</span></button></form></div>
        </section>
        <section id="history-section" aria-labelledby="history-title" hidden><div class="section-heading"><div><span class="eyebrow">DEIN RECHERCHEVERLAUF</span><h2 id="history-title">Meine Historie</h2></div><button id="refresh-history" class="button secondary" type="button">Aktualisieren ↻</button></div><p class="muted">Deine gespeicherten Abfragen, nach Haupt- und Unterkategorie gruppiert. Wiederherstellen lädt nur das gespeicherte Ergebnis – keine neue Anbieterabfrage.</p><div id="history-list" aria-live="polite"></div><div class="pagination"><button id="history-prev" class="button secondary" type="button">← Zurück</button><span id="history-page" class="muted">Seite 1</span><button id="history-next" class="button secondary" type="button">Weiter →</button></div></section>
        <footer class="page-footer"><span>OSINT FRAMEWORK <span class="footer-divider">/</span> Von Quellen zu Erkenntnissen.</span><span>Recherchiere verantwortungsvoll.</span></footer>
      </div>
    </main>
  </div>
</body>
</html>
