/**
 * Turbo Feedback — Background Service Worker
 *
 * Handles turbo-feedback:// deep links received via external messages.
 * Deep links configure project URL-to-workspace mappings stored in
 * chrome.storage.local.
 *
 * Expected deep link format:
 *   turbo-feedback://configure?host=myapp.test&workspace=/Users/dev/Sites/myapp
 */

chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
  if (message?.type === 'configure' && message.host && message.workspace) {
    configureProject(message.host, message.workspace)
      .then(() => sendResponse({ success: true }))
      .catch((err) => sendResponse({ success: false, error: err.message }));
    return true; // keep the message channel open for async response
  }
});

/**
 * Handle navigation to turbo-feedback:// URLs.
 *
 * Chrome fires onBeforeNavigate for custom protocol URLs; we intercept them
 * here, parse the parameters, and save the project configuration.
 */
if (chrome.webNavigation) {
  chrome.webNavigation.onBeforeNavigate.addListener((details) => {
    if (details.url && details.url.startsWith('turbo-feedback://configure')) {
      try {
        // Parse the URL — turbo-feedback://configure?host=x&workspace=y
        const url = new URL(details.url.replace('turbo-feedback://', 'https://turbo-feedback/'));
        const host = url.searchParams.get('host');
        const workspace = url.searchParams.get('workspace');

        if (host && workspace) {
          configureProject(host, workspace);
        }
      } catch {
        // silently ignore malformed URLs
      }
    }
  });
}

/**
 * Save a host → workspace mapping to chrome.storage.local.
 */
async function configureProject(host, workspace) {
  const { projects = {} } = await chrome.storage.local.get('projects');
  projects[host] = workspace;
  await chrome.storage.local.set({ projects });
}
