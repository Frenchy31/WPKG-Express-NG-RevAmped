'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { element, loadWpkg, toNodeList } = require('./harness');

const wpkg = loadWpkg();

/**
 * Builds a package as packages.xml exposes it.
 *
 * @param {Object<string, string>} attributes Attributes.
 * @param {Array<Object>} children Child elements.
 * @returns {Object}
 */
function packageNode(attributes = {}, children = []) {
    return element('package', { id: 'app', name: 'App', revision: '1.0', ...attributes }, children);
}

describe('package accessors', () => {
    it('reads identity attributes', () => {
        const node = packageNode({ id: '7zip', name: '7-Zip', revision: '23.01' });

        assert.equal(wpkg.getPackageID(node), '7zip');
        assert.equal(wpkg.getPackageName(node), '7-Zip');
        assert.equal(wpkg.getPackageRevision(node), '23.01');
    });

    it('reads the priority as a number, 0 when absent', () => {
        assert.equal(wpkg.getPackagePriority(packageNode({ priority: '50' })), 50);
        assert.equal(wpkg.getPackagePriority(packageNode()), 0);
    });

    it('reads reboot, false when absent', () => {
        assert.equal(wpkg.getPackageReboot(packageNode({ reboot: 'postponed' })), 'postponed');
        assert.equal(wpkg.getPackageReboot(packageNode({ reboot: 'true' })), 'true');
        assert.equal(wpkg.getPackageReboot(packageNode()), 'false');
    });

    it('reads execute, unset when absent', () => {
        assert.equal(wpkg.getPackageExecute(packageNode({ execute: 'always' })), 'always');
        assert.equal(wpkg.getPackageExecute(packageNode({ execute: 'once' })), 'once');
        assert.ok(['', null].includes(wpkg.getPackageExecute(packageNode())));
    });
});

describe('sortPackageNodes', () => {
    const named = (id, priority) => packageNode({ id, priority: String(priority) });
    const ids = (nodes) => Array.from(nodes, (node) => node.getAttribute('id'));

    it('sorts by decreasing priority', () => {
        const sorted = wpkg.sortPackageNodes([named('low', 1), named('high', 100), named('mid', 50)], 'PRIORITY', 2);

        assert.deepEqual(ids(sorted), ['high', 'mid', 'low']);
    });

    it('sorts by increasing priority', () => {
        const sorted = wpkg.sortPackageNodes([named('high', 100), named('low', 1)], 'PRIORITY', 1);

        assert.deepEqual(ids(sorted), ['low', 'high']);
    });
});

describe('list helpers', () => {
    it('uniqueAttributeNodes keeps the first node of each value', () => {
        const nodes = toNodeList([packageNode({ id: 'a', name: 'first' }), packageNode({ id: 'a', name: 'second' }), packageNode({ id: 'b' })]);

        const unique = Array.from(wpkg.uniqueAttributeNodes(nodes, 'id'));

        assert.deepEqual(unique.map((node) => node.getAttribute('id')), ['a', 'b']);
        assert.equal(unique[0].getAttribute('name'), 'first');
    });

    it('concatenateList appends the second list', () => {
        assert.deepEqual(Array.from(wpkg.concatenateList(['a'], ['b', 'c'])), ['a', 'b', 'c']);
    });

    it('trim removes whitespace from one side', () => {
        assert.equal(wpkg.trim('  value'), 'value');
        assert.equal(wpkg.trim('value  '), 'value');
        assert.equal(wpkg.trim(null), null);
    });

    it('trim removes whitespace from both sides', { todo: 'bug in 1.3.1: the regular expression has no g flag' }, () => {
        assert.equal(wpkg.trim('  value  '), 'value');
    });
});

describe('parseISODate', () => {
    it('reads a full date', () => {
        const date = wpkg.parseISODate('2026-11-06T10:30:00');

        assert.equal(date.getFullYear(), 2026);
        assert.equal(date.getMonth(), 10);
        assert.equal(date.getDate(), 6);
        assert.equal(date.getHours(), 10);
    });

    it('fills missing parts with the start of the period', () => {
        const date = wpkg.parseISODate('2026-11');

        assert.equal(date.getMonth(), 10);
        assert.equal(date.getDate(), 1);
    });
});
