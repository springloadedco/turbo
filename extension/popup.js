const NATIVE_HOST = 'com.springloaded.turbo_feedback';

let matchedWorkspace = null;

document.addEventListener('DOMContentLoaded', async () => {
  const captureBtn = document.getElementById('capture-btn');
  const annotationEl = document.getElementById('annotation');
  const statusEl = document.getElementById('status');
  const projectIndicator = document.getElementById('project-indicator');
  const projectName = document.getElementById('project-name');
  const projectList = document.getElementById('project-list');

  // Load projects and match current tab
  const { projects = {} } = await chrome.storage.local.get('projects');

  renderProjectList(projectList, projects);

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (tab?.url) {
    try {
      const tabHost = new URL(tab.url).hostname;
      for (const [host, workspace] of Object.entries(projects)) {
        if (tabHost === host || tabHost.endsWith('.' + host)) {
          matchedWorkspace = workspace;
          projectIndicator.classList.add('matched');
          projectName.textContent = `${host} → ${shortenPath(workspace)}`;
          captureBtn.disabled = false;
          break;
        }
      }
    } catch {
      // invalid URL — leave unmatched
    }
  }

  captureBtn.addEventListener('click', async () => {
    if (!matchedWorkspace) return;

    captureBtn.disabled = true;
    captureBtn.textContent = 'Capturing...';

    try {
      // Capture the visible tab as a PNG data URL
      const dataUrl = await chrome.tabs.captureVisibleTab(null, { format: 'png' });
      const base64Image = dataUrl.replace(/^data:image\/png;base64,/, '');

      // Generate timestamped filename
      const now = new Date();
      const timestamp = [
        now.getFullYear(),
        pad(now.getMonth() + 1),
        pad(now.getDate()),
        '-',
        pad(now.getHours()),
        pad(now.getMinutes()),
        pad(now.getSeconds()),
      ].join('');
      const filename = `${timestamp}-feedback.png`;

      // Gather viewport info
      const viewport = tab
        ? { width: tab.width, height: tab.height }
        : null;

      const annotation = annotationEl.value.trim() || null;

      // Send to native messaging host
      const response = await sendNativeMessage({
        image: base64Image,
        filename,
        annotation,
        url: tab?.url || null,
        viewport,
        workspace: matchedWorkspace,
      });

      if (response?.success) {
        // Copy trigger string to clipboard
        const trigger = annotation ? `[feedback] ${annotation}` : '[feedback]';
        await copyToClipboard(trigger);

        showStatus(statusEl, 'success', 'Screenshot captured. Trigger copied to clipboard.');
        setTimeout(() => window.close(), 1500);
      } else {
        showStatus(statusEl, 'error', response?.error || 'Native host returned an error.');
        captureBtn.disabled = false;
        captureBtn.textContent = 'Capture';
      }
    } catch (err) {
      showStatus(statusEl, 'error', err.message || 'Failed to capture screenshot.');
      captureBtn.disabled = false;
      captureBtn.textContent = 'Capture';
    }
  });
});

/**
 * Send a message to the native messaging host and return the response.
 */
function sendNativeMessage(message) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendNativeMessage(NATIVE_HOST, message, (response) => {
      if (chrome.runtime.lastError) {
        reject(new Error(chrome.runtime.lastError.message));
      } else {
        resolve(response);
      }
    });
  });
}

/**
 * Copy text to the clipboard using the Clipboard API (requires clipboardWrite).
 */
async function copyToClipboard(text) {
  // In a popup context, we can use the Clipboard API directly
  // because the popup has focus and the clipboardWrite permission is granted.
  await navigator.clipboard.writeText(text);
}

/**
 * Show a status message.
 */
function showStatus(el, type, message) {
  el.textContent = message;
  el.className = `status ${type}`;
}

/**
 * Render the list of configured projects.
 */
function renderProjectList(listEl, projects) {
  const entries = Object.entries(projects);
  if (entries.length === 0) {
    listEl.innerHTML =
      '<li class="empty">No projects configured. Use a <code>turbo-feedback://</code> link to add one.</li>';
    return;
  }

  listEl.innerHTML = entries
    .map(
      ([host, workspace]) =>
        `<li><span class="host">${escapeHtml(host)}</span><span class="workspace" title="${escapeHtml(workspace)}">${escapeHtml(shortenPath(workspace))}</span></li>`
    )
    .join('');
}

/**
 * Shorten a workspace path for display.
 */
function shortenPath(p) {
  const home = '/Users/';
  if (p.startsWith(home)) {
    const rest = p.slice(home.length);
    const slash = rest.indexOf('/');
    if (slash !== -1) {
      return '~/' + rest.slice(slash + 1);
    }
  }
  return p;
}

/**
 * Escape HTML entities.
 */
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/**
 * Zero-pad a number to two digits.
 */
function pad(n) {
  return String(n).padStart(2, '0');
}
