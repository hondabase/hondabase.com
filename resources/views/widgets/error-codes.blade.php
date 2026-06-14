<div class="widget widget-codes"
     x-data="{
        q: '',
        codes: @js($codes),
        get filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.codes;
            return this.codes.filter(c =>
                c.code.some(n => n.includes(s)) ||
                c.short.toLowerCase().includes(s) ||
                c.long.toLowerCase().includes(s));
        }
     }">
    <div class="widget-bar">
        <span class="widget-title">OBD trouble code lookup</span>
        <input type="search" class="widget-search" x-model="q"
               placeholder="Code or keyword (e.g. 1, O2, knock)" aria-label="Search trouble codes">
    </div>
    <ul class="code-list">
        <template x-for="c in filtered" :key="c.short + '-' + c.code.join('-')">
            <li class="code-item">
                <span class="code-num" x-text="c.code.join(' / ')"></span>
                <span class="code-body"><strong x-text="c.short"></strong> <span x-text="c.long"></span></span>
            </li>
        </template>
        <li class="code-empty" x-show="filtered.length === 0">No matching codes.</li>
    </ul>
</div>
