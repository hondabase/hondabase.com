<div class="widget widget-codes"
     x-data="{
        q: '',
        codes: @js($codes),
        get filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.codes;
            return this.codes.filter(c =>
                c.code.some(n => n.includes(s)) ||
                c.system.toLowerCase().includes(s) ||
                c.short.toLowerCase().includes(s) ||
                c.long.toLowerCase().includes(s) ||
                c.causes.toLowerCase().includes(s));
        }
     }">
    <div class="widget-bar">
        <span class="widget-title">OBD trouble code lookup</span>
        <input type="search" class="widget-search" x-model="q"
               placeholder="Code or keyword (e.g. 1, VTEC, transmission)" aria-label="Search trouble codes">
    </div>
    <div class="code-container">
        <table class="code-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>System</th>
                    <th>Description & Causes</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="c in filtered" :key="c.system + '-' + c.short + '-' + c.code.join('-')">
                    <tr class="code-row">
                        <td class="code-num" x-text="c.code.join(' / ')"></td>
                        <td class="code-system" x-text="c.system"></td>
                        <td class="code-details">
                            <strong x-text="c.short"></strong>: <span x-text="c.long"></span>
                            <div class="code-causes" x-show="c.causes">
                                <em>Possible causes:</em> <span x-text="c.causes"></span>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="filtered.length === 0">
                    <td colspan="3" class="code-empty">No matching codes found.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
