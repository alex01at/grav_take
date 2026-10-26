/**
 * admin2-Seite /plugin/take: Feiern mit ihren Eintragungen (Name,
 * Mitgebrachtes, E-Mail). admin2 lädt diese Datei als Web Component
 * (Tag-Name steht in window.__GRAV_PAGE_TAG). Die Feiern selbst
 * (Name+Datum) werden in der Plugin-Konfiguration gepflegt (siehe
 * onApiPluginPageInfo in take.php, Button "Feiern anlegen").
 */

const TAG = window.__GRAV_PAGE_TAG;

const STYLE = `
.tk { display: grid; gap: 1.5rem; }
.tk h3 { font-size: 1rem; font-weight: 600; margin: 0 0 .5rem; display: flex; justify-content: space-between; gap: 1rem; }
.tk table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.tk th, .tk td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid color-mix(in srgb, currentColor 15%, transparent); vertical-align: top; }
.tk th { font-weight: 600; opacity: .7; }
.tk button { font: inherit; font-size: .8rem; padding: .25rem .6rem; border-radius: .375rem; cursor: pointer;
  border: 1px solid color-mix(in srgb, currentColor 25%, transparent); background: transparent; color: inherit; }
.tk button.danger { color: #dc2626; border-color: color-mix(in srgb, #dc2626 40%, transparent); }
.tk .muted { opacity: .65; }
.tk .card { border: 1px solid color-mix(in srgb, currentColor 15%, transparent); border-radius: .5rem; padding: 1rem; overflow-x: auto; }
.tk input[type=text] { font: inherit; width: 100%; box-sizing: border-box; padding: .3rem .4rem; }
`;

class TakePage extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `<style>${STYLE}</style><div class="tk"><p class="muted">Lade Feiern …</p></div>`;
        this.load();
    }

    api(path, options = {}) {
        const headers = { 'Content-Type': 'application/json' };
        if (window.__GRAV_API_TOKEN) headers['X-API-Token'] = window.__GRAV_API_TOKEN;
        const url = (window.__GRAV_API_SERVER_URL || '') + (window.__GRAV_API_PREFIX || '/api/v1') + path;
        return fetch(url, { ...options, headers }).then(async (res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            return json.data ?? json;
        });
    }

    toast(message, type = 'success') {
        const t = window.__GRAV_TOAST;
        if (t && typeof t[type] === 'function') t[type](message);
    }

    async load() {
        try {
            const data = await this.api('/take/events');
            this.events = data.events || [];
            this.render();
        } catch (e) {
            this.querySelector('.tk').innerHTML = `<p class="danger">Fehler beim Laden: ${esc(e.message)}</p>`;
        }
    }

    render() {
        const root = this.querySelector('.tk');

        if (!this.events.length) {
            root.innerHTML = '<div class="card"><p class="muted">Noch keine Feiern angelegt – trage sie über den Button "Feiern anlegen" oben in der Plugin-Konfiguration ein.</p></div>';
            return;
        }

        root.innerHTML = this.events.map((ev) => this.eventCard(ev)).join('');

        this.events.forEach((ev) => {
            ev.entries.forEach((entry) => {
                const row = root.querySelector(`tr[data-row="${ev.key}:${entry.id}"]`);
                if (!row) return;
                row.querySelector('[data-save]').addEventListener('click', () => this.save(ev.key, entry.id, row));
                row.querySelector('[data-delete]').addEventListener('click', () => this.remove(ev.key, entry.id));
            });
        });
    }

    eventCard(ev) {
        const rows = ev.entries.length
            ? ev.entries.map((entry) => `
                <tr data-row="${ev.key}:${entry.id}">
                  <td><input type="text" value="${esc(entry.name)}" data-field="name"></td>
                  <td><input type="text" value="${esc((entry.items || []).join(', '))}" data-field="items"></td>
                  <td class="muted">${esc(entry.email || '')}</td>
                  <td style="white-space:nowrap">
                    <button data-save>Speichern</button>
                    <button class="danger" data-delete>Löschen</button>
                  </td>
                </tr>`).join('')
            : '<tr><td colspan="4" class="muted">Noch niemand eingetragen.</td></tr>';

        return `
          <div class="card">
            <h3><span>${esc(ev.name)}</span><span class="muted">${esc(ev.date)}</span></h3>
            <table>
              <thead><tr><th>Name</th><th>Mitgebracht</th><th>E-Mail</th><th></th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>`;
    }

    async save(eventKey, id, row) {
        const name = row.querySelector('[data-field="name"]').value;
        const items = row.querySelector('[data-field="items"]').value.split(',').map((s) => s.trim()).filter(Boolean);
        try {
            await this.api(`/take/events/${encodeURIComponent(eventKey)}/${encodeURIComponent(id)}`, {
                method: 'PATCH',
                body: JSON.stringify({ name, items }),
            });
            this.toast('Gespeichert');
            this.load();
        } catch (e) {
            this.toast(`Speichern fehlgeschlagen: ${e.message}`, 'error');
        }
    }

    async remove(eventKey, id) {
        if (!confirm('Diesen Eintrag wirklich löschen?')) return;
        try {
            await this.api(`/take/events/${encodeURIComponent(eventKey)}/${encodeURIComponent(id)}`, { method: 'DELETE' });
            this.toast('Eintrag gelöscht');
            this.load();
        } catch (e) {
            this.toast(`Löschen fehlgeschlagen: ${e.message}`, 'error');
        }
    }
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

if (!customElements.get(TAG)) customElements.define(TAG, TakePage);
