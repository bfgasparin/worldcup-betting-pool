import { useSyncExternalStore } from 'react';

/**
 * Drives the "install this app to your home screen" (PWA) experience.
 *
 * Follows the same module-level store pattern as {@link useAppearance}: a single set of
 * listeners is wired once in {@link initializeInstallPrompt} (so React StrictMode's double
 * mount can't double-register them), and components read a cached, immutable snapshot via
 * `useSyncExternalStore`. Everything is SSR-safe — on the server the snapshot is all-false,
 * so nothing renders until the browser hydrates.
 *
 * Dismissal is remembered per-device in `localStorage` (no server round-trip), mirroring how
 * appearance/timezone preferences are stored.
 */

/** Chromium-only event fired when the browser is willing to install the app. */
interface BeforeInstallPromptEvent extends Event {
    readonly platforms: string[];
    prompt: () => Promise<void>;
    readonly userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
}

export type InstallPromptState = {
    /** We hold a usable `beforeinstallprompt` (Android/Chromium) → one-tap install is possible. */
    readonly canInstall: boolean;
    /** The app is running as an installed PWA right now (launched from the home screen). */
    readonly isStandalone: boolean;
    /** The app has been installed (`appinstalled` fired this session, or running standalone). */
    readonly isInstalled: boolean;
    /** iOS Safari, which has no programmatic install — it needs the manual instructions sheet. */
    readonly isIOS: boolean;
    /** An in-app webview (Instagram/Facebook/etc.) that cannot install — stay quiet there. */
    readonly isInAppBrowser: boolean;
    /** The user dismissed the prompt within the cooldown window (keep the banner hidden). */
    readonly dismissed: boolean;
};

export type InstallOutcome = 'accepted' | 'dismissed' | 'unavailable';

export type UseInstallPromptReturn = InstallPromptState & {
    /** Trigger the native install prompt (Android); resolves to the user's choice. */
    readonly promptInstall: () => Promise<InstallOutcome>;
    /** Remember that the user dismissed the prompt (per-device; cooldown applies). */
    readonly dismiss: () => void;
};

const DISMISSED_KEY = 'pwa-install-dismissed-at';
const COOLDOWN_MS = 14 * 24 * 60 * 60 * 1000; // re-offer 14 days after a dismissal

const SERVER_SNAPSHOT: InstallPromptState = {
    canInstall: false,
    isStandalone: false,
    isInstalled: false,
    isIOS: false,
    isInAppBrowser: false,
    dismissed: false,
};

const listeners = new Set<() => void>();
let deferredPrompt: BeforeInstallPromptEvent | null = null;
let installed = false;
let initialized = false;

const notify = (): void => listeners.forEach((listener) => listener());

const subscribe = (callback: () => void): (() => void) => {
    listeners.add(callback);

    return () => {
        listeners.delete(callback);
    };
};

const detectStandalone = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (window.navigator as Navigator & { standalone?: boolean })
            .standalone === true
    );
};

const detectIOS = (): boolean => {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const ua = navigator.userAgent;

    // iPadOS 13+ reports a desktop Safari UA, so also treat a touch-capable Mac as iOS.
    return (
        /iphone|ipad|ipod/i.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
};

const detectInAppBrowser = (): boolean => {
    if (typeof navigator === 'undefined') {
        return false;
    }

    // Conservative match for the common social/messaging webviews that can't install.
    return /FBAN|FBAV|Instagram|Line\/|MicroMessenger|; wv\)/i.test(
        navigator.userAgent,
    );
};

const isDismissed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    const stored = Number(window.localStorage.getItem(DISMISSED_KEY));

    return (
        Number.isFinite(stored) &&
        stored > 0 &&
        Date.now() - stored < COOLDOWN_MS
    );
};

const computeSnapshot = (): InstallPromptState => {
    const standalone = detectStandalone();

    return {
        canInstall: deferredPrompt !== null,
        isStandalone: standalone,
        isInstalled: installed || standalone,
        isIOS: detectIOS(),
        isInAppBrowser: detectInAppBrowser(),
        dismissed: isDismissed(),
    };
};

// Cached snapshot — `useSyncExternalStore` requires a stable reference between changes.
let snapshot: InstallPromptState = computeSnapshot();

const refresh = (): void => {
    snapshot = computeSnapshot();
    notify();
};

const promptInstall = async (): Promise<InstallOutcome> => {
    if (!deferredPrompt) {
        return 'unavailable';
    }

    const event = deferredPrompt;

    await event.prompt();
    const { outcome } = await event.userChoice;

    // A captured prompt can only be used once; drop it regardless of outcome.
    deferredPrompt = null;
    refresh();

    return outcome;
};

const dismiss = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(DISMISSED_KEY, String(Date.now()));
    refresh();
};

/** Wire the install lifecycle listeners exactly once, from the app entrypoint. */
export function initializeInstallPrompt(): void {
    if (typeof window === 'undefined' || initialized) {
        return;
    }

    initialized = true;

    window.addEventListener('beforeinstallprompt', (event) => {
        // Suppress Chrome's default mini-infobar; we surface our own branded prompt instead.
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        refresh();
    });

    window.addEventListener('appinstalled', () => {
        installed = true;
        deferredPrompt = null;
        refresh();
    });

    window
        .matchMedia('(display-mode: standalone)')
        .addEventListener('change', refresh);

    snapshot = computeSnapshot();
}

export function useInstallPrompt(): UseInstallPromptReturn {
    const state = useSyncExternalStore(
        subscribe,
        () => snapshot,
        () => SERVER_SNAPSHOT,
    );

    return { ...state, promptInstall, dismiss };
}
