(function AntiScreenshotModule() {
    const overlay = document.getElementById('antiScreenshotOverlay');

    function flashOverlay(duration = 1500) {
        if (!overlay) return;
        overlay.style.display = 'block';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, duration);
    }

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    }

    function blockDangerousKeys(e) {
        const key = e.key.toLowerCase();

        // Screenshot/Print prevention
        if (
            key === "printscreen" ||
            (e.metaKey && e.shiftKey && ["3", "4", "5"].includes(key)) || // macOS Cmd+Shift+3/4/5
            (e.ctrlKey && e.shiftKey && key === "s") || // Windows Snip Tool
            (e.altKey && key === "printscreen")
        ) {
            e.preventDefault();
            navigator.clipboard.writeText("");
            flashOverlay();
            alert("Screenshots are disabled.");
        }

        // Developer tools
        if (
            key === "f12" ||
            (e.ctrlKey && e.shiftKey && ["i", "c", "j"].includes(key)) || // Ctrl+Shift+I/C/J
            (e.ctrlKey && key === "u") // View Source
        ) {
            e.preventDefault();
            alert("Developer tools access is disabled.");
        }

        // Print
        if (e.ctrlKey && key === "p") {
            e.preventDefault();
            alert("Printing is disabled.");
        }
    }

    // Attach listeners
    document.addEventListener('keydown', blockDangerousKeys);

    // Mobile overlay protection
    if (isMobileDevice()) {
        overlay.style.display = 'block';
        console.warn("Mobile device detected: screen blurred for privacy.");
    }
})
();
