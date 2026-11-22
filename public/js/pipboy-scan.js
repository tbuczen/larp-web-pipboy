// Holodisk scanner: reads QR codes from the rear camera and submits the code
// through the same form as manual entry. Uses the browser's BarcodeDetector
// where available (Chrome on Android) and the vendored jsQR elsewhere (iOS).
// Frames are decoded on the device; only the decoded text is submitted.
(() => {
    const form = document.querySelector('[data-scan-form]');
    const viewport = document.querySelector('[data-scan-viewport]');
    const video = document.querySelector('[data-scan-video]');
    const startButton = document.querySelector('[data-scan-start]');
    const stopButton = document.querySelector('[data-scan-stop]');
    const status = document.querySelector('[data-scan-status]');
    if (!form || !video || !navigator.mediaDevices?.getUserMedia || (!('BarcodeDetector' in window) && !window.jsQR)) {
        return;
    }
    const say = (text) => { status.textContent = text; };
    let stream = null;
    let timer = null;
    let detector = null;
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    const decode = async () => {
        if (detector) {
            const codes = await detector.detect(video);
            return codes[0]?.rawValue ?? null;
        }
        const width = Math.min(video.videoWidth, 720);
        const height = Math.round(video.videoHeight * (width / video.videoWidth));
        if (!width || !height) { return null; }
        canvas.width = width;
        canvas.height = height;
        context.drawImage(video, 0, 0, width, height);
        return window.jsQR(context.getImageData(0, 0, width, height).data, width, height, { inversionAttempts: 'attemptBoth' })?.data ?? null;
    };

    const stop = () => {
        window.clearTimeout(timer);
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        viewport.hidden = true;
        stopButton.hidden = true;
        startButton.hidden = false;
    };

    const tick = async () => {
        if (!stream) { return; }
        try {
            const text = await decode();
            if (text) {
                say('SIGNAL ACQUIRED - DOWNLOADING…');
                form.querySelector('[data-scan-code]').value = text;
                stop();
                startButton.hidden = true;
                form.requestSubmit();
                return;
            }
        } catch { /* a frame that cannot be decoded; keep scanning */ }
        timer = window.setTimeout(tick, 200);
    };

    const start = async () => {
        try {
            if ('BarcodeDetector' in window && !detector) {
                const formats = await window.BarcodeDetector.getSupportedFormats?.() ?? ['qr_code'];
                detector = formats.includes('qr_code') ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
            }
            if (!detector && !window.jsQR) { throw new Error('no decoder'); }
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream;
            await video.play();
            viewport.hidden = false;
            startButton.hidden = true;
            stopButton.hidden = false;
            say('SCANNING…');
            tick();
        } catch (error) {
            stop();
            say(error?.name === 'NotAllowedError' ? 'CAMERA ACCESS DENIED - TYPE THE CODE BELOW' : 'NO CAMERA AVAILABLE - TYPE THE CODE BELOW');
        }
    };

    startButton.hidden = false;
    startButton.addEventListener('click', start);
    stopButton.addEventListener('click', () => { stop(); say('SCANNER OFF'); });
    document.addEventListener('visibilitychange', () => { if (document.hidden && stream) { stop(); say('SCANNER OFF'); } });
})();
