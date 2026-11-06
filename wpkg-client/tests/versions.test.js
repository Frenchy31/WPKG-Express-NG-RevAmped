'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { loadWpkg } = require('./harness');

const wpkg = loadWpkg();
const compare = (left, right) => Math.sign(wpkg.versionCompare(left, right));

describe('versionCompare', () => {
    it('treats identical versions as equal', () => {
        assert.equal(compare('1.2.3', '1.2.3'), 0);
    });

    it('compares numerically, not as text', () => {
        assert.equal(compare('1.2', '1.10'), -1);
        assert.equal(compare('1.10', '1.2'), 1);
        assert.equal(compare('23.01', '9.20'), 1);
    });

    it('orders on the first difference', () => {
        assert.equal(compare('2.0', '1.99.99'), 1);
        assert.equal(compare('1.1.9', '1.2.0'), -1);
    });

    it('is antisymmetric', () => {
        for (const [left, right] of [['1.0', '2.0'], ['1.2.3', '1.2.4'], ['10', '9']]) {
            assert.equal(compare(left, right), -compare(right, left));
        }
    });
});
