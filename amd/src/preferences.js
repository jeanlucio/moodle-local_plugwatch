/**
 * Preferences UI manager for Plugin Monitor.
 *
 * @module     local_plugwatch/preferences
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Str from 'core/str';
import Pending from 'core/pending';
import PluginSearch from 'local_plugwatch/plugin_search';

/**
 * Initialize the preferences UI.
 *
 * @param {String} rootSelector The root element selector
 */
export const init = (rootSelector) => {
    const root = $(rootSelector);
    if (!root.length) {
        return;
    }

    const maxPlugins = parseInt(root.data('maxplugins'), 10);
    const tbody = root.find('[data-region="plugwatch-tbody"]');
    const statusDiv = root.find('[data-region="plugwatch-status"]');
    const countBadge = root.find('[data-region="plugwatch-count"]');

    const updateCount = () => {
        const currentCount = tbody.find('tr').length;
        Str.get_string('watchedplugins_count', 'local_plugwatch', {
            current: currentCount,
            max: maxPlugins
        }).then((str) => {
            countBadge.text(str);
            return str;
        }).catch(Notification.exception);
    };

    const setStatus = (message, isError = false) => {
        statusDiv.removeClass('alert alert-success alert-danger').empty();
        if (message) {
            statusDiv.addClass('alert ' + (isError ? 'alert-danger' : 'alert-success')).text(message);
            setTimeout(() => {
                statusDiv.removeClass('alert alert-success alert-danger').empty();
            }, 5000);
        }
    };

    // Initialize the search module.
    PluginSearch.init(root, (selectedPlugin) => {
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

    // Handle remove buttons
    tbody.on('click', '[data-action="remove"]', function(e) {
        e.preventDefault();
        const btn = $(this);
        const component = btn.data('component');

        const pendingPromise = new Pending('local_plugwatch/remove_plugin');

        Ajax.call([{
            methodname: 'local_plugwatch_remove_plugin',
            args: {component: component}
        }])[0].then((result) => {
            if (result.success) {
                btn.closest('tr').remove();
                updateCount();

                // If empty, reload to show empty state
                if (tbody.find('tr').length === 0) {
                    window.location.reload();
                } else {
                    // eslint-disable-next-line promise/no-nesting
                    Str.get_string('plugin', 'local_plugwatch').then((pluginStr) => {
                        setStatus(pluginStr + ' ' + component + ' removed.');
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
