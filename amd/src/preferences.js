/**
 * Preferences UI manager for Plugin Monitor.
 *
 * @module     local_plugwatch/preferences
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Pending from 'core/pending';
import {init as initPluginSearch} from 'local_plugwatch/plugin_search';

/**
 * Initialize the preferences UI.
 *
 * @param {String} rootSelector The root element selector
 */
export const init = (rootSelector) => {
    const root = document.querySelector(rootSelector);
    if (!root) {
        return;
    }

    const maxPlugins = parseInt(root.dataset.maxplugins, 10);
    const tbody = root.querySelector('[data-region="plugwatch-tbody"]');
    const statusDiv = root.querySelector('[data-region="plugwatch-status"]');
    const countBadge = root.querySelector('[data-region="plugwatch-count"]');

    const updateCount = () => {
        const currentCount = tbody.querySelectorAll('tr').length;
        getString('watchedplugins_count', 'local_plugwatch', {
            current: currentCount,
            max: maxPlugins
        }).then((str) => {
            countBadge.textContent = str;
            return str;
        }).catch(Notification.exception);
    };

    const setStatus = (message, isError = false) => {
        statusDiv.classList.remove('alert', 'alert-success', 'alert-danger');
        statusDiv.textContent = '';
        if (message) {
            statusDiv.classList.add('alert', isError ? 'alert-danger' : 'alert-success');
            statusDiv.textContent = message;
            setTimeout(() => {
                statusDiv.classList.remove('alert', 'alert-success', 'alert-danger');
                statusDiv.textContent = '';
            }, 5000);
        }
    };

    // Initialize the search module.
    initPluginSearch(root, (selectedPlugin) => {
        // Callback when a plugin is selected and Add is clicked.
        const pendingPromise = new Pending('local_plugwatch/add_plugin');

        Ajax.call([{
            methodname: 'local_plugwatch_add_plugin',
            args: {component: selectedPlugin.component}
        }])[0].then((result) => {
            if (!result.success) {
                setStatus(result.message, true);
                return result;
            }

            // Reload page to reflect changes properly for MVP
            window.location.reload();
            return result;
        }).catch(Notification.exception)
          .always(() => pendingPromise.resolve());
    });

    // Handle remove buttons (event delegation, since rows are added/removed dynamically).
    // tbody does not exist when the watch list is empty (the template renders the
    // empty-state message instead), so there is nothing to delegate from in that case.
    if (!tbody) {
        return;
    }

    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="remove"]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        const component = btn.dataset.component;

        const pendingPromise = new Pending('local_plugwatch/remove_plugin');

        Ajax.call([{
            methodname: 'local_plugwatch_remove_plugin',
            args: {component: component}
        }])[0].then((result) => {
            if (result.success) {
                btn.closest('tr').remove();

                // If empty, reload to show empty state. Do this before updateCount()
                // or the string-fetching below: both are async, and the reload would
                // otherwise tear down the page mid-flight, leaving their promises to
                // resolve into a torn-down context (a stray console error, no visible effect).
                if (tbody.querySelectorAll('tr').length === 0) {
                    window.location.reload();
                } else {
                    updateCount();
                    // eslint-disable-next-line promise/no-nesting
                    getString('plugin', 'local_plugwatch').then((pluginStr) => {
                        setStatus(`${pluginStr} ${component} removed.`);
                        return pluginStr;
                    }).catch(Notification.exception);
                }
            } else {
                setStatus(result.message, true);
            }
            return result;
        }).catch(Notification.exception)
          .always(() => pendingPromise.resolve());
    });
};
