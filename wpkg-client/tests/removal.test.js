'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { element, loadWpkg } = require('./harness');

const packageNode = (id) => element('package', { id, name: id, revision: '1' });

/**
 * Asks wpkg.js which installed packages it would remove.
 *
 * @param {Array<string>} installed Packages recorded in the local wpkg.xml.
 * @param {Array<string>} inProfile Packages the profile references.
 * @param {Array<string>} manual Packages installed by hand.
 * @returns {Array<string>} Identifiers to remove.
 */
function removed(installed, inProfile, manual = []) {
    const wpkg = loadWpkg();

    wpkg.getSettingNodes = () => installed.map(packageNode);
    wpkg.getProfilePackageNodes = () => inProfile.map(packageNode);
    wpkg.getPackagesManuallyInstalled = () => manual.map(packageNode);
    wpkg.getPackageReferences = () => [];
    wpkg.isZombie = () => false;

    return Array.from(wpkg.getPackagesRemoved(), (node) => node.getAttribute('id'));
}

describe('getPackagesRemoved', () => {
    it('keeps packages the profile still references', () => {
        assert.deepEqual(removed(['a', 'b'], ['a', 'b']), []);
    });

    it('removes a package dropped from the profile', () => {
        assert.deepEqual(removed(['a', 'b'], ['a']), ['b']);
    });

    it('removes everything when a valid profile lost its packages', () => {
        // The reason bin/cake compare_feeds is mandatory before a migrated database goes live.
        assert.deepEqual(removed(['a', 'b'], []), ['a', 'b']);
    });

    it('matches identifiers exactly: renaming a package removes it', () => {
        assert.deepEqual(removed(['7zip'], ['7-zip']), ['7zip']);
    });

    it('keeps manually installed packages', () => {
        assert.deepEqual(removed(['a', 'manual'], ['a'], ['manual']), []);
    });

    it('does nothing on a machine without packages', () => {
        assert.deepEqual(removed([], ['a']), []);
    });
});
