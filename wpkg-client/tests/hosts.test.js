'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { element, loadWpkg } = require('./harness');

/**
 * Loads wpkg.js on a machine, with the hosts of a hosts.xml.
 *
 * @param {string} hostname Machine name.
 * @param {Array<string>} names Host entries, in feed order.
 * @param {boolean} applyMultiple Value of the applyMultiple setting.
 * @returns {Array<string>} Names of the entries applying to the machine.
 */
function applying(hostname, names, applyMultiple = false) {
    const wpkg = loadWpkg({ hostname });
    const nodes = names.map((name) => element('host', { name, 'profile-id': 'p' }));

    wpkg.getHostNodes = () => nodes;
    wpkg.isApplyMultiple = () => applyMultiple;

    return Array.from(wpkg.getHostsApplying(), (node) => node.getAttribute('name'));
}

describe('getHostsApplying', () => {
    it('matches a name exactly, whatever the case', () => {
        assert.deepEqual(applying('room1-pc01', ['ROOM1-PC01']), ['ROOM1-PC01']);
    });

    it('matches a regular expression', () => {
        assert.deepEqual(applying('room1-pc01', ['room1-pc.+']), ['room1-pc.+']);
    });

    it('matches the whole name only', () => {
        assert.deepEqual(applying('room1-pc01-old', ['room1-pc\\d+']), []);
    });

    it('returns nothing without a match', () => {
        assert.deepEqual(applying('other', ['room1-pc.+', 'room2-pc.+']), []);
    });

    it('keeps the first match of the feed: host order is a contract', () => {
        assert.deepEqual(applying('room1-pc01', ['room1-pc.+', '.+']), ['room1-pc.+']);
        assert.deepEqual(applying('room1-pc01', ['.+', 'room1-pc.+']), ['.+']);
    });

    it('does not favour an exact name over an earlier pattern', () => {
        assert.deepEqual(applying('room1-pc01', ['.+', 'room1-pc01']), ['.+']);
    });

    it('applies every match with applyMultiple', () => {
        assert.deepEqual(applying('room1-pc01', ['room1-pc.+', '.+'], true), ['room1-pc.+', '.+']);
    });
});
