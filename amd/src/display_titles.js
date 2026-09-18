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
 * Adds configured user-title prefixes to visible Moodle profile links.
 *
 * @module     local_usertitles/display_titles
 * @copyright  2026 Richard Rangel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as ajaxCall} from 'core/ajax';

const USER_LINK_SELECTOR = [
    'a[href*="/user/view.php"]',
    'a[href*="/user/profile.php"]',
].join(',');
const ATTRIBUTE_USERID = 'data-local-usertitles-userid';
const TITLE_CLASS = 'local-usertitles-visual-prefix';
const MAX_BATCH_SIZE = 200;

const titleCache = new Map();
const linksByUser = new Map();
const requestedUserIds = new Set();
let scanScheduled = false;
let currentUserId = 0;

/**
 * Normalizes visible text for safe full-name comparisons.
 *
 * @param {String} value Text to normalize.
 * @returns {String}
 */
const normalizeText = (value) => value.replace(/\s+/g, ' ').trim();

/**
 * Extracts a user id from a standard Moodle user link.
 *
 * @param {HTMLAnchorElement} link User profile link.
 * @returns {Number|null}
 */
const getUserId = (link) => {
    try {
        const url = new URL(link.href, document.baseURI);
        const userId = Number.parseInt(url.searchParams.get('id'), 10);
        if (Number.isInteger(userId) && userId > 0) {
            return userId;
        }
        return url.pathname.endsWith('/user/profile.php') && currentUserId > 0
            ? currentUserId
            : null;
    } catch {
        return null;
    }
};

/**
 * Adds a link to the local user index.
 *
 * @param {Number} userId Moodle user id.
 * @param {HTMLAnchorElement} link User profile link.
 */
const indexLink = (userId, link) => {
    if (!linksByUser.has(userId)) {
        linksByUser.set(userId, new Set());
    }
    linksByUser.get(userId).add(link);
};

/**
 * Adds the visual prefix to one link without changing its underlying data.
 *
 * @param {HTMLAnchorElement} link User profile link.
 * @param {Object|null} title Assigned title and Moodle full name.
 */
const applyTitle = (link, title) => {
    if (!link.isConnected || !title || link.querySelector(`.${TITLE_CLASS}`)) {
        return;
    }

    const visibleName = normalizeText(link.textContent);
    const expectedName = normalizeText(title.fullname);
    if (!visibleName || !expectedName || visibleName !== expectedName) {
        return;
    }

    const prefix = document.createElement('span');
    prefix.className = TITLE_CLASS;
    prefix.textContent = `${title.abbreviation} `;

    const walker = document.createTreeWalker(link, NodeFilter.SHOW_TEXT);
    let nameNode = walker.nextNode();
    while (nameNode && !normalizeText(nameNode.nodeValue)) {
        nameNode = walker.nextNode();
    }
    if (nameNode) {
        nameNode.parentNode.insertBefore(prefix, nameNode);
    }
};

/**
 * Applies the cached title to every indexed link for a user.
 *
 * @param {Number} userId Moodle user id.
 */
const applyCachedTitle = (userId) => {
    const links = linksByUser.get(userId);
    if (!links) {
        return;
    }

    for (const link of links) {
        if (!link.isConnected) {
            links.delete(link);
            continue;
        }
        applyTitle(link, titleCache.get(userId));
    }
};

/**
 * Retrieves titles for one batch of user ids.
 *
 * @param {Number[]} userIds Moodle user ids.
 * @returns {Promise<void>}
 */
const requestBatch = async(userIds) => {
    const request = {
        methodname: 'local_usertitles_get_user_titles',
        args: {userids: userIds},
    };

    try {
        const response = await ajaxCall([request])[0];
        const returned = new Map(
            response.map((item) => [Number(item.userid), {
                abbreviation: item.abbreviation,
                fullname: item.fullname,
            }])
        );
        for (const userId of userIds) {
            titleCache.set(userId, returned.get(userId) || null);
            applyCachedTitle(userId);
        }
    } catch {
        for (const userId of userIds) {
            requestedUserIds.delete(userId);
        }
    }
};

/**
 * Retrieves uncached titles in bounded batches.
 *
 * @param {Number[]} userIds Moodle user ids.
 * @returns {Promise<void>}
 */
const requestTitles = async(userIds) => {
    for (let offset = 0; offset < userIds.length; offset += MAX_BATCH_SIZE) {
        await requestBatch(userIds.slice(offset, offset + MAX_BATCH_SIZE));
    }
};

/**
 * Scans a document fragment for standard Moodle user links.
 *
 * @param {Document|Element} root Root node.
 */
const scan = (root = document) => {
    const links = [];
    if (root instanceof HTMLAnchorElement && root.matches(USER_LINK_SELECTOR)) {
        links.push(root);
    }
    if (typeof root.querySelectorAll === 'function') {
        links.push(...root.querySelectorAll(USER_LINK_SELECTOR));
    }

    const newUserIds = new Set();
    for (const link of links) {
        if (!link.textContent.trim()) {
            continue;
        }

        let userId = Number.parseInt(link.getAttribute(ATTRIBUTE_USERID), 10);
        if (!Number.isInteger(userId) || userId < 1) {
            userId = getUserId(link);
            if (!userId) {
                continue;
            }
            link.setAttribute(ATTRIBUTE_USERID, String(userId));
        }

        indexLink(userId, link);
        if (titleCache.has(userId)) {
            applyCachedTitle(userId);
        } else if (!requestedUserIds.has(userId)) {
            requestedUserIds.add(userId);
            newUserIds.add(userId);
        }
    }

    const pageUrl = new URL(window.location.href);
    const isUserPage = pageUrl.pathname.endsWith('/user/view.php')
        || pageUrl.pathname.endsWith('/user/profile.php');
    if (isUserPage) {
        const requestedId = Number.parseInt(pageUrl.searchParams.get('id'), 10);
        const pageUserId = Number.isInteger(requestedId) && requestedId > 0
            ? requestedId
            : currentUserId;
        const heading = document.querySelector('.page-header-headings h1');
        if (pageUserId > 0 && heading) {
            heading.setAttribute(ATTRIBUTE_USERID, String(pageUserId));
            indexLink(pageUserId, heading);
            if (titleCache.has(pageUserId)) {
                applyCachedTitle(pageUserId);
            } else if (!requestedUserIds.has(pageUserId)) {
                requestedUserIds.add(pageUserId);
                newUserIds.add(pageUserId);
            }
        }
    }

    if (newUserIds.size) {
        requestTitles([...newUserIds]);
    }
};

/**
 * Coalesces dynamic DOM updates into one scan.
 */
const scheduleScan = () => {
    if (scanScheduled) {
        return;
    }
    scanScheduled = true;
    window.requestAnimationFrame(() => {
        scanScheduled = false;
        scan(document);
    });
};

/**
 * Starts global visual title display.
 *
 * @param {number|string} loggedInUserId The current user's ID.
 */
export const init = (loggedInUserId = 0) => {
    currentUserId = Number.parseInt(loggedInUserId, 10) || 0;
    scan(document);

    const observer = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => mutation.addedNodes.length > 0)) {
            scheduleScan();
        }
    });
    observer.observe(document.body, {childList: true, subtree: true});
};
