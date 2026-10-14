(function () {
  const button = document.getElementById('cctv-check-button');
  const result = document.getElementById('cctv-health-result');
  const cameras = document.getElementById('cctv-camera-list');
  if (!button || !result || !cameras) return;

  button.addEventListener('click', async function () {
    button.disabled = true;
    button.textContent = 'Checking…';
    result.className = 'tg-alert warning';
    result.textContent = 'Checking the private CCTV service…';
    cameras.hidden = true;
    cameras.replaceChildren();
    try {
      const response = await fetch('/tagore/platform/cctv/status', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const data = await response.json();
      result.className = 'tg-alert ' + (data.reachable ? 'success' : 'warning');
      result.textContent = data.message || 'No status message returned.';
      if (data.reachable) {
        result.textContent += ' Cameras reported: ' + Number(data.camera_count || 0) + '. Detectors reported: ' + Number(data.detector_count || 0) + '.';
        const names = Array.isArray(data.camera_names) ? data.camera_names : [];
        for (const name of names) {
          const card = document.createElement('div');
          card.className = 'tg-module';
          const heading = document.createElement('b');
          heading.textContent = name;
          const detail = document.createElement('span');
          detail.textContent = 'Camera is registered with Frigate; stream health is not verified by this check.';
          card.append(heading, detail);
          cameras.append(card);
        }
        cameras.hidden = names.length === 0;
      }
    } catch (error) {
      result.className = 'tg-alert warning';
      result.textContent = 'Could not read CCTV status. Check your login, ERP connection and private NVR configuration.';
    } finally {
      button.disabled = false;
      button.textContent = 'Check connection';
    }
  });
})();
