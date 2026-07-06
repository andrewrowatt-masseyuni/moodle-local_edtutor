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
 * Education Tutor dashboard view toggle and filters.
 *
 * Switches between the by-student and by-course views, filters both views by
 * one student or course (selecting one resets the other) and by timeframe,
 * and persists the view and timeframe choices as user preferences. Rows carry
 * a server-computed data-timeframe classification, so no date arithmetic
 * happens client side.
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
    filterstudent: '[data-action="local_edtutor-filterstudent"]',
    filtercourse: '[data-action="local_edtutor-filtercourse"]',
    timeframe: '[data-action="local_edtutor-timeframe"]',
    studentcard: '[data-region="local_edtutor-studentcard"]',
    coursesection: '[data-region="local_edtutor-coursesection"]',
    activityrow: '[data-region="local_edtutor-activityrow"]',
    coursecard: '[data-region="local_edtutor-coursecard"]',
    courseactivity: '[data-region="local_edtutor-courseactivity"]',
    studentrow: '[data-region="local_edtutor-studentrow"]',
    noduegroup: '[data-region="local_edtutor-noduegroup"]',
    nomatches: '[data-region="local_edtutor-nomatches"]',
};

const HIDDEN = 'local_edtutor-hidden';

const VIEW_PREFERENCE = 'local_edtutor_dashboard_view';
const TIMEFRAME_PREFERENCE = 'local_edtutor_dashboard_timeframe';

/**
 * Read the identity filter from the student and course selects.
 *
 * @param {HTMLElement} root Dashboard root element.
 * @returns {{studentid: ?string, courseid: ?string}}
 */
const getIdentityFilter = (root) => {
    const studentid = root.querySelector(Selectors.filterstudent)?.value ?? 'all';
    const courseid = root.querySelector(Selectors.filtercourse)?.value ?? 'all';
    return {
        studentid: studentid === 'all' ? null : studentid,
        courseid: courseid === 'all' ? null : courseid,
    };
};

/**
 * Whether a row's server-computed classification passes the timeframe filter.
 *
 * Mirrors timeframe_visible() in \local_edtutor\output\dashboard.
 *
 * @param {string} rowTimeframe One of submitted, overdue, upcoming or future.
 * @param {string} filter One of all, duesoon or overdue.
 * @returns {boolean}
 */
const timeframeMatches = (rowTimeframe, filter) => {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'overdue') {
        return rowTimeframe === 'overdue';
    }
    return rowTimeframe === 'overdue' || rowTimeframe === 'upcoming';
};

/**
 * Toggle the hidden marker class, reporting the resulting visibility.
 *
 * @param {HTMLElement} element
 * @param {boolean} visible
 * @returns {boolean} The visibility that was applied.
 */
const setVisible = (element, visible) => {
    element.classList.toggle(HIDDEN, !visible);
    return visible;
};

/**
 * Hide each no-due-date group whose rows are all hidden.
 *
 * @param {HTMLElement} container Card or course section.
 * @param {string} rowSelector Selector for the group's leaf rows.
 */
const updateNoDueGroups = (container, rowSelector) => {
    container.querySelectorAll(Selectors.noduegroup).forEach(group => {
        const anyVisible = [...group.querySelectorAll(rowSelector)]
            .some(row => !row.classList.contains(HIDDEN));
        setVisible(group, anyVisible);
    });
};

/**
 * Refilter the by-student view.
 *
 * @param {HTMLElement} view The by-student view container.
 * @param {{studentid: ?string, courseid: ?string}} identity Active identity filter.
 * @param {string} timeframe Active timeframe filter.
 * @returns {number} Number of visible student cards.
 */
const filterByStudentView = (view, identity, timeframe) => {
    let visibleCards = 0;
    view.querySelectorAll(Selectors.studentcard).forEach(card => {
        let cardVisible = !identity.studentid || card.dataset.studentid === identity.studentid;
        if (cardVisible) {
            const sections = card.querySelectorAll(Selectors.coursesection);
            let visibleSections = 0;
            sections.forEach(section => {
                let sectionVisible = !identity.courseid || section.dataset.courseid === identity.courseid;
                if (sectionVisible) {
                    const rows = section.querySelectorAll(Selectors.activityrow);
                    let visibleRows = 0;
                    rows.forEach(row => {
                        if (setVisible(row, timeframeMatches(row.dataset.timeframe, timeframe))) {
                            visibleRows++;
                        }
                    });
                    updateNoDueGroups(section, Selectors.activityrow);
                    // A section with no activities at all only shows unfiltered,
                    // matching the server-side initial render.
                    sectionVisible = visibleRows > 0 || (timeframe === 'all' && !rows.length);
                }
                if (setVisible(section, sectionVisible)) {
                    visibleSections++;
                }
            });
            cardVisible = visibleSections > 0
                || (timeframe === 'all' && !identity.courseid && !sections.length);
        }
        if (setVisible(card, cardVisible)) {
            visibleCards++;
        }
    });
    return visibleCards;
};

