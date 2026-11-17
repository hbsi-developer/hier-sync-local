const { app, BrowserWindow, Menu, Notification, Tray, dialog, nativeImage } = require('electron');
const { spawn, spawnSync } = require('child_process');
const fs = require('fs');
const net = require('net');
const path = require('path');

const packaged = app.isPackaged;
const appRoot = packaged ? path.join(process.resourcesPath, 'laravel') : path.join(__dirname, '..');
// User data (database, logs, sessions) lives outside the install folder so updates never wipe it.
const dataDir = packaged ? app.getPath('userData') : path.join(appRoot, 'storage', 'desktop');

// The app was renamed ("ZK Attendance" -> "Hierpayroll AST" -> "Hier Sync Local"); carry over
// data saved under an old name, newest first.
if (packaged) {
    for (const oldName of ['Hierpayroll AST', 'ZK Attendance']) {
        const oldDir = path.join(app.getPath('appData'), oldName);
        try {
            if (fs.existsSync(oldDir) && !fs.existsSync(dataDir)) fs.renameSync(oldDir, dataDir);
        } catch (e) {
            console.log('[migrate-userdata]', e.message);
        }
    }
}

// Dev fallback: newest x64 PHP 8.x installed under Laragon, highest version first.
function laragonPhps() {
    const root = 'C:\\laragon\\bin\\php';
    const ver = (d) => (d.match(/^php-(\d+)\.(\d+)\.(\d+)/) || []).slice(1).map(Number);
    let dirs = [];
    try { dirs = fs.readdirSync(root); } catch { return []; }
    return dirs
        .filter((d) => /^php-8\./.test(d) && /x64/i.test(d))
        .sort((a, b) => {
            const [x, y] = [ver(a), ver(b)];
            return (y[0] - x[0]) || (y[1] - x[1]) || (y[2] - x[2]);
        })
        .map((d) => path.join(root, d, 'php.exe'));
}

function findPhp() {
    const candidates = [
        packaged ? path.join(process.resourcesPath, 'php', 'php.exe') : path.join(appRoot, 'php', 'php.exe'),
        process.env.PHP_BINARY,
        ...laragonPhps(),
    ].filter(Boolean);
    return candidates.find((p) => fs.existsSync(p));
}

function freePort() {
    return new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.listen(0, '127.0.0.1', () => {
            const { port } = srv.address();
            srv.close(() => resolve(port));
        });
        srv.on('error', reject);
    });
}

function waitForServer(port, tries = 60) {
    return new Promise((resolve, reject) => {
        const attempt = (n) => {
            const s = net.connect(port, '127.0.0.1');
            s.on('connect', () => { s.destroy(); resolve(); });
            s.on('error', () => (n <= 0 ? reject(new Error('PHP server did not start')) : setTimeout(() => attempt(n - 1), 250)));
        };
        attempt(tries);
    });
}

let phpProc;

function phpArgs(php) {
    return [
        '-c', path.join(appRoot, 'desktop', 'php.ini'),
        '-d', `extension_dir=${path.join(path.dirname(php), 'ext')}`,
    ];
}

function laravelEnv() {
    fs.mkdirSync(path.join(dataDir, 'storage', 'framework', 'views'), { recursive: true });
    fs.mkdirSync(path.join(dataDir, 'storage', 'framework', 'cache'), { recursive: true });
    fs.mkdirSync(path.join(dataDir, 'storage', 'framework', 'sessions'), { recursive: true });
    fs.mkdirSync(path.join(dataDir, 'storage', 'logs'), { recursive: true });

    const db = path.join(dataDir, 'database.sqlite');
    if (!fs.existsSync(db)) fs.writeFileSync(db, '');

    // APP_KEY is generated once and kept in user data.
    const keyFile = path.join(dataDir, 'app.key');
    if (!fs.existsSync(keyFile)) {
        fs.writeFileSync(keyFile, 'base64:' + require('crypto').randomBytes(32).toString('base64'));
    }

    return {
        ...process.env,
        APP_ENV: packaged ? 'production' : 'local',
        APP_DEBUG: packaged ? 'false' : 'true',
        APP_KEY: fs.readFileSync(keyFile, 'utf8'),
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: db,
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'file',
        QUEUE_CONNECTION: 'sync',
        LARAVEL_STORAGE_PATH: path.join(dataDir, 'storage'),    };
}

