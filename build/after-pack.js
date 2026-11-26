// signAndEditExecutable is off (winCodeSign cannot extract without symlink rights),
// so electron-builder never stamps our icon on the exe. Do it here with rcedit.
const path = require('path');

exports.default = async function (context) {
    if (context.electronPlatformName !== 'win32') return;
    const { default: rcedit } = await import('rcedit');
    const exe = path.join(context.appOutDir, `${context.packager.appInfo.productFilename}.exe`);
    await rcedit(exe, { icon: path.join(__dirname, 'icon.ico') });
};
