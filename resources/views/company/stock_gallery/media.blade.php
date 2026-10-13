<dialog id="sg-media" class="sg-dialog" aria-labelledby="sg-title">
    <div class="sg-header"><h4 id="sg-title">Stock Gallery · Media & Try-On</h4><button type="button" id="sg-close" class="btn btn-secondary">Close</button></div>
    <p id="sg-label"></p>
    <div id="sg-status" class="sg-status" role="status" aria-live="polite" aria-atomic="true">
        <span id="sg-spinner" class="sg-spinner" aria-hidden="true" hidden></span>
        <div><strong id="sg-status-title">Media status</strong><p id="sg-message"></p></div>
        <button type="button" id="sg-refresh" class="btn btn-sm btn-secondary" hidden>Retry status check</button>
    </div>
    <div id="sg-management">
        <p>Category: <strong id="sg-category"></strong> · Set the category in Item Master → Edit Item.</p>
        <div class="sg-upload">
            <label>Product photo (JPG, PNG, WebP; up to 15 MB)<input id="sg-file" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"></label>
            <button type="button" id="sg-upload" class="btn btn-primary">Upload & Remove Background</button>
            <button type="button" id="sg-process" class="btn btn-info">Remove Background / Retry</button>
        </div>
        <div class="sg-comparison">
            <figure><figcaption>Original</figcaption><img id="sg-original" alt="Original jewellery"></figure>
            <figure><figcaption>Transparent cutout — review fine edges</figcaption><img id="sg-cutout" alt="Processed jewellery"></figure>
        </div>
        <label><input type="checkbox" id="sg-approved"> I have checked and approve this cutout for the gallery</label><br>
        <label><input type="checkbox" id="sg-enabled"> Enable Try On for this label</label>
    </div>
    <details id="sg-placement" open>
        <summary>Preview placement</summary>
        <div class="sg-controls">
            <label>Size<input id="sg-scale" type="range" min="0.1" max="4" step="0.05" value="1"></label>
            <label>Horizontal<input id="sg-x" type="range" min="-1" max="1" step="0.01" value="0"></label>
            <label>Vertical<input id="sg-y" type="range" min="-1" max="1" step="0.01" value="0"></label>
            <label>Rotation<input id="sg-rotation" type="range" min="-180" max="180" step="1" value="0"></label>
            <label id="sg-finger-label">Finger<select id="sg-finger" class="form-select"><option value="ring">Ring</option><option value="index">Index</option><option value="middle">Middle</option><option value="little">Little</option><option value="thumb">Thumb</option></select></label>
            <label id="sg-earring-label">Earring image<select id="sg-earring_mode" class="form-select"><option value="single">Single earring (mirror for other ear)</option><option value="pair">Pair (split left/right halves)</option></select></label>
        </div>
        <small>Approximate 2D preview, not a size measurement. Use a front-facing photo; pair images should have one earring in each half.</small>
    </details>
    <div class="sg-camera-actions">
        <button type="button" id="sg-camera" class="btn btn-primary">Start Camera Preview</button>
        <button type="button" id="sg-stop" class="btn btn-secondary">Stop Camera</button>
        <button type="button" id="sg-save" class="btn btn-success">Save Media & Try-On Settings</button>
    </div>
    <p id="sg-camera-message" role="status">Camera frames stay in your browser. HTTPS or localhost is required.</p>
    <video id="sg-video" autoplay muted playsinline hidden></video>
    <canvas id="sg-canvas" width="640" height="480" hidden aria-label="Live jewellery preview"></canvas>
    <details class="sg-diagnostics" open>
        <summary>Processing activity / diagnostics</summary>
        <pre id="sg-log" style="max-height:180px;overflow:auto;white-space:pre-wrap;font-size:12px;background:#f3f4f6;color:#172033;padding:12px" aria-live="polite"></pre>
    </details>
