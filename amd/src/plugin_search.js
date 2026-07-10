/**
 * Plugin search UI component.
 *
 * @module     local_plugwatch/plugin_search
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Initialize the plugin search autocomplete.
 *
 * @param {jQuery} root The root element containing the search elements
 * @param {Function} onAddCallback Callback function when Add is clicked
 */
export const init = (root, onAddCallback) => {
    const searchInput = root.find('[data-region="plugwatch-search-input"]');
    const addBtn = root.find('[data-region="plugwatch-add-btn"]');
    const resultsContainer = root.find('[data-region="plugwatch-search-results"]');

    let searchTimeout = null;
    let selectedPlugin = null;

    const clearResults = () => {
        resultsContainer.empty().prop('hidden', true);
    };

    const renderResults = (results) => {
        clearResults();
        if (!results || results.length === 0) {
            getString('searchnoresults', 'local_plugwatch').then((str) => {
                const empty = $('<div class="list-group-item text-muted"></div>').text(str);
                resultsContainer.append(empty).prop('hidden', false);
                return str;
            }).catch(Notification.exception);
            return;
        }

        results.forEach((plugin) => {
            const btn = $('<button type="button" class="list-group-item list-group-item-action"></button>');
            btn.text(`${plugin.name} (${plugin.component})`);
            btn.data('plugin', plugin);
            resultsContainer.append(btn);
        });

        resultsContainer.prop('hidden', false);
    };

    // Handle input typing
    searchInput.on('input', function() {
        const query = $(this).val().trim();

        selectedPlugin = null;
        addBtn.prop('disabled', true);

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        if (query.length < 3) {
            clearResults();
            return;
        }

        searchTimeout = setTimeout(() => {
            Ajax.call([{
                methodname: 'local_plugwatch_search_plugins',
                args: {query: query}
            }])[0].then((results) => {
                renderResults(results);
                return results;
            }).catch(Notification.exception);
        }, 500); // 500ms debounce
    });

    // Handle selecting a result
    resultsContainer.on('click', '.list-group-item', function(e) {
        e.preventDefault();
        const btn = $(this);
        const plugin = btn.data('plugin');

        selectedPlugin = plugin;
        searchInput.val(`${plugin.name} (${plugin.component})`);
        addBtn.prop('disabled', false);
        clearResults();
    });

    // Close results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('[data-region="plugwatch-search-input"], [data-region="plugwatch-search-results"]').length) {
            clearResults();
        }
    });

    // Handle Add button
    addBtn.on('click', function(e) {
        e.preventDefault();
        if (selectedPlugin && typeof onAddCallback === 'function') {
            addBtn.prop('disabled', true);
            onAddCallback(selectedPlugin);
        }
    });
};
