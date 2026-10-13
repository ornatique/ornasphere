// Loaded only by Stock Gallery. No camera access occurs until Start is pressed.
export function initStockGalleryMedia(config) {
    const el = name => document.getElementById(`sg-${name}`);
    const dialog = el('media');
    const trace = config.trace || ((event, detail = '') => console.info('[Stock Gallery]', event, detail));
    let lastStatus = '';
    let endpoint = '', data = null, poll = null, generation = 0, busy = false;
    let stream = null, detector = null, frame = null, cameraGeneration = 0, artwork = null;
    let lastVideoTime = -1, lastInference = 0, tracking = null;
    const keys = ['scale', 'x', 'y', 'rotation', 'finger', 'earring_mode'];
    const message = (text, state = 'info', title = 'Media status') => {
        el('message').textContent = text;
        el('status-title').textContent = title;
        el('status').dataset.state = state;
        el('spinner').hidden = state !== 'busy';
        el('status').setAttribute('aria-busy', String(state === 'busy'));
    };
    const cameraMessage = text => { el('camera-message').textContent = text; };

    async function request(url, options = {}) {
        const requestId = globalThis.crypto?.randomUUID?.();
        const method = options.method || 'GET';
        trace('Request started', `${method}${requestId ? ' · ID ' + requestId : ''}`);
        const started = performance.now();
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), options.method ? 120000 : 30000);
        try {
        const response = await fetch(url, { credentials: 'same-origin', ...options, signal: controller.signal,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, ...(requestId ? {'X-Gallery-Request-Id': requestId} : {}), ...options.headers } });
        trace('Server response', `HTTP ${response.status} · ${Math.round(performance.now()-started)} ms`);
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Server returned a page instead of JSON. Your session may have expired, or the media route may not be deployed. Sign in and retry.');
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || `Request failed (${response.status}). Please sign in again if your session expired.`);
        return payload;
        } catch (error) {
            trace('REQUEST ERROR', error.name === 'AbortError' ? 'Timed out waiting for server' : error.message);
            if (error.name === 'AbortError') throw new Error('The server is taking too long to respond. Check the status again before uploading another image.');
            throw error;
        } finally { clearTimeout(timer); }
    }

    function render(value, settings = false) {
        data = value;
        if (value.status !== lastStatus) {
            trace('Processing status', `${value.status}${value.error ? ' · ' + value.error : ''}`);
            lastStatus = value.status;
        }
        el('category').textContent = value.category;
        el('label').textContent = value.label || '';
        for (const [id, url] of [['original', value.original_url], ['cutout', value.cutout_url]]) {
            el(id).hidden = !url;
            if (url) el(id).src = url;
            else el(id).removeAttribute('src');
        }
        el('approved').checked = !!value.approved;
        el('enabled').checked = !!value.enabled;
        el('approved').disabled = !value.cutout_url;
        el('enabled').disabled = !value.cutout_url || value.category === 'none';
        el('finger-label').hidden = value.category !== 'ring';
        el('earring-label').hidden = value.category !== 'earrings';
        if (settings) for (const key of keys) el(key).value = value.settings[key];
        const pending = ['queued', 'processing'].includes(value.status);
        el('process').disabled = pending || !value.original_url;
        el('save').disabled = !value.cutout_url;
        el('camera').disabled = !value.cutout_url || value.category === 'none';
        el('upload').disabled = busy;
        el('refresh').hidden = true;
        message(value.error || ({ queued: 'Waiting for the image worker. This status updates automatically. If it stays queued, ask your administrator to check the stock-gallery worker.',
            processing: 'Removing background…', ready: 'Cutout ready. Review the edges, preview placement, then save.',
            unprocessed: 'Your current gallery image is ready for background removal.', empty: 'Upload a clear product photo to begin.' }[value.status] || value.status),
            pending ? 'busy' : value.status === 'failed' ? 'error' : value.status === 'ready' ? 'ready' : 'info',
            {queued: 'Queued — waiting to start', processing: 'Processing — removing background', ready: 'Ready — review your image', failed: 'Processing failed', unprocessed: 'Not processed yet', empty: 'No image uploaded'}[value.status] || 'Media status');
        clearTimeout(poll);
        if (pending && dialog.open) {
            const current = generation;
            poll = setTimeout(async () => {
                try { const next = await request(endpoint); if (current === generation && dialog.open) render(next); }
                catch (error) { if (current === generation) statusError(error); }
            }, 2500);
        }
    }

    async function open(key, tryOnly) {
        trace('Opening media', tryOnly ? 'Try On' : 'Manage Media'); lastStatus = '';
        stopCamera(); clearTimeout(poll); generation++;
        const current = generation;
        data = null;
        endpoint = config.base.replace('__KEY__', encodeURIComponent(key));
        el('management').hidden = tryOnly;
        el('save').hidden = tryOnly;
        el('file').value = '';
        el('camera').disabled = true;
        el('canvas').hidden = true;
        el('original').hidden = el('cutout').hidden = true;
        el('label').textContent = ''; el('category').textContent = '';
        el('upload').disabled = el('process').disabled = el('save').disabled = true;
        el('approved').disabled = el('enabled').disabled = true;
        el('approved').checked = el('enabled').checked = false;
        el('refresh').hidden = true;
        message('Fetching this item’s photo and processing status…', 'busy', 'Loading media');
        if (!dialog.open) dialog.showModal();
        try {
            const value = await request(endpoint);
            if (current !== generation || !dialog.open) return;
            render(value, true);
            if (tryOnly && !value.enabled) { message('Try On is not enabled for the current image.'); el('camera').disabled = true; }
        } catch (error) { if (current === generation) statusError(error); }
    }

    function statusError(error) {
        message(error.message, 'error', 'Unable to check status');
        el('refresh').hidden = false;
    }
    el('refresh').onclick = async () => {
        const current = generation;
        el('refresh').hidden = true;
        message('Checking the latest server status…', 'busy', 'Refreshing status');
        try { const value = await request(endpoint); if (current === generation && dialog.open) render(value, !data); }
        catch (error) { if (current === generation) statusError(error); }
    };

    async function mutation(action) {
        if (busy || !data) return;
        busy = true;
        const current = generation;
        el('upload').disabled = el('process').disabled = el('save').disabled = true;
        try { await action(current); }
        catch (error) { if (current === generation) statusError(error); }
        finally {
            busy = false; el('upload').disabled = false;
            if (current === generation && data) {
                el('process').disabled = !data.original_url || ['queued', 'processing'].includes(data.status);
                el('save').disabled = !data.cutout_url;
            }
        }
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('.sg-media-open, .sg-try-open');
        if (button) { event.preventDefault(); event.stopPropagation(); open(button.dataset.key, button.classList.contains('sg-try-open')); }
    });
    el('close').onclick = () => dialog.close();
    dialog.addEventListener('close', () => { generation++; clearTimeout(poll); stopCamera(); });
    el('upload').onclick = () => mutation(async current => {
        trace('Upload button clicked');
        const file = el('file').files[0];
        if (!file) throw new Error('Choose a JPG, PNG or WebP photo first.');
        if (file.size > 15 * 1024 * 1024) throw new Error('Please choose an image 15 MB or smaller.');
        stopCamera(); message('Uploading your photo. Background removal will be queued after upload.', 'busy', 'Uploading image');
        const form = new FormData(); form.append('image', file);
        const value = await request(`${endpoint}/upload`, { method: 'POST', body: form });
        if (current === generation) render(value, true);
        window.dispatchEvent(new Event('stock-gallery-media-saved'));
    });
    el('process').onclick = () => mutation(async current => {
        trace('Remove Background button clicked');
        stopCamera(); message('Sending image to the background worker…', 'busy', 'Starting background removal');
        const value = await request(`${endpoint}/process`, { method: 'POST' });
        if (current === generation) render(value);
        window.dispatchEvent(new Event('stock-gallery-media-saved'));
    });
    const settings = () => Object.fromEntries(keys.map(key => [key, ['finger', 'earring_mode'].includes(key) ? el(key).value : Number(el(key).value)]));
    el('save').onclick = () => mutation(async current => {
        trace('Save settings clicked');
        message('Saving your approval and try-on placement…', 'busy', 'Saving settings');
        const value = await request(endpoint, { method: 'PUT', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ ...settings(), revision: data.revision, approved: el('approved').checked, enabled: el('enabled').checked }) });
        if (current === generation) { render(value); message('Saved. The approved image is now used in Stock Gallery.'); }
        window.dispatchEvent(new Event('stock-gallery-media-saved'));
    });

    function stopCamera() {
        cameraGeneration++;
        if (frame) cancelAnimationFrame(frame);
        frame = null;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null; el('video').srcObject = null;
        if (detector) detector.close();
        detector = null; tracking = null;
        el('canvas').hidden = true;
        el('camera').disabled = !data?.cutout_url || data?.category === 'none';
        cameraMessage('Camera stopped. Camera frames are not uploaded.');
    }
    el('stop').onclick = stopCamera;
    window.addEventListener('pagehide', stopCamera);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stopCamera(); });

    el('camera').onclick = async () => {
        if (!data?.cutout_url || data.category === 'none') return;
        stopCamera();
        const current = cameraGeneration;
        el('camera').disabled = true;
        try {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) throw new Error('Camera preview requires HTTPS or localhost and a supported browser.');
            cameraMessage('Loading tracking model. The first preview may take a moment…');
            const version = '0.10.22-rc.20250304';
            const vision = await import(`https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${version}/vision_bundle.mjs`);
            const files = await vision.FilesetResolver.forVisionTasks(`https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${version}/wasm`);
            const hand = ['ring', 'bracelet'].includes(data.category);
            const model = hand ? 'hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task' : 'face_landmarker/face_landmarker/float16/1/face_landmarker.task';
            const tracker = await (hand ? vision.HandLandmarker : vision.FaceLandmarker).createFromOptions(files, {
                baseOptions: { modelAssetPath: `https://storage.googleapis.com/mediapipe-models/${model}`, delegate: 'CPU' },
                runningMode: 'VIDEO', ...(hand ? {numHands: 1} : {numFaces: 1})
            });
            if (current !== cameraGeneration) { tracker.close(); return; }
            detector = tracker;
            artwork = await loadImage(data.cutout_url);
            if (current !== cameraGeneration) return;
            const camera = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 640}, height: {ideal: 480}}, audio: false});
            if (current !== cameraGeneration) { camera.getTracks().forEach(track => track.stop()); return; }
            stream = camera;
            const video = el('video'); video.srcObject = stream;
            await video.play();
            if (current !== cameraGeneration) return;
            el('canvas').hidden = false; lastVideoTime = -1; lastInference = 0;
            const draw = now => {
                if (current !== cameraGeneration || !detector) return;
                try {
                    if (video.readyState >= 2 && video.videoWidth) {
                        const canvas = el('canvas');
                        if (canvas.width !== video.videoWidth || canvas.height !== video.videoHeight) {canvas.width = video.videoWidth; canvas.height = video.videoHeight;}
                        if (now - lastInference > 90 && video.currentTime !== lastVideoTime) {
                            const result = detector.detectForVideo(video, now);
                            tracking = hand ? result.landmarks?.[0] : result.faceLandmarks?.[0];
                            lastVideoTime = video.currentTime; lastInference = now;
                        }
                        const ctx = canvas.getContext('2d');
                        ctx.save(); ctx.translate(canvas.width, 0); ctx.scale(-1, 1);
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        if (tracking) drawJewellery(ctx, tracking, canvas.width, canvas.height);
                        ctx.restore();
                        cameraMessage(tracking ? 'Tracking · Approximate 2D preview. Adjust the placement controls as needed.' : `Move your ${hand ? 'hand' : 'face'} into view in good light.`);
                    }
                    frame = requestAnimationFrame(draw);
                } catch (error) { stopCamera(); cameraMessage('Tracking stopped. Please restart the camera preview.'); }
            };
            frame = requestAnimationFrame(draw);
        } catch (error) {
            if (current !== cameraGeneration) return;
            stopCamera();
            cameraMessage(error.name === 'NotAllowedError' ? 'Camera permission was denied. Allow camera access in your browser, then retry.' : `Preview unavailable: ${error.message}`);
        }
    };

    function loadImage(url) {
        return new Promise((resolve, reject) => {const image = new Image(); image.onload = () => resolve(image); image.onerror = () => reject(new Error('Cutout could not be loaded.')); image.src = url;});
    }

    function drawJewellery(ctx, marks, width, height) {
        const s = settings();
        const pt = index => ({x: marks[index].x * width, y: marks[index].y * height});
        const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
        const midpoint = (a, b) => ({x: (a.x+b.x)/2, y: (a.y+b.y)/2});
        const paint = (point, base, angle = 0, side = 0) => {
            let sx = 0, sw = artwork.width;
            if (data.category === 'earrings' && s.earring_mode === 'pair') {sw /= 2; sx = side * sw;}
            const w = base * s.scale, h = w * artwork.height / sw;
            ctx.save(); ctx.translate(point.x + s.x * base, point.y + s.y * base);
            ctx.rotate(angle + s.rotation * Math.PI/180);
            if (side && s.earring_mode === 'single') ctx.scale(-1, 1);
            ctx.drawImage(artwork, sx, 0, sw, artwork.height, -w/2, -h/2, w, h); ctx.restore();
        };
        if (data.category === 'earrings') {
            const left = pt(234), right = pt(454), chin = pt(152);
            const base = distance(left, right) * .18;
            paint({x:left.x, y:left.y+(chin.y-left.y)*.55}, base, 0, 0);
            paint({x:right.x, y:right.y+(chin.y-right.y)*.55}, base, 0, 1);
        } else if (data.category === 'necklace') {
            const chin = pt(152), base = distance(pt(234), pt(454));
            paint({x:chin.x, y:chin.y+base*.55}, base*1.25);
        } else if (data.category === 'ring') {
            const [a,b] = {thumb:[2,3],index:[5,6],middle:[9,10],ring:[13,14],little:[17,18]}[s.finger];
            const from = pt(a), to = pt(b);
            paint(midpoint(from,to), distance(pt(5),pt(17))*.24, Math.atan2(to.y-from.y,to.x-from.x)+Math.PI/2);
        } else if (data.category === 'bracelet') {
            const wrist = pt(0), palm = pt(9);
            paint(wrist, distance(pt(5),pt(17))*.85, Math.atan2(palm.y-wrist.y,palm.x-wrist.x)+Math.PI/2);
        }
    }
    return {open};
}