</dialog>
<style>
.sg-dialog{width:min(960px,96vw);max-height:92vh;overflow:auto;border:1px solid #d1d5db;border-radius:14px;padding:24px;background:#fff;color:#172033;box-shadow:0 24px 80px #0005}
.sg-status{display:flex;align-items:center;gap:12px;padding:14px;margin:12px 0;border:1px solid #b8d4f5;border-radius:8px;background:#edf5ff;color:#172033}.sg-status[data-state="error"]{background:#fff0f0;border-color:#dc9b9b}.sg-status[data-state="ready"]{background:#edf9f0;border-color:#9bccaa}.sg-status #sg-message{margin:3px 0 0}.sg-spinner{display:inline-block;flex:0 0 22px;width:22px;height:22px;border:3px solid #bad1ed;border-top-color:#2563eb;border-radius:50%;animation:sg-spin .8s linear infinite}@keyframes sg-spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.sg-spinner{animation:none}}
.sg-dialog::backdrop{background:#0009}.sg-header,.sg-upload,.sg-camera-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.sg-header{justify-content:space-between}.sg-header h4{margin:0}.sg-upload{margin:16px 0}.sg-comparison{display:grid;grid-template-columns:1fr 1fr;gap:16px}.sg-comparison img{width:100%;height:220px;object-fit:contain;background:repeating-conic-gradient(#e5e7eb 0% 25%,#fff 0% 50%) 50%/20px 20px}.sg-comparison figure{margin:0 0 16px}.sg-controls{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:12px 0}.sg-controls input{display:block;width:100%}.sg-camera-actions{margin:18px 0 10px}#sg-canvas{width:100%;height:auto;border-radius:12px;background:#18202c}#sg-message{margin-top:12px}.sg-dialog [hidden]{display:none!important}@media(max-width:600px){.sg-dialog{padding:16px}.sg-controls{grid-template-columns:1fr 1fr}.sg-comparison img{height:150px}}
</style>
<script>
(() => {
    const dialog = document.getElementById('sg-media');
    const output = document.getElementById('sg-log');
    const lines = [];
    const trace = (event, detail = '') => {
        const line = `${new Date().toLocaleTimeString()}  ${event}${detail ? ' — ' + detail : ''}`;
        lines.push(line); if (lines.length > 100) lines.shift();
        output.textContent = lines.join('\n'); output.scrollTop = output.scrollHeight;
        console.info('[Stock Gallery]', line);
    };
    const url = {{ Illuminate\Support\Js::from(asset('public/js/stock-gallery-media.js') . '?v=' . (is_file(public_path('js/stock-gallery-media.js')) ? filemtime(public_path('js/stock-gallery-media.js')) : 'missing')) }};
    let loading = false;
    trace('Media loader ready');
    document.getElementById('sg-close').onclick = () => dialog.close();
    document.addEventListener('click', async function bootstrap(event) {
        const button = event.target.closest('.sg-media-open, .sg-try-open');
        if (!button) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (loading) return;
        loading = true;
        const oldText = button.textContent;
        button.disabled = true; button.textContent = 'Loading…';
        trace('Button clicked', button.classList.contains('sg-try-open') ? 'Try On' : 'Manage Media');
        const spinner = document.getElementById('sg-spinner');
        const title = document.getElementById('sg-status-title');
        const message = document.getElementById('sg-message');
        spinner.hidden = false; title.textContent = 'Loading media tools';
        message.textContent = 'Loading the media script. Please wait…';
        if (!dialog.open) dialog.showModal();
        let timer;
        try {
            trace('Loading JavaScript', url);
            const module = await Promise.race([
                import(url),
                new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Script loading timed out. Check the file URL and internet connection.')), 20000); })
            ]);
            const api = module.initStockGalleryMedia({
                base: {{ Illuminate\Support\Js::from(route('company.stock-gallery.media.show', [$company->slug, '__KEY__'])) }},
                csrf: {{ Illuminate\Support\Js::from(csrf_token()) }}, trace
            });
            if (!api || typeof api.open !== 'function') throw new Error('An older media script is cached. Upload the latest JavaScript file and refresh.');
            trace('Media script initialized');
            document.removeEventListener('click', bootstrap, true);
            if (dialog.open) await api.open(button.dataset.key, button.classList.contains('sg-try-open'));
        } catch (error) {
            trace('SCRIPT ERROR', error.message);
            spinner.hidden = true; title.textContent = 'Media tools could not load';
            document.getElementById('sg-status').dataset.state = 'error';
            message.textContent = 'The media JavaScript could not load. Check public/js/stock-gallery-media.js on the server. Close this window and click again to retry. Details are below.';
        } finally {
            clearTimeout(timer); loading = false; button.disabled = false; button.textContent = oldText;
        }
    }, true);
})();
</script>
