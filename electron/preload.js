// Narrow bridge between the Laravel page (http://127.0.0.1) and the main process.
// The page can only call these five functions; it never gets Node, and the main process
// re-checks the sender's origin on every call. The stored password is never sent back to the page.
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('hierSync', {
    hasPassword: () => ipcRenderer.invoke('sync:has-password'),
    setPassword: (password) => ipcRenderer.invoke('sync:set-password', String(password)),
    clearPassword: () => ipcRenderer.invoke('sync:clear-password'),
    testConnection: () => ipcRenderer.invoke('sync:test'),
    pushNow: () => ipcRenderer.invoke('sync:push-now'),
});
