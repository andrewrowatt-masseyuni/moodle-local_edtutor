// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Education Tutor dashboard view toggle.
 *
 * Switches between the by-student and by-course views and persists the
 * choice as a user preference.
 *
 * @module     local_edtutor/dashboard
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';
import Notification from 'core/notification';

const Selectors = {
    region: '[data-region="local_edtutor-dashboard"]',
    toggle: '[data-action="local_edtutor-viewtoggle"]',
    bystudent: '[data-region="local_edtutor-view-bystudent"]',
    bycourse: '[data-region="local_edtutor-view-bycourse"]',
};

const PREFERENCE_NAME = 'local_edtutor_dashboard_view';

/**
 * Initialise the dashboard view toggle.
 */
export const init = () => {
    const root = document.querySelector(Selectors.region);
    if (!root) {
        return;
    }
    root.addEventListener('change', (e) => {
        const input = e.target.closest(Selectors.toggle);
        if (!input) {
            return;
        }
        const view = input.value;
        root.querySelector(Selectors.bystudent).classList.toggle('d-none', view !== 'bystudent');
        root.querySelector(Selectors.bycourse).classList.toggle('d-none', view !== 'bycourse');
        setUserPreference(PREFERENCE_NAME, view).catch(Notification.exception);
    });
};
