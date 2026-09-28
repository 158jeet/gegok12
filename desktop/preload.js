const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('TagoreDesktop', {
  platform: process.platform,
  version: process.versions.electron,
  offlineFirst: true
});
