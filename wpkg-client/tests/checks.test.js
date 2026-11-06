'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { element, loadWpkg } = require('./harness');

const check = (attributes, children = []) => element('check', attributes, children);
const fileExists = (path) => check({ type: 'file', condition: 'exists', path });
const passing = fileExists('C:\\present.txt');
const failing = fileExists('C:\\absent.txt');

const logical = (condition, children, value) => check(
    value === undefined ? { type: 'logical', condition } : { type: 'logical', condition, value },
    children,
);

const machine = {
    env: { ProgramFiles: 'C:\\Program Files' },
    files: ['C:\\present.txt', 'C:\\Program Files\\app.exe', 'C:\\app.exe'],
    fileVersions: { 'C:\\app.exe': '1.2.3.4' },
    fileSizes: { 'C:\\app.exe': 1024 },
    registry: { 'HKLM\\Software\\Vendor\\Version': '1.2.3' },
};
const wpkg = loadWpkg(machine);

describe('logical checks', () => {
    it('and needs every child', () => {
        assert.equal(wpkg.checkCondition(logical('and', [passing, passing])), true);
        assert.equal(wpkg.checkCondition(logical('and', [passing, failing])), false);
    });

    it('or needs one child', () => {
        assert.equal(wpkg.checkCondition(logical('or', [failing, passing])), true);
        assert.equal(wpkg.checkCondition(logical('or', [failing, failing])), false);
    });

    it('not is true when no child passes', () => {
        assert.equal(wpkg.checkCondition(logical('not', [failing])), true);
        assert.equal(wpkg.checkCondition(logical('not', [passing])), false);
    });

    it('atleast counts passing children', () => {
        assert.equal(wpkg.checkCondition(logical('atleast', [passing, failing], '1')), true);
        assert.equal(wpkg.checkCondition(logical('atleast', [passing, failing], '2')), false);
    });

    it('atleast without a value throws: the web application must emit it', () => {
        assert.throws(
            () => wpkg.checkCondition(logical('atleast', [passing])),
            /requires a value/,
        );
    });

    it('atmost caps passing children', () => {
        assert.equal(wpkg.checkCondition(logical('atmost', [passing, failing], '1')), true);
        assert.equal(wpkg.checkCondition(logical('atmost', [passing, passing], '1')), false);
    });

    it('nests', () => {
        const tree = logical('and', [logical('or', [failing, passing]), logical('not', [failing])]);

        assert.equal(wpkg.checkCondition(tree), true);
    });

    it('rejects an unknown condition', () => {
        assert.throws(() => wpkg.checkCondition(logical('xor', [passing])), /unknown/);
    });
});

describe('file checks', () => {
    it('exists', () => {
        assert.equal(wpkg.checkCondition(passing), true);
        assert.equal(wpkg.checkCondition(failing), false);
    });

    it('expands environment variables in the path', () => {
        assert.equal(wpkg.checkCondition(fileExists('%ProgramFiles%\\app.exe')), true);
    });

    it('sizeequals compares the size in bytes', () => {
        const sized = (value) => check({ type: 'file', condition: 'sizeequals', path: 'C:\\app.exe', value });

        assert.equal(wpkg.checkCondition(sized('1024')), true);
        assert.equal(wpkg.checkCondition(sized('1')), false);
    });

    it('compares file versions', () => {
        const versioned = (condition, value) => check({ type: 'file', condition, path: 'C:\\app.exe', value });

        assert.equal(wpkg.checkCondition(versioned('versionequalto', '1.2.3.4')), true);
        assert.equal(wpkg.checkCondition(versioned('versionsmallerthan', '2.0')), true);
        assert.equal(wpkg.checkCondition(versioned('versiongreaterthan', '2.0')), false);
        assert.equal(wpkg.checkCondition(versioned('versiongreaterorequal', '1.2.3.4')), true);
        assert.equal(wpkg.checkCondition(versioned('versionlessorequal', '1.0')), false);
    });

    it('rejects an unknown condition', () => {
        assert.throws(
            () => wpkg.checkCondition(check({ type: 'file', condition: 'bogus', path: 'C:\\app.exe' })),
            /unknown/i,
        );
    });
});

describe('registry checks', () => {
    const registry = (condition, path, value) => check({ type: 'registry', condition, path, ...(value && { value }) });

    it('exists', () => {
        assert.equal(wpkg.checkCondition(registry('exists', 'HKLM\\Software\\Vendor\\Version')), true);
        assert.equal(wpkg.checkCondition(registry('exists', 'HKLM\\Software\\Vendor\\Absent')), false);
    });

    it('equals compares the stored value', () => {
        assert.equal(wpkg.checkCondition(registry('equals', 'HKLM\\Software\\Vendor\\Version', '1.2.3')), true);
        assert.equal(wpkg.checkCondition(registry('equals', 'HKLM\\Software\\Vendor\\Version', '9')), false);
    });
});

describe('host checks', () => {
    const host = (condition, value) => check({ type: 'host', condition, value });
    const onHost = loadWpkg({ hostname: 'room1-pc01' });

    it('matches the hostname with a regular expression', () => {
        assert.equal(onHost.checkCondition(host('hostname', 'room1-pc.+')), true);
        assert.equal(onHost.checkCondition(host('hostname', 'room2-pc.+')), false);
    });

    it('matches the architecture and the operating system', () => {
        assert.equal(onHost.checkCondition(host('architecture', 'x64')), true);
        assert.equal(onHost.checkCondition(host('os', 'windows 10')), true);
        assert.equal(onHost.checkCondition(host('os', 'windows 7')), false);
    });
});

describe('check types', () => {
    it('rejects an unknown type', () => {
        assert.throws(() => wpkg.checkCondition(check({ type: 'bogus', condition: 'exists' })), /unknown/i);
    });
});