async function startLaravel() {
    const php = findPhp();
    if (!php) throw new Error('php.exe not found. Place a PHP 8.2+ x64 build in the "php" folder.');

    const env = laravelEnv();
    const opts = { cwd: appRoot, env, windowsHide: true };

    const mig = spawnSync(php, [...phpArgs(php), 'artisan', 'migrate', '--force'], opts);
    if (mig.status !== 0) throw new Error('Database migration failed:\n' + mig.stderr?.toString() + mig.stdout?.toString());

    const port = await freePort();
    phpProc = spawn(php, [...phpArgs(php), '-S', `127.0.0.1:${port}`, '-t', path.join(appRoot, 'public'), path.join(appRoot, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')],
        // Laravel's router script resolves index.php from the working directory, so serve from public/.
        { ...opts, cwd: path.join(appRoot, 'public') });
    phpProc.stderr.on('data', (d) => console.log('[php]', d.toString().trim()));
    await waitForServer(port);
    return port;
}

// Auto sync: a separate PHP process runs once a minute so a slow device never blocks the UI
// server. The command itself decides which devices are due (see devices:auto-sync).
let autoSyncTimer;
let autoSyncRunning = false;

function startAutoSync(php) {
    const tick = () => {
        if (autoSyncRunning) return;
        autoSyncRunning = true;
        const p = spawn(php, [...phpArgs(php), 'artisan', 'devices:auto-sync'], { cwd: appRoot, env: laravelEnv(), windowsHide: true });
        p.stdout.on('data', (d) => console.log('[auto-sync]', d.toString().trim()));
        p.stderr.on('data', (d) => console.log('[auto-sync:err]', d.toString().trim()));
        p.on('close', () => { autoSyncRunning = false; });
    };
    setTimeout(tick, 10000);
    autoSyncTimer = setInterval(tick, 60000);
}

const ICON_PATH = path.join(__dirname, 'icon.png');
const startHidden = process.argv.includes('--hidden');
let win;
let tray;
let quitting = false;
let notifiedTray = false;

const SPLASH =`<!doctype html><meta charset="utf-8"><title>Hier Sync Local</title>
<style>
  html,body{height:100%;margin:0}
  body{display:grid;place-items:center;background:#212735;color:#fff;font:600 15px "Montserrat","Segoe UI",sans-serif}
  .box{text-align:center}
  .ring{width:46px;height:46px;margin:0 auto 18px;border:4px solid rgba(255,255,255,.15);border-top-color:#15DD58;border-radius:50%;animation:s 0.9s linear infinite}
  h1{margin:0 0 6px;font-size:22px;font-weight:900;letter-spacing:-.01em}
  p{margin:0;color:#9aa4b8;font-weight:500}
  @keyframes s{to{transform:rotate(360deg)}}
</style>
<div class="box"><div class="ring"></div><h1>Hier Sync Local</h1><p>Starting up…</p></div>`;

async function createWindow() {
    win = new BrowserWindow({
        width: 1200, height: 800, title: 'Hier Sync Local', icon: ICON_PATH,
        autoHideMenuBar: true, backgroundColor: '#212735', show: false,
        maximizable: false, fullscreenable: false,
    });
    // Electron ignores the constructor option on some Windows setups, so enforce it explicitly.
    win.setMaximizable(false);
    win.on('maximize', () => win.unmaximize());
    // Show a splash right away: migrating the database and starting PHP takes several seconds.
    win.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(SPLASH));
    win.once('ready-to-show', () => { if (!startHidden) win.show(); });

    // Windows/Electron can leave the page without keyboard focus (inputs show but won't accept typing),
    // e.g. after the splash is swapped for the app or after a native confirm() dialog. Re-focus the page.
    const focusPage = () => { if (win && !win.isDestroyed() && win.isFocused()) win.webContents.focus(); };
    win.on('focus', focusPage);
    win.webContents.on('did-finish-load', focusPage);
    win.webContents.on('dom-ready', focusPage);

    // Closing the window keeps the app (and auto sync) running in the system tray.
    win.on('close', (e) => {
        if (quitting) return;
        e.preventDefault();
        win.hide();
        if (!notifiedTray && Notification.isSupported()) {
            notifiedTray = true;
            new Notification({ title: 'Hier Sync Local is still running', body: 'Auto sync continues in the background. Use the tray icon to open or quit.' }).show();
        }
    });

    try {
        const port = await startLaravel();
        await win.loadURL(`http://127.0.0.1:${port}/`);
        startAutoSync(findPhp());
    } catch (e) {
        dialog.showErrorBox('Hier Sync Local failed to start', String(e.message || e));
        quitting = true;
        app.quit();
    }
}

function showWindow() {
    if (!win) return;
    if (win.isMinimized()) win.restore();
    win.show();
    win.focus();
}

function runSyncNow() {
    const php = findPhp();
    if (!php) return;
    const p = spawn(php, [...phpArgs(php), 'artisan', 'devices:auto-sync', '--force'], { cwd: appRoot, env: laravelEnv(), windowsHide: true });
    p.on('close', () => {
        if (Notification.isSupported()) new Notification({ title: 'Hier Sync Local', body: 'Sync finished. Open the app to see the results.' }).show();
        if (win && win.isVisible()) win.webContents.reload();
    });
}

function createTray() {
    tray = new Tray(nativeImage.createFromPath(ICON_PATH).resize({ width: 16, height: 16 }));
    tray.setToolTip('Hier Sync Local');
    const menu = () => Menu.buildFromTemplate([
        { label: 'Open Hier Sync Local', click: showWindow },
        { label: 'Sync all devices now', click: runSyncNow },
        { type: 'separator' },
        {
            label: 'Start with Windows',
            type: 'checkbox',
            enabled: packaged,
            checked: app.getLoginItemSettings().openAtLogin,
            click: (item) => app.setLoginItemSettings({ openAtLogin: item.checked, args: ['--hidden'] }),
        },
        { type: 'separator' },
        { label: 'Quit', click: () => { quitting = true; app.quit(); } },
    ]);
    tray.on('click', showWindow);
    tray.on('right-click', () => tray.popUpContextMenu(menu()));
}

if (!app.requestSingleInstanceLock()) {
    // Another copy is already running (possibly hidden in the tray): it will show its window.
    quitting = true;
    app.quit();
} else {
    app.on('second-instance', showWindow);
    app.whenReady().then(() => { createTray(); createWindow(); });
}

// Stay alive in the tray when every window is closed.
app.on('window-all-closed', () => {});
app.on('before-quit', () => { quitting = true; });
app.on('quit', () => { clearInterval(autoSyncTimer); if (phpProc) phpProc.kill(); });
