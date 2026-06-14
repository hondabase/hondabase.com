<div class="widget widget-wiring" x-data="{
    model: 'all'
}">
    <style>
        .widget-wiring {
            border: 1px solid var(--border);
            background: var(--bg-2);
            margin: 1.5rem 0;
            padding: 1.25rem;
            border-radius: 4px;
        }
        .widget-wiring .controls-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .widget-wiring .control-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .widget-wiring label {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--muted);
        }
        .widget-wiring select {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            background: var(--bg);
            color: var(--txt);
            border: 1px solid var(--border-2);
            padding: 0.35rem 0.6rem;
            border-radius: 2px;
        }
        .widget-wiring select:focus {
            outline: none;
            border-color: var(--amber);
        }
        .widget-wiring table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .widget-wiring th, .widget-wiring td {
            border: 1px solid var(--border-2);
            padding: 0.6rem 0.75rem;
            text-align: left;
            vertical-align: middle;
        }
        .widget-wiring th {
            font-family: var(--font-mono);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.05rem;
            color: var(--amber-2);
            background: var(--panel);
        }
        .widget-wiring .wire-circle {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
            border: 1px solid #333;
        }
        .widget-wiring .wire-circle.split {
            background: linear-gradient(90deg, var(--color1) 50%, var(--color2) 50%);
        }
        .widget-wiring .wire-red { --color1: #FF0000; background: var(--color1); }
        .widget-wiring .wire-black { --color1: #000000; background: var(--color1); }
        .widget-wiring .wire-white { --color1: #FFFFFF; background: var(--color1); }
        .widget-wiring .wire-brown { --color1: #8B4513; background: var(--color1); }
        .widget-wiring .wire-yellow-black { --color1: #FFD700; --color2: #000000; }
        .widget-wiring .wire-green-white { --color1: #008000; --color2: #FFFFFF; }
        
        .widget-wiring .wire-label {
            vertical-align: middle;
            font-family: var(--font-mono);
            font-size: 0.85rem;
        }
        .widget-wiring .note-text {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.75rem;
            font-style: italic;
        }
    </style>

    <div class="controls-row">
        <div class="control-group">
            <label>Select Wideband Model:</label>
            <select x-model="model">
                <option value="all">All Models / Overview</option>
                <option value="30-4110">AEM 30-4100 / 30-4110 (Single-Ended)</option>
                <option value="30-0300">AEM 30-0300 / 30-2310 (Differential)</option>
            </select>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Wideband Wire</th>
                <th>OBD1 ECU Pin</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Power (+12V)</strong></td>
                <td><span class="wire-circle wire-red"></span><span class="wire-label">RED</span></td>
                <td><span class="wire-circle split wire-yellow-black"></span>A25 (YELLOW on BLACK)</td>
            </tr>
            <tr>
                <td><strong>Power Ground</strong></td>
                <td><span class="wire-circle wire-black"></span><span class="wire-label">BLACK</span></td>
                <td><span class="wire-circle wire-black"></span>A24 (BLACK)</td>
            </tr>
            <tr>
                <td><strong>+ Analog Output (5V)</strong></td>
                <td><span class="wire-circle wire-white"></span><span class="wire-label">WHITE</span></td>
                <td><span class="wire-circle wire-white"></span>D14 (WHITE)</td>
            </tr>
            <tr x-show="model !== '30-4110'">
                <td><strong>- Analog Output (Ground)</strong></td>
                <td><span class="wire-circle wire-brown"></span><span class="wire-label">BROWN</span></td>
                <td><span class="wire-circle split wire-green-white"></span>D22 (GREEN on WHITE)</td>
            </tr>
        </tbody>
    </table>
    <div class="note-text" x-show="model !== '30-4110'">Not all models have this output wire. Connecting the Brown wire provides the best ground with the least interference.</div>
</div>
