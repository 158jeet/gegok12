# TagoreK12 Windows Client

The Windows client is an Electron desktop shell around the TagoreK12 ERP. The ERP's offline-first PWA layer stores approved client data and pending sync events in IndexedDB and synchronizes them with the Laravel API when connectivity returns.

## Local testing

Set TAGORE_SERVER_URL to the TagoreK12 server URL, then run:

    npm install
    npm start

## Build Windows installer

    npm install
    npm run dist

The installer is produced under desktop/dist/.