/**
 * Refilter the by-course view.
 *
 * @param {HTMLElement} view The by-course view container.
 * @param {{studentid: ?string, courseid: ?string}} identity Active identity filter.
 * @param {string} timeframe Active timeframe filter.
 * @returns {number} Number of visible course cards.
 */
const filterByCourseView = (view, identity, timeframe) => {
    let visibleCards = 0;
    view.querySelectorAll(Selectors.coursecard).forEach(card => {
        let cardVisible = !identity.courseid || card.dataset.courseid === identity.courseid;
        if (cardVisible) {
            let visibleBlocks = 0;
            card.querySelectorAll(Selectors.courseactivity).forEach(block => {
                let visibleRows = 0;
                block.querySelectorAll(Selectors.studentrow).forEach(row => {
                    const show = (!identity.studentid || row.dataset.studentid === identity.studentid)
                        && timeframeMatches(row.dataset.timeframe, timeframe);
                    if (setVisible(row, show)) {
                        visibleRows++;
                    }
                });
                if (setVisible(block, visibleRows > 0)) {
                    visibleBlocks++;
                }
            });
            updateNoDueGroups(card, Selectors.courseactivity);
            cardVisible = visibleBlocks > 0;
        }
        if (setVisible(card, cardVisible)) {
            visibleCards++;
        }
    });
    return visibleCards;
};

/**
 * Reapply both filters to both views and update the no-matches alerts.
 *
 * @param {HTMLElement} root Dashboard root element.
 */
const applyFilters = (root) => {
    const identity = getIdentityFilter(root);
    const timeframe = root.querySelector(Selectors.timeframe)?.value ?? 'all';

    const bystudent = root.querySelector(Selectors.bystudent);
    const bycourse = root.querySelector(Selectors.bycourse);
    const visibleStudentCards = filterByStudentView(bystudent, identity, timeframe);
    const visibleCourseCards = filterByCourseView(bycourse, identity, timeframe);

    bystudent.querySelectorAll(Selectors.nomatches).forEach(alert => {
        setVisible(alert, visibleStudentCards === 0);
    });
    bycourse.querySelectorAll(Selectors.nomatches).forEach(alert => {
        setVisible(alert, visibleCourseCards === 0);
    });
};

/**
 * Initialise the dashboard view toggle and filters.
 */
export const init = () => {
    const root = document.querySelector(Selectors.region);
    if (!root) {
        return;
    }
    root.addEventListener('change', (e) => {
        const toggle = e.target.closest(Selectors.toggle);
        if (toggle) {
            const view = toggle.value;
            root.querySelector(Selectors.bystudent).classList.toggle('d-none', view !== 'bystudent');
            root.querySelector(Selectors.bycourse).classList.toggle('d-none', view !== 'bycourse');
            setUserPreference(VIEW_PREFERENCE, view).catch(Notification.exception);
            return;
        }
        const timeframe = e.target.closest(Selectors.timeframe);
        if (timeframe) {
            applyFilters(root);
            setUserPreference(TIMEFRAME_PREFERENCE, timeframe.value).catch(Notification.exception);
            return;
        }
        // Only one of the student and course filters applies at a time:
        // picking one resets the other.
        const studentfilter = e.target.closest(Selectors.filterstudent);
        if (studentfilter) {
            if (studentfilter.value !== 'all') {
                root.querySelector(Selectors.filtercourse).value = 'all';
            }
            applyFilters(root);
            return;
        }
        const coursefilter = e.target.closest(Selectors.filtercourse);
        if (coursefilter) {
            if (coursefilter.value !== 'all') {
                root.querySelector(Selectors.filterstudent).value = 'all';
            }
            applyFilters(root);
        }
    });
    // The initial state is rendered server side; this pass is an idempotent
    // safety net that also covers browsers restoring old select values.
    applyFilters(root);
};
