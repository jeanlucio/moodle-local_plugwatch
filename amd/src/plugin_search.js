/**
 * Plugin search UI component.
 *
 * @module     local_plugwatch/plugin_search
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Initialize the plugin search autocomplete.
 *
 * @param {Element} root The root element containing the search elements
 * @param {Function} onAddCallback Callback function when Add is clicked
 */
export const init = (root, onAddCallback) => {
    const searchInput = root.querySelector('[data-region="plugwatch-search-input"]');
    const addBtn = root.querySelector('[data-region="plugwatch-add-btn"]');
    const resultsContainer = root.querySelector('[data-region="plugwatch-search-results"]');

    let searchTimeout = null;
    let selectedPlugin = null;

    const clearResults = () => {
        resultsContainer.innerHTML = '';
        resultsContainer.hidden = true;
    };

    const renderResults = (results) => {
        clearResults();
        if (!results || results.length === 0) {
            getString('searchnoresults', 'local_plugwatch').then((str) => {
                const empty = document.createElement('div');
                empty.className = 'list-group-item text-muted';
                empty.textContent = str;
                resultsContainer.appendChild(empty);
                resultsContainer.hidden = false;
                return str;
            }).catch(Notification.exception);
            return;
        }

        results.forEach((plugin) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.textContent = `${plugin.name} (${plugin.component})`;
            btn.dataset.component = plugin.component;
            btn.dataset.name = plugin.name;
            resultsContainer.appendChild(btn);
        });

        resultsContainer.hidden = false;
    };

    // Handle input typing
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();

        selectedPlugin = null;
        addBtn.disabled = true;

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
                args: {query}
            }])[0].then((results) => {
                renderResults(results);
                return results;
            }).catch(Notification.exception);
        }, 500); // 500ms debounce
    });

    // Handle selecting a result (event delegation, since results are added/removed dynamically).
    resultsContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.list-group-item');
        if (!btn) {
            return;
        }
        e.preventDefault();
        const plugin = {component: btn.dataset.component, name: btn.dataset.name};

        selectedPlugin = plugin;
        searchInput.value = `${plugin.name} (${plugin.component})`;
        addBtn.disabled = false;
        clearResults();
    });

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-region="plugwatch-search-input"], [data-region="plugwatch-search-results"]')) {
            clearResults();
        }
    });

    // Handle Add button
    addBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (selectedPlugin && typeof onAddCallback === 'function') {
            addBtn.disabled = true;
            onAddCallback(selectedPlugin);
        }
    });
};
